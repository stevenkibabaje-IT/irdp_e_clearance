<?php
declare(strict_types=1);

/** Password recovery, password changes, rate limiting and account session validation. */
function rate_limit(PDO $pdo,string $purpose,string $identity,int $max,int $minutes): void {
    $hash=hash('sha256',$purpose.'|'.$identity);
    $pdo->prepare('INSERT INTO rate_limits(bucket_hash,window_start,attempts) VALUES (?,NOW(),1) ON DUPLICATE KEY UPDATE attempts=IF(window_start<DATE_SUB(NOW(),INTERVAL ? MINUTE),1,attempts+1),window_start=IF(window_start<DATE_SUB(NOW(),INTERVAL ? MINUTE),NOW(),window_start)')->execute([$hash,$minutes,$minutes]);
    $s=$pdo->prepare('SELECT attempts FROM rate_limits WHERE bucket_hash=?');
    $s->execute([$hash]);
    if((int)$s->fetchColumn()>$max) {
        throw new RuntimeException('Too many attempts. Please try again later.');
    }
}
function request_ip(): string {
    return (string)($_SERVER['REMOTE_ADDR']??'cli');
}
function create_reset_credential(PDO $pdo,int $userId,?int $adminId,string $identityReference=''): string {
    $token=bin2hex(random_bytes(32));
    $pdo->prepare('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$userId]);
    $pdo->prepare('INSERT INTO password_resets(user_id,token_hash,expires_at,issued_by,identity_reference) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE),?,?)')->execute([$userId,hash('sha256',$token),$adminId,$identityReference?:null]);
    if($adminId) {
        audit($pdo,'PASSWORD_RESET_ISSUED',$adminId,null,'Identity checked for account #'.$userId.'. Verification reference: '.$identityReference);
    }
    return $token;
}
function request_password_recovery(PDO $pdo,string $username): void {
    // Identical response for existing, unknown, unverified and throttled accounts.
    try {
        rate_limit($pdo,'recovery-ip',request_ip(),5,30);
        rate_limit($pdo,'recovery-account',mb_strtolower($username),3,30);
    } catch(RuntimeException $e) {
        return;
    }
    $s=$pdo->prepare('SELECT id FROM users WHERE username=? AND active=1');
    $s->execute([$username]);
    $user=$s->fetch();
    if(!$user) {
        return;
    }
    $pdo->beginTransaction();
    try {
        $lock=$pdo->prepare('SELECT id,email,email_verified_at,active FROM users WHERE id=? FOR UPDATE');
        $lock->execute([$user['id']]);
        $user=$lock->fetch();
        if(!$user || !(int)$user['active']) {$pdo->commit();return;}
        // The account lock serializes submissions and avoids duplicate open requests.
        $s=$pdo->prepare('SELECT id FROM password_reset_requests WHERE user_id=? AND status IN ("PENDING","ISSUED") FOR UPDATE');
        $s->execute([$user['id']]);
        if($s->fetchColumn()) {$pdo->commit();return;}
        $pdo->prepare('INSERT INTO password_reset_requests(user_id) VALUES (?)')->execute([$user['id']]);
        $requestId=(int)$pdo->lastInsertId();
        $browserToken=create_reset_credential($pdo,(int)$user['id'],null);
        $s=$pdo->prepare('SELECT id FROM password_resets WHERE token_hash=?');
        $s->execute([hash('sha256',$browserToken)]);
        $pdo->prepare('UPDATE password_reset_requests SET reset_id=? WHERE id=?')->execute([(int)$s->fetchColumn(),$requestId]);
        $_SESSION['recovery_browser']=['request_id'=>$requestId,'credential'=>$browserToken];
        foreach($pdo->query('SELECT u.id FROM users u INNER JOIN roles r ON r.id=u.role_id WHERE r.name="ADMIN" AND u.active=1') as $admin) {
            notify($pdo,(int)$admin['id'],'Password reset requested','Account '.$username.' requested recovery. Open Password Reset Requests to verify identity and issue a reset code.');
        }
        audit($pdo,'PASSWORD_RESET_REQUESTED',(int)$user['id'],null,'Recovery request #'.$requestId.' submitted.');
        $pdo->commit();
    } catch(Throwable $e) {
        if($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    // A delivery failure must leave the request available for admin-assisted recovery.
    if(PASSWORD_MAIL_ENABLED && $user['email'] && $user['email_verified_at'] && empty($_SESSION['recovery_browser'])) {
        $pdo->beginTransaction();
        try {
            $lock=$pdo->prepare('SELECT active FROM users WHERE id=? FOR UPDATE');$lock->execute([$user['id']]);
            if(!(int)$lock->fetchColumn()) {$pdo->commit();return;}
            $s=$pdo->prepare('SELECT status FROM password_reset_requests WHERE id=? FOR UPDATE');$s->execute([$requestId]);
            if($s->fetchColumn()!=='PENDING') {$pdo->commit();return;}
            $token=create_reset_credential($pdo,(int)$user['id'],null);
            $s=$pdo->prepare('SELECT id FROM password_resets WHERE token_hash=?');$s->execute([hash('sha256',$token)]);
            $resetId=(int)$s->fetchColumn();
            $link=rtrim(APPLICATION_ORIGIN,'/').'/auth/reset.php#credential='.$token;
            if(!mail($user['email'],'IRDP password recovery',"Use this link within 30 minutes to choose a new password:\n".$link,"From: ".MAIL_FROM)) {
                throw new RuntimeException('Mail delivery unavailable.');
            }
            $pdo->prepare('UPDATE password_reset_requests SET status="ISSUED",reset_id=?,handled_at=NOW(),identity_reference="Verified account email" WHERE id=?')->execute([$resetId,$requestId]);
            $pdo->commit();
        } catch(Throwable $e) {
            if($pdo->inTransaction()) {$pdo->rollBack();}
            error_log('Password recovery delivery failed; request retained for administrator review.');
        }
    }
}

function admin_password_recovery(PDO $pdo,array $input,int $adminId): ?string {
    $admin=user_record($pdo,$adminId);
    if(!$admin || !(int)$admin['active'] || $admin['role_name']!=='ADMIN') {
        throw new RuntimeException('Administrator access required.');
    }
    $action=text_input($input,'action',10);
    if(!in_array($action,['issue','reject'],true)) {throw new RuntimeException('Choose a valid recovery action.');}
    $requestId=optional_id($input['request_id']??null,'request_id');
    $browserApproval=$action==='issue' && ($input['browser_approval']??'')==='1' && $requestId;
    $reference=$action==='issue'?($browserApproval?'Administrator approved the registration-number recovery request':text_input($input,'identity_reference',255)):'';
    $reason=$action==='reject'?text_input($input,'decision_reason',255):'';
    if($action==='issue' && !$browserApproval && ($input['identity_checked']??'')!=='1') {
        throw new ValidationException(['identity_reference'=>'Verify institutional identity before issuing a reset code.']);
    }
    if($action==='reject' && !$requestId) {throw new RuntimeException('Select a request to reject.');}
    if($requestId) {
        $s=$pdo->prepare('SELECT user_id FROM password_reset_requests WHERE id=?');$s->execute([$requestId]);
    } else {
        $username=text_input($input,'username',100);
        $s=$pdo->prepare('SELECT id FROM users WHERE username=?');$s->execute([$username]);
    }
    $userId=(int)$s->fetchColumn();
    if(!$userId) {throw new RuntimeException('Account or recovery request not found.');}
    $pdo->beginTransaction();
    try {
        $s=$pdo->prepare('SELECT id,email,active FROM users WHERE id=? FOR UPDATE');$s->execute([$userId]);$user=$s->fetch();
        if(!$user) {throw new RuntimeException('Account not found.');}
        $s=$pdo->prepare($requestId
            ?'SELECT * FROM password_reset_requests WHERE id=? AND user_id=? FOR UPDATE'
            :'SELECT * FROM password_reset_requests WHERE user_id=? AND status IN ("PENDING","ISSUED") ORDER BY id DESC LIMIT 1 FOR UPDATE');
        $s->execute($requestId?[$requestId,$userId]:[$userId]);$request=$s->fetch();
        if($requestId && (!$request || !in_array($request['status'],['PENDING','ISSUED'],true))) {
            throw new RuntimeException('This request has already been closed. Refresh the request list.');
        }
        if($action==='reject') {
            if($request['reset_id']) {$pdo->prepare('UPDATE password_resets SET used_at=NOW() WHERE id=? AND used_at IS NULL')->execute([$request['reset_id']]);}
            $pdo->prepare('UPDATE password_reset_requests SET status="REJECTED",handled_by=?,handled_at=NOW(),decision_reason=? WHERE id=?')->execute([$adminId,$reason,$requestId]);
            audit($pdo,'PASSWORD_RESET_REJECTED',$adminId,null,'Recovery request #'.$requestId.'. Reason: '.$reason);
            $pdo->commit();return null;
        }
        if(!(int)$user['active']) {throw new RuntimeException('Recovery is available only for active accounts.');}
        if($browserApproval && $request && $request['reset_id']) {
            $s=$pdo->prepare('UPDATE password_resets SET expires_at=DATE_ADD(NOW(),INTERVAL 30 MINUTE),issued_by=?,identity_reference=? WHERE id=? AND used_at IS NULL');
            $s->execute([$adminId,$reference,$request['reset_id']]);
            if(!$s->rowCount()){throw new RuntimeException('Request access has expired or been replaced. Ask the student to submit a new request.');}
            $pdo->prepare('UPDATE password_reset_requests SET status="ISSUED",handled_by=?,handled_at=NOW(),identity_reference=?,decision_reason=NULL WHERE id=?')->execute([$adminId,$reference,$requestId]);
            audit($pdo,'PASSWORD_RESET_APPROVED',$adminId,null,'Browser recovery request #'.$requestId.' approved.');
            $pdo->commit();return 'BROWSER_APPROVED';
        }
        if(($input['email_verified']??'')==='1' && !empty($user['email'])) {
            $pdo->prepare('UPDATE users SET email_verified_at=NOW() WHERE id=?')->execute([$userId]);
        }
        if(!$request) {
            $pdo->prepare('INSERT INTO password_reset_requests(user_id) VALUES (?)')->execute([$userId]);
            $requestId=(int)$pdo->lastInsertId();
        } else {$requestId=(int)$request['id'];}
        $token=create_reset_credential($pdo,$userId,$adminId,$reference);
        $s=$pdo->prepare('SELECT id FROM password_resets WHERE token_hash=?');$s->execute([hash('sha256',$token)]);
        $pdo->prepare('UPDATE password_reset_requests SET status="ISSUED",reset_id=?,handled_by=?,handled_at=NOW(),identity_reference=?,decision_reason=NULL WHERE id=?')->execute([(int)$s->fetchColumn(),$adminId,$reference,$requestId]);
        $pdo->commit();return $token;
    } catch(Throwable $e) {
        if($pdo->inTransaction()) {$pdo->rollBack();}
        throw $e;
    }
}
function reset_password(PDO $pdo,string $token,mixed $password,mixed $confirmation): void {
    rate_limit($pdo,'reset-attempt',request_ip(),10,30);
    if(!preg_match('/^[a-f0-9]{64}$/D',$token)) {
        throw new RuntimeException('Invalid or expired reset credential.');
    }
    $new=password_policy($password,$confirmation);
    $pdo->beginTransaction();
    try {
        $s=$pdo->prepare('SELECT user_id FROM password_resets WHERE token_hash=?');
        $s->execute([hash('sha256',$token)]);
        $userId=$s->fetchColumn();
        if(!$userId) {
            throw new RuntimeException('Invalid or expired reset credential.');
        }
        // Account lock serializes token issuance, password changes and resets for this user.
        $s=$pdo->prepare('SELECT id,active FROM users WHERE id=? FOR UPDATE');
        $s->execute([$userId]);
        $user=$s->fetch();
        $s=$pdo->prepare('SELECT p.id FROM password_resets p WHERE p.token_hash=? AND p.used_at IS NULL AND p.expires_at>NOW() AND NOT EXISTS (SELECT 1 FROM password_reset_requests r WHERE r.reset_id=p.id AND r.status<>"ISSUED") FOR UPDATE');
        $s->execute([hash('sha256',$token)]);
        if(!$user || !(int)$user['active'] || !$s->fetchColumn()) {
            throw new RuntimeException('Invalid or expired reset credential.');
        }
        $pdo->prepare('UPDATE users SET password_hash=?,auth_version=auth_version+1,force_password_change=0 WHERE id=?')->execute([hash_new_password($new),$userId]);
        $pdo->prepare('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$userId]);
        $pdo->prepare('UPDATE password_reset_requests SET status="COMPLETED",completed_at=NOW() WHERE user_id=? AND status IN ("PENDING","ISSUED")')->execute([$userId]);
        audit($pdo,'PASSWORD_RESET',(int)$userId,null,'Password reset completed; existing sessions revoked.');
        $pdo->commit();
    } catch(Throwable $e) {
        if($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
function change_password(PDO $pdo,int $userId,mixed $current,mixed $new,mixed $confirmation): void {
    rate_limit($pdo,'password-change',request_ip().'|'.$userId,10,30);
    $password=password_policy($new,$confirmation);
    $current=existing_password_input($current,'current_password');
    $pdo->beginTransaction();
    try {
        $s=$pdo->prepare('SELECT password_hash FROM users WHERE id=? AND active=1 FOR UPDATE');
        $s->execute([$userId]);
        $hash=$s->fetchColumn();
        if(!$hash || !is_string($current) || !password_verify($current,$hash)) {
            throw new ValidationException(['current_password'=>'Current password is incorrect.']);
        }
        if(password_verify($password,$hash)) {throw new ValidationException(['password'=>'Choose a password different from your current password.']);}
        $pdo->prepare('UPDATE users SET password_hash=?,auth_version=auth_version+1,force_password_change=0 WHERE id=?')->execute([hash_new_password($password),$userId]);
        $pdo->prepare('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$userId]);
        $pdo->prepare('UPDATE password_reset_requests SET status="COMPLETED",completed_at=NOW() WHERE user_id=? AND status IN ("PENDING","ISSUED")')->execute([$userId]);
        audit($pdo,'PASSWORD_CHANGED',$userId,null,'Existing sessions revoked.');
        $pdo->commit();
    } catch(Throwable $e) {
        if($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
function validate_session_account(PDO $pdo): void {
    if(!current_user()) {
        return;
    }
    $user=user_record($pdo,(int)current_user()['id']);
    if(!$user || !(int)$user['active'] || (int)($_SESSION['user']['auth_version']??0)!==(int)$user['auth_version']) {
        end_authenticated_session('revoked');
    }
    $_SESSION['user']['role']=$user['role_name'];
    $_SESSION['user']['full_name']=$user['full_name'];
    if($user['role_name'] !== 'STUDENT' && (int)$user['force_password_change'] && !session_api_request() && !in_array(basename($_SERVER['SCRIPT_NAME']??''),['change_password.php','logout.php'],true)) {
        redirect('auth/change_password.php');
    }
}
