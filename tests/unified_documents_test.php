<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once __DIR__.'/../includes/installer.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/unified_documents_migrations.php';
function document_check(bool $ok,string $message): void {if(!$ok){throw new RuntimeException($message);}}
$database='irdp_documents_'.bin2hex(random_bytes(8));$server=null;$created=false;$exit=0;
try {
    $backup=realpath($argv[1]??__DIR__.'/../storage/private/normalization-backup/before-document-merge.sql');
    document_check($backup!==false,'A pre-merge backup is required.');
    $server=new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4',getenv('IRDP_TEST_USER')?:'root',getenv('IRDP_TEST_PASSWORD')?:'',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    $server->exec('CREATE DATABASE '.$database.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;
    $env=getenv();$env['MYSQL_PWD']=getenv('IRDP_TEST_PASSWORD')?:'';
    $restore=proc_open(['C:/xampp/mysql/bin/mysql.exe','--host=127.0.0.1','--port=3306','--user='.(getenv('IRDP_TEST_USER')?:'root'),$database],
        [0=>['file',$backup,'r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);
    document_check(is_resource($restore),'Backup restore could not start.');
    $output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    document_check(proc_close($restore)===0,'Backup restore failed: '.$errors);
    $server->exec('USE '.$database);$pdo=$server;
    $beforeTables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    document_check(count($beforeTables)===30,'Expected a 30-table pre-merge backup.');
    $before=$pdo->query('SELECT * FROM transcripts ORDER BY id')->fetchAll();
    $certificates=$pdo->query('SELECT * FROM certificates ORDER BY id')->fetchAll();
    document_check(count($before)>0&&count($certificates)>0,'Backup has no document preservation fixtures.');
    $r=$pdo->query('SELECT * FROM clearance_requests ORDER BY id LIMIT 1')->fetch();
    $pdo->exec('INSERT IGNORE INTO academic_cycles(label) VALUES ("2090/2091")');
    $pdo->prepare('INSERT INTO clearance_requests(student_id,academic_year,status) VALUES (?,"2090/2091","CANCELLED")')->execute([$r['student_id']]);
    $legacyRequest=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO certificates(clearance_request_id,certificate_number,verification_code,issued_at) VALUES (?,"IRDP-CLR-2090-999999","ABCDEF1234","2090-01-01 10:00:00")')->execute([$legacyRequest]);
    $certificates=$pdo->query('SELECT * FROM certificates ORDER BY id')->fetchAll();
    migrate_unified_documents($pdo);
    document_check(count($pdo->query('SHOW TABLES')->fetchAll())===29,'Migration did not reduce tables to 29.');
    foreach($before as $old){
        $s=$pdo->prepare('SELECT * FROM transcripts WHERE id=?');$s->execute([$old['id']]);$new=$s->fetch();
        foreach($old as $key=>$value){document_check($new[$key]===$value,'Changed original document field '.$key);}
    }
    foreach($certificates as $old){
        $s=$pdo->prepare('SELECT * FROM transcripts WHERE legacy_certificate_id=?');$s->execute([$old['id']]);$new=$s->fetch();
        document_check((bool)$new,'Lost legacy certificate identity.');
        foreach(['clearance_request_id','certificate_number','verification_code','issued_at'] as $key){document_check($new[$key]===$old[$key],'Changed legacy '.$key);}
    }
    $s=$pdo->prepare('SELECT * FROM transcripts WHERE clearance_request_id=?');$s->execute([$legacyRequest]);$identity=$s->fetch();
    document_check($identity['snapshot_json']===null&&$identity['generated_by']===null&&$identity['verification_token']===null,'Legacy-only certificate received fabricated document metadata.');
    document_check(transcript_for_student($pdo,(int)$r['student_id'],$legacyRequest)===null,'Identity-only certificate treated as a downloadable snapshot.');
    $merged=$pdo->query('SELECT * FROM transcripts ORDER BY id')->fetchAll();
    migrate_unified_documents($pdo);ensure_database_ready($pdo,true);
    document_check($pdo->query('SELECT * FROM transcripts ORDER BY id')->fetchAll()===$merged,'Repeated migration changed document records.');
    document_check(count($pdo->query('SHOW TABLES')->fetchAll())===28,'Forced setup recreated retired tables.');
    foreach($before as $doc){
        $s=$pdo->prepare('SELECT snapshot_json,snapshot_sha256,status FROM transcripts WHERE verification_token=?');$s->execute([$doc['verification_token']]);$new=$s->fetch();
        document_check($new['snapshot_json']===$doc['snapshot_json']&&$new['snapshot_sha256']===$doc['snapshot_sha256']&&$new['status']===$doc['status'],'Old verification token or snapshot changed.');
    }
    echo "PASS: full backup restored; 30 tables become 29; legacy numbers/codes/dates/IDs, document IDs/tokens/snapshots/statuses preserved; identity-only legacy records remain honest; repeated migration and forced setup are stable.\n";
}catch(Throwable $e){fwrite(STDERR,'FAIL: '.$e->getMessage()."\n");$exit=1;}
finally{if($created&&$server&&preg_match('/^irdp_documents_[a-f0-9]{16}$/D',$database)){$server->exec('DROP DATABASE '.$database);}}
exit($exit);
