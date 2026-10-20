<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once __DIR__.'/../includes/installer.php';
require_once __DIR__.'/../includes/functions.php';
function import_check(bool $ok,string $message): void {if(!$ok){throw new RuntimeException($message);}}
$database='irdp_import_'.bin2hex(random_bytes(8));$server=null;$created=false;$exit=0;
try{
    $server=new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4',getenv('IRDP_TEST_USER')?:'root',getenv('IRDP_TEST_PASSWORD')?:'',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    $server->exec('CREATE DATABASE '.$database.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;$server->exec('USE '.$database);
    $pdo=ensure_database_ready($server);
    import_check(count($pdo->query('SHOW TABLES')->fetchAll())===28,'Expected 28 tables.');
    $admin=(int)$pdo->query('SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name="ADMIN" LIMIT 1')->fetchColumn();
    $rows=array_slice(student_template_rows(),1);
    $preview=preview_import($pdo,$rows);
    foreach($preview as $item){import_check(!$item['errors'],'Template preview failed: '.implode(' ',$item['errors']));}
    $result=commit_import($pdo,$preview,$admin);
    import_check($result['created']===2&&$result['failed']===0,'Student import failed without import_runs.');
    $again=commit_import($pdo,$preview,$admin);
    import_check($again['created']===0&&$again['skipped']===2,'Duplicate import was not skipped.');
    import_check((int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action='STUDENT_IMPORTED'")->fetchColumn()===2,'Per-student import audit missing.');
    import_check((int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action='STUDENT_IMPORT_COMPLETED'")->fetchColumn()===2,'Import outcome audit missing.');
    // Non-empty historical tables are protected until explicitly archived.
    $pdo->exec('CREATE TABLE import_runs(id INT PRIMARY KEY)');$pdo->exec('INSERT INTO import_runs VALUES (1)');
    $blocked=false;try{migrate_retired_feature_tables($pdo);}catch(RuntimeException $e){$blocked=str_contains($e->getMessage(),'import_runs');}
    import_check($blocked,'Non-empty legacy import_runs was dropped.');
    $pdo->exec('DELETE FROM import_runs');migrate_retired_feature_tables($pdo);
    ensure_database_ready($pdo,true);
    import_check(count($pdo->query('SHOW TABLES')->fetchAll())===28,'Forced setup recreated import_runs.');
    echo "PASS: 28 tables; real imports and duplicate skips succeed; audit history retained; non-empty legacy data protected; empty table removed and not recreated.\n";
}catch(Throwable $e){fwrite(STDERR,'FAIL: '.$e->getMessage()."\n");$exit=1;}
finally{if($created&&$server&&preg_match('/^irdp_import_[a-f0-9]{16}$/D',$database)){$server->exec('DROP DATABASE '.$database);}}
exit($exit);
