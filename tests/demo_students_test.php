<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Command line only.');}
require_once __DIR__.'/../includes/installer.php';
require_once __DIR__.'/../includes/functions.php';
date_default_timezone_set('Africa/Dar_es_Salaam');
$_SESSION=[];

function demo_check(bool $condition,string $message): void {if(!$condition){throw new RuntimeException($message);}}
function demo_http(string $path,array $post=[],?string $cookie=null,bool $isPost=false): array {
    $curl=curl_init('http://127.0.0.1:18088'.$path);
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>false]);
    if($cookie){curl_setopt($curl,CURLOPT_COOKIEFILE,$cookie);curl_setopt($curl,CURLOPT_COOKIEJAR,$cookie);}
    if($isPost){curl_setopt($curl,CURLOPT_POST,true);curl_setopt($curl,CURLOPT_POSTFIELDS,$post);}
    $body=curl_exec($curl);$code=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$error=curl_error($curl);curl_close($curl);
    if($body===false){throw new RuntimeException('HTTP request failed: '.$error);}
    return [$code,$body];
}
function demo_csrf(string $html): string {
    if(!preg_match('/name="csrf_token" value="([^"]+)"/',$html,$match)){throw new RuntimeException('CSRF token missing.');}
    return html_entity_decode($match[1],ENT_QUOTES,'UTF-8');
}
function expect_duplicate(callable $operation,string $message): void {
    try{$operation();}catch(PDOException $error){if((int)($error->errorInfo[1]??0)===1062){return;}throw $error;}
    throw new RuntimeException($message);
}

$dsn=$argv[1]??'mysql:host=127.0.0.1;port=3306;charset=utf8mb4';
$database='irdp_demo_test_'.bin2hex(random_bytes(8));$server=null;$pdo=null;$web=null;$created=false;$exit=0;$files=[];
try{
    $server=new PDO($dsn,getenv('IRDP_TEST_USER')?:'root',getenv('IRDP_TEST_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $server->exec('CREATE DATABASE '.$database.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;$server->exec('USE '.$database);$pdo=ensure_database_ready($server);
    $definitions=require __DIR__.'/../config/demo_students.php';
    demo_check(count($definitions)===5,'Demo definition count is not five.');
    $studentCount=(int)$pdo->query('SELECT COUNT(*) FROM users u INNER JOIN roles r ON r.id=u.role_id WHERE r.name="STUDENT"')->fetchColumn();
    demo_check($studentCount===5,'A fresh installation did not contain exactly five demo students.');
    $readme=file_get_contents(__DIR__.'/../README.md');
    $records=[];
    foreach($definitions as $definition){
        $registration=$definition['registration_number'];$password=demo_student_initial_password($definition);
        $stmt=$pdo->prepare('SELECT u.id,u.username,u.password_hash,u.full_name,u.active,u.force_password_change,s.id AS student_id,s.registration_number,s.programme FROM users u INNER JOIN roles r ON r.id=u.role_id INNER JOIN students s ON s.user_id=u.id WHERE r.name="STUDENT" AND u.username=?');
        $stmt->execute([$registration]);$record=$stmt->fetch();
        demo_check((bool)$record,'Missing demo account '.$registration.'.');
        demo_check($record['username']===$record['registration_number'],'Login username and registration number differ.');
        demo_check($record['full_name']===$definition['full_name']&&$record['programme']===$definition['programme']&&(int)$record['active']===1,'Demo identity, programme or status mismatch for '.$registration.'.');
        demo_check((int)$record['force_password_change']===0,'Demo account forces a password change for '.$registration.'.');
        demo_check($record['password_hash']!==$password&&password_verify($password,$record['password_hash']),'Demo password is plaintext or cannot be verified for '.$registration.'.');
        demo_check((password_get_info($record['password_hash'])['algoName']??'')==='argon2id','Demo password is not Argon2id for '.$registration.'.');
        demo_check(str_contains($readme,'| '.$definition['full_name'].' | '.$registration.' | '.$password.' | '.$definition['programme'].' |'),'README credential row differs for '.$registration.'.');
        $records[]=$record+['password'=>$password];
    }
    demo_check(count(array_unique(array_column($records,'username')))===5&&count(array_unique(array_column($records,'student_id')))===5,'Demo registration numbers are not unique.');
    echo "PASS: five unique active demo accounts, password rule, Argon2id hashes and README credentials\n";

    $cycle='2025/2026';$pdo->prepare('UPDATE clearance_period SET cycle_id=(SELECT id FROM academic_cycles WHERE label=?),mode="MANUAL_OPEN" WHERE id=1')->execute([$cycle]);
    $insertRequest=$pdo->prepare('INSERT INTO clearance_requests(student_id,academic_year,status,started_at) VALUES (?, ?, "IN_PROGRESS", NOW())');
    foreach($records as &$record){$insertRequest->execute([$record['student_id'],$cycle]);$record['request_id']=(int)$pdo->lastInsertId();}
    unset($record);
    expect_duplicate(fn()=>$insertRequest->execute([$records[0]['student_id'],$cycle]),'Database accepted a duplicate student clearance cycle.');
    expect_duplicate(fn()=>$pdo->prepare('INSERT INTO users(username,password_hash,full_name,role_id) SELECT ?,?,"Duplicate Demo",id FROM roles WHERE name="STUDENT"')->execute([$records[0]['username'],demo_student_password_hash('Duplicate123')]),'Database accepted a duplicate registration username.');
    demo_check(get_clearance_for_cycle($pdo,(int)$records[0]['student_id'],$cycle)['id']===$records[0]['request_id'],'Backend cycle lookup did not return the existing request.');
    echo "PASS: database and backend registration/cycle uniqueness\n";

    foreach($records as $index=>$record){$pdo->prepare('INSERT INTO notifications(user_id,title,message) VALUES (?,"Private demo notice",?)')->execute([$record['id'],'Only '.$record['username']]);}
    preg_match('/(?:^|;)port=(\d+)/',$dsn,$portMatch);$environment=getenv();$environment['DB_HOST']='127.0.0.1';$environment['DB_PORT']=$portMatch[1]??'3306';$environment['DB_NAME']=$database;$environment['DB_USER']=getenv('IRDP_TEST_USER')?:'root';$environment['DB_PASS']=getenv('IRDP_TEST_PASSWORD')?:'';
    $sessionDirectory=__DIR__.'/.demo-sessions';if(!is_dir($sessionDirectory)){mkdir($sessionDirectory,0700);}
    $log=__DIR__.'/demo-http.log';$files[]=$log;$web=proc_open([PHP_BINARY,'-d','session.save_path='.$sessionDirectory,'-S','127.0.0.1:18088','-t',dirname(__DIR__),__DIR__.'/http_router.php'],[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,dirname(__DIR__),$environment);
    demo_check(is_resource($web),'Demo HTTP server could not start.');fclose($pipes[0]);
    $ready=false;for($attempt=0;$attempt<30;$attempt++){$socket=@fsockopen('127.0.0.1',18088,$number,$error,0.2);if($socket){fclose($socket);$ready=true;break;}usleep(100000);}demo_check($ready,'Demo HTTP server did not become ready.');
    $adminCookie=__DIR__.'/demo-admin.cookies';$files[]=$adminCookie;[$code,$adminLogin]=demo_http('/auth/login.php',[],$adminCookie);[$code]=demo_http('/auth/login.php',['csrf_token'=>demo_csrf($adminLogin),'username'=>'admin','password'=>'Admin@IRDP2026'],$adminCookie,true);demo_check($code===302,'Admin login failed during registration uniqueness test.');
    [$code,$usersPage]=demo_http('/admin/users.php',[],$adminCookie);$studentRole=(int)$pdo->query('SELECT id FROM roles WHERE name="STUDENT"')->fetchColumn();$department=(int)$pdo->query('SELECT id FROM departments WHERE code="EPM"')->fetchColumn();
    [$code,$duplicatePage]=demo_http('/admin/users.php',['csrf_token'=>demo_csrf($usersPage),'full_name'=>'Duplicate Demo Student','username'=>$records[0]['username'],'email'=>'','password'=>'Unique garden 82','confirm_password'=>'Unique garden 82','role_id'=>(string)$studentRole,'office_id'=>'','department_id'=>(string)$department,'programme'=>$records[0]['programme'],'academic_year'=>$cycle],$adminCookie,true);
    demo_check($code===200&&str_contains($duplicatePage,'Username already exists.')&&str_contains($duplicatePage,'username-error'),'Admin UI/backend did not show a field-level duplicate registration error.');
    echo "PASS: admin UI and backend reject duplicate student registration numbers\n";
    foreach($records as $index=>$record){
        $cookie=__DIR__.'/demo-'.$index.'.cookies';$files[]=$cookie;
        [$code,$login]=demo_http('/auth/login.php',[],$cookie);demo_check($code===200,'Login page unavailable.');
        [$code]=demo_http('/auth/login.php',['csrf_token'=>demo_csrf($login),'username'=>$record['username'],'password'=>$record['password']],$cookie,true);demo_check($code===302,'Demo login failed for '.$record['username'].'.');
        [$code,$dashboard]=demo_http('/student/dashboard.php',[],$cookie);demo_check($code===200&&str_contains($dashboard,$record['registration_number'])&&str_contains($dashboard,'Continue Clearance')&&!str_contains($dashboard,'name="current_password"'),'Dashboard routing failed or forced password form appeared for '.$record['username'].'.');
        [$code,$changeForm]=demo_http('/auth/change_password.php',[],$cookie);demo_check($code===200&&str_contains($changeForm,'name="current_password"'),'Optional change-password form missing for '.$record['username'].'.');
        [$code,$profile]=demo_http('/student/profile.php',[],$cookie);demo_check($code===200&&str_contains($profile,$record['full_name'])&&str_contains($profile,'auth/change_password.php'),'Own profile or optional password link missing for '.$record['username'].'.');
        $other=$records[($index+1)%count($records)];
        [$code]=demo_http('/files/profile.php?student='.$other['student_id'],[],$cookie);demo_check($code===403,'Student accessed another demo profile: '.$record['username'].'.');
        [$code,$removedCertificate]=demo_http('/certificates/certificate.php?id='.$other['request_id'],[],$cookie);demo_check($code===302&&!str_contains($removedCertificate,'STUDENT CLEARANCE CERTIFICATE'),'Removed certificate view still exposes a demo clearance: '.$record['username'].'.');
        [$code,$notifications]=demo_http('/student/notifications.php',[],$cookie);demo_check($code===200&&str_contains($notifications,'Only '.$record['username'])&&!str_contains($notifications,'Only '.$other['username']),'Student notification privacy failed for '.$record['username'].'.');
        demo_check(!str_contains($dashboard,'student/results.php')&&!str_contains($dashboard,'Academic Results'),'Student dashboard still exposes Academic Results.');
        [$code]=demo_http('/student/results.php',[],$cookie);demo_check($code===302,'Removed results page did not return the student to their dashboard.');
        [$code]=demo_http('/student/start.php',[],$cookie);demo_check($code===302&&(int)$pdo->query('SELECT COUNT(*) FROM clearance_requests WHERE student_id='.(int)$record['student_id'].' AND academic_year="'.$cycle.'"')->fetchColumn()===1,'Duplicate clearance start was not blocked for '.$record['username'].'.');
        $temporary='Temporary garden '.$index.' 82';change_password($pdo,(int)$record['id'],$record['password'],$temporary,$temporary);demo_check(password_verify($temporary,(string)$pdo->query('SELECT password_hash FROM users WHERE id='.(int)$record['id'])->fetchColumn()),'Optional password change failed for '.$record['username'].'.');
        change_password($pdo,(int)$record['id'],$temporary,$record['password'],$record['password']);
        echo 'PASS: '.$record['username']." login, dashboard, optional password, privacy and duplicate-cycle checks\n";
    }
    echo "PASS: all five demo students verified independently\n";
}catch(Throwable $error){fwrite(STDERR,'FAIL: '.$error->getMessage().PHP_EOL);$exit=1;}
finally{
    if($pdo&&$pdo->inTransaction()){$pdo->rollBack();}
    if(is_resource($web)){proc_terminate($web);proc_close($web);}
    foreach($files as $file){if(is_file($file)){unlink($file);}}
    $sessionTarget=realpath(__DIR__.'/.demo-sessions');$expected=realpath(__DIR__).DIRECTORY_SEPARATOR.'.demo-sessions';
    if($sessionTarget&&strcasecmp($sessionTarget,$expected)===0){foreach(new DirectoryIterator($sessionTarget) as $entry){if($entry->isFile()&&preg_match('/^sess_[a-zA-Z0-9,-]+$/D',$entry->getFilename())){unlink($entry->getPathname());}}rmdir($sessionTarget);}
    if($created&&$server&&preg_match('/^irdp_demo_test_[a-f0-9]{16}$/D',$database)){$server->exec('DROP DATABASE '.$database);echo "Isolated demo database removed.\n";}
}
exit($exit);
