<?php
declare(strict_types=1);
// Isolated browser fixtures: never connects to or modifies the application database.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Command line only.'); }
require_once __DIR__.'/../includes/installer.php';
require_once __DIR__.'/../includes/functions.php';
date_default_timezone_set('Africa/Dar_es_Salaam');
$_SESSION = [];

$dsn = $argv[1] ?? 'mysql:host=127.0.0.1;port=3306;charset=utf8mb4';
$database = 'irdp_responsive_'.bin2hex(random_bytes(8));
$temporary = sys_get_temp_dir().DIRECTORY_SEPARATOR.'irdp-responsive-'.bin2hex(random_bytes(8));
$server = null; $pdo = null; $web = null; $browser = null; $created = false; $exit = 0;
try {
    mkdir($temporary, 0700);
    mkdir($temporary.'/sessions', 0700);
    $server = new PDO($dsn, getenv('IRDP_TEST_USER') ?: 'root', getenv('IRDP_TEST_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $server->exec('CREATE DATABASE '.$database.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true; $server->exec('USE '.$database);
    $pdo = ensure_database_ready($server);
    $pdo->exec("SET time_zone='+03:00'");
    $cycle = '2026/2027';
    $pdo->prepare('UPDATE clearance_period SET mode="MANUAL_OPEN",cycle_id=(SELECT id FROM academic_cycles WHERE label=?) WHERE id=1')->execute([$cycle]);
    $definitions = require __DIR__.'/../config/demo_students.php';
    $records = [];
    foreach (array_merge(array_slice($definitions, 0, 3), [$definitions[4]]) as $definition) {
        $s = $pdo->prepare('SELECT s.*,u.username FROM students s INNER JOIN users u ON u.id=s.user_id WHERE u.username=?');
        $s->execute([$definition['registration_number']]);
        $record = $s->fetch();
        $record['password'] = demo_student_initial_password($definition);
        $pdo->prepare('INSERT INTO clearance_requests(student_id,academic_year,status,started_at) VALUES (?,?,"IN_PROGRESS",NOW())')->execute([$record['id'], $cycle]);
        $record['request_id'] = (int)$pdo->lastInsertId();
        $record['stages'] = [];
        foreach ($pdo->query('SELECT * FROM workflow_steps ORDER BY step_number')->fetchAll() as $step) {
            $number = (int)$step['step_number'];
            $reviewer = find_office_reviewer($pdo, (int)$step['office_id'], $number === 7 ? (int)$record['department_id'] : 0);
            if (!$reviewer) { throw new RuntimeException('Fixture office reviewer missing.'); }
            $pdo->prepare('INSERT INTO clearance_stages(clearance_request_id,workflow_step_id,office_id,assigned_officer_id,original_officer_id,status,actionable_at) VALUES (?,?,?,?,?,?,?)')
                ->execute([$record['request_id'], $step['id'], $step['office_id'], $reviewer, $reviewer, 'PENDING', date('Y-m-d H:i:s')]);
            $record['stages'][$number] = (int)$pdo->lastInsertId();
            ensure_review_cycle($pdo, $record['stages'][$number]);
        }
        $records[] = $record;
    }
    // A completed workflow makes the transcript available on the dashboard.
    foreach ($records[0]['stages'] as $number => $stageId) {
        $stage = stage_context($pdo, $stageId);
        if ($number === 11) {
            // Historical approval fixture preserves coverage of existing certificates.
            $legacy=json_encode(['decision'=>'CLEARED','debt'=>'0','recovered'=>'0'],JSON_THROW_ON_ERROR);
            $pdo->prepare('UPDATE clearance_stages SET status="APPROVED",details_json=?,reviewed_at=NOW() WHERE id=?')->execute([$legacy,$stageId]);
            $pdo->prepare('INSERT INTO stage_actions(stage_id,officer_id,action,comments,details_json) VALUES (?,?,"APPROVED","Historical Finance approval",?)')->execute([$stageId,$stage['assigned_officer_id'],$legacy]);
            $pdo->prepare('UPDATE clearance_requests SET status="COMPLETED",completed_at=NOW() WHERE id=?')->execute([$records[0]['request_id']]);
            issue_certificate($pdo,(int)$records[0]['request_id']);
            ensure_transcript($pdo,(int)$records[0]['id'],(int)$stage['assigned_officer_id'],(int)$records[0]['request_id']);
            continue;
        }
        $fields = review_fields($number);
        $input = ['action'=>'APPROVED', 'comments'=>'Responsive test approval', 'corrective_instructions'=>'', 'review_cycle'=>'1'];
        foreach ($fields as [$key, $label, $type]) { $input[$key] = $type === 'number' ? '0' : ($type === 'asset' ? 'AVAILABLE' : 'CLEARED'); }
        process_stage_decision($pdo, $stageId, (int)$stage['assigned_officer_id'], $input, $fields);
    }
    foreach ($records[3]['stages'] as $number => $stageId) {
        $stage=stage_context($pdo,$stageId);$fields=review_fields($number);
        $input=['action'=>$number===11?'REJECTED':'APPROVED','comments'=>'Responsive Finance fixture','corrective_instructions'=>'','review_cycle'=>'1'];
        foreach($fields as [$key,$label,$type]){$input[$key]=$type==='control_number'?'991234567890':($type==='number'?'0':($type==='asset'?'AVAILABLE':'CLEARED'));}
        process_stage_decision($pdo,$stageId,(int)$stage['assigned_officer_id'],$input,$fields);
    }
    $rejected = stage_context($pdo, $records[1]['stages'][1]);
    process_stage_decision($pdo, (int)$rejected['id'], (int)$rejected['assigned_officer_id'], [
        'action'=>'REJECTED', 'comments'=>'Return the library book and submit the receipt.',
        'corrective_instructions'=>str_repeat('Confirm the returned items with this office. ', 8), 'review_cycle'=>'1', 'amount'=>'2500',
    ], review_fields(1));
    // Stress realistic long labels, plus an unbroken filename/identifier.
    $pdo->prepare('UPDATE users SET full_name=? WHERE id=?')->execute(['Alexandria Mwakalinga '.str_repeat('Mwambalasa', 10), $records[1]['user_id']]);
    $pdo->prepare('INSERT INTO notifications(user_id,title,message) VALUES (?,?,?)')->execute([$records[1]['user_id'], 'Office review update', str_repeat('ReceiptReference', 24).'.pdf']);
    $doc = transcript_for_student($pdo, (int)$records[0]['id'], (int)$records[0]['request_id']);
    $fixture = [
        'admin'=>['username'=>'admin', 'password'=>'Admin@IRDP2026'],
        'student'=>['username'=>$records[1]['username'], 'password'=>$records[1]['password']],
        'completed'=>['username'=>$records[0]['username'], 'password'=>$records[0]['password']],
        'new_student'=>['username'=>$definitions[3]['registration_number'], 'password'=>demo_student_initial_password($definitions[3])],
        'officer'=>['username'=>'LIB001', 'password'=>'Mrema@2026'],
        'finance_officer'=>['username'=>'FIN001','password'=>'Mollel@2026'],
        'finance_student'=>['username'=>$records[3]['username'],'password'=>$records[3]['password']],
        'finance_stage'=>$records[3]['stages'][11],
        'review_stage'=>$records[2]['stages'][1], 'rejected_stage'=>$records[1]['stages'][1],
        'certificate_request'=>$records[0]['request_id'], 'transcript_token'=>$doc['verification_token'],
    ];
    file_put_contents($temporary.'/fixture.json', json_encode($fixture, JSON_THROW_ON_ERROR));

    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$socket) { throw new RuntimeException('Test port unavailable: '.$error); }
    $address = stream_socket_get_name($socket, false); fclose($socket);
    $port = (int)substr(strrchr($address, ':'), 1);
    preg_match('/(?:^|;)host=([^;]+)/', $dsn, $hostMatch);
    preg_match('/(?:^|;)port=(\\d+)/', $dsn, $portMatch);
    $environment = getenv();
    $environment['DB_HOST'] = $hostMatch[1] ?? '127.0.0.1'; $environment['DB_PORT'] = $portMatch[1] ?? '3306';
    $environment['DB_NAME'] = $database; $environment['DB_USER'] = getenv('IRDP_TEST_USER') ?: 'root';
    $environment['DB_PASS'] = getenv('IRDP_TEST_PASSWORD') ?: '';
    $web = proc_open([PHP_BINARY, '-d', 'session.save_path='.$temporary.'/sessions', '-S', '127.0.0.1:'.$port, '-t', dirname(__DIR__), __DIR__.'/http_router.php'],
        [0=>['pipe','r'], 1=>['file',$temporary.'/http.log','a'], 2=>['file',$temporary.'/http.log','a']], $pipes, dirname(__DIR__), $environment);
    if (!is_resource($web)) { throw new RuntimeException('Test server could not start.'); } fclose($pipes[0]);
    $ready = false;
    for ($attempt=0; $attempt<50; $attempt++) {
        $connection = @fsockopen('127.0.0.1', $port, $errno, $error, .1);
        if ($connection) { fclose($connection); $ready=true; break; } usleep(100000);
    }
    if (!$ready) { throw new RuntimeException('Test server did not become ready.'); }
    $node = getenv('IRDP_NODE_BINARY') ?: 'node';
    if ($node === 'node' && PHP_OS_FAMILY === 'Windows') {
        $electron = getenv('LOCALAPPDATA').'/Programs/Microsoft VS Code/Code.exe';
        if (is_file($electron)) { $node=$electron; $environment['ELECTRON_RUN_AS_NODE']='1'; }
    }
    $browser = proc_open([$node, __DIR__.'/responsive_test.js', 'http://127.0.0.1:'.$port, $temporary.'/fixture.json'],
        [0=>['pipe','r'], 1=>STDOUT, 2=>STDERR], $pipes, dirname(__DIR__), $environment);
    if (!is_resource($browser)) { throw new RuntimeException('Node browser runner could not start.'); } fclose($pipes[0]);
    $exit=proc_close($browser); $browser=null;
    if ($exit !== 0) { throw new RuntimeException('Responsive browser checks failed.'); }
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: '.$error->getMessage().PHP_EOL); $exit=1;
} finally {
    if (is_resource($browser)) { proc_terminate($browser); proc_close($browser); }
    if (is_resource($web)) { proc_terminate($web); proc_close($web); }
    $pdo=null;
    if ($created && $server && preg_match('/^irdp_responsive_[a-f0-9]{16}$/D', $database)) {
        $server->exec('DROP DATABASE '.$database); echo "Isolated responsive database removed.\n";
    }
    $target=realpath($temporary);
    $expected=realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.basename($temporary);
    if ($target && strcasecmp($target, $expected) === 0 && preg_match('/^irdp-responsive-[a-f0-9]{16}$/D', basename($target))) {
        foreach (new DirectoryIterator($target.'/sessions') as $entry) { if ($entry->isFile()) { unlink($entry->getPathname()); } }
        rmdir($target.'/sessions');
        foreach (['fixture.json','http.log'] as $file) { if (is_file($target.'/'.$file)) { unlink($target.'/'.$file); } }
        rmdir($target);
    }
}
exit($exit);
