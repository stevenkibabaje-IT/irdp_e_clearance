<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(403); exit('Command line only.'); }
require_once __DIR__.'/../includes/installer.php';
require_once __DIR__.'/../includes/functions.php';
date_default_timezone_set('Africa/Dar_es_Salaam'); $_SESSION=[];

function otp_check(bool $condition,string $message): void { if(!$condition){throw new RuntimeException($message);} }
function otp_rejected(callable $operation,string $message): void {
    try {$operation();} catch(RuntimeException $exception){return;} throw new RuntimeException($message);
}
function otp_csrf(string $html): string {
    otp_check((bool)preg_match('/name="csrf_token" value="([a-f0-9]{64})"/',$html,$match),'CSRF field missing.');return $match[1];
}
final class OtpTestBrowser {
    public string $cookie='';
    public function __construct(private string $origin) {}
    public function request(string $route,?array $post=null): array {
        $curl=curl_init($this->origin.$route);
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIE=>$this->cookie]);
        if($post!==null){curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);}
        $response=curl_exec($curl);if($response===false){throw new RuntimeException(curl_error($curl));}
        $length=(int)curl_getinfo($curl,CURLINFO_HEADER_SIZE);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
        $headers=substr($response,0,$length);
        if(preg_match('/^Set-Cookie:\s*(PHPSESSID=[^;\r\n]+)/mi',$headers,$match)){$this->cookie=$match[1];}
        return [$status,substr($response,$length),$headers];
    }
}

$dsn=$argv[1]??'mysql:host=127.0.0.1;port=3306;charset=utf8mb4';
$database='irdp_otp_'.bin2hex(random_bytes(8));$temporary=sys_get_temp_dir().DIRECTORY_SEPARATOR.'irdp-otp-'.bin2hex(random_bytes(8));
$server=$pdo=$web=$smtp=null;$created=false;$exit=0;
try {
    mkdir($temporary,0700);mkdir($temporary.'/sessions',0700);
    $server=new PDO($dsn,getenv('IRDP_TEST_USER')?:'root',getenv('IRDP_TEST_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $server->exec('CREATE DATABASE '.$database.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;$server->exec('USE '.$database);$pdo=ensure_database_ready($server);
    $users=$pdo->query('SELECT u.*,s.id AS student_id,r.name AS role_name FROM users u INNER JOIN roles r ON r.id=u.role_id INNER JOIN students s ON s.user_id=u.id ORDER BY s.id')->fetchAll();
    foreach($users as $index=>&$user){$user['email']='student'.$index.'@example.test';$pdo->prepare('UPDATE users SET email=? WHERE id=?')->execute([$user['email'],$user['id']]);}unset($user);
    $accountsBefore=$pdo->query('SELECT * FROM users ORDER BY id')->fetchAll();
    $pdo->exec('DROP TABLE student_password_otps');
    $pdo->prepare('DELETE FROM schema_migrations WHERE version=?')->execute([APPLICATION_SCHEMA_VERSION]);
    ensure_database_ready($pdo,false);
    otp_check(count($pdo->query('SHOW TABLES')->fetchAll())===30 && $pdo->query('SELECT * FROM users ORDER BY id')->fetchAll()===$accountsBefore,'Additive OTP upgrade changed accounts or failed to add its table.');
    ensure_database_ready($pdo,false);
    echo "PASS: additive OTP migration preserves accounts and is repeatable\n";
    $codes=[];
    $sender=static function(array $user,string $otp) use (&$codes): void {$codes[(int)$user['id']]=$otp;};
    $issue=static function(array $user,?callable $delivery=null) use ($pdo,$sender): string {
        // Independent scenarios start with clean throttles and an elapsed resend interval.
        $pdo->exec('DELETE FROM rate_limits');
        $pdo->prepare('UPDATE student_password_otps SET created_at=DATE_SUB(NOW(),INTERVAL 2 MINUTE) WHERE user_id=?')->execute([$user['id']]);
        return request_student_password_otp($pdo,$user['email'],$delivery??$sender);
    };
    $row=static function(string $challenge) use ($pdo): array {
        $stmt=$pdo->prepare('SELECT * FROM student_password_otps WHERE challenge_hash=?');$stmt->execute([hash('sha256',$challenge)]);return $stmt->fetch()?:[];
    };
    $challenge=$issue($users[0]);$stored=$row($challenge);$otp=$codes[(int)$users[0]['id']];
    otp_check(preg_match('/^[0-9]{6}$/D',$otp)===1 && $stored['otp_hash']!==$otp && password_verify($otp,$stored['otp_hash']) && $stored['challenge_hash']!==$challenge,'OTP or challenge is not securely generated/stored.');
    $before=user_record($pdo,(int)$users[0]['id']);
    $wrong=$otp==='000000'?'000001':'000000';
    otp_rejected(fn()=>verify_student_password_otp($pdo,$challenge,$wrong),'Incorrect OTP accepted.');
    otp_check((int)$row($challenge)['attempts']===1 && user_record($pdo,(int)$users[0]['id'])['password_hash']===$before['password_hash'],'Wrong OTP changed password or failed to count an attempt.');
    $grant=verify_student_password_otp($pdo,$challenge,$otp);
    otp_check(preg_match('/^[a-f0-9]{64}$/D',$grant)===1 && $row($challenge)['used_at'] && user_record($pdo,(int)$users[0]['id'])['email_verified_at'],'OTP did not produce a restricted, one-use grant.');
    otp_rejected(fn()=>verify_student_password_otp($pdo,$challenge,$otp),'OTP replay accepted.');
    otp_rejected(fn()=>reset_password($pdo,$grant,'River meadow lantern 62','Wrong confirmation'),'Password mismatch accepted.');
    reset_password($pdo,$grant,'River meadow lantern 62','River meadow lantern 62');
    $updated=user_record($pdo,(int)$users[0]['id']);
    otp_check(password_verify('River meadow lantern 62',$updated['password_hash']) && (int)$updated['auth_version']===(int)$before['auth_version']+1,'Reset did not save password or revoke sessions.');
    otp_rejected(fn()=>reset_password($pdo,$grant,'Cloud garden willow 73','Cloud garden willow 73'),'Reset grant replay accepted.');
    otp_check((int)$pdo->query('SELECT COUNT(*) FROM notifications WHERE title="Password updated"')->fetchColumn()===1,'Password reset notification missing.');
    echo "PASS: hashed six-digit OTP, counted wrong attempt, one-use verification/grant, confirmation, password reset and session revocation\n";

    $challenge=$issue($users[1]);$otp=$codes[(int)$users[1]['id']];$wrong=$otp==='000000'?'000001':'000000';
    for($attempt=0;$attempt<5;$attempt++){otp_rejected(fn()=>verify_student_password_otp($pdo,$challenge,$wrong),'Incorrect OTP accepted.');}
    otp_check((int)$row($challenge)['attempts']===5 && $row($challenge)['used_at'],'Five failed attempts did not invalidate the challenge.');
    otp_rejected(fn()=>verify_student_password_otp($pdo,$challenge,$otp),'Locked OTP remained usable.');
    $challenge=$issue($users[2]);$pdo->prepare('UPDATE student_password_otps SET expires_at=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE challenge_hash=?')->execute([hash('sha256',$challenge)]);
    otp_rejected(fn()=>verify_student_password_otp($pdo,$challenge,$codes[(int)$users[2]['id']]),'Expired OTP accepted.');
    $first=$issue($users[3]);$firstCode=$codes[(int)$users[3]['id']];
    $count=(int)$pdo->query('SELECT COUNT(*) FROM student_password_otps')->fetchColumn();
    request_student_password_otp($pdo,$users[3]['email'],$sender);
    otp_check((int)$pdo->query('SELECT COUNT(*) FROM student_password_otps')->fetchColumn()===$count,'60-second resend cooldown was bypassed.');
    $second=$issue($users[3]);
    otp_rejected(fn()=>verify_student_password_otp($pdo,$first,$firstCode),'Resending did not invalidate the old code.');
    verify_student_password_otp($pdo,$second,$codes[(int)$users[3]['id']]);
    echo "PASS: five-attempt limit, expiry, resend cooldown and invalidation of replaced OTPs\n";

    $definitions=require __DIR__.'/../config/demo_students.php';
    $challenge=$issue($users[4]);
    change_password($pdo,(int)$users[4]['id'],demo_student_initial_password($definitions[4]),'Maple ocean garden 82','Maple ocean garden 82');
    otp_rejected(fn()=>verify_student_password_otp($pdo,$challenge,$codes[(int)$users[4]['id']]),'OTP survived a password change.');
    $challenge=$issue($users[5]);$grant=verify_student_password_otp($pdo,$challenge,$codes[(int)$users[5]['id']]);
    update_student_notification_email($pdo,(int)$users[5]['id'],['email'=>'changed@example.test','current_password'=>demo_student_initial_password($definitions[5])]);
    otp_rejected(fn()=>reset_password($pdo,$grant,'Maple ocean garden 82','Maple ocean garden 82'),'Grant survived an email change.');
    $challenge=$issue($users[6]);$pdo->prepare('UPDATE users SET active=0 WHERE id=?')->execute([$users[6]['id']]);
    otp_rejected(fn()=>verify_student_password_otp($pdo,$challenge,$codes[(int)$users[6]['id']]),'Inactive account recovered.');
    $challenge=$issue($users[7]);$pdo->prepare('UPDATE users SET email=? WHERE id=?')->execute(['external-change@example.test',$users[7]['id']]);
    otp_rejected(fn()=>verify_student_password_otp($pdo,$challenge,$codes[(int)$users[7]['id']]),'OTP accepted after its recipient changed externally.');
    echo "PASS: password/email changes, grant revocation, inactive accounts and recipient binding\n";

    $pdo->exec('DELETE FROM rate_limits');$count=(int)$pdo->query('SELECT COUNT(*) FROM student_password_otps')->fetchColumn();$called=0;
    $pdo->exec('UPDATE users SET email="staff@example.test" WHERE username="LIB001"');
    foreach(['unknown@example.test','staff@example.test',$users[6]['email']] as $email){
        $dummy=request_student_password_otp($pdo,$email,static function()use(&$called):void{$called++;});otp_check(strlen($dummy)===64,'Neutral recovery challenge missing.');
    }
    otp_check($called===0 && (int)$pdo->query('SELECT COUNT(*) FROM student_password_otps')->fetchColumn()===$count,'Unknown, staff or inactive address issued OTP.');
    $pdo->exec('DELETE FROM rate_limits');
    $pdo->prepare('UPDATE student_password_otps SET created_at=DATE_SUB(NOW(),INTERVAL 2 MINUTE) WHERE user_id=?')->execute([$users[8]['id']]);
    $failed=request_student_password_otp($pdo,$users[8]['email'],static function():void{throw new RuntimeException('private-smtp-secret');});
    otp_check($row($failed)['used_at'] && !$row($failed)['delivered_at'],'Failed SMTP delivery left an active challenge.');
    otp_check(!str_contains(json_encode($pdo->query('SELECT * FROM audit_logs')->fetchAll(),JSON_THROW_ON_ERROR),'private-smtp-secret'),'SMTP details leaked to audit.');
    $pdo->exec('DELETE FROM rate_limits');$sent=0;
    for($attempt=0;$attempt<4;$attempt++){
        $pdo->prepare('UPDATE student_password_otps SET created_at=DATE_SUB(NOW(),INTERVAL 2 MINUTE) WHERE user_id=?')->execute([$users[8]['id']]);
        request_student_password_otp($pdo,$users[8]['email'],static function()use(&$sent):void{$sent++;});
    }
    otp_check($sent===3,'Per-email request limit was bypassed.');
    request_password_recovery($pdo,$users[9]['username']);
    otp_check((int)$pdo->query('SELECT COUNT(*) FROM password_reset_requests')->fetchColumn()===0,'Registration number enabled student recovery without email OTP.');
    echo "PASS: neutral unknown/staff/inactive requests, failed delivery, per-email throttling and registration-number bypass protection\n";

    // Real SMTP and HTTP flow, with all mail confined to a local sink.
    $smtp=proc_open([PHP_BINARY,__DIR__.'/email_smtp_fixture.php',$temporary.'/message.txt'],[0=>['pipe','r'],1=>['pipe','w'],2=>['file',$temporary.'/smtp.log','a']],$smtpPipes);
    otp_check(is_resource($smtp),'SMTP fixture did not start.');fclose($smtpPipes[0]);$address=trim((string)fgets($smtpPipes[1]));
    otp_check((bool)preg_match('/^127\.0\.0\.1:([0-9]+)$/D',$address,$match),'SMTP fixture address missing.');$smtpPort=$match[1];
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);$address=stream_socket_get_name($socket,false);fclose($socket);$port=(int)substr(strrchr($address,':'),1);
    preg_match('/(?:^|;)host=([^;]+)/',$dsn,$hostMatch);preg_match('/(?:^|;)port=(\d+)/',$dsn,$portMatch);
    $environment=getenv();$environment['DB_NAME']=$database;$environment['DB_HOST']=$hostMatch[1]??'127.0.0.1';$environment['DB_PORT']=$portMatch[1]??'3306';$environment['DB_USER']=getenv('IRDP_TEST_USER')?:'root';$environment['DB_PASS']=getenv('IRDP_TEST_PASSWORD')?:'';
    foreach(['ENABLED'=>'1','HOST'=>'127.0.0.1','PORT'=>$smtpPort,'ENCRYPTION'=>'none','USERNAME'=>'','PASSWORD'=>'','FROM_EMAIL'=>'sender@example.test','FROM_NAME'=>'IRDP OTP test','TIMEOUT'=>'3'] as $key=>$value){$environment['IRDP_SMTP_'.$key]=$value;}
    $web=proc_open([PHP_BINARY,'-d','session.save_path='.$temporary.'/sessions','-S','127.0.0.1:'.$port,'-t',dirname(__DIR__),__DIR__.'/http_router.php'],[0=>['pipe','r'],1=>['file',$temporary.'/http.log','a'],2=>['file',$temporary.'/http.log','a']],$pipes,dirname(__DIR__),$environment);
    otp_check(is_resource($web),'HTTP fixture did not start.');fclose($pipes[0]);
    $ready=false;for($attempt=0;$attempt<50;$attempt++){$socket=@fsockopen('127.0.0.1',$port,$errno,$error,.1);if($socket){fclose($socket);$ready=true;break;}usleep(100000);}otp_check($ready,'HTTP fixture not ready.');
    $browser=new OtpTestBrowser('http://127.0.0.1:'.$port);$otherBrowser=new OtpTestBrowser('http://127.0.0.1:'.$port);
    $pdo->exec('DELETE FROM rate_limits');
    [$status,$html]=$browser->request('/auth/forgot.php');$csrf=otp_csrf($html);
    otp_check($status===200 && str_contains($html,'name="email"') && !str_contains($html,'name="username"') && !str_contains($html,'Staff recovery'),'Forgot form is not email-only.');
    [$status]=$browser->request('/auth/forgot.php',['email'=>$users[10]['email']]);otp_check($status===419,'Missing CSRF accepted.');
    [$status,$body,$headers]=$browser->request('/auth/forgot.php',['csrf_token'=>$csrf,'email'=>$users[10]['email']]);
    otp_check($status===302 && str_contains($headers,'/auth/verify_otp.php') && $body==='','Email request did not give a neutral empty redirect.');
    [$status,$html]=$browser->request('/auth/verify_otp.php');
    otp_check($status===200 && str_contains($html,'autocomplete="one-time-code"') && !str_contains($html,'name="password"'),'OTP step skipped or OTP input missing.');
    fclose($smtpPipes[1]);otp_check(proc_close($smtp)===0,'Local SMTP delivery failed.');$smtp=null;
    otp_check(is_file($temporary.'/message.txt'),'SMTP fixture received no email DATA.');
    $message=quoted_printable_decode(file_get_contents($temporary.'/message.txt'));
    otp_check(str_contains($message,$users[10]['email']) && preg_match('/OTP yako ya kubadilisha nenosiri ni: ([0-9]{6})/',$message,$match)===1,'SMTP did not deliver the OTP to the registered email.');$httpOtp=$match[1];
    [$status]=$browser->request('/auth/reset.php',['csrf_token'=>otp_csrf($html),'password'=>'Cedar ocean garden 72','confirm_password'=>'Cedar ocean garden 72']);
    otp_check($status===302,'Password form was reachable before OTP proof.');
    [$status,$otherForm]=$otherBrowser->request('/auth/forgot.php');
    [$unknownStatus,$unknownBody,$unknownHeaders]=$otherBrowser->request('/auth/forgot.php',['csrf_token'=>otp_csrf($otherForm),'email'=>'not-registered@example.test']);
    otp_check($unknownStatus===302 && $unknownBody===$body && str_contains($unknownHeaders,'/auth/verify_otp.php'),'Unknown email response reveals account existence.');
    [$status,$otherForm]=$otherBrowser->request('/auth/verify_otp.php');
    [$status,$invalid]=$otherBrowser->request('/auth/verify_otp.php',['csrf_token'=>otp_csrf($otherForm),'otp'=>$httpOtp]);
    otp_check($status===200 && str_contains($invalid,STUDENT_OTP_INVALID_MESSAGE),'OTP worked in another request/browser.');
    [$status,$html]=$browser->request('/auth/verify_otp.php');
    [$status]=$browser->request('/auth/verify_otp.php',['csrf_token'=>otp_csrf($html),'otp'=>$httpOtp]);otp_check($status===302,'Correct email OTP was not verified.');
    [$status,$html]=$browser->request('/auth/reset.php');
    otp_check($status===200 && str_contains($html,'name="password"') && !str_contains($html,'name="credential"'),'Reset grant leaked or verified password form missing.');
    [$status,$done]=$browser->request('/auth/reset.php',['csrf_token'=>otp_csrf($html),'password'=>'Cedar ocean garden 72','confirm_password'=>'Cedar ocean garden 72']);
    otp_check($status===200 && str_contains($done,'Password updated') && password_verify('Cedar ocean garden 72',user_record($pdo,(int)$users[10]['id'])['password_hash']),'End-to-end password reset failed.');
    [$status]=$browser->request('/student/dashboard.php');otp_check($status===302,'Password reset automatically logged in.');
    $log=file_get_contents($temporary.'/http.log');otp_check(!preg_match('/Fatal error|Parse error|PHP Warning/',$log),'HTTP flow emitted PHP errors.');
    echo "PASS: real local SMTP, simple email form, CSRF, neutral redirects, browser binding, OTP gate, password form and complete HTTP reset without auto-login\n";
} catch(Throwable $error) {fwrite(STDERR,'FAIL: '.$error->getMessage().PHP_EOL);$exit=1;}
finally {
    if($pdo&&$pdo->inTransaction()){$pdo->rollBack();}
    foreach([$web,$smtp] as $process){if(is_resource($process)){proc_terminate($process);proc_close($process);}}
    $pdo=null;
    if($created&&$server&&preg_match('/^irdp_otp_[a-f0-9]{16}$/D',$database)){$server->exec('DROP DATABASE '.$database);echo "Isolated OTP database removed.\n";}
    $target=realpath($temporary);
    if($target&&strcasecmp(dirname($target),realpath(sys_get_temp_dir()))===0&&preg_match('/^irdp-otp-[a-f0-9]{16}$/D',basename($target))){
        foreach(new DirectoryIterator($target.'/sessions') as $entry){if($entry->isFile()){unlink($entry->getPathname());}}rmdir($target.'/sessions');
        foreach(new DirectoryIterator($target) as $entry){if($entry->isFile()){unlink($entry->getPathname());}}rmdir($target);
    }
}
exit($exit);
