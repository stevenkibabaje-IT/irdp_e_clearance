<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Command line only.'); }
require_once __DIR__ . '/../includes/installer.php';
require_once __DIR__ . '/../includes/functions.php';
date_default_timezone_set('Africa/Dar_es_Salaam');
$_SESSION = [];
function email_check(bool $condition, string $message): void { if (!$condition) { throw new RuntimeException($message); } }
function email_rejected(callable $fn, string $message): void { try { $fn(); } catch (RuntimeException|LogicException $e) { return; } throw new RuntimeException($message); }
function email_http($curl, string $path, ?array $post = null): array {
    curl_setopt_array($curl,[CURLOPT_URL=>'http://127.0.0.1:18089'.$path,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>15,CURLOPT_POST=>$post!==null]);
    if ($post !== null) { curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post)); }
    else { curl_setopt($curl,CURLOPT_HTTPGET,true); }
    $body=curl_exec($curl);
    if ($body===false) { throw new RuntimeException('HTTP fixture unavailable.'); }
    return [(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE),$body];
}
function email_csrf(string $body): string { if(!preg_match('/name="csrf_token" value="([^"]+)"/',$body,$m)){throw new RuntimeException('CSRF missing.');}return html_entity_decode($m[1],ENT_QUOTES,'UTF-8'); }
$database='irdp_email_test_'.bin2hex(random_bytes(8));
$server=null;$created=false;$smtp=null;$web=null;$exit=0;
$runtime=__DIR__.'/.email-sessions/'.$database;
try {
    $server=db_connect(false);
    $server->exec('CREATE DATABASE '.$database.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created=true;$server->exec('USE '.$database);$pdo=ensure_database_ready($server);
    $user=(int)$pdo->query("SELECT id FROM users WHERE username='IRDP/ODICT/MA25/0001'")->fetchColumn();
    $other=(int)$pdo->query("SELECT id FROM users WHERE username='IRDP/BTCRP/MA25/0002'")->fetchColumn();
    $admin=(int)$pdo->query("SELECT id FROM users WHERE username='admin'")->fetchColumn();
    $pdo->prepare('UPDATE users SET email=? WHERE id=?')->execute(['student@example.test',$user]);
    $pdo->prepare('UPDATE users SET email=? WHERE id=?')->execute(['admin@example.test',$admin]);
    notify($pdo,$user,'Clearance stage approved','Stage 1 approved. Unicode: mwanafunzi ✓');
    $notification=(int)$pdo->query('SELECT MAX(id) FROM notifications')->fetchColumn();
    queue_notification_email($pdo,$notification);
    email_check((int)$pdo->query('SELECT COUNT(*) FROM email_outbox')->fetchColumn()===1,'Notification queued more than once.');
    notify($pdo,$other,'No email','Account without an email');notify($pdo,$admin,'Staff','Staff notification');
    email_check((int)$pdo->query('SELECT COUNT(*) FROM email_outbox')->fetchColumn()===1,'Staff or missing address was queued.');
    $pdo->beginTransaction();notify($pdo,$user,'Rolled back','Never deliver this');$pdo->rollBack();
    email_check((int)$pdo->query('SELECT COUNT(*) FROM notifications WHERE title="Rolled back"')->fetchColumn()===0,'Notification escaped rollback.');
    email_check((int)$pdo->query('SELECT COUNT(*) FROM email_outbox')->fetchColumn()===1,'Email escaped rollback.');
    $config=['enabled'=>true,'host'=>'127.0.0.1','port'=>2525,'encryption'=>'none','username'=>'','password'=>'','from_email'=>'sender@example.test','from_name'=>'IRDP test','timeout'=>3];
    $disabled=process_notification_emails($pdo,20,array_replace($config,['enabled'=>false]));
    email_check($disabled['problem']!==null&&(int)$pdo->query('SELECT attempts FROM email_outbox')->fetchColumn()===0,'Disabled delivery consumed an attempt.');
    $pdo->beginTransaction();email_rejected(fn()=>process_notification_emails($pdo,20,$config),'Worker sent inside a transaction.');$pdo->rollBack();
    email_check(notification_mail_problem(array_replace($config,['host'=>'smtp.gmail.com']))!==null,'Remote unencrypted SMTP accepted.');
    echo "PASS: one student email per notification, missing email, staff filtering, disabled settings and transaction rollback\n";

    mkdir($runtime,0700,true);
    $messagePath=$runtime.'/message.txt';
    $smtp=proc_open([PHP_BINARY,__DIR__.'/email_smtp_fixture.php',$messagePath],[0=>['pipe','r'],1=>['pipe','w'],2=>['file',$runtime.'/smtp.log','a']],$smtpPipes);
    email_check(is_resource($smtp),'SMTP fixture failed to start.');fclose($smtpPipes[0]);
    $address=trim((string)fgets($smtpPipes[1]));
    email_check((bool)preg_match('/^127\.0\.0\.1:([0-9]+)$/D',$address,$match),'SMTP fixture address unavailable.');$config['port']=(int)$match[1];
    $result=process_notification_emails($pdo,20,$config);
    email_check($result['sent']===1&&$result['retry']===0,'PHPMailer did not send to local SMTP.');
    fclose($smtpPipes[1]);email_check(proc_close($smtp)===0,'SMTP fixture failed.');$smtp=null;
    $message=file_get_contents($messagePath);
    email_check(str_contains($message,'student@example.test')&&str_contains($message,'IRDP e-Clearance: Clearance stage approved')&&str_contains(quoted_printable_decode($message),'mwanafunzi ✓'),'SMTP message recipient, subject or Unicode body missing.');
    $calls=0;process_notification_emails($pdo,20,$config,function()use(&$calls){$calls++;});email_check($calls===0,'Sent mail was sent again.');
    echo "PASS: real PHPMailer SMTP delivery, recipient, Unicode content and no re-send of SENT emails\n";

    notify($pdo,$user,'Failure isolation','Keep notification when SMTP fails');
    $failed=(int)$pdo->query('SELECT MAX(id) FROM email_outbox')->fetchColumn();
    $result=process_notification_emails($pdo,20,$config,function(){throw new RuntimeException('secret-password-must-not-appear');});
    email_check($result['retry']===1,'SMTP failure did not queue retry.');
    $row=$pdo->query('SELECT * FROM email_outbox WHERE id='.$failed)->fetch();
    email_check($row['status']==='RETRY'&&(int)$row['attempts']===1&&!str_contains($row['last_error'],'secret-password'),'Retry leaked credentials or state is incorrect.');
    email_check((int)$pdo->query('SELECT COUNT(*) FROM notifications WHERE title="Failure isolation"')->fetchColumn()===1,'SMTP failure removed in-app notification.');
    process_notification_emails($pdo,20,$config,function()use(&$calls){$calls++;});email_check($calls===0,'Retry ignored backoff.');
    $pdo->exec('UPDATE email_outbox SET attempts=4,available_at=NOW() WHERE id='.$failed);
    $result=process_notification_emails($pdo,20,$config,function(){throw new RuntimeException('failure');});
    email_check($result['failed']===1,'Repeated failure did not stop at five attempts.');
    echo "PASS: SMTP failure isolation, private error messages, backoff and retry limit\n";

    notify($pdo,$user,'Old email','Do not send to an old address');
    email_rejected(fn()=>update_student_notification_email($pdo,$user,['email'=>'new@example.test','current_password'=>'wrong']),'Incorrect password changed email.');
    $pdo->prepare('UPDATE users SET email=? WHERE id=?')->execute(['duplicate@example.test',$other]);
    email_rejected(fn()=>update_student_notification_email($pdo,$user,['email'=>'duplicate@example.test','current_password'=>'KIBABAJE0001']),'Duplicate email accepted.');
    update_student_notification_email($pdo,$user,['email'=>'new@example.test','current_password'=>'KIBABAJE0001']);
    email_check((int)$pdo->query('SELECT COUNT(*) FROM email_outbox WHERE status="CANCELLED"')->fetchColumn()===2,'Old queued recipients were not cancelled.');
    notify($pdo,$user,'Changed externally','Worker checks current email');
    $pdo->prepare('UPDATE users SET email=? WHERE id=?')->execute(['latest@example.test',$user]);
    $result=process_notification_emails($pdo,20,$config,function(){throw new RuntimeException('Should never send');});email_check($result['cancelled']===1,'Worker delivered to stale email.');
    echo "PASS: email ownership password check, uniqueness and cancellation of stale recipients\n";

    $before=(int)$pdo->query('SELECT COUNT(*) FROM email_outbox')->fetchColumn();
    $cycle=(int)$pdo->query("SELECT id FROM academic_cycles WHERE label='2026/2027'")->fetchColumn();
    update_clearance_period($pdo,['mode'=>'MANUAL_OPEN','cycle_id'=>(string)$cycle,'remarks'=>'Email test opening'], $admin);
    email_check((int)$pdo->query('SELECT COUNT(*) FROM email_outbox')->fetchColumn()===$before+2,'Clearance opening did not queue emailed students.');
    echo "PASS: clearance period opening uses student email queue\n";

    $pendingBefore=(int)$pdo->query('SELECT COUNT(*) FROM email_outbox WHERE status="PENDING"')->fetchColumn();
    $selected=notify($pdo,$user,'Selected test message','Send just this test message');
    $result=process_notification_emails($pdo,20,$config,static function(){},$selected);
    email_check($result['sent']===1&&(int)$pdo->query('SELECT COUNT(*) FROM email_outbox WHERE status="PENDING"')->fetchColumn()===$pendingBefore,'Targeted test sent unrelated pending notifications.');
    echo "PASS: test delivery sends only its selected notification\n";

    $environment=getenv();$environment['DB_HOST']=DB_HOST;$environment['DB_PORT']=(string)DB_PORT;$environment['DB_NAME']=$database;$environment['DB_USER']=DB_USER;$environment['DB_PASS']=DB_PASS;
    $web=proc_open([PHP_BINARY,'-d','session.save_path='.$runtime,'-S','127.0.0.1:18089','-t',dirname(__DIR__),__DIR__.'/http_router.php'],[0=>['pipe','r'],1=>['file',$runtime.'/web.log','a'],2=>['file',$runtime.'/web.log','a']],$pipes,dirname(__DIR__),$environment);
    email_check(is_resource($web),'HTTP fixture failed to start.');fclose($pipes[0]);
    $ready=false;for($i=0;$i<30;$i++){$socket=@fsockopen('127.0.0.1',18089,$number,$error,0.1);if($socket){fclose($socket);$ready=true;break;}usleep(100000);}email_check($ready,'HTTP server unavailable.');
    $curl=curl_init();curl_setopt($curl,CURLOPT_COOKIEFILE,'');
    [$code,$body]=email_http($curl,'/auth/login.php');
    [$code]=email_http($curl,'/auth/login.php',['csrf_token'=>email_csrf($body),'username'=>'IRDP/ODICT/MA25/0001','password'=>'KIBABAJE0001']);email_check($code===302,'Student login failed.');
    [$code,$body]=email_http($curl,'/student/profile.php');email_check($code===200&&str_contains($body,'latest@example.test')&&str_contains($body,'Notification email'),'Student email form absent.');
    [$code,$body]=email_http($curl,'/student/profile.php',['csrf_token'=>email_csrf($body),'mode'=>'email','email'=>'http@example.test','current_password'=>'KIBABAJE0001']);email_check($code===302,'Profile email submission failed.');
    [$code]=email_http($curl,'/admin/email_notifications.php');email_check($code===403,'Student accessed email administration.');
    [$code]=email_http($curl,'/student/profile.php',['mode'=>'email','email'=>'csrf@example.test','current_password'=>'KIBABAJE0001']);email_check($code===400||$code===419||$code===403,'Missing CSRF accepted.');
    curl_close($curl);$curl=curl_init();curl_setopt($curl,CURLOPT_COOKIEFILE,'');
    [$code,$body]=email_http($curl,'/auth/login.php');
    [$code]=email_http($curl,'/auth/login.php',['csrf_token'=>email_csrf($body),'username'=>'admin','password'=>'Admin@IRDP2026']);email_check($code===302,'Admin login failed.');
    [$code,$body]=email_http($curl,'/admin/email_notifications.php');email_check($code===200&&str_contains($body,'Recent delivery history'),'Admin delivery history unavailable.');curl_close($curl);
    echo "PASS: student profile HTTP save, CSRF protection and admin-only delivery history\n";
} catch (Throwable $e) { fwrite(STDERR,'FAIL: '.$e->getMessage()."\n");$exit=1; }
finally {
    if(is_resource($web)){proc_terminate($web);proc_close($web);}
    if(is_resource($smtp)){proc_terminate($smtp);proc_close($smtp);}
    if(is_dir($runtime)){foreach(glob($runtime.'/*')?:[] as $file){if(is_file($file)){unlink($file);}}rmdir($runtime);}
    if($created&&$server&&preg_match('/^irdp_email_test_[a-f0-9]{16}$/D',$database)){$server->exec('DROP DATABASE '.$database);echo "Isolated email test database removed.\n";}
}
exit($exit);
