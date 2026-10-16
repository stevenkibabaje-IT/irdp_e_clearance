<?php
declare(strict_types=1);

// Real HTTP and concurrent requests against a disposable database only.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Command line only.'); }
require_once __DIR__.'/../includes/installer.php';
require_once __DIR__.'/../includes/functions.php';
date_default_timezone_set('Africa/Dar_es_Salaam');
$_SESSION = [];

function once_check(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException($message); }
}
function once_http(string $origin, string $route, string $cookie, ?array $post = null): array
{
    $curl = curl_init($origin.$route);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30,
        CURLOPT_COOKIEFILE=>$cookie, CURLOPT_COOKIEJAR=>$cookie, CURLOPT_FOLLOWLOCATION=>false]);
    if ($post !== null) { curl_setopt_array($curl, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>http_build_query($post)]); }
    $body = curl_exec($curl);
    if ($body === false) { throw new RuntimeException(curl_error($curl)); }
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    return [$status, $body];
}
function once_csrf(string $html): string
{
    once_check((bool)preg_match('/name="csrf_token" value="([^"]+)"/', $html, $match), 'Missing CSRF token.');
    return html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
}

$dsn = $argv[1] ?? 'mysql:host=127.0.0.1;port=3306;charset=utf8mb4';
$database = 'irdp_once_'.bin2hex(random_bytes(8));
$temporary = sys_get_temp_dir().DIRECTORY_SEPARATOR.'irdp-once-'.bin2hex(random_bytes(8));
$server = $pdo = $multi = null; $web = $curls = []; $created = false; $stored = null; $exit = 0;
try {
    mkdir($temporary, 0700); mkdir($temporary.'/sessions', 0700);
    $server = new PDO($dsn, getenv('IRDP_TEST_USER') ?: 'root', getenv('IRDP_TEST_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    $server->exec('CREATE DATABASE '.$database.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true; $server->exec('USE '.$database); $pdo = ensure_database_ready($server);
    $definitions = require __DIR__.'/../config/demo_students.php'; $definition = $definitions[0];
    $stmt = $pdo->prepare('SELECT s.*,u.username FROM students s INNER JOIN users u ON u.id=s.user_id WHERE u.username=?');
    $stmt->execute([$definition['registration_number']]); $student = $stmt->fetch();
    $cycle = '2026/2027';
    $pdo->prepare('UPDATE clearance_period SET mode="MANUAL_OPEN",cycle_id=(SELECT id FROM academic_cycles WHERE label=?) WHERE id=1')->execute([$cycle]);
    // A genuine approved fee fixture makes both concurrent submissions eligible to start.
    $feeId = request_clearance_fee($pdo, (int)$student['user_id']);
    $fee = clearance_fee_record($pdo, $feeId); $number = '991234567891';
    $stored = bin2hex(random_bytes(24)).'.pdf';
    file_put_contents(private_path('evidence', $stored), "%PDF-1.4\n% isolated clearance test\n");
    $pdo->prepare('INSERT INTO clearance_fee_receipts(payment_id,payment_version,control_number,uploader_id,stored_name,original_name,mime_type,byte_size) VALUES (?,1,?,?,?,"test.pdf","application/pdf",40)')
        ->execute([$feeId,$number,$student['user_id'],$stored]);
    $receipt = (int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE clearance_fee_payments SET status="APPROVED",control_number=?,receipt_id=?,approved_by=?,approved_at=NOW() WHERE id=?')
        ->execute([$number,$receipt,$fee['assigned_officer_id'],$feeId]);

    preg_match('/(?:^|;)host=([^;]+)/', $dsn, $hostMatch); preg_match('/(?:^|;)port=(\d+)/', $dsn, $portMatch);
    $environment = getenv();
    $environment['DB_HOST']=$hostMatch[1]??'127.0.0.1'; $environment['DB_PORT']=$portMatch[1]??'3306';
    $environment['DB_NAME']=$database; $environment['DB_USER']=getenv('IRDP_TEST_USER')?:'root';
    $environment['DB_PASS']=getenv('IRDP_TEST_PASSWORD')?:'';
    $origins = $tokens = [];
    for ($index=0; $index<2; $index++) {
        $socket=stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        once_check((bool)$socket, 'Test port unavailable.');
        $address=stream_socket_get_name($socket,false); fclose($socket);
        $port=(int)substr(strrchr($address,':'),1); $origins[$index]='http://127.0.0.1:'.$port;
        $web[$index]=proc_open([PHP_BINARY,'-d','session.save_path='.$temporary.'/sessions','-S','127.0.0.1:'.$port,'-t',dirname(__DIR__),__DIR__.'/http_router.php'],
            [0=>['pipe','r'],1=>['file',$temporary.'/http-'.$index.'.log','a'],2=>['file',$temporary.'/http-'.$index.'.log','a']],$pipes,dirname(__DIR__),$environment);
        once_check(is_resource($web[$index]), 'Test HTTP server did not start.'); fclose($pipes[0]);
        $ready=false;
        for ($attempt=0;$attempt<50;$attempt++) {
            $connection=@fsockopen('127.0.0.1',$port,$errno,$error,.1);
            if ($connection) { fclose($connection);$ready=true;break; } usleep(100000);
        }
        once_check($ready, 'Test server not ready.');
        $cookie=$temporary.'/cookie-'.$index;
        [$status,$html]=once_http($origins[$index],'/auth/login.php',$cookie);
        [$status]=once_http($origins[$index],'/auth/login.php',$cookie,['csrf_token'=>once_csrf($html),'username'=>$student['username'],'password'=>demo_student_initial_password($definition)]);
        once_check($status===302, 'Student login failed.');
        [$status,$html]=once_http($origins[$index],'/student/start.php',$cookie);
        once_check($status===200, 'First clearance must be available.'); $tokens[$index]=once_csrf($html);
    }
    // Hold the shared student lock until both HTTP requests have passed their initial check.
    $pdo->beginTransaction();
    $lock=$pdo->prepare('SELECT id FROM students WHERE id=? FOR UPDATE'); $lock->execute([$student['id']]);
    $multi=curl_multi_init();
    foreach ($origins as $index=>$origin) {
        $curl=curl_init($origin.'/student/start.php');
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_POST=>true,
            CURLOPT_COOKIEFILE=>$temporary.'/cookie-'.$index,
            CURLOPT_POSTFIELDS=>http_build_query(['csrf_token'=>$tokens[$index],'academic_year'=>$cycle])]);
        curl_multi_add_handle($multi,$curl); $curls[]=$curl;
    }
    $waiting=0;
    for ($attempt=0;$attempt<200;$attempt++) {
        curl_multi_exec($multi,$running);
        curl_multi_select($multi,.05);
        $waiting=(int)$pdo->query('SELECT COUNT(*) FROM information_schema.INNODB_LOCK_WAITS w INNER JOIN information_schema.INNODB_TRX t ON t.trx_id=w.blocking_trx_id WHERE t.trx_mysql_thread_id=CONNECTION_ID()')->fetchColumn();
        if ($waiting>=2) { break; } usleep(50000);
    }
    once_check($waiting>=2, 'Both start requests must reach the same student lock: '.json_encode(['waiting'=>$waiting,'queries'=>$pdo->query('SELECT STATE,INFO FROM information_schema.PROCESSLIST WHERE DB=DATABASE() AND ID<>CONNECTION_ID()')->fetchAll(),'responses'=>array_map(static fn($curl)=>['status'=>curl_getinfo($curl,CURLINFO_RESPONSE_CODE),'body'=>substr(strip_tags(curl_multi_getcontent($curl)),0,200)],$curls)],JSON_THROW_ON_ERROR));
    $pdo->commit();
    do { curl_multi_exec($multi,$running); if($running){curl_multi_select($multi,.1);} } while($running);
    $responses=[];
    foreach ($curls as $curl) { once_check(curl_errno($curl)===0,curl_error($curl));$responses[]=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE); }
    sort($responses); once_check($responses===[200,302], 'Exactly one simultaneous start must succeed.');
    $request=get_student_clearance($pdo,(int)$student['id']);
    once_check((int)$pdo->query('SELECT COUNT(*) FROM clearance_requests')->fetchColumn()===1, 'Concurrent starts created duplicate requests.');
    once_check(count(get_clearance_stages($pdo,(int)$request['id']))===11, 'The successful first start must create eleven office reviews.');
    echo "PASS: two independent sessions submit simultaneously; one clearance and eleven office reviews are created\n";

    $newCycle='2027/2028'; $pdo->prepare('INSERT IGNORE INTO academic_cycles(label) VALUES (?)')->execute([$newCycle]);
    $pdo->prepare('UPDATE clearance_period SET cycle_id=(SELECT id FROM academic_cycles WHERE label=?) WHERE id=1')->execute([$newCycle]);
    foreach (['IN_PROGRESS','PAUSED','COMPLETED','CANCELLED'] as $requestStatus) {
        $pdo->prepare('UPDATE clearance_requests SET status=? WHERE id=?')->execute([$requestStatus,$request['id']]);
        [$status,$html]=once_http($origins[0],'/student/dashboard.php',$temporary.'/cookie-0');
        once_check($status===200 && substr_count($html,'class="office-status-item ')===11, 'Existing offices disappeared after changing academic cycle.');
        once_check(!str_contains($html,'student/start.php') && !str_contains($html,'student/clearance_fee.php'), 'Existing registration exposes a second start or entry fee.');
        [$status]=once_http($origins[0],'/student/start.php',$temporary.'/cookie-0',['csrf_token'=>once_csrf($html),'academic_year'=>$newCycle]);
        once_check($status===302, 'Direct second start was not blocked for '.$requestStatus.'.');
        [$status]=once_http($origins[0],'/student/clearance_fee.php',$temporary.'/cookie-0',['csrf_token'=>once_csrf($html),'mode'=>'REQUEST']);
        once_check($status===302, 'A second entry fee was not blocked.');
        $rejected=false;
        try { request_clearance_fee($pdo,(int)$student['user_id']); } catch(RuntimeException $error) { $rejected=str_contains($error->getMessage(),'already has a clearance'); }
        once_check($rejected, 'Fee service must reject an existing registration across cycles.');
        once_check((int)$pdo->query('SELECT COUNT(*) FROM clearance_requests')->fetchColumn()===1 && (int)$pdo->query('SELECT COUNT(*) FROM clearance_fee_payments')->fetchColumn()===1, 'A repeat attempt changed request/payment counts.');
        echo 'PASS: '.$requestStatus." remains the same clearance; a new cycle, direct POST and fee service cannot start another\n";
    }
    $other=$pdo->query('SELECT id,user_id,phone FROM students WHERE id<>'.(int)$student['id'].' LIMIT 1')->fetch();
    [$status,$html]=once_http($origins[0],'/student/profile.php',$temporary.'/cookie-0');
    $profileToken=once_csrf($html);
    [$status]=once_http($origins[0],'/student/profile.php',$temporary.'/cookie-0',[
        'csrf_token'=>$profileToken,'mode'=>'phone','phone'=>'0712 345 678','student_id'=>(string)$other['id'],'user_id'=>(string)$other['user_id'],
    ]);
    once_check($status===302, 'Student phone save failed.');
    $phones=$pdo->query('SELECT s.phone,u.phone AS account_phone FROM students s INNER JOIN users u ON u.id=s.user_id WHERE s.id='.(int)$student['id'])->fetch();
    once_check($phones['phone']==='+255712345678' && $phones['account_phone']===$phones['phone'], 'Contact number was not normalized and synchronized.');
    once_check($pdo->query('SELECT phone FROM students WHERE id='.(int)$other['id'])->fetchColumn()===$other['phone'], 'A forged student identifier changed another profile.');
    foreach (['not-a-number','123','+255<script>',''] as $invalid) {
        [$status,$html]=once_http($origins[0],'/student/profile.php',$temporary.'/cookie-0',['csrf_token'=>$profileToken,'mode'=>'phone','phone'=>$invalid]);
        once_check($status===200 && str_contains($html,'phone-error'), 'Invalid phone must show a field error.');
        once_check($pdo->query('SELECT phone FROM students WHERE id='.(int)$student['id'])->fetchColumn()==='+255712345678', 'Invalid phone input changed saved contact.');
    }
    [$status]=once_http($origins[0],'/student/profile.php',$temporary.'/cookie-0',['mode'=>'phone','phone'=>'0787654321']);
    once_check($status===419 && $pdo->query('SELECT phone FROM students WHERE id='.(int)$student['id'])->fetchColumn()==='+255712345678', 'A phone update without CSRF was accepted.');
    $blocked=false;
    try { update_student_phone($pdo,(int)$fee['assigned_officer_id'],['phone'=>'0787654321']); } catch(RuntimeException $error) { $blocked=true; }
    once_check($blocked, 'A staff account was accepted as a student phone profile.');
    echo "PASS: own phone save, normalized student/account records, forged IDs, invalid numbers, CSRF and staff-role protection\n";
} catch(Throwable $error) { fwrite(STDERR,'FAIL: '.$error->getMessage().PHP_EOL);$exit=1; }
finally {
    if($pdo && $pdo->inTransaction()){$pdo->rollBack();}
    foreach($curls as $curl){if($multi){curl_multi_remove_handle($multi,$curl);}curl_close($curl);}
    if($multi){curl_multi_close($multi);}
    foreach($web as $process){if(is_resource($process)){proc_terminate($process);proc_close($process);}}
    if($stored!==null){private_delete('evidence',$stored);}
    $pdo=null;
    if($created && $server && preg_match('/^irdp_once_[a-f0-9]{16}$/D',$database)){$server->exec('DROP DATABASE '.$database);echo "Isolated clearance database removed.\n";}
    $target=realpath($temporary);
    if($target && strcasecmp(dirname($target),realpath(sys_get_temp_dir()))===0 && preg_match('/^irdp-once-[a-f0-9]{16}$/D',basename($target))){
        foreach(new DirectoryIterator($target.'/sessions') as $entry){if($entry->isFile()){unlink($entry->getPathname());}}rmdir($target.'/sessions');
        foreach(new DirectoryIterator($target) as $entry){if($entry->isFile()){unlink($entry->getPathname());}}rmdir($target);
    }
}
exit($exit);
