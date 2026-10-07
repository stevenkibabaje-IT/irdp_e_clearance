<?php
declare(strict_types=1);

// Upgrade checks use a randomly named database and never connect to the application database.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Command line only.'); }
require_once __DIR__.'/../includes/installer.php';
require_once __DIR__.'/../includes/functions.php';
date_default_timezone_set('Africa/Dar_es_Salaam');
$_SESSION = [];

function parallel_check(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}

function legacy_clearance_fixture(PDO $pdo, array $student, string $cycle, string $status): array
{
    $pdo->prepare('INSERT IGNORE INTO academic_cycles(label) VALUES (?)')->execute([$cycle]);
    $pdo->prepare('INSERT INTO clearance_requests(student_id,academic_year,status,started_at,completed_at) VALUES (?,?,?,NOW(),?)')
        ->execute([$student['id'],$cycle,$status,$status==='COMPLETED'?date('Y-m-d H:i:s'):null]);
    $request = (int)$pdo->lastInsertId();
    $stages = [];
    foreach ($pdo->query('SELECT * FROM workflow_steps ORDER BY step_number') as $step) {
        $number = (int)$step['step_number'];
        $reviewer = find_office_reviewer($pdo,(int)$step['office_id'],$number===7?(int)$student['department_id']:0);
        parallel_check($reviewer!==null,'Missing fixture reviewer.');
        $state = $status==='COMPLETED'?'APPROVED':($status==='CANCELLED'?'LOCKED':match($number){1=>'APPROVED',2=>'REJECTED',3=>'IN_REVIEW',default=>'LOCKED'});
        $details = [];
        if (in_array($state,['APPROVED','REJECTED'],true)) {
            if ($number===11) { $details=['decision'=>'CLEARED','debt'=>'0','recovered'=>'0']; }
            else { foreach(review_fields($number) as [$key,$label,$type]) { $details[$key]=$type==='number'?'0':($type==='asset'?'AVAILABLE':'CLEARED'); } }
            if ($state==='REJECTED') { $details['amount']='5000'; }
        }
        $opened = $state==='LOCKED'?null:date('Y-m-d H:i:s');
        $closed = in_array($state,['APPROVED','REJECTED'],true)?$opened:null;
        $comments = $state==='REJECTED'?'Return the missing item.':'Historical office decision';
        $pdo->prepare('INSERT INTO clearance_stages(clearance_request_id,workflow_step_id,office_id,assigned_officer_id,original_officer_id,status,started_at,actionable_at,reviewed_at,comments,details_json) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$request,$step['id'],$step['office_id'],$reviewer,$reviewer,$state,$opened,$closed?null:$opened,$closed,$comments,json_encode($details,JSON_THROW_ON_ERROR)]);
        $id = (int)$pdo->lastInsertId();
        $stages[$number] = $id;
        $pdo->prepare('INSERT INTO review_cycles(stage_id,cycle_number,opened_at,closed_at,student_response) VALUES (?,1,?,?,?)')
            ->execute([$id,$opened,$closed,$state==='REJECTED'?'Historical response retained':null]);
        if ($closed) {
            $pdo->prepare('INSERT INTO stage_actions(stage_id,officer_id,action,comments,details_json) VALUES (?,?,?,?,?)')
                ->execute([$id,$reviewer,$state,$comments,json_encode($details,JSON_THROW_ON_ERROR)]);
        }
    }
    return [$request,$stages];
}

$dsn=$argv[1]??'mysql:host=127.0.0.1;port=3306;charset=utf8mb4';
$database='irdp_parallel_'.bin2hex(random_bytes(8));
$server=null; $created=false; $exit=0;
try {
    $server=new PDO($dsn,getenv('IRDP_TEST_USER')?:'root',getenv('IRDP_TEST_PASSWORD')?:'',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    $server->exec('CREATE DATABASE '.$database.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created=true; $server->exec('USE '.$database); $pdo=ensure_database_ready($server);
    $student=$pdo->query('SELECT * FROM students ORDER BY id LIMIT 1')->fetch();
    [$active,$activeStages]=legacy_clearance_fixture($pdo,$student,'2035/2036','PAUSED');
    [$cancelled,$cancelledStages]=legacy_clearance_fixture($pdo,$student,'2036/2037','CANCELLED');
    [$completed,$completedStages]=legacy_clearance_fixture($pdo,$student,'2037/2038','COMPLETED');
    issue_certificate($pdo,$completed);
    ensure_transcript($pdo,(int)$student['id'],(int)$pdo->query('SELECT id FROM users WHERE username="admin"')->fetchColumn(),$completed);
    $pdo->prepare('INSERT INTO stage_evidence(stage_id,cycle_number,uploader_id,stored_name,original_name,mime_type,byte_size) VALUES (?,1,?,?,"historical-receipt.pdf","application/pdf",64)')
        ->execute([$activeStages[2],$student['user_id'],bin2hex(random_bytes(24)).'.pdf']);
    $preservedTables=['users','students','stage_actions','stage_evidence','certificates','transcripts'];
    $before=[];
    foreach($preservedTables as $table){$before[$table]=$pdo->query('SELECT * FROM '.$table.' ORDER BY id')->fetchAll();}
    $recordedStages=$pdo->query('SELECT * FROM clearance_stages WHERE clearance_request_id<>'.$active.' OR status<>"LOCKED" ORDER BY id')->fetchAll();
    $recordedCycles=$pdo->query('SELECT rc.* FROM review_cycles rc INNER JOIN clearance_stages cs ON cs.id=rc.stage_id WHERE cs.clearance_request_id<>'.$active.' OR cs.status<>"LOCKED" ORDER BY rc.id')->fetchAll();
    $terminalRequests=$pdo->query('SELECT * FROM clearance_requests WHERE id IN ('.$cancelled.','.$completed.') ORDER BY id')->fetchAll();

    // Simulate an existing installation before this upgrade's runtime marker.
    $pdo->exec("DELETE FROM schema_migrations WHERE version='2026_parallel_clearance_v1'");
    $pdo->prepare('DELETE FROM schema_migrations WHERE version=?')->execute([APPLICATION_SCHEMA_VERSION]);
    ensure_database_ready($pdo,false);
    parallel_check(database_schema_ready($pdo),'Upgrade did not record runtime readiness.');
    foreach($preservedTables as $table){parallel_check($pdo->query('SELECT * FROM '.$table.' ORDER BY id')->fetchAll()===$before[$table],'Upgrade changed '.$table.'.');}
    parallel_check($pdo->query('SELECT * FROM clearance_stages WHERE clearance_request_id<>'.$active.' OR status<>"PENDING" ORDER BY id')->fetchAll()===$recordedStages,'Upgrade changed recorded decisions, in-review stages or terminal stages.');
    parallel_check($pdo->query('SELECT * FROM review_cycles WHERE stage_id NOT IN ('.implode(',',array_slice($activeStages,3)).') ORDER BY id')->fetchAll()===$recordedCycles,'Upgrade changed historical review cycles.');
    parallel_check($pdo->query('SELECT * FROM clearance_requests WHERE id IN ('.$cancelled.','.$completed.') ORDER BY id')->fetchAll()===$terminalRequests,'Upgrade changed completed/cancelled requests.');
    $opened=$pdo->query('SELECT cs.*,rc.opened_at FROM clearance_stages cs INNER JOIN review_cycles rc ON rc.stage_id=cs.id AND rc.cycle_number=cs.review_cycle WHERE cs.clearance_request_id='.$active.' AND cs.status="PENDING"')->fetchAll();
    parallel_check(count($opened)===8,'Upgrade did not open every formerly locked office.');
    foreach($opened as $row){parallel_check($row['started_at']!==null&&$row['actionable_at']!==null&&$row['opened_at']!==null&&$row['assigned_officer_id']===$row['original_officer_id'],'Upgrade lost review timestamps or assignments.');}
    parallel_check(stage_context($pdo,$activeStages[11])['request_status']==='PAUSED','Upgrade cleared an outstanding student rejection.');
    parallel_check($pdo->query("SHOW COLUMNS FROM clearance_stages LIKE 'status'")->fetch()['Default']==='PENDING','Fresh stage default still locks independent reviews.');
    echo "PASS: upgrade opens eight locked offices, retains active rejection and in-review state, and preserves approvals/evidence/assignments/documents/terminal requests\n";

    $after=[];
    foreach(['clearance_requests','clearance_stages','review_cycles','stage_actions','stage_evidence','certificates','transcripts'] as $table){$after[$table]=$pdo->query('SELECT * FROM '.$table.' ORDER BY id')->fetchAll();}
    migrate_parallel_clearance($pdo);
    ensure_database_ready($pdo);
    foreach($after as $table=>$rows){parallel_check($pdo->query('SELECT * FROM '.$table.' ORDER BY id')->fetchAll()===$rows,'Repeated migration changed '.$table.'.');}
    echo "PASS: parallel upgrade and forced setup are repeatable without changing historical records\n";
} catch(Throwable $e) {
    fwrite(STDERR,'FAIL: '.$e->getMessage()."\n"); $exit=1;
} finally {
    if($created&&$server&&preg_match('/^irdp_parallel_[a-f0-9]{16}$/D',$database)){
        $server->exec('DROP DATABASE '.$database);
        echo "Isolated parallel database removed.\n";
    }
}
exit($exit);
