<?php
declare(strict_types=1);

const STUDENT_OTP_MINUTES = 10;
const STUDENT_OTP_ATTEMPTS = 5;
const STUDENT_OTP_RESEND_SECONDS = 60;
const STUDENT_OTP_REQUEST_MESSAGE = 'If the email is registered, an OTP will arrive shortly. Check your inbox and spam folder.';
const STUDENT_OTP_INVALID_MESSAGE = 'Invalid or expired OTP. Request a new code.';

function invalidate_student_password_otps(PDO $pdo, int $userId): void
{
    $pdo->prepare('UPDATE student_password_otps SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$userId]);
}

/** Recovery codes go straight to SMTP; never store them in notifications or logs. */
function send_student_password_otp(array $user, string $otp, ?array $config = null): void
{
    $config ??= notification_mail_config();
    if (notification_mail_problem($config) !== null) { throw new RuntimeException('Recovery email delivery is unavailable.'); }
    require_once __DIR__.'/vendor/phpmailer/src/Exception.php';
    require_once __DIR__.'/vendor/phpmailer/src/PHPMailer.php';
    require_once __DIR__.'/vendor/phpmailer/src/SMTP.php';
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $config['host'];
    $mail->Port = (int)$config['port'];
    $mail->SMTPAuth = $config['username'] !== '';
    $mail->Username = $config['username'];
    $mail->Password = $config['password'];
    $mail->SMTPSecure = $config['encryption'] === 'none' ? '' : $config['encryption'];
    $mail->SMTPAutoTLS = $config['encryption'] !== 'none';
    $mail->Timeout = max(1, min(30, (int)$config['timeout']));
    $mail->CharSet = 'UTF-8';
    $mail->addCustomHeader('Content-Language', 'en');
    $mail->setFrom($config['from_email'], $config['from_name']);
    $mail->addAddress($user['email'], $user['full_name']);
    $mail->Subject = 'IRDP e-Clearance: Password reset OTP';
    $mail->Body = "Hello ".$user['full_name'].",\n\nYour password reset OTP is: ".$otp."\nThis code expires in ".STUDENT_OTP_MINUTES
        ." minutes and can be used once. Enter it in the same browser where you requested recovery."
        ."\nDo not share this code with anyone. If you did not request a password reset, ignore this email; your password has not changed.\n\nIRDP e-Clearance";
    try { $mail->send(); }
    catch (Throwable $error) { throw new RuntimeException('Recovery email delivery failed.'); }
}

/** Returns an opaque browser challenge for known and unknown addresses alike. */
function request_student_password_otp(PDO $pdo, string $email, ?callable $sender = null): string
{
    $email = email_value($email);
    $challenge = bin2hex(random_bytes(32));
    try {
        rate_limit($pdo, 'student-otp-request-ip', request_ip(), 10, 30);
        rate_limit($pdo, 'student-otp-request-email', mb_strtolower($email), 3, 30);
    } catch (RuntimeException $error) { return $challenge; }
    // The expensive hash is computed for unknown addresses too, before any lookup.
    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hash = hash_new_password($otp);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT u.id,r.name AS role_name FROM users u INNER JOIN roles r ON r.id=u.role_id WHERE LOWER(u.email)=LOWER(?) LIMIT 2');
        $stmt->execute([$email]);
        $matches = $stmt->fetchAll();
        if (count($matches) !== 1 || $matches[0]['role_name'] !== 'STUDENT') { $pdo->commit(); return $challenge; }
        $stmt = $pdo->prepare('SELECT u.id,u.email,u.full_name,u.auth_version,u.active,r.name AS role_name FROM users u INNER JOIN roles r ON r.id=u.role_id WHERE u.id=? FOR UPDATE');
        $stmt->execute([$matches[0]['id']]);
        $user = $stmt->fetch();
        if (!$user || !(int)$user['active'] || $user['role_name'] !== 'STUDENT' || strcasecmp((string)$user['email'], $email) !== 0) {
            $pdo->commit(); return $challenge;
        }
        $stmt = $pdo->prepare('SELECT id FROM student_password_otps WHERE user_id=? AND created_at>DATE_SUB(NOW(),INTERVAL 60 SECOND) LIMIT 1');
        $stmt->execute([$user['id']]);
        if ($stmt->fetchColumn()) { $pdo->commit(); return $challenge; }
        invalidate_student_password_otps($pdo, (int)$user['id']);
        $pdo->prepare('INSERT INTO student_password_otps(user_id,challenge_hash,otp_hash,email,auth_version,expires_at) VALUES (?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE))')
            ->execute([$user['id'],hash('sha256',$challenge),$hash,$user['email'],$user['auth_version']]);
        $id = (int)$pdo->lastInsertId();
        audit($pdo,'STUDENT_PASSWORD_OTP_REQUESTED',(int)$user['id'],null,'Email recovery challenge issued.');
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $error;
    }
    $deliver = static function() use ($pdo,$user,$otp,$id,$sender): void {
        try {
            // Discard obsolete deliveries without sending an old code to a changed email.
            $stmt = $pdo->prepare('SELECT o.id FROM student_password_otps o INNER JOIN users u ON u.id=o.user_id WHERE o.id=? AND o.used_at IS NULL AND o.expires_at>NOW() AND u.active=1 AND LOWER(u.email)=LOWER(o.email) AND u.auth_version=o.auth_version');
            $stmt->execute([$id]);
            if (!$stmt->fetchColumn()) { return; }
            ($sender ?? 'send_student_password_otp')($user,$otp);
            $pdo->prepare('UPDATE student_password_otps SET delivered_at=NOW() WHERE id=? AND used_at IS NULL')->execute([$id]);
        } catch (Throwable $error) {
            try {
                $pdo->prepare('UPDATE student_password_otps SET used_at=NOW() WHERE id=? AND used_at IS NULL')->execute([$id]);
                audit($pdo,'STUDENT_PASSWORD_OTP_DELIVERY_FAILED',(int)$user['id'],null,'Recovery email delivery failed; request a new OTP or contact support.');
            } catch (Throwable $ignored) { /* Never emit SMTP details or the OTP. */ }
            error_log('Student password recovery email delivery failed.');
        }
    };
    if ($sender !== null) { $deliver(); }
    else { register_shutdown_function($deliver); }
    return $challenge;
}

/** Verify atomically, then issue a fresh reset grant kept only in the PHP session. */
function verify_student_password_otp(PDO $pdo, string $challenge, mixed $otp): string
{
    rate_limit($pdo,'student-otp-verify-ip',request_ip(),30,30);
    if (!preg_match('/^[a-f0-9]{64}$/D',$challenge)) { throw new ValidationException(['otp'=>STUDENT_OTP_INVALID_MESSAGE]); }
    $stmt=$pdo->prepare('SELECT user_id FROM student_password_otps WHERE challenge_hash=?');
    $stmt->execute([hash('sha256',$challenge)]); $userId=$stmt->fetchColumn();
    if (!$userId) { throw new ValidationException(['otp'=>STUDENT_OTP_INVALID_MESSAGE]); }
    $pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare('SELECT u.*,r.name AS role_name FROM users u INNER JOIN roles r ON r.id=u.role_id WHERE u.id=? FOR UPDATE');
        $stmt->execute([$userId]); $user=$stmt->fetch();
        $stmt=$pdo->prepare('SELECT *,expires_at>NOW() AS valid_time FROM student_password_otps WHERE challenge_hash=? FOR UPDATE');
        $stmt->execute([hash('sha256',$challenge)]); $row=$stmt->fetch();
        if (!$user || !$row || !(int)$user['active'] || $user['role_name']!=='STUDENT' || $row['used_at'] || !$row['delivered_at']
            || !(int)$row['valid_time'] || (int)$row['attempts']>=STUDENT_OTP_ATTEMPTS
            || strcasecmp((string)$user['email'],$row['email'])!==0 || (int)$user['auth_version']!==(int)$row['auth_version']) {
            $pdo->commit(); throw new ValidationException(['otp'=>STUDENT_OTP_INVALID_MESSAGE]);
        }
        $correct=is_string($otp) && preg_match('/^[0-9]{6}$/D',$otp) && password_verify($otp,$row['otp_hash']);
        if (!$correct) {
            $pdo->prepare('UPDATE student_password_otps SET attempts=attempts+1,used_at=IF(attempts>=?,NOW(),NULL) WHERE id=?')
                ->execute([STUDENT_OTP_ATTEMPTS,$row['id']]);
            $pdo->commit(); throw new ValidationException(['otp'=>STUDENT_OTP_INVALID_MESSAGE]);
        }
        $credential=create_reset_credential($pdo,(int)$userId,null,'Student email OTP verified');
        $stmt=$pdo->prepare('SELECT id FROM password_resets WHERE token_hash=?');
        $stmt->execute([hash('sha256',$credential)]); $resetId=(int)$stmt->fetchColumn();
        $pdo->prepare('UPDATE password_resets SET expires_at=DATE_ADD(NOW(),INTERVAL 10 MINUTE) WHERE id=?')->execute([$resetId]);
        $pdo->prepare('UPDATE student_password_otps SET used_at=NOW(),reset_id=? WHERE id=?')->execute([$resetId,$row['id']]);
        $pdo->prepare('UPDATE users SET email_verified_at=COALESCE(email_verified_at,NOW()) WHERE id=?')->execute([$userId]);
        audit($pdo,'STUDENT_PASSWORD_OTP_VERIFIED',(int)$userId,null,'Email OTP verified; restricted password reset granted.');
        $pdo->commit();
        return $credential;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $error;
    }
}

/** Finish the generic redirect before SMTP, keeping delivery time out of account lookup. */
function redirect_to_student_otp(): never
{
    ignore_user_abort(true);
    header('Location: '.url('auth/verify_otp.php'));
    header('Content-Length: 0');
    header('Connection: close');
    session_write_close();
    if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); }
    else { while (ob_get_level()>0) { ob_end_flush(); } flush(); }
    exit;
}
