<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Command line only.');}
require_once __DIR__.'/../includes/installer.php';
require_once __DIR__.'/../includes/functions.php';
function migration_check(bool $condition,string $message): void {
    if(!$condition){throw new RuntimeException($message);}
}
function migration_fixture(PDO $pdo,int $student,string $cycle,string $status,bool $otherRejected=false,bool $withNumber=true): int {
    $pdo->prepare('INSERT INTO academic_cycles(label) VALUES (?)')->execute([$cycle]);
    $pdo->prepare('INSERT INTO clearance_requests(student_id,academic_year,status,started_at,completed_at) VALUES (?,?,?,NOW(),IF(?="COMPLETED",NOW(),NULL))')->execute([$student,$cycle,$status,$status]);
    $request=(int)$pdo->lastInsertId();
    $record=get_student($pdo,$student);
    foreach($pdo->query('SELECT * FROM workflow_steps ORDER BY step_number')->fetchAll() as $step) {
        $number=(int)$step['step_number'];
        $reviewer=find_office_reviewer($pdo,(int)$step['office_id'],$number===7?(int)$record['department_id']:0);
        $state=$status==='COMPLETED'?'APPROVED':($number===11 || ($otherRejected && $number===1)?'REJECTED':'PENDING');
        $details=[];
        foreach(review_fields($number) as [$key,$label,$type]){$details[$key]=$type==='number'?'0':($type==='asset'?'AVAILABLE':($type==='control_number'?'991234567890':'CLEARED'));}
        if($number===11){$details=$status==='COMPLETED'?['decision'=>'CLEARED','debt'=>'0','recovered'=>'0']:($withNumber?['control_number'=>'991234567890','payment_status'=>'AWAITING_PAYMENT']:['decision'=>'NOT_CLEARED','debt'=>'5000','recovered'=>'0']);}
        $pdo->prepare('INSERT INTO clearance_stages(clearance_request_id,workflow_step_id,office_id,assigned_officer_id,original_officer_id,status,comments,details_json,reviewed_at,actionable_at) VALUES (?,?,?,?,?,?,?, ?,IF(?="PENDING",NULL,NOW()),IF(?="PENDING",NOW(),NULL))')
            ->execute([$request,$step['id'],$step['office_id'],$reviewer,$reviewer,$state,'Historical office finding',json_encode($details,JSON_THROW_ON_ERROR),$state,$state]);
        $id=(int)$pdo->lastInsertId(); ensure_review_cycle($pdo,$id);
        if($state!=='PENDING') {
            $pdo->prepare('UPDATE review_cycles SET closed_at=NOW() WHERE stage_id=?')->execute([$id]);
            $pdo->prepare('INSERT INTO stage_actions(stage_id,officer_id,action,comments,details_json,review_cycle) VALUES (?,?,?,?,?,1)')->execute([$id,$reviewer,$state,'Historical office finding',json_encode($details,JSON_THROW_ON_ERROR)]);
        }
    }
    return $request;
}
$dsn=$argv[1]??'mysql:host=127.0.0.1;port=3306;charset=utf8mb4';
$database='irdp_finance_migration_'.bin2hex(random_bytes(8));$server=null;$pdo=null;$created=false;$exit=0;
try {
    $server=new PDO($dsn,getenv('IRDP_TEST_USER')?:'root',getenv('IRDP_TEST_PASSWORD')?:'',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $server->exec('CREATE DATABASE '.$database.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;$server->exec('USE '.$database);
    $pdo=ensure_database_ready($server);$student=(int)$pdo->query('SELECT id FROM students ORDER BY id LIMIT 1')->fetchColumn();
    $active=migration_fixture($pdo,$student,'2031/2032','PAUSED');
    $multiple=migration_fixture($pdo,$student,'2032/2033','PAUSED',true);
    $completed=migration_fixture($pdo,$student,'2033/2034','COMPLETED');
    $cancelled=migration_fixture($pdo,$student,'2034/2035','CANCELLED');
    $legacy=migration_fixture($pdo,$student,'2035/2036','PAUSED',false,false);
    issue_certificate($pdo,$completed);ensure_transcript($pdo,$student,(int)$pdo->query('SELECT id FROM users WHERE username="FIN001"')->fetchColumn(),$completed);
    $history=$pdo->query('SELECT * FROM stage_actions ORDER BY id')->fetchAll();
    $documents=$pdo->query('SELECT * FROM certificates ORDER BY id')->fetchAll();
    $transcripts=$pdo->query('SELECT * FROM transcripts ORDER BY id')->fetchAll();
    $terminalStages=$pdo->query('SELECT * FROM clearance_stages WHERE clearance_request_id IN ('.$completed.','.$cancelled.') ORDER BY id')->fetchAll();
    $terminalRequests=$pdo->query('SELECT * FROM clearance_requests WHERE id IN ('.$completed.','.$cancelled.') ORDER BY id')->fetchAll();
    $pdo->exec("ALTER TABLE stage_actions MODIFY COLUMN action ENUM('APPROVED','REJECTED') NOT NULL");
    $pdo->exec("DELETE FROM schema_migrations WHERE version IN ('2026_finance_payment_requests_v1','".APPLICATION_SCHEMA_VERSION."')");
    ensure_database_ready($pdo,false);
    foreach([$active,$multiple,$legacy] as $request) {
        $query=$pdo->prepare('SELECT cs.* FROM clearance_stages cs INNER JOIN workflow_steps ws ON ws.id=cs.workflow_step_id WHERE cs.clearance_request_id=? AND ws.step_number=11');$query->execute([$request]);$stage=$query->fetch();
        migration_check($stage['status']==='PENDING' && (int)$stage['review_cycle']===2 && $stage['reviewed_at']===null,'Active Finance rejection was not reopened safely.');
        $details=finance_payment_details($stage);
        if($request!==$legacy){migration_check($details['control_number']==='991234567890' && $details['payment_status']==='AWAITING_PAYMENT','Existing control number was lost.');}
        else {migration_check($details['debt']==='5000','Legacy office finding was lost.');}
        $requestState=$pdo->query('SELECT status FROM clearance_requests WHERE id='.$request)->fetchColumn();
        migration_check($requestState===($request===$multiple?'PAUSED':'IN_PROGRESS'),'Reopening Finance cleared another office rejection or left the request paused.');
    }
    migration_check($pdo->query('SELECT * FROM stage_actions ORDER BY id')->fetchAll()===$history,'Historical decisions changed.');
    migration_check($pdo->query('SELECT * FROM certificates ORDER BY id')->fetchAll()===$documents && $pdo->query('SELECT * FROM transcripts ORDER BY id')->fetchAll()===$transcripts,'Issued documents changed.');
    migration_check($pdo->query('SELECT * FROM clearance_stages WHERE clearance_request_id IN ('.$completed.','.$cancelled.') ORDER BY id')->fetchAll()===$terminalStages
        && $pdo->query('SELECT * FROM clearance_requests WHERE id IN ('.$completed.','.$cancelled.') ORDER BY id')->fetchAll()===$terminalRequests,'Completed or cancelled records changed.');
    $before=$pdo->query('SELECT * FROM clearance_stages ORDER BY id')->fetchAll();ensure_database_ready($pdo);
    migration_check($pdo->query('SELECT * FROM clearance_stages ORDER BY id')->fetchAll()===$before,'Repeated migration changed stages.');
    echo "PASS: legacy Finance payment requests reopen without changing history, other rejections, terminal records or documents; migration is repeatable\n";
} catch(Throwable $error){fwrite(STDERR,'FAIL: '.$error->getMessage().PHP_EOL);$exit=1;}
finally {
    $pdo=null;
    if($created && $server && preg_match('/^irdp_finance_migration_[a-f0-9]{16}$/D',$database)){$server->exec('DROP DATABASE '.$database);echo "Isolated Finance migration database removed.\n";}
}
exit($exit);
