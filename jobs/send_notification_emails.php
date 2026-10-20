<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Command line only.');}
require_once __DIR__.'/../includes/installer.php';
require_once __DIR__.'/../includes/functions.php';
date_default_timezone_set('Africa/Dar_es_Salaam');
try{
    if(in_array('--check',$argv,true)){
        $pdo=application_database();$config=notification_mail_config();
        $problem=notification_mail_problem($config);$extensions=[];
        foreach(['pdo_mysql','mbstring','openssl'] as $extension){$extensions[$extension]=extension_loaded($extension);}
        echo json_encode(['check_only'=>true,'configuration_problem'=>$problem,'extensions'=>$extensions,
            'queue'=>$pdo->query('SELECT status,COUNT(*) AS total FROM email_outbox GROUP BY status')->fetchAll()],JSON_THROW_ON_ERROR),"\n";
        exit($problem!==null||in_array(false,$extensions,true)?1:0);
    }
    $result=process_notification_emails(application_database());
    echo json_encode($result,JSON_THROW_ON_ERROR),"\n";
    exit($result['problem']!==null||$result['failed']>0||$result['retry']>0?1:0);
}catch(Throwable $error){fwrite(STDERR,"Email worker failed. Check database availability and SMTP configuration.\n");exit(1);}
