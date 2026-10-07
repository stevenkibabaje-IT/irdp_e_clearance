<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Command line only.');}
require_once __DIR__.'/../includes/installer.php';
require_once __DIR__.'/../includes/functions.php';
date_default_timezone_set('Africa/Dar_es_Salaam');$_SESSION=[];
function check(bool $condition,string $message): void {if(!$condition){throw new RuntimeException($message);}}
function rejected(callable $operation,string $message): void {try{$operation();}catch(RuntimeException $e){return;}throw new RuntimeException($message);}
function fixture_stage(PDO $pdo,int $student,int $office,int $step,int $reviewer,string $age='NOW()'): array
{
    static $cycleStart=2030;$cycle=$cycleStart.'/'.(++$cycleStart);$pdo->prepare('INSERT IGNORE INTO academic_cycles(label) VALUES (?)')->execute([$cycle]);$pdo->prepare('UPDATE clearance_period SET cycle_id=(SELECT id FROM academic_cycles WHERE label=?),mode="MANUAL_OPEN" WHERE id=1')->execute([$cycle]);
    $pdo->prepare('INSERT INTO clearance_requests(student_id,academic_year,status,started_at) VALUES (?,?,"IN_PROGRESS",NOW())')->execute([$student,$cycle]);$request=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO clearance_stages(clearance_request_id,workflow_step_id,office_id,assigned_officer_id,original_officer_id,status,actionable_at,started_at) VALUES (?,?,?,?,?,"PENDING",'.$age.','.$age.')')->execute([$request,$step,$office,$reviewer,$reviewer]);$id=(int)$pdo->lastInsertId();ensure_review_cycle($pdo,$id);return [$request,$id];
}
function http_call(string $path,array $post=[],?string $cookie=null,bool $isPost=false,array $headers=[]): array
{
    $c=curl_init('http://127.0.0.1:18087'.$path);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60,CURLOPT_HEADER=>false,CURLOPT_FOLLOWLOCATION=>false]);
    if($cookie){curl_setopt($c,CURLOPT_COOKIEFILE,$cookie);curl_setopt($c,CURLOPT_COOKIEJAR,$cookie);}
    if($headers){curl_setopt($c,CURLOPT_HTTPHEADER,$headers);}
    if($isPost){curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,$post);}
    $body=curl_exec($c);$code=(int)curl_getinfo($c,CURLINFO_RESPONSE_CODE);$GLOBALS['feature_http_times'][]=curl_getinfo($c,CURLINFO_TOTAL_TIME);$err=curl_error($c);curl_close($c);if($body===false){throw new RuntimeException('HTTP test failed: '.$err);}return [$code,$body];
}
function token_from(string $html): string {if(!preg_match('/name="csrf_token" value="([^"]+)"/',$html,$m)){throw new RuntimeException('CSRF field missing.');}return html_entity_decode($m[1],ENT_QUOTES,'UTF-8');}
function test_login(string $username,string $password,string $cookie): void
{
    [$code,$body]=http_call('/auth/login.php',[],$cookie);check($code===200,'Login page unavailable.');
    [$code,$body]=http_call('/auth/login.php',['csrf_token'=>token_from($body),'username'=>$username,'password'=>$password],$cookie,true);check($code===302,'Test login failed for '.$username.': '.strip_tags($body));
}
$dsn=$argv[1]??'mysql:host=127.0.0.1;port=13317;charset=utf8mb4';$database='irdp_test_'.bin2hex(random_bytes(8));$pdo=null;$other=null;$server=null;$web=null;$files=[];$created=false;$exit=0;
try{
    check(password_policy('Orbit meadow lantern 52','Orbit meadow lantern 52')==='Orbit meadow lantern 52','Passphrase rejected.');
    rejected(fn()=>password_policy('Abc1234','Abc1234'),'Short password accepted.');check(password_policy('Abc12345','Abc12345')==='Abc12345','Eight-character password rejected.');rejected(fn()=>password_policy(str_repeat('x',65),str_repeat('x',65)),'Overlong password accepted.');rejected(fn()=>password_policy('Abc123','other'),'Confirmation mismatch accepted.');rejected(fn()=>password_policy('',''),'Empty password accepted.');
    check(password_verify('ExistingLongPassword',password_hash('ExistingLongPassword',PASSWORD_DEFAULT)),'Existing long password verification changed.');
    foreach(['2026/2028','2026-2027'] as $cycle){rejected(fn()=>academic_cycle($cycle),'Invalid cycle accepted.');}
    check(person_name("Anne O'Neil-Said")==="Anne O'Neil-Said",'Valid real name rejected.');check(!valid_date('2026-02-30'),'Impossible date accepted.');rejected(fn()=>positive_id('1junk'),'Invalid ID accepted.');
    echo "PASS: password policy, legacy login compatibility and shared validation\n";
    $server=new PDO($dsn,getenv('IRDP_TEST_USER')?:'root',getenv('IRDP_TEST_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);$server->exec("SET time_zone='+03:00'");$server->exec('CREATE DATABASE '.$database.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;$server->exec('USE '.$database);$pdo=ensure_database_ready($server);foreach(['student_results','programme_modules','grading_rules'] as $removedTable){check($pdo->query("SHOW TABLES LIKE '".$removedTable."'")->fetchColumn()===false,'Fresh setup recreated the removed results table '.$removedTable);}$pdo->exec("UPDATE clearance_period SET mode='MANUAL_OPEN',cycle_id=(SELECT id FROM academic_cycles WHERE label='2026/2027' LIMIT 1),remarks='Feature test period' WHERE id=1");
    // Exercise an existing installation's old transcript source enum.
    $pdo->exec('ALTER TABLE transcripts MODIFY result_source ENUM("DEMO","OFFICIAL") NOT NULL');
    $pdo->exec("DELETE FROM schema_migrations WHERE version='2026_clearance_documents_v3'");
    migrate_clearance_documents($pdo);
    check(str_contains((string)$pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='transcripts' AND COLUMN_NAME='result_source'")->fetchColumn(),"'CLEARANCE'"),'Existing transcript storage was not upgraded to clearance documents.');
    $ids=[];foreach($pdo->query('SELECT id,username FROM users') as $u){$ids[$u['username']]=(int)$u['id'];}$admin=$ids['admin'];$primary=$ids['LIB001'];$backup=$ids['SPORT001'];$studentUser=$ids['IRDP/ODICT/MA25/0001'];$student=(int)$pdo->query('SELECT id FROM students WHERE user_id='.$studentUser)->fetchColumn();$office=(int)$pdo->query('SELECT office_id FROM user_offices WHERE user_id='.$primary)->fetchColumn();$step=(int)$pdo->query('SELECT id FROM workflow_steps WHERE step_number=1')->fetchColumn();$department=(int)$pdo->query('SELECT id FROM departments WHERE code="EPM"')->fetchColumn();
    check(existing_academic_cycle($pdo,'2026/2027')==='2026/2027','Existing academic cycle rejected.');
    rejected(fn()=>existing_academic_cycle($pdo,'2098/2099'),'Nonexistent academic cycle accepted.');
    unique_account_email($pdo,'new-student@example.invalid');
    $existingEmail=$pdo->query('SELECT email FROM users WHERE email IS NOT NULL AND email<>"" LIMIT 1')->fetchColumn();
    if($existingEmail){rejected(fn()=>unique_account_email($pdo,$existingEmail),'Duplicate account email accepted.');}
    foreach(['12345','Anne <script>','   '] as $name){rejected(fn()=>person_name($name),'Invalid name accepted.');}
    check(registration_number(' IRDP/ODICT/MA25/0001 ')==='IRDP/ODICT/MA25/0001','Registration trimming changed a valid number.');
    rejected(fn()=>registration_number('IRDP/<bad>/MA26/0002'),'Invalid registration accepted.');
    rejected(fn()=>email_value('not-an-email'),'Invalid email accepted.');
    rejected(fn()=>validate_review_details([['amount','Amount','number']],['amount'=>'junk']),'Text accepted as amount.');
    rejected(fn()=>validate_review_details([['item','Item','asset']],['item'=>'UNKNOWN']),'Unlisted clearance option accepted.');
    echo "PASS: existing/nonexistent academic cycles, duplicate emails, names, registration numbers and clearance values\n";
    check(database_schema_ready($pdo),'Installed database did not record its runtime version.');
    // Exercise upgrading an installation that still has the retired queue and preference.
    $studentsBefore=$pdo->query('SELECT * FROM students ORDER BY id')->fetchAll();
    $usersBefore=$pdo->query('SELECT * FROM users ORDER BY id')->fetchAll();
    $pdo->exec('ALTER TABLE students ADD COLUMN sms_opt_in TINYINT NOT NULL DEFAULT 0');
    $pdo->exec('CREATE TABLE sms_outbox (id INT AUTO_INCREMENT PRIMARY KEY,student_id INT NOT NULL, FOREIGN KEY(student_id) REFERENCES students(id)) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO sms_outbox(student_id) VALUES ('.$student.')');
    $pdo->exec("INSERT IGNORE INTO schema_migrations(version) VALUES ('2026_clearance_sms_v1'),('2026_runtime_v3_sms')");
    $pdo->prepare('DELETE FROM schema_migrations WHERE version=?')->execute([APPLICATION_SCHEMA_VERSION]);
    ensure_database_ready($pdo,false);
    ensure_database_ready($pdo,false);
    check(!(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sms_outbox'")->fetchColumn(),'Retired queue remains.');
    check(!(int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students' AND COLUMN_NAME='sms_opt_in'")->fetchColumn(),'Retired preference remains.');
    check($studentsBefore===$pdo->query('SELECT * FROM students ORDER BY id')->fetchAll()&&$usersBefore===$pdo->query('SELECT * FROM users ORDER BY id')->fetchAll(),'Cleanup changed student or account data.');
    check(database_schema_ready($pdo),'Cleanup did not record the new runtime version.');
    echo "PASS: retired notification cleanup preserves students, phone numbers and accounts; repeated startup is safe\n";
    // A normal request must not take the installation lock or reseed data.
    $lockConnection=new PDO($dsn,getenv('IRDP_TEST_USER')?:'root',getenv('IRDP_TEST_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$lockConnection->exec('USE '.$database);$lockConnection->query('SELECT GET_LOCK(CONCAT(DATABASE(), ":irdp_schema"), 0)');
    $runtimeEnv=getenv();$runtimeEnv['DB_HOST']='127.0.0.1';preg_match('/(?:^|;)port=(\d+)/',$dsn,$runtimePort);$runtimeEnv['DB_PORT']=$runtimePort[1]??'3306';$runtimeEnv['DB_NAME']=$database;
    $runtimeEnv['DB_USER']=getenv('IRDP_TEST_USER')?:'root';$runtimeEnv['DB_PASS']=getenv('IRDP_TEST_PASSWORD')?:'';
    $probe=proc_open([PHP_BINARY,'-r','require "includes/installer.php"; $db=application_database(); echo $db->query("SELECT DATABASE()")->fetchColumn();'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$runtimePipes,dirname(__DIR__),$runtimeEnv);check(is_resource($probe),'Runtime startup probe unavailable.');fclose($runtimePipes[0]);$runtimeOutput=stream_get_contents($runtimePipes[1]);$runtimeError=stream_get_contents($runtimePipes[2]);fclose($runtimePipes[1]);fclose($runtimePipes[2]);$runtimeCode=proc_close($probe);
    $lockConnection->query('SELECT RELEASE_LOCK(CONCAT(DATABASE(), ":irdp_schema"))');$lockConnection=null;
    check($runtimeCode===0&&$runtimeOutput===$database,'Normal startup attempted installation or selected the wrong database: '.$runtimeError);
    echo "PASS: normal requests bypass database installation and its schema lock\n";
    // Ordinary office assignment works without availability, backups or escalation.
    $supervisor=create_account($pdo,['username'=>'TESTSUP','full_name'=>'Taylor Example','password'=>'Cloud lantern meadow 47','confirm_password'=>'Cloud lantern meadow 47','role_id'=>(string)$pdo->query('SELECT id FROM roles WHERE name="SUPERVISOR"')->fetchColumn(),'email'=>'','office_id'=>'','department_id'=>''],$admin);
    $pdo->prepare('INSERT INTO user_offices(user_id,office_id) VALUES (?,?)')->execute([$backup,$office]);
    [$request,$stage]=fixture_stage($pdo,$student,$office,$step,$backup,'DATE_SUB(NOW(),INTERVAL 73 HOUR)');
    $pdo->prepare('INSERT INTO officer_availability(officer_id,status,start_at,reason,changed_by) VALUES (?,"UNAVAILABLE",NOW(),"Legacy availability record",?)')->execute([$primary,$admin]);
    check(find_office_reviewer($pdo,$office)===$primary,'Retired availability records blocked an active office officer.');
    $pdo->beginTransaction();route_clearance_stage($pdo,$request,$stage,$admin);lock_review_stage($pdo,$request,$stage,$backup,'PENDING');$pdo->commit();
    check((int)$pdo->query('SELECT COUNT(*) FROM clearance_escalations')->fetchColumn()===0,'Ordinary routing generated an escalation.');
    check(!office_authority($pdo,$supervisor,$office),'Legacy Supervisor implicitly gained office access.');
    $pdo->beginTransaction();rejected(fn()=>lock_review_stage($pdo,$request,$stage,$supervisor,'PENDING'),'Reviewer without office access reviewed stage.');$pdo->rollBack();
    $pdo->prepare('UPDATE users SET active=0 WHERE id=?')->execute([$backup]);
    $pdo->beginTransaction();rejected(fn()=>lock_review_stage($pdo,$request,$stage,$backup,'PENDING'),'Inactive officer reviewed stage.');$pdo->rollBack();
    $pdo->prepare('UPDATE users SET active=1 WHERE id=?')->execute([$backup]);
    echo "PASS: active office assignment, retired availability ignored and unauthorized/inactive reviews blocked\n";
    $pdo->prepare('UPDATE clearance_stages SET status="REJECTED",comments="Return library item",corrective_instructions="Attach the returned-item receipt",resubmissions=0,assignment_status="REASSIGNED" WHERE id=?')->execute([$stage]);$pdo->prepare('UPDATE clearance_requests SET status="PAUSED" WHERE id=?')->execute([$request]);
    $pdo->prepare('INSERT INTO stage_actions(stage_id,officer_id,action,comments,corrective_instructions,review_cycle) VALUES (?,?,"REJECTED","Return library item","Attach receipt",1)')->execute([$stage,$backup]);
    resubmit_stage($pdo,$stage,$studentUser,'I returned the item.',[]);
    $state=stage_context($pdo,$stage);check($state['status']==='PENDING'&&(int)$state['review_cycle']===2&&(int)$state['resubmissions']===1,'Resubmission did not start a new cycle.');
    check((int)$pdo->query('SELECT COUNT(*) FROM stage_actions WHERE stage_id='.$stage)->fetchColumn()===1,'Prior rejection history lost.');
    rejected(fn()=>resubmit_stage($pdo,$stage,$ids['IRDP/BTCRP/MA25/0002'],'Wrong owner',[]),'Wrong Student resubmitted stage.');
    rejected(fn()=>process_stage_decision($pdo,$stage,$backup,['action'=>'APPROVED','comments'=>'','corrective_instructions'=>'','review_cycle'=>'1','amount'=>'0'],[['amount','Amount','number']]),'Stale cycle approved.');
    rejected(fn()=>process_stage_decision($pdo,$stage,$backup,['action'=>'APPROVED','comments'=>'','corrective_instructions'=>'','review_cycle'=>'2','amount'=>'1'],[['amount','Amount','number']]),'Outstanding debt approved.');
    rejected(fn()=>process_stage_decision($pdo,$stage,$backup,['action'=>'APPROVED_EVIDENCE','comments'=>'','corrective_instructions'=>'','review_cycle'=>'2','amount'=>'1'],[['amount','Amount','number']]),'Evidence confirmation accepted without current supporting files.');
    $pdo->prepare('UPDATE clearance_stages SET status="REJECTED",resubmissions=3,actionable_at=NULL WHERE id=?')->execute([$stage]);$pdo->prepare('UPDATE clearance_requests SET status="PAUSED" WHERE id=?')->execute([$request]);
    resubmit_stage($pdo,$stage,$studentUser,'Fourth correction without an appeal',[]);
    check((int)stage_context($pdo,$stage)['review_cycle']===3 && (int)stage_context($pdo,$stage)['resubmissions']===4,'Ordinary corrections remained dependent on the removed appeal feature.');
    check((int)$pdo->query('SELECT COUNT(*) FROM stage_appeals')->fetchColumn()===0,'Correction created an appeal.');
    ob_start();student_evidence_form($pdo,stage_context($pdo,$stage));$evidenceForm=ob_get_clean();
    check(str_contains($evidenceForm,'name="mode" value="resubmit"') && !str_contains($evidenceForm,'Supervisor appeal'),'Evidence form still offers appeals.');
    echo "PASS: rejection history, new review cycles, liability checks and repeated ordinary corrections\n";
    // Reset expiry, single use, session revocation, mismatch and account change.
    $version=(int)user_record($pdo,$studentUser)['auth_version'];$pdo->beginTransaction();$credential=create_reset_credential($pdo,$studentUser,$admin,'Fictional ID check');$pdo->commit();
    check((string)$pdo->query('SELECT token_hash FROM password_resets WHERE user_id='.$studentUser.' ORDER BY id DESC LIMIT 1')->fetchColumn()!==$credential,'Plaintext reset token stored.');
    rejected(fn()=>reset_password($pdo,$credential,'Stone orchard violet 62','Mismatch'),'Reset confirmation mismatch accepted.');
    reset_password($pdo,$credential,'Stone orchard violet 62','Stone orchard violet 62');check((int)user_record($pdo,$studentUser)['auth_version']===$version+1,'Sessions were not revoked after reset.');rejected(fn()=>reset_password($pdo,$credential,'River lantern planet 83','River lantern planet 83'),'Token reuse accepted.');
    $pdo->beginTransaction();$expired=create_reset_credential($pdo,$studentUser,$admin,'Expiry check');$pdo->commit();$pdo->prepare('UPDATE password_resets SET expires_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE token_hash=?')->execute([hash('sha256',$expired)]);rejected(fn()=>reset_password($pdo,$expired,'Stone orchard violet 62','Stone orchard violet 62'),'Expired token accepted.');
    rejected(fn()=>change_password($pdo,$studentUser,'wrong','Cedar ocean lantern 91','Cedar ocean lantern 91'),'Wrong current password accepted.');change_password($pdo,$studentUser,'Stone orchard violet 62','Cedar ocean lantern 91','Cedar ocean lantern 91');
    for($i=0;$i<3;$i++){rate_limit($pdo,'test-throttle','fictional',3,30);}rejected(fn()=>rate_limit($pdo,'test-throttle','fictional',3,30),'Rate limiter did not block.');
    echo "PASS: reset hash/expiry/reuse, password confirmation/change, session revocation and throttling\n";
    // Import preview and commit use fictional rows only.
    $rows=[['Morgan Example','IRDP/ODICT/MA26/9101','ODICT','EPM','2026/2027','morgan@example.invalid'],['Duplicate Example','IRDP/ODICT/MA26/9102','ODICT','EPM','2026/2027',''],['Duplicate Example','IRDP/ODICT/MA26/9102','ODICT','EPM','2026/2027',''],['Invalid Example','wrong','ODICT','EPM','2026/2027','']];
    $preview=preview_import($pdo,$rows);check(!$preview[0]['errors']&&$preview[1]['errors']&&$preview[2]['errors']&&$preview[3]['errors'],'Import preview misclassified rows.');$import=commit_import($pdo,$preview,$admin);check($import['created']===1&&$import['skipped']===3&&$import['failed']===0,'Import counts incorrect.');$repeat=commit_import($pdo,$preview,$admin);check($repeat['created']===0&&$repeat['skipped']===4,'Repeated import created duplicates.');
    check((int)$pdo->query('SELECT force_password_change FROM users WHERE username="IRDP/ODICT/MA26/9101"')->fetchColumn()===0,'Imported student has a forced-password flag.');
    $emailPreview=preview_import($pdo,[
        ['Email Example One','IRDP/ODICT/MA26/9191','ODICT','EPM','2026/2027','same@example.invalid'],
        ['Email Example Two','IRDP/ODICT/MA26/9192','ODICT','EPM','2026/2027','SAME@example.invalid'],
    ]);
    check($emailPreview[0]['errors']&&$emailPreview[1]['errors'],'Duplicate emails were not flagged at import preview.');
    echo "PASS: bulk preview, invalid/duplicate rows/emails, confirmed import and repeat protection\n";
    // Two connections must serialize decisions.
    $other=new PDO($dsn,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);$other->exec('USE '.$database);$other->exec('SET SESSION innodb_lock_wait_timeout=1');
    $pdo->beginTransaction();lock_review_stage($pdo,$request,$stage,$backup,'PENDING');$other->beginTransaction();$blocked=false;try{lock_review_stage($other,$request,$stage,$backup,'PENDING');}catch(PDOException $e){$blocked=(int)($e->errorInfo[1]??0)===1205;}finally{$other->rollBack();}$pdo->rollBack();check($blocked,'Concurrent decision bypassed request lock.');
    $pdo->beginTransaction();lock_account_creation($pdo);$other->beginTransaction();$blocked=false;
    try{lock_account_creation($other);}catch(PDOException $e){$blocked=(int)($e->errorInfo[1]??0)===1205;}finally{$other->rollBack();}
    $pdo->rollBack();check($blocked,'Concurrent account/email validation bypassed the account creation lock.');$other=null;
    echo "PASS: simultaneous decisions serialized\n";
    preg_match('/(?:^|;)port=(\d+)/', $dsn, $testPort);
    $env=getenv();$env['DB_HOST']='127.0.0.1';$env['DB_PORT']=$testPort[1]??'3306';$env['DB_NAME']=$database;$env['DB_USER']=getenv('IRDP_TEST_USER')?:'root';$env['DB_PASS']=getenv('IRDP_TEST_PASSWORD')?:'';
    $sessionDirectory=__DIR__.'/.feature-sessions';if(!is_dir($sessionDirectory)){mkdir($sessionDirectory,0700);}
    $log=__DIR__.'/feature-http.log';$files[]=$log;$web=proc_open([PHP_BINARY,'-d','session.save_path='.$sessionDirectory,'-d','upload_max_filesize=6M','-d','post_max_size=32M','-S','127.0.0.1:18087','-t',dirname(__DIR__),__DIR__.'/http_router.php'],[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,dirname(__DIR__),$env);
    if(!is_resource($web)){throw new RuntimeException('Test web server unavailable.');}fclose($pipes[0]);
    $ready=false;for($i=0;$i<30;$i++){$socket=@fsockopen('127.0.0.1',18087,$errno,$error,0.2);if($socket){fclose($socket);$ready=true;break;}usleep(100000);}check($ready,'Test web server did not start.');
    $pdo->exec('INSERT IGNORE INTO academic_cycles(label) VALUES ("2027/2028")');
    $pdo->prepare('INSERT INTO clearance_requests(student_id,academic_year,status,started_at) VALUES (?,"2027/2028","IN_PROGRESS",NOW())')->execute([$student]);$fullRequest=(int)$pdo->lastInsertId();$fullStages=[];
    foreach($pdo->query('SELECT * FROM workflow_steps ORDER BY step_number')->fetchAll() as $workflow){
        $number=(int)$workflow['step_number'];$assigned=find_office_reviewer($pdo,(int)$workflow['office_id'],$number===7?$department:0);check($assigned!==null,'Full workflow fixture has no officer.');
        $pdo->prepare('INSERT INTO clearance_stages(clearance_request_id,workflow_step_id,office_id,assigned_officer_id,original_officer_id,status,actionable_at) VALUES (?,?,?,?,?,?,?)')->execute([$fullRequest,$workflow['id'],$workflow['office_id'],$assigned,$assigned,$number===1?'PENDING':'LOCKED',$number===1?date('Y-m-d H:i:s'):null]);$fullStages[$number]=(int)$pdo->lastInsertId();ensure_review_cycle($pdo,$fullStages[$number]);
    }
    foreach($fullStages as $number=>$id){
        $state=stage_context($pdo,$id);$fields=review_fields($number);$input=['action'=>'APPROVED','comments'=>'Fictional review','corrective_instructions'=>'','review_cycle'=>(string)$state['review_cycle']];
        foreach($fields as [$key,$label,$type]){$input[$key]=$type==='number'?'0':($type==='asset'?'AVAILABLE':'CLEARED');}
        if ($number === 11) {
            $control='991234567890';$newControl='991234567891';
            $periodBeforeFinance=$pdo->query('SELECT cycle_id FROM clearance_period WHERE id=1')->fetchColumn();
            $pdo->exec('UPDATE clearance_period SET mode="MANUAL_OPEN",cycle_id=(SELECT id FROM academic_cycles WHERE label="2027/2028") WHERE id=1');
            $financeCookie=__DIR__.'/feature-finance.cookies';$files[]=$financeCookie;
            $financeStudentCookie=__DIR__.'/feature-finance-student.cookies';$files[]=$financeStudentCookie;
            $otherStudentCookie=__DIR__.'/feature-finance-other.cookies';$files[]=$otherStudentCookie;
            $otherOfficeCookie=__DIR__.'/feature-finance-other-office.cookies';$files[]=$otherOfficeCookie;
            test_login('FIN001','Mollel@2026',$financeCookie);
            test_login('IRDP/ODICT/MA25/0001','Cedar ocean lantern 91',$financeStudentCookie);
            $demoDefinitions=require __DIR__.'/../config/demo_students.php';
            test_login($demoDefinitions[1]['registration_number'],demo_student_initial_password($demoDefinitions[1]),$otherStudentCookie);
            test_login('LIB001','Mrema@2026',$otherOfficeCookie);
            $receipt=__DIR__.'/finance-test-receipt.pdf';$files[]=$receipt;
            file_put_contents($receipt,"%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n");
            [$code,$financeReview]=http_call('/officer/review.php?stage='.$id,[],$financeCookie);
            check($code===200&&str_contains($financeReview,'name="control_number"'),'Finance control field missing.');
            foreach(['decision','debt','recovered','comments','corrective_instructions'] as $removed){
                check(!str_contains($financeReview,'name="'.$removed.'"'),'Finance form still contains '.$removed);
            }
            $decision=['csrf_token'=>token_from($financeReview),'stage_id'=>(string)$id,'review_cycle'=>'1','action'=>'APPROVED','control_number'=>$control];
            foreach(['APPROVED','APPROVED_EVIDENCE','APPROVED_CORRECTION'] as $bypass){
                [$code,$denied]=http_call('/officer/review.php?stage='.$id,array_replace($decision,['action'=>$bypass]),$financeCookie,true);
                check($code===200&&str_contains($denied,'required before approval')&&stage_context($pdo,$id)['status']==='PENDING','Finance approved without a receipt.');
            }
            foreach(['12','abc123','9912<script>','1e12',str_repeat('9',31)] as $invalid){
                [$code,$invalidControl]=http_call('/officer/review.php?stage='.$id,array_replace($decision,['action'=>'REJECTED','control_number'=>$invalid]),$financeCookie,true);
                check($code===200&&str_contains($invalidControl,'control_number-error')&&stage_context($pdo,$id)['status']==='PENDING','Invalid control number published.');
            }
            [$code]=http_call('/officer/review.php?stage='.$id,array_replace($decision,['action'=>'REJECTED']),$financeCookie,true);
            check($code===302&&stage_context($pdo,$id)['status']==='REJECTED','Finance could not request payment with only a control number.');
            // Existing rejected Finance stages can receive a control number without losing history.
            $pdo->prepare('UPDATE clearance_stages SET details_json=? WHERE id=?')->execute([json_encode(['decision'=>'NOT_CLEARED','debt'=>'2500','recovered'=>'0']),$id]);
            [$code,$legacyFinance]=http_call('/officer/review.php?stage='.$id,[],$financeCookie);
            check($code===200&&str_contains($legacyFinance,'name="control_number"'),'Legacy rejected Finance stage cannot be reviewed.');
            [$code,$financeQueue]=http_call('/officer/dashboard.php',[],$financeCookie);
            check($code===200&&str_contains($financeQueue,'officer/review.php?stage='.$id),'Finance cannot update an outstanding control number.');
            [$code]=http_call('/officer/review.php?stage='.$id,array_replace($decision,['csrf_token'=>token_from($legacyFinance),'action'=>'REJECTED']),$financeCookie,true);
            check($code===302&&(int)stage_context($pdo,$id)['review_cycle']===2,'Legacy payment request did not preserve its previous review cycle.');
            foreach(['/student/dashboard.php','/student/status.php','/student/resubmit.php?stage='.$id] as $paymentPage){
                [$code,$paymentHtml]=http_call($paymentPage,[],$financeStudentCookie);
                check($code===200&&str_contains($paymentHtml,$control)&&str_contains($paymentHtml,'Upload payment receipt'),'Student cannot see control number and receipt upload.');
            }
            $upload=['csrf_token'=>token_from($paymentHtml),'stage_id'=>(string)$id,'mode'=>'finance_payment','control_number'=>$control];
            $beforeReceipt=stage_context($pdo,$id);
            [$code,$missingReceipt]=http_call('/student/resubmit.php?stage='.$id,$upload,$financeStudentCookie,true);
            check($code===200&&str_contains($missingReceipt,'evidence-error')&&stage_context($pdo,$id)===$beforeReceipt,'Missing receipt check failed: HTTP '.$code.'; '.substr(strip_tags($missingReceipt),-1500).'; state changed: '.(stage_context($pdo,$id)===$beforeReceipt?'no':'yes'));
            [$code]=http_call('/student/resubmit.php?stage='.$id,array_replace($upload,['mode'=>'resubmit','response'=>'Payment completed']),$financeStudentCookie,true);
            check($code===200&&stage_context($pdo,$id)===$beforeReceipt,'Explanation bypassed mandatory receipt.');
            [$code]=http_call('/student/resubmit.php?stage='.$id,[],$otherStudentCookie);check($code===403,'Another student accessed Finance payment form.');
            $badReceipt=__DIR__.'/finance-invalid-receipt.pdf';$files[]=$badReceipt;file_put_contents($badReceipt,'This is not a PDF receipt.');
            [$code,$badReceiptPage]=http_call('/student/resubmit.php?stage='.$id,$upload+['evidence[0]'=>new CURLFile($badReceipt,'application/pdf','receipt.pdf')],$financeStudentCookie,true);
            check($code===200&&str_contains($badReceiptPage,'evidence-error')&&stage_context($pdo,$id)===$beforeReceipt,'Invalid receipt content accepted.');
            [$code,$tooMany]=http_call('/student/resubmit.php?stage='.$id,$upload+['evidence[0]'=>new CURLFile($receipt,'application/pdf','receipt.pdf'),'evidence[1]'=>new CURLFile($receipt,'application/pdf','another.pdf')],$financeStudentCookie,true);
            check($code===200&&str_contains($tooMany,'one payment receipt')&&stage_context($pdo,$id)===$beforeReceipt,'Multiple receipts accepted.');
            foreach([1,2] as $attempt){
                $issued=$attempt===1?$control:$newControl;
                [$code,$paymentHtml]=http_call('/student/resubmit.php?stage='.$id,[],$financeStudentCookie);
                $upload=['csrf_token'=>token_from($paymentHtml),'stage_id'=>(string)$id,'mode'=>'finance_payment','control_number'=>$issued];
                if($attempt===2){
                    [$code,$staleNumber]=http_call('/student/resubmit.php?stage='.$id,array_replace($upload,['control_number'=>$control])+['evidence[0]'=>new CURLFile($receipt,'application/pdf','receipt.pdf')],$financeStudentCookie,true);
                    check($code===200&&str_contains($staleNumber,'control number changed')&&stage_context($pdo,$id)['status']==='REJECTED','Previous control number accepted.');
                }
                [$code]=http_call('/student/resubmit.php?stage='.$id,$upload+['evidence[0]'=>new CURLFile($receipt,'application/pdf','receipt.pdf')],$financeStudentCookie,true);
                foreach($pdo->query('SELECT stored_name FROM stage_evidence WHERE stage_id='.$id) as $stored){$files[]=private_path('evidence',$stored['stored_name']);}
                check($code===302&&stage_context($pdo,$id)['status']==='PENDING'&&stage_context($pdo,$id)['request_status']==='IN_PROGRESS','Receipt submission failed or skipped review.');
                $payment=finance_payment_details(stage_context($pdo,$id));
                check($payment['payment_status']==='AWAITING_REVIEW'&&$payment['receipt_control_number']===$issued,'Receipt not bound to control number.');
                [$code]=http_call('/certificates/certificate.php?id='.$fullRequest,[],$financeStudentCookie);check($code===409,'Receipt upload released certificate.');
                [$code,$pendingPayment]=http_call('/student/dashboard.php',[],$financeStudentCookie);
                check($code===200&&str_contains($pendingPayment,'Awaiting Finance review')&&!str_contains($pendingPayment,'name="evidence[]"'),'Pending payment status missing.');
                [$code,$financeReview]=http_call('/officer/review.php?stage='.$id,[],$financeCookie);
                check($code===200&&str_contains($financeReview,'receipt.pdf'),'Finance cannot see receipt.');
                [$code]=http_call('/files/evidence.php?id='.$payment['receipt_id'],[],$financeCookie);check($code===200,'Finance receipt download blocked.');
                [$code]=http_call('/files/evidence.php?id='.$payment['receipt_id'],[],$otherStudentCookie);check($code===403,'Another student accessed receipt.');
                [$code]=http_call('/files/evidence.php?id='.$payment['receipt_id'],[],$otherOfficeCookie);check($code===403,'Another office accessed receipt.');
                $decision=['csrf_token'=>token_from($financeReview),'stage_id'=>(string)$id,'review_cycle'=>(string)stage_context($pdo,$id)['review_cycle'],'action'=>'APPROVED','control_number'=>$issued];
                [$code,$staleApproval]=http_call('/officer/review.php?stage='.$id,array_replace($decision,['review_cycle'=>'1']),$financeCookie,true);
                check($code===200&&str_contains($staleApproval,'review cycle changed')&&stage_context($pdo,$id)['status']==='PENDING','Stale Finance decision accepted.');
                if($attempt===1){
                    [$code,$wrongNumber]=http_call('/officer/review.php?stage='.$id,array_replace($decision,['control_number'=>$newControl]),$financeCookie,true);
                    check($code===200&&str_contains($wrongNumber,'request a new receipt')&&stage_context($pdo,$id)['status']==='PENDING','Changed control approved against old receipt.');
                    [$code]=http_call('/officer/review.php?stage='.$id,array_replace($decision,['action'=>'REJECTED','control_number'=>$newControl]),$financeCookie,true);
                    check($code===302&&stage_context($pdo,$id)['status']==='REJECTED'&&!finance_receipt_available($pdo,stage_context($pdo,$id)),'Reject failed to invalidate receipt.');
                }else{
                    [$code]=http_call('/officer/review.php?stage='.$id,$decision,$financeCookie,true);
                    check($code===302&&stage_context($pdo,$id)['status']==='APPROVED'&&stage_context($pdo,$id)['request_status']==='COMPLETED','Finance approval did not complete clearance.');
                }
            }
            check(finance_details_clear(finance_payment_details(stage_context($pdo,$id))),'Approved payment did not clear finance.');
            check((int)$pdo->query('SELECT COUNT(*) FROM stage_evidence WHERE stage_id='.$id)->fetchColumn()===2,'Rejected payment history lost.');
            $pdo->prepare('UPDATE clearance_period SET cycle_id=? WHERE id=1')->execute([$periodBeforeFinance]);
            echo "PASS: Finance control-only review, mandatory/private receipts, owner/control/cycle checks, rejections and final approval\n";
            continue;
        }
        if(in_array($number,[2,8],true)) {
            $reject=array_replace($input,['action'=>'REJECTED','comments'=>'Fictional unresolved office finding','corrective_instructions'=>'Resolve with the office']);
            if($number===2){$reject['amount']='20000';}
            if($number===8){$reject['key']='MISSING';$reject['bucket']='MISSING';$reject['so_has_to_pay']='20000';$reject['nothing_amount']='5000';}
            rejected(fn()=>process_stage_decision($pdo,$id,(int)$state['assigned_officer_id'],array_replace($reject,['action'=>'APPROVED_CORRECTION']),$fields),'Correction approval bypassed the required student resubmission.');
            process_stage_decision($pdo,$id,(int)$state['assigned_officer_id'],$reject,$fields);
            check(stage_context($pdo,$fullStages[$number-1])['status']==='APPROVED','Rejection undid prior approval.');
            if($number<11){check(stage_context($pdo,$fullStages[$number+1])['status']==='LOCKED','Rejection unlocked later stage.');}
            resubmit_stage($pdo,$id,$studentUser,'Correction verified with the office; no attachment needed.',[]);
            $input=array_replace($reject,['action'=>'APPROVED_CORRECTION','review_cycle'=>'2']);
        }
        process_stage_decision($pdo,$id,(int)$state['assigned_officer_id'],$input,$fields);
        check(liabilities_clear(json_decode(stage_context($pdo,$id)['details_json'],true),$number),'Verified correction retained unresolved liabilities or missing items.');
        rejected(fn()=>process_stage_decision($pdo,$id,(int)$state['assigned_officer_id'],$input,$fields),'Duplicate decision accepted.');
    }
    check(certificate_release_allowed($pdo,$fullRequest),'Complete liability-free workflow did not pass release.');check((int)$pdo->query('SELECT COUNT(*) FROM certificates WHERE clearance_request_id='.$fullRequest)->fetchColumn()===1,'Certificate not issued exactly once.');
    echo "PASS: all 11 sequential decisions, rejection/resubmission, prerequisite locks and certificate release\n";
    $transcript=transcript_for_student($pdo,$student,$fullRequest);
    check($transcript&&$transcript['result_source']==='CLEARANCE','Final clearance did not generate its clearance transcript.');
    $clearanceSnapshot=json_decode($transcript['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
    check($clearanceSnapshot['document_type']==='CLEARANCE_TRANSCRIPT'&&$clearanceSnapshot['cycle']==='2027/2028'&&array_column($clearanceSnapshot['approvals'],'step_number')===range(1,11),'Transcript has missing offices or uses the wrong clearance cycle.');
    check($clearanceSnapshot['clearance']['request_id']===$fullRequest&&$clearanceSnapshot['clearance']['status']==='COMPLETED'&&!empty($clearanceSnapshot['clearance']['completed_at']),'Transcript omitted clearance completion details.');
    check(hash_equals($transcript['snapshot_sha256'],hash('sha256',$transcript['snapshot_json'])),'Transcript hash is invalid.');
    check(!preg_match('/"[^"]*(?:marks|grade|gpa|credits|semester|module)[^"]*"\\s*:/i',$transcript['snapshot_json']),'Clearance transcript contains academic results.');
    foreach($clearanceSnapshot['approvals'] as $approval){
        $record=$pdo->query('SELECT a.officer_id,a.created_at,u.full_name FROM stage_actions a INNER JOIN users u ON u.id=a.officer_id WHERE a.stage_id='.(int)$approval['stage_id'].' ORDER BY a.id DESC LIMIT 1')->fetch();
        check($approval['status']==='APPROVED'&&$approval['officer_id']===(int)$record['officer_id']&&$approval['officer_name']===$record['full_name']&&$approval['approved_at']===$record['created_at'],'Transcript does not match the actual final approving officer/date.');
    }
    check(ensure_transcript($pdo,$student,$admin,$fullRequest)['id']===$transcript['id'],'Repeated transcript access issued another document.');
    rejected(fn()=>ensure_transcript($pdo,$student,$admin,$request),'Incomplete request issued a transcript.');
    echo "PASS: all eleven actual office approvals, clearance cycle, immutable snapshots and no marks/GPA/results tables\n";
    // Exercise real HTTP forms and file uploads against the isolated database.
    $pdo->prepare('UPDATE users SET force_password_change=1 WHERE id=?')->execute([$studentUser]);
    $cookie=__DIR__.'/feature-student.cookies';$files[]=$cookie;test_login('IRDP/ODICT/MA25/0001','Cedar ocean lantern 91',$cookie);
    [$code,$body]=http_call('/student/dashboard.php',[],$cookie);check($code===200 && !str_contains($body,'name="current_password"'),'Legacy forced flag blocked student dashboard or displayed password form.');
    [$code,$body]=http_call('/student/profile.php',[],$cookie);check($code===200 && str_contains($body,'auth/change_password.php'),'Optional profile password link missing.');
    [$code,$body]=http_call('/auth/change_password.php',[],$cookie);check($code===200 && str_contains($body,'name="current_password"'),'Optional password form unavailable.');
    echo "PASS: legacy forced flag allows student dashboard/profile and optional current-password form\n";
    $savedCycleId=$pdo->query('SELECT cycle_id FROM clearance_period WHERE id=1')->fetchColumn();
    $pdo->exec('UPDATE clearance_period SET cycle_id=(SELECT id FROM academic_cycles WHERE label="2027/2028") WHERE id=1');
    [$code,$completedDashboard]=http_call('/student/dashboard.php',[],$cookie);
    check($code===200&&str_contains($completedDashboard,'transcripts/download.php')&&!str_contains($completedDashboard,'Academic Results'),'Completed student lost transcript access or still sees Academic Results.');
    [$code,$transcriptPdf]=http_call('/transcripts/download.php',[],$cookie);
    check($code===200&&str_starts_with($transcriptPdf,'%PDF-')&&str_contains($transcriptPdf,'CLEARANCE TRANSCRIPT')&&str_contains($transcriptPdf,'OFFICE APPROVALS'),'Clearance transcript PDF is missing its title/approval table.');
    foreach(['GPA','Marks','Grade','Credits','ACADEMIC RESULTS','FICTIONAL MARKS'] as $removedLabel){check(!str_contains($transcriptPdf,$removedLabel),'Clearance PDF still contains '.$removedLabel);}
    preg_match_all('/\((.*?)\) Tj ET/',$transcriptPdf,$pdfTextMatches);
    $plainPdf=iconv('Windows-1252','UTF-8',str_replace(['\\(','\\)','\\\\'],['(',')','\\'],implode(' ',$pdfTextMatches[1])));
    foreach($clearanceSnapshot['approvals'] as $approval){
        check(str_contains($plainPdf,$approval['office'])&&str_contains($plainPdf,$approval['officer_name'])&&str_contains($plainPdf,substr($approval['approved_at'],0,10)),'Transcript PDF omitted office/officer/date: '.$approval['office']);
    }
    [$code,$sameTranscriptPdf]=http_call('/transcripts/download.php',[],$cookie);
    check($code===200&&str_starts_with($sameTranscriptPdf,'%PDF-')&&str_contains($sameTranscriptPdf,'OFFICE APPROVALS')&&transcript_for_student($pdo,$student,$fullRequest)['snapshot_json']===$transcript['snapshot_json'],'Repeated download changed the clearance snapshot.');
    [$code,$verification]=http_call('/transcripts/verify.php?token='.$transcript['verification_token']);
    check($code===200&&str_contains($verification,'DOCUMENT VERIFIED')&&str_contains($verification,'Clearance transcript')&&str_contains($verification,'Approved offices')&&!str_contains($verification,'fictional marks'),'Clearance transcript verification failed.');
    [$code,$invalidVerification]=http_call('/transcripts/verify.php?token=invalid');
    check($code===200&&str_contains($invalidVerification,'DOCUMENT INVALID'),'Unknown transcript token was accepted.');
    [$code]=http_call('/student/results.php',[],$cookie);check($code===302,'Removed student results route is still active.');
    $pdo->prepare('UPDATE clearance_period SET cycle_id=? WHERE id=1')->execute([$savedCycleId]);
    echo "PASS: clearance PDF download, all offices/officers/dates, no marks/GPA, stable snapshot and verification\n";
    [$code,$body]=http_call('/auth/change_password.php',['csrf_token'=>token_from($body),'current_password'=>'Cedar ocean lantern 91','password'=>'Abc1234','confirm_password'=>'Abc1234'],$cookie,true);
    check($code===200 && str_contains($body,'Password must contain at least 8 characters.'),'Server accepted short optional password or omitted field error.');
    [$code,$body]=http_call('/auth/change_password.php',['csrf_token'=>token_from($body),'current_password'=>'wrong','password'=>'Garden sea breeze 82','confirm_password'=>'Garden sea breeze 82'],$cookie,true);
    check($code===200 && str_contains($body,'Current password is incorrect.'),'Optional change bypassed current password.');
    [$code,$body]=http_call('/admin/import.php',[],$cookie);check($code===403,'Student accessed Admin import.');[$code]=http_call('/auth/change_password.php',['password'=>'abc'],$cookie,true);check($code===419,'CSRF bypass accepted.');[$code]=http_call('/storage/private/test.txt',[],$cookie);check($code===403,'Private storage exposed.');
    [$code,$profile]=http_call('/student/profile.php',[],$cookie);check($code===200,'Profile page failed.');$csrf=token_from($profile);
    check(str_contains($profile,'href="#main-content"')&&str_contains($profile,'id="accessibilityTextSize"')&&str_contains($profile,'aria-controls="sidebar"')&&str_contains($profile,'aria-expanded="false"'),'Shared keyboard navigation or display controls missing.');
    $png=__DIR__.'/fictional-profile.png';$files[]=$png;file_put_contents($png,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAIAAAAlC+aJAAAAC0lEQVR4nGNgGAWjAABAAAEAuV8pAAAAAElFTkSuQmCC'));
    // Generate a valid fictional portrait-shaped image with the Windows built-in image library.
    $imageScript=__DIR__.'/fixture_image.ps1';$files[]=$imageScript;file_put_contents($imageScript,'param([string]$Path)'.PHP_EOL.'Add-Type -AssemblyName System.Drawing'.PHP_EOL.'$bitmap=New-Object System.Drawing.Bitmap 96,128'.PHP_EOL.'$graphics=[System.Drawing.Graphics]::FromImage($bitmap)'.PHP_EOL.'$graphics.Clear([System.Drawing.Color]::LightGreen)'.PHP_EOL.'$bitmap.Save($Path,[System.Drawing.Imaging.ImageFormat]::Png)'.PHP_EOL.'$graphics.Dispose();$bitmap.Dispose()');
    $proc=proc_open(['powershell.exe','-NoProfile','-NonInteractive','-ExecutionPolicy','Bypass','-File',$imageScript,'-Path',$png],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$p);fclose($p[0]);$imageOutput=stream_get_contents($p[1]);$imageError=stream_get_contents($p[2]);fclose($p[1]);fclose($p[2]);check(proc_close($proc)===0,'Fixture image generation failed: '.$imageError);
    [$code,$body]=http_call('/student/profile.php',['csrf_token'=>$csrf,'mode'=>'upload','picture'=>new CURLFile($png,'image/png','fictional.png')],$cookie,true);check($code===302,'Valid profile upload failed: '.strip_tags($body));
    $profileName=$pdo->query('SELECT profile_file FROM students WHERE id='.$student)->fetchColumn();$files[]=private_path('profiles',$profileName);check(getimagesize(private_path('profiles',$profileName))[0]===256,'Profile not resized.');
    [$code,$imageBody]=http_call('/files/profile.php?student='.$student.'&v='.$profileName,[],$cookie);check($code===200&&$imageBody===file_get_contents(private_path('profiles',$profileName)),'Saved portrait endpoint did not return the uploaded image.');
    $portraitTag='"'.hash('sha256',$student.'|'.$profileName).'"';
    [$code,$cachedBody]=http_call('/files/profile.php?student='.$student,[],$cookie,false,['If-None-Match: '.$portraitTag]);check($code===304&&$cachedBody==='','Unchanged portrait did not revalidate without retransmission.');
    [$code,$dashboard]=http_call('/student/dashboard.php',[],$cookie);check($code===200&&substr_count($dashboard,'&amp;v='.$profileName)>=2,'Dashboard and account header did not show the saved portrait.');
    [$code,$body]=http_call('/student/profile.php',[],$cookie);[$code]=http_call('/student/profile.php',['csrf_token'=>token_from($body),'mode'=>'upload','picture'=>new CURLFile($png,'image/png','fictional.png')],$cookie,true);check($code===302&&!is_file(private_path('profiles',$profileName)),'Profile replacement did not remove old file.');$profileName=$pdo->query('SELECT profile_file FROM students WHERE id='.$student)->fetchColumn();$files[]=private_path('profiles',$profileName);
    [$code,$replacedBody]=http_call('/files/profile.php?student='.$student,[],$cookie,false,['If-None-Match: '.$portraitTag]);check($code===200&&$replacedBody===file_get_contents(private_path('profiles',$profileName)),'Portrait replacement returned the stale cached image.');
    $bad=__DIR__.'/invalid-upload.png';$files[]=$bad;file_put_contents($bad,'<?php echo "invalid";');[$code,$body]=http_call('/student/profile.php',[],$cookie);[$code,$body]=http_call('/student/profile.php',['csrf_token'=>token_from($body),'mode'=>'upload','picture'=>new CURLFile($bad,'image/png','invalid.png')],$cookie,true);check($code===200&&str_contains($body,'content type'),'Invalid profile image accepted.');
    [$code,$body]=http_call('/student/profile.php',[],$cookie);[$code]=http_call('/student/profile.php',['csrf_token'=>token_from($body),'mode'=>'remove'],$cookie,true);check($code===302&&!is_file(private_path('profiles',$profileName)),'Profile removal failed.');
    echo "PASS: HTTP CSRF, direct authorization, private storage and profile upload/resize/replace/remove\n";
    // Latest student request: exercise reject -> visible upload -> office review -> next stage.
    [$request,$stage]=fixture_stage($pdo,$student,$office,$step,$primary);
    $nextWorkflow=$pdo->query('SELECT * FROM workflow_steps WHERE step_number=2')->fetch();
    $nextReviewer=find_office_reviewer($pdo,(int)$nextWorkflow['office_id']);
    $pdo->prepare('INSERT INTO clearance_stages(clearance_request_id,workflow_step_id,office_id,assigned_officer_id,original_officer_id,status) VALUES (?,?,?,?,?,"LOCKED")')->execute([$request,$nextWorkflow['id'],$nextWorkflow['office_id'],$nextReviewer,$nextReviewer]);$nextStage=(int)$pdo->lastInsertId();
    $officerCookie=__DIR__.'/feature-officer.cookies';$files[]=$officerCookie;test_login('LIB001','Mrema@2026',$officerCookie);
    [$code,$review]=http_call('/officer/review.php?stage='.$stage,[],$officerCookie);check($code===200,'Office review unavailable.');
    $pdo->prepare('UPDATE offices SET active=0 WHERE id=?')->execute([$office]);
    [$code,$unauthorizedReview]=http_call('/officer/review.php?stage='.$stage,[],$officerCookie);
    $pdo->prepare('UPDATE offices SET active=1 WHERE id=?')->execute([$office]);
    check($code===403&&!str_contains($unauthorizedReview,'IRDP/ODICT/MA25/0001'),'Review form exposed student details without active office authority.');
    foreach(['-1','1e3','1.234','1000000000000',"0\0"] as $badAmount) {
        $beforeReview=stage_context($pdo,$stage);
        [$code,$badReview]=http_call('/officer/review.php?stage='.$stage,['csrf_token'=>token_from($review),'stage_id'=>(string)$stage,'review_cycle'=>'1','action'=>'APPROVED','amount'=>$badAmount,'comments'=>'','corrective_instructions'=>''],$officerCookie,true);
        check($code===200&&str_contains($badReview,'amount-error')&&stage_context($pdo,$stage)===$beforeReview,'Malformed money amount changed a stage.');
    }
    [$code]=http_call('/officer/review.php?stage=invalid',[],$officerCookie);check($code===400,'Malformed office stage ID caused a server error.');
    [$code,$body]=http_call('/officer/review.php?stage='.$stage,['csrf_token'=>token_from($review),'stage_id'=>(string)$stage,'review_cycle'=>'1','action'=>'REJECTED','comments'=>'Missing return receipt','corrective_instructions'=>'Attach the returned-item receipt','amount'=>'500000'],$officerCookie,true);check($code===302&&stage_context($pdo,$stage)['status']==='REJECTED','Office rejection failed.');
    [$code,$dashboard]=http_call('/student/dashboard.php',[],$cookie);check($code===200&&str_contains($dashboard,'Respond / Upload Evidence'),'Student dashboard omitted rejection action.');
    [$code,$status]=http_call('/student/status.php',[],$cookie);check($code===200&&str_contains($status,'name="evidence[]"')&&str_contains($status,'Submit evidence to office')&&str_contains($status,'Attach the returned-item receipt'),'My Clearance omitted the visible evidence form or instructions.');
    [$code,$body]=http_call('/student/resubmit.php?stage='.$stage,[],$cookie);$csrf=token_from($body);
    $beforeResponse=stage_context($pdo,$stage);
    [$code,$invalidResponse]=http_call('/student/resubmit.php?stage='.$stage,['csrf_token'=>$csrf,'stage_id'=>(string)$stage,'mode'=>'resubmit','response'=>'   '],$cookie,true);
    check($code===200&&str_contains($invalidResponse,'response-error')&&stage_context($pdo,$stage)===$beforeResponse,'Blank student response reopened clearance.');
    $pdf=__DIR__.'/fictional-evidence.pdf';$files[]=$pdf;file_put_contents($pdf,"%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n");
    [$code,$body]=http_call('/student/resubmit.php?stage='.$stage,['csrf_token'=>$csrf,'stage_id'=>(string)$stage,'mode'=>'resubmit','response'=>'Fictional receipt attached','evidence[0]'=>new CURLFile($pdf,'application/pdf','receipt.pdf')],$cookie,true);check($code===302,'Evidence resubmission failed: '.strip_tags($body));$s=$pdo->query('SELECT * FROM stage_evidence WHERE stage_id='.$stage.' ORDER BY id DESC LIMIT 1');$file=$s->fetch();check((bool)$file,'Evidence not recorded.');$files[]=private_path('evidence',$file['stored_name']);
    [$code,$body]=http_call('/files/evidence.php?id='.$file['id'],[],$cookie);check($code===200&&str_starts_with($body,'%PDF-'),'Owner download failed.');
    check(stage_context($pdo,$stage)['status']==='PENDING'&&stage_context($pdo,$nextStage)['status']==='LOCKED','Evidence submission skipped office approval.');
    [$code,$status]=http_call('/student/status.php',[],$cookie);check($code===200&&str_contains($status,'Awaiting office review')&&!str_contains($status,'name="evidence[]"'),'Student status did not reflect submitted evidence.');
    [$code,$queue]=http_call('/officer/dashboard.php',[],$officerCookie);check($code===200&&str_contains($queue,'Resubmitted: review student evidence'),'Responsible office did not see resubmission.');
    [$code,$review]=http_call('/officer/review.php?stage='.$stage,[],$officerCookie);check($code===200&&str_contains($review,'Student resubmission')&&str_contains($review,'Fictional receipt attached')&&str_contains($review,'receipt.pdf')&&str_contains($review,'value="APPROVED_EVIDENCE"'),'Office did not receive the student response and evidence approval action.');
    [$code,$body]=http_call('/files/evidence.php?id='.$file['id'],[],$officerCookie);check($code===200&&str_starts_with($body,'%PDF-'),'Responsible office cannot open evidence.');
    $notification=$pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND title="Clearance stage resubmitted"');$notification->execute([$primary]);check((int)$notification->fetchColumn()>0,'Responsible office was not notified.');
    // The approval form still contains the old 500,000 finding. Evidence
    // acceptance must resolve it and unlock the next stage in one transaction.
    [$code,$body]=http_call('/officer/review.php?stage='.$stage,['csrf_token'=>token_from($review),'stage_id'=>(string)$stage,'review_cycle'=>(string)stage_context($pdo,$stage)['review_cycle'],'action'=>'APPROVED_EVIDENCE','comments'=>'Missing return receipt','corrective_instructions'=>'Attach the returned-item receipt','amount'=>'500000'],$officerCookie,true);check($code===302&&stage_context($pdo,$stage)['status']==='APPROVED'&&stage_context($pdo,$nextStage)['status']==='PENDING','Office evidence approval did not unlock the next stage.');
    $approvedStage=stage_context($pdo,$stage);check(json_decode($approvedStage['details_json'],true)['amount']==='0'&&$approvedStage['comments']==='Evidence verified; office requirements resolved.'&&$approvedStage['corrective_instructions']==='','Evidence approval retained unresolved office findings.');
    $originalDetails=$pdo->query('SELECT details_json FROM stage_actions WHERE stage_id='.$stage.' AND action="REJECTED" ORDER BY id LIMIT 1')->fetchColumn();check(json_decode($originalDetails,true)['amount']==='500000','Evidence approval erased the original rejection finding.');
    [$code,$history]=http_call('/student/resubmit.php?stage='.$stage,[],$cookie);check($code===200&&str_contains($history,'Missing return receipt')&&str_contains($history,'receipt.pdf'),'Approval lost rejection or evidence history.');
    echo "PASS: HTTP rejection, visible student evidence form, office review and next-stage continuation\n";
    // Reproduce the live failure: an explanation-only resubmission retains
    // the previous 20,000 finding until the officer verifies the correction.
    [$responseRequest,$responseStage]=fixture_stage($pdo,$student,$office,$step,$primary);
    $pdo->prepare('INSERT INTO clearance_stages(clearance_request_id,workflow_step_id,office_id,assigned_officer_id,original_officer_id,status) VALUES (?,?,?,?,?,"LOCKED")')->execute([$responseRequest,$nextWorkflow['id'],$nextWorkflow['office_id'],$backup,$backup]);$responseNext=(int)$pdo->lastInsertId();
    process_stage_decision($pdo,$responseStage,$primary,['action'=>'REJECTED','review_cycle'=>'1','amount'=>'20000','comments'=>'Payment pending','corrective_instructions'=>'Verify payment with the office'],review_fields(1));
    [$code,$responseForm]=http_call('/student/resubmit.php?stage='.$responseStage,[],$cookie);
    [$code,$body]=http_call('/student/resubmit.php?stage='.$responseStage,['csrf_token'=>token_from($responseForm),'stage_id'=>(string)$responseStage,'mode'=>'resubmit','response'=>'Payment verified with the office.'], $cookie,true);
    check($code===302&&stage_context($pdo,$responseNext)['status']==='LOCKED','Response-only submission bypassed office review.');
    [$code,$responseReview]=http_call('/officer/review.php?stage='.$responseStage,[],$officerCookie);
    check($code===200&&str_contains($responseReview,'value="APPROVED_CORRECTION"')&&str_contains($responseReview,'Approve correction and continue'),'Officer cannot approve a verified explanation-only correction.');
    $approval=['csrf_token'=>token_from($responseReview),'stage_id'=>(string)$responseStage,'review_cycle'=>'2','action'=>'APPROVED_CORRECTION','amount'=>'20000','comments'=>'Payment pending','corrective_instructions'=>'Verify payment with the office'];
    rejected(fn()=>process_stage_decision($pdo,$responseStage,$backup,$approval,review_fields(1)),'Unassigned officer approved a correction.');
    rejected(fn()=>process_stage_decision($pdo,$responseStage,$primary,array_replace($approval,['review_cycle'=>'1']),review_fields(1)),'Stale correction approval succeeded.');
    $pdo->prepare('UPDATE review_cycles SET student_response="" WHERE stage_id=? AND cycle_number=2')->execute([$responseStage]);
    rejected(fn()=>process_stage_decision($pdo,$responseStage,$primary,$approval,review_fields(1)),'Correction approval accepted without a current response or files.');
    $pdo->prepare('UPDATE review_cycles SET student_response="Payment verified with the office." WHERE stage_id=? AND cycle_number=2')->execute([$responseStage]);
    [$code,$body]=http_call('/officer/review.php?stage='.$responseStage,$approval,$officerCookie,true);
    check($code===302&&stage_context($pdo,$responseStage)['status']==='APPROVED'&&stage_context($pdo,$responseNext)['status']==='PENDING'&&stage_context($pdo,$responseNext)['request_status']==='IN_PROGRESS','Verified response-only correction did not allow continuation.');
    $corrected=stage_context($pdo,$responseStage);
    check(json_decode($corrected['details_json'],true)['amount']==='0'&&$corrected['corrective_instructions']==='','Verified response-only correction retained the previous debt.');
    $oldFinding=$pdo->query('SELECT details_json FROM stage_actions WHERE stage_id='.$responseStage.' AND action="REJECTED"')->fetchColumn();
    check(json_decode($oldFinding,true)['amount']==='20000','Verified response-only correction erased rejection history.');
    [$code,$continued]=http_call('/student/status.php',[],$cookie);
    check($code===200&&str_contains($continued,'id="stage-'.$responseStage.'"')&&str_contains($continued,'id="stage-'.$responseNext.'"')&&str_contains($continued,'APPROVED')&&str_contains($continued,'Pending'),'Student cannot track the next stage after correction approval.');
    echo "PASS: explanation-only rejection correction, office approval, preserved history, stale/unauthorized guards and student continuation\n";
    $wrongCookie=__DIR__.'/feature-wrong.cookies';$files[]=$wrongCookie;test_login('IRDP/BTCRP/MA25/0002','MFINANGA0002',$wrongCookie);[$code]=http_call('/files/evidence.php?id='.$file['id'],[],$wrongCookie);check($code===403,'Unauthorized evidence download allowed.');
    [$code]=http_call('/files/profile.php?student='.$student,[],$wrongCookie);check($code===403,'Unauthorized profile view allowed.');
    [$code]=http_call('/files/profile.php?student='.$student,[],$wrongCookie,false,['If-None-Match: "'.hash('sha256',$student.'|default').'"']);check($code===403,'Portrait cache bypassed authorization.');
    [$code]=http_call('/transcripts/download.php?student='.$student,[],$wrongCookie);check($code===409,'Uncleared student bypassed transcript gating with another student ID.');
    echo "PASS: HTTP evidence upload, cycle association, owner download and unauthorized access\n";
    $adminCookie=__DIR__.'/feature-admin.cookies';$files[]=$adminCookie;test_login('admin','Admin@IRDP2026',$adminCookie);

    // Submit malformed inputs directly, bypassing browser constraints.
    [$code,$validationAdminPage]=http_call('/admin/users.php',[],$adminCookie);
    check($code===200,'Admin validation forms unavailable.');$adminCsrf=token_from($validationAdminPage);
    $officerRole=(int)$pdo->query('SELECT id FROM roles WHERE name="OFFICER"')->fetchColumn();
    $studentRole=(int)$pdo->query('SELECT id FROM roles WHERE name="STUDENT"')->fetchColumn();
    $accountInput=['csrf_token'=>$adminCsrf,'full_name'=>'Anne Example','username'=>'AUDIT001','email'=>'audit@example.invalid','password'=>'Garden cloud meadow 82','confirm_password'=>'Garden cloud meadow 82','role_id'=>(string)$officerRole,'office_id'=>(string)$office,'department_id'=>'','programme'=>'','academic_year'=>''];
    $studentInput=array_replace($accountInput,['role_id'=>(string)$studentRole,'username'=>'IRDP/ODICT/MA26/9901','programme'=>'ODICT','academic_year'=>'2026/2027','department_id'=>(string)$department]);
    $accountCount=(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    foreach([
        [$accountInput,['full_name'=>''],'full_name'],
        [$accountInput,['full_name'=>'Anne <script>'],'full_name'],
        [$accountInput,['full_name'=>str_repeat('界',161)],'full_name'],
        [$accountInput,['full_name'=>"Anne\0"],'full_name'],
        [$accountInput,['full_name'=>"\xC3\x28"],'full_name'],
        [$accountInput,['username'=>'bad username'],'username'],
        [$accountInput,['username'=>'LIB001'],'username'],
        [$accountInput,['email'=>'bad-email'],'email'],
        [$accountInput,['password'=>'Abc1234','confirm_password'=>'Abc1234'],'password'],
        [$accountInput,['confirm_password'=>'Different garden 82'],'confirm_password'],
        [$accountInput,['role_id'=>'999999'],'role_id'],
        [$accountInput,['office_id'=>'999999'],'office_id'],
        [$accountInput,['office_id'=>''],'office_id'],
        [$accountInput,['department_id'=>'999999'],'department_id'],
        [$studentInput,['username'=>'bad-registration'],'username'],
        [$studentInput,['programme'=>'BTCRP'],'programme'],
        [$studentInput,['academic_year'=>'2098/2099'],'academic_year'],
        [$studentInput,['department_id'=>''],'department_id'],
    ] as [$base,$changes,$field]) {
        [$code,$validationResponse]=http_call('/admin/users.php',array_replace($base,$changes),$adminCookie,true);
        check($code===200&&str_contains($validationResponse,$field.'-error')&&(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()===$accountCount,'Account accepted invalid '.$field.' or omitted its field error.');
    }
    $departmentOffice=(int)$pdo->query('SELECT office_id FROM workflow_steps WHERE step_number=7')->fetchColumn();
    [$code,$validationResponse]=http_call('/admin/users.php',array_replace($accountInput,['office_id'=>(string)$departmentOffice]),$adminCookie,true);
    check($code===200&&str_contains($validationResponse,'department_id-error'),'Departmental officer was accepted without a department.');
    $pdo->prepare('UPDATE offices SET active=0 WHERE id=?')->execute([$office]);
    [$code,$validationResponse]=http_call('/admin/users.php',$accountInput,$adminCookie,true);
    $pdo->prepare('UPDATE offices SET active=1 WHERE id=?')->execute([$office]);
    check($code===200&&str_contains($validationResponse,'office_id-error'),'Inactive account office was accepted.');

    $existingOffice=(string)$pdo->query('SELECT name FROM offices WHERE id='.$office)->fetchColumn();
    $existingDepartment=(string)$pdo->query('SELECT name FROM departments WHERE id='.$department)->fetchColumn();
    $referenceCounts=[];foreach(['offices','departments','programmes'] as $table){$referenceCounts[$table]=(int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();}
    foreach([
        ['/admin/offices.php',['name'=>'','description'=>''],'name'],
        ['/admin/offices.php',['name'=>$existingOffice,'description'=>''],'name'],
        ['/admin/offices.php',['name'=>'Audit office','description'=>str_repeat('a',256)],'description'],
        ['/admin/departments.php',['code'=>'lower','name'=>'Audit department'],'code'],
        ['/admin/departments.php',['code'=>'EPM','name'=>'Audit department'],'code'],
        ['/admin/departments.php',['code'=>'AUDIT','name'=>$existingDepartment],'name'],
        ['/admin/programmes.php',['code'=>'bad code','name'=>'Audit programme'],'code'],
        ['/admin/programmes.php',['code'=>'ODICT','name'=>'Audit programme'],'code'],
        ['/admin/programmes.php',['code'=>'AUDIT','name'=>str_repeat('a',121)],'name'],
        ['/admin/workflow.php',['step_number'=>'12','title'=>'Audit step','office_id'=>(string)$office],'step_number'],
        ['/admin/workflow.php',['step_number'=>'1','title'=>'Audit step','office_id'=>'999999'],'office_id'],
        ['/admin/workflow.php',['step_number'=>'1','title'=>str_repeat('a',181),'office_id'=>(string)$office],'title'],
    ] as [$path,$values,$field]) {
        [$code,$validationResponse]=http_call($path,['csrf_token'=>$adminCsrf]+$values,$adminCookie,true);
        check($code===200&&str_contains($validationResponse,$field.'-error'),'Invalid reference/workflow field was accepted: '.$path.' '.$field);
    }
    foreach($referenceCounts as $table=>$count){check((int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()===$count,'Invalid form created a reference record.');}

    $beforePeriod=$pdo->query('SELECT * FROM clearance_period WHERE id=1')->fetch();
    $periodInput=['csrf_token'=>$adminCsrf,'mode'=>'SCHEDULED','remarks'=>'Validation check','cycle_id'=>(string)$beforePeriod['cycle_id'],'new_cycle'=>'','opens_at'=>'2031-01-01T10:00','closes_at'=>'2031-01-02T10:00'];
    foreach([
        [['mode'=>'UNKNOWN'],'mode'],[['cycle_id'=>'999999'],'cycle_id'],[['new_cycle'=>'2026/2028'],'new_cycle'],
        [['opens_at'=>'2026-02-30T10:00'],'opens_at'],[['closes_at'=>'2031-01-01T09:00'],'closes_at'],
        [['opens_at'=>''],'closes_at'],[['remarks'=>''],'remarks'],
    ] as [$changes,$field]) {
        [$code,$validationResponse]=http_call('/admin/clearance_period.php',array_replace($periodInput,$changes),$adminCookie,true);
        check($code===200&&str_contains($validationResponse,$field.'-error')&&$pdo->query('SELECT * FROM clearance_period WHERE id=1')->fetch()===$beforePeriod,'Invalid period changed the schedule or omitted a field error.');
    }
    [$code,$validationResponse]=http_call('/admin/transcripts.php',['csrf_token'=>$adminCsrf,'transcript_id'=>'invalid','reason'=>'Audit rejection'],$adminCookie,true);
    check($code===200&&str_contains($validationResponse,'Choose a valid record.'),'Transcript validation failed to handle invalid input.');
    foreach([
        '/certificates/certificate.php?id=bad','/certificates/download.php?id=0',
        '/transcripts/download.php?student=invalid','/admin/recovery.php?page=1abc','/admin/recovery.php?page=0',
        '/admin/recovery.php?page=100001','/admin/recovery.php?status=UNKNOWN',
        '/reports/generate.php?type=custom','/reports/generate.php?type=custom&from=2026-02-30&to=2026-10-07',
        '/reports/generate.php?type=custom&from=2026-10-08&to=2026-10-07',
        '/reports/generate.php?type=custom&from=2026-10-07%00&to=2026-10-07',
        '/reports/generate.php?type=UNKNOWN','/admin/users.php?field[]=nested',
    ] as $path) {
        [$code]=http_call($path,[],$adminCookie);check($code===400,'Malformed URL was not rejected: '.$path.' HTTP '.$code);
    }
    [$code]=http_call('/admin/users.php',['csrf_token'=>$adminCsrf,'full_name[]'=>'Anne'],$adminCookie,true);
    check($code===400,'Nested POST values were accepted.');
    [$code]=http_call('/admin/users.php',['full_name'=>'Anne'],$adminCookie,true);check($code===419,'State change without CSRF was accepted.');
    [$code,$validReport]=http_call('/reports/generate.php?type=custom&from=2026-10-01&to=2026-10-07',[],$adminCookie);
    check($code===200&&str_starts_with($validReport,'%PDF-'),'Valid custom report was rejected.');

    $publicCookie=__DIR__.'/feature-validation-public.cookies';$files[]=$publicCookie;
    [$code,$certificateForm]=http_call('/certificates/verify.php',[],$publicCookie);
    $issuedCertificate=$pdo->query('SELECT * FROM certificates WHERE clearance_request_id='.$fullRequest)->fetch();
    $certificateInput=['csrf_token'=>token_from($certificateForm),'certificate_number'=>$issuedCertificate['certificate_number'],'verification_code'=>$issuedCertificate['verification_code']];
    [$code,$certificateResponse]=http_call('/certificates/verify.php',$certificateInput,$publicCookie,true);
    check($code===200&&str_contains($certificateResponse,'Certificate verified.'),'Valid certificate failed verification.');
    $savedDetails=$pdo->query('SELECT details_json FROM clearance_stages WHERE id='.$fullStages[1])->fetchColumn();
    foreach(['{}','{"amount":"junk"}'] as $corruptDetails) {
        $pdo->prepare('UPDATE clearance_stages SET details_json=? WHERE id=?')->execute([$corruptDetails,$fullStages[1]]);
        [$code,$certificateResponse]=http_call('/certificates/verify.php',$certificateInput,$publicCookie,true);
        check($code===200&&str_contains($certificateResponse,'could not be verified')&&!str_contains($certificateResponse,'Certificate verified.'),'Incomplete/malformed approval displayed certificate verification success.');
    }
    $pdo->prepare('UPDATE clearance_stages SET details_json=? WHERE id=?')->execute([$savedDetails,$fullStages[1]]);
    [$code,$certificateResponse]=http_call('/certificates/verify.php',array_replace($certificateInput,['verification_code'=>'not-a-code']),$publicCookie,true);
    check($code===200&&str_contains($certificateResponse,'verification_code-error')&&!str_contains($certificateResponse,'Certificate verified.'),'Malformed verification code was accepted.');
    [$code,$publicLogin]=http_call('/auth/login.php',[],$publicCookie);
    [$code,$invalidLogin]=http_call('/auth/login.php',['csrf_token'=>token_from($publicLogin),'username'=>"' OR 1=1 --",'password'=>'Garden cloud meadow 82'],$publicCookie,true);
    check($code===200&&str_contains($invalidLogin,'Invalid username or password.'),'SQL-like username bypassed login.');
    [$code,$invalidLogin]=http_call('/auth/login.php',['csrf_token'=>token_from($publicLogin),'username'=>'LIB001','password'=>"Garden\0cloud"],$publicCookie,true);
    check($code===200&&str_contains($invalidLogin,'valid current password'),'Control characters were accepted in login passwords.');
    echo "PASS: HTTP invalid/Unicode/nested inputs, duplicate/active references, period and report ranges, strict IDs, money and certificate verification guards\n";
    [$assignedRequest,$assignedStage]=fixture_stage($pdo,$student,$office,$step,$primary);
    $pdo->prepare('INSERT INTO clearance_stages(clearance_request_id,workflow_step_id,office_id,assigned_officer_id,original_officer_id,status) VALUES (?,?,?,?,?,"LOCKED")')->execute([$assignedRequest,$nextWorkflow['id'],$nextWorkflow['office_id'],$backup,$backup]);$assignedNext=(int)$pdo->lastInsertId();
    [$code,$adminDashboard]=http_call('/admin/dashboard.php',[],$adminCookie);
    check($code===200,'Admin dashboard unavailable.');
    foreach(['admin/emergency.php','admin/continuity.php','admin/settings.php','supervisor/dashboard.php','admin/academic_results.php','Academic Results','Escalations','Emergency','Settings and Authority'] as $removed) {
        check(!str_contains($adminDashboard,$removed),'Admin dashboard still exposes '.$removed);
    }
    $snapshot=stage_context($pdo,$assignedStage);
    $settingsBefore=$pdo->query('SELECT * FROM app_settings ORDER BY name')->fetchAll();
    $officeAccessBefore=$pdo->query('SELECT * FROM user_offices ORDER BY user_id,office_id')->fetchAll();
    foreach(['/admin/emergency.php','/admin/continuity.php','/admin/settings.php','/supervisor/dashboard.php','/admin/academic_results.php'] as $retiredPath) {
        [$code]=http_call($retiredPath,[],$adminCookie);check($code===302,'Retired feature page is still active: '.$retiredPath);
        [$code]=http_call($retiredPath,['csrf_token'=>token_from($adminDashboard),'stage_id'=>(string)$assignedStage,'mode'=>'assign','reviewer_id'=>(string)$backup,'reason'=>'Old action','action'=>'grant','office_id'=>(string)$office],$adminCookie,true);
        check($code===302,'Old feature POST was not retired: '.$retiredPath);
        check(stage_context($pdo,$assignedStage)===$snapshot,'Old feature action changed a clearance stage.');
    }
    check($pdo->query('SELECT * FROM app_settings ORDER BY name')->fetchAll()===$settingsBefore,'Retired settings action changed settings.');
    check($pdo->query('SELECT * FROM user_offices ORDER BY user_id,office_id')->fetchAll()===$officeAccessBefore,'Retired authority action changed office access.');
    [$code]=http_call('/admin/settings.php',[],$wrongCookie);check($code===403,'Student accessed a retired staff route.');
    $legacyCookie=__DIR__.'/feature-legacy-supervisor.cookies';$files[]=$legacyCookie;
    $pdo->prepare('UPDATE users SET force_password_change=0 WHERE id=?')->execute([$supervisor]);
    test_login('TESTSUP','Cloud lantern meadow 47',$legacyCookie);
    [$code,$legacyDashboard]=http_call('/officer/dashboard.php',[],$legacyCookie);check($code===200,'Legacy reviewer dashboard unavailable.');
    check(role_dashboard('SUPERVISOR')==='officer/dashboard.php','Legacy reviewer login still points to the removed dashboard.');
    foreach(['admin/continuity.php','supervisor/dashboard.php','Escalations','Priority Cases','Assigned by Admin / Supervisor'] as $removed) {
        check(!str_contains($legacyDashboard,$removed),'Reviewer dashboard still exposes '.$removed);
    }
    [$code,$queue]=http_call('/officer/dashboard.php',[],$officerCookie);check($code===200&&str_contains($queue,'officer/review.php?stage='.$assignedStage.'"'),'Normal office queue lost its assigned task.');
    [$code,$normalReview]=http_call('/officer/review.php?stage='.$assignedStage,[],$officerCookie);check($code===200,'Normal officer cannot review after feature removal.');
    [$code,$body]=http_call('/officer/review.php?stage='.$assignedStage,['csrf_token'=>token_from($normalReview),'stage_id'=>(string)$assignedStage,'review_cycle'=>'1','action'=>'APPROVED','amount'=>'0','comments'=>'Office review complete','corrective_instructions'=>''],$officerCookie,true);
    check($code===302&&stage_context($pdo,$assignedNext)['status']==='PENDING','Normal approval did not continue clearance after feature removal.');
    echo "PASS: removed feature links/routes/actions, legacy reviewer login and normal office clearance continuation\n";
    [$code,$template]=http_call('/admin/import_template.php?format=xlsx',[],$adminCookie);check($code===200&&str_starts_with($template,'PK'),'XLSX template generation failed.');$xlsx=__DIR__.'/fictional-import.xlsx';$files[]=$xlsx;file_put_contents($xlsx,$template);$parsed=xlsx_rows($xlsx);check($parsed[0]===STUDENT_IMPORT_HEADERS&&$parsed[1][1]==='IRDP/ODICT/MA26/9001','XLSX text registration formatting not preserved.');
    [$code,$page]=http_call('/admin/import.php',[],$adminCookie);[$code,$page]=http_call('/admin/import.php',['csrf_token'=>token_from($page),'mode'=>'preview','file'=>new CURLFile($xlsx,'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','students.xlsx')],$adminCookie,true);check($code===200&&str_contains($page,'IRDP/ODICT/MA26/9001')&&str_contains($page,'Import valid rows'),'XLSX preview endpoint failed.');
    echo "PASS: XLSX template, safe parsing and real import preview\n";
    // Common XLSX writers use prefixed XML roots and may include an empty
    // shared-string table. Both are valid even though SimpleXML casts them false.
    $namespacedZip=__DIR__.'/fictional-namespaced.zip';$files[]=$namespacedZip;copy($xlsx,$namespacedZip);
    $archive=new PharData($namespacedZip);$demoRows=[STUDENT_IMPORT_HEADERS];
    for($i=1;$i<=50;$i++) {
        $demoRows[]=['Demo Student '.chr(65+intdiv($i-1,26)).chr(65+(($i-1)%26)),'IRDP/ODICT/MA26/'.(9500+$i),'ODICT','EPM','2026/2027',''];
    }
    $sheet='<?xml version="1.0" encoding="UTF-8"?><x:worksheet xmlns:x="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><x:sheetData>';
    foreach($demoRows as $rowIndex=>$row) {
        $sheet.='<x:row r="'.($rowIndex+1).'">';
        foreach($row as $col=>$value) {
            $sheet.='<x:c r="'.chr(65+$col).($rowIndex+1).'" t="inlineStr"><x:is><x:t>'.htmlspecialchars($value,ENT_XML1|ENT_QUOTES,'UTF-8').'</x:t></x:is></x:c>';
        }
        $sheet.='</x:row>';
    }
    $sheet.='</x:sheetData></x:worksheet>';
    $archive['xl/worksheets/sheet1.xml']=$sheet;
    $archive['xl/sharedStrings.xml']='<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>';
    $archive['[Content_Types].xml']=str_replace('</Types>','<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>',$archive['[Content_Types].xml']->getContent());
    $archive['xl/_rels/workbook.xml.rels']=str_replace('</Relationships>','<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/></Relationships>',$archive['xl/_rels/workbook.xml.rels']->getContent());
    unset($archive);$namespacedXlsx=__DIR__.'/fictional-namespaced.xlsx';$files[]=$namespacedXlsx;copy($namespacedZip,$namespacedXlsx);
    check(xlsx_rows($namespacedXlsx)===$demoRows,'Namespaced workbook with empty shared strings did not preserve all 50 students.');
    [$code,$importPage]=http_call('/admin/import.php',[],$adminCookie);
    [$code,$importPreview]=http_call('/admin/import.php',['csrf_token'=>token_from($importPage),'mode'=>'preview','file'=>new CURLFile($namespacedXlsx,'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','demo-students.xlsx')],$adminCookie,true);
    check($code===200&&str_contains($importPreview,'IRDP/ODICT/MA26/9550')&&substr_count($importPreview,'>Valid</td>')===50,'50-student XLSX upload preview failed.');
    check(preg_match('/name="nonce" value="([a-f0-9]+)"/',$importPreview,$nonce)===1,'Import confirmation nonce missing.');
    [$code,$importResult]=http_call('/admin/import.php',['csrf_token'=>token_from($importPreview),'mode'=>'commit','nonce'=>$nonce[1],'confirm'=>'1'],$adminCookie,true);
    check($code===200&&str_contains($importResult,'Created: 50')&&(int)$pdo->query('SELECT COUNT(*) FROM users WHERE username LIKE "IRDP/ODICT/MA26/95%"')->fetchColumn()===50,'50-student XLSX import commit failed.');
    echo "PASS: prefixed worksheet/empty shared strings, 50-student HTTP XLSX preview and confirmed import\n";
    // Public recovery must reach Admin even with email disabled/unverified.
    $recoveryCookie=__DIR__.'/feature-recovery.cookies';$files[]=$recoveryCookie;
    [$code,$forgot]=http_call('/auth/forgot.php',[],$recoveryCookie);check($code===200,'Forgot Password unavailable.');$recoveryCsrf=token_from($forgot);
    [$code,$submitted]=http_call('/auth/forgot.php',['csrf_token'=>$recoveryCsrf,'username'=>'IRDP/ODICT/MA25/0001'],$recoveryCookie,true);
    check($code===302,'Recovery did not redirect to browser approval page.');
    [$code,$submitted]=http_call('/auth/reset.php',[],$recoveryCookie);
    check($code===200&&str_contains($submitted,'awaiting administrator approval'),'Recovery approval instructions missing.');
    $recovery=$pdo->query('SELECT * FROM password_reset_requests WHERE user_id='.$studentUser.' ORDER BY id DESC LIMIT 1')->fetch();
    check($recovery&&$recovery['status']==='PENDING'&&$recovery['reset_id'],'Forgot Password did not bind a pending browser recovery request.');
    [$code,$duplicate]=http_call('/auth/forgot.php',['csrf_token'=>$recoveryCsrf,'username'=>'IRDP/ODICT/MA25/0001'],$recoveryCookie,true);
    check((int)$pdo->query('SELECT COUNT(*) FROM password_reset_requests WHERE user_id='.$studentUser.' AND status IN ("PENDING","ISSUED")')->fetchColumn()===1,'Duplicate open recovery requests.');
    $requestCount=(int)$pdo->query('SELECT COUNT(*) FROM password_reset_requests')->fetchColumn();
    [$code,$unknown]=http_call('/auth/forgot.php',['csrf_token'=>$recoveryCsrf,'username'=>'fictional-unknown'],$recoveryCookie,true);
    if($code===302){[$code,$unknown]=http_call('/auth/reset.php',[],$recoveryCookie);}
    preg_match('/<div class="alert info">(.*?)<\/div>/s',$submitted,$knownMessage);preg_match('/<div class="alert info">(.*?)<\/div>/s',$unknown,$unknownMessage);
    check($code===200&&$knownMessage[1]===$unknownMessage[1]&&(int)$pdo->query('SELECT COUNT(*) FROM password_reset_requests')->fetchColumn()===$requestCount,'Recovery exposed unknown account or queued it.');
    $s=$pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND title="Password reset requested"');$s->execute([$admin]);check((int)$s->fetchColumn()===1,'Admin not notified or duplicate notifications created.');
    [$code]=http_call('/admin/recovery.php',[],$officerCookie);check($code===403,'Officer accessed reset requests.');
    [$code]=http_call('/admin/recovery.php',['csrf_token'=>token_from($normalReview),'request_id'=>(string)$recovery['id'],'action'=>'issue'],$officerCookie,true);check($code===403,'Officer submitted admin recovery action.');
    rejected(fn()=>admin_password_recovery($pdo,['action'=>'issue','request_id'=>(string)$recovery['id'],'identity_reference'=>'Fictional check','identity_checked'=>'1'],$primary),'Officer helper issued a credential.');
    [$code,$recoveryPage]=http_call('/admin/recovery.php?request='.$recovery['id'],[],$adminCookie);
    check($code===200&&str_contains($recoveryPage,'IRDP/ODICT/MA25/0001')&&str_contains($recoveryPage,'Approve student password change'),'Admin queue missing request.');
    [$code,$approvedBrowser]=http_call('/admin/recovery.php',['csrf_token'=>token_from($recoveryPage),'request_id'=>(string)$recovery['id'],'action'=>'issue','browser_approval'=>'1'],$adminCookie,true);
    check($code===302,'Browser recovery approval failed.');
    [$code,$browserForm]=http_call('/auth/reset.php',[],$recoveryCookie);
    check($code===200&&str_contains($browserForm,'Administrator approval received')&&!str_contains($browserForm,'name="credential"'),'Approved student still requires a reset code.');
    [$code,$unverified]=http_call('/admin/recovery.php',['csrf_token'=>token_from($recoveryPage),'request_id'=>(string)$recovery['id'],'action'=>'issue','identity_reference'=>'Fictional ID record'],$adminCookie,true);
    check($code===200&&str_contains($unverified,'Verify institutional identity')&&!str_contains($unverified,'id="reset-code"'),'Admin issued code without checking identity.');
    [$code,$issued]=http_call('/admin/recovery.php',['csrf_token'=>token_from($unverified),'request_id'=>(string)$recovery['id'],'username'=>'admin','action'=>'issue','identity_reference'=>'Fictional ID record','identity_checked'=>'1'],$adminCookie,true);
    check($code===200&&preg_match('/id="reset-code"[^>]*>([a-f0-9]{64})<\/code>/',$issued,$match)===1,'Admin did not receive reset code.');$resetCode=$match[1];
    $record=$pdo->query('SELECT pr.*,p.user_id AS token_user,p.token_hash FROM password_reset_requests pr INNER JOIN password_resets p ON p.id=pr.reset_id WHERE pr.id='.(int)$recovery['id'])->fetch();
    check($record['status']==='ISSUED'&&(int)$record['token_user']===$studentUser&&$record['token_hash']===hash('sha256',$resetCode),'Issued code linked to wrong account or stored in plaintext.');
    [$code,$issuedAgain]=http_call('/admin/recovery.php?request='.$recovery['id'],[],$adminCookie);check(!str_contains($issuedAgain,$resetCode),'Code redisplayed after issuance.');
    // Replacement revokes the old code, and only a completed reset closes the request.
    [$code,$replacement]=http_call('/admin/recovery.php',['csrf_token'=>token_from($issuedAgain),'request_id'=>(string)$recovery['id'],'action'=>'issue','identity_reference'=>'Fictional repeated ID check','identity_checked'=>'1'],$adminCookie,true);
    check(preg_match('/id="reset-code"[^>]*>([a-f0-9]{64})<\/code>/',$replacement,$match)===1,'Replacement code missing.');$replacementCode=$match[1];
    [$code,$resetPage]=http_call('/auth/reset.php',[],$recoveryCookie);
    [$code,$invalid]=http_call('/auth/reset.php',['csrf_token'=>token_from($resetPage),'credential'=>$resetCode,'password'=>'Birch meadow compass 74','confirm_password'=>'Birch meadow compass 74'],$recoveryCookie,true);
    check($code===200&&str_contains($invalid,'Invalid or expired reset credential'),'Replaced reset code accepted.');
    [$code,$mismatch]=http_call('/auth/reset.php',['csrf_token'=>token_from($invalid),'credential'=>$replacementCode,'password'=>'Birch meadow compass 74','confirm_password'=>'Wrong123'],$recoveryCookie,true);
    check(str_contains($mismatch,'Password confirmation must match')&&$pdo->query('SELECT status FROM password_reset_requests WHERE id='.(int)$recovery['id'])->fetchColumn()==='ISSUED','Mismatched confirmation completed request.');
    [$code,$resetResult]=http_call('/auth/reset.php',['csrf_token'=>token_from($mismatch),'credential'=>$replacementCode,'password'=>'Birch meadow compass 74','confirm_password'=>'Birch meadow compass 74'],$recoveryCookie,true);
    check($code===200&&str_contains($resetResult,'Password updated')&&$pdo->query('SELECT status FROM password_reset_requests WHERE id='.(int)$recovery['id'])->fetchColumn()==='COMPLETED','Successful reset did not complete the queued request.');
    [$code]=http_call('/student/dashboard.php',[],$cookie);check($code===302,'Old student session survived password reset.');
    [$code,$loginPage]=http_call('/auth/login.php',[],$recoveryCookie);
    [$code,$oldLogin]=http_call('/auth/login.php',['csrf_token'=>token_from($loginPage),'username'=>'IRDP/ODICT/MA25/0001','password'=>'Cedar ocean lantern 91'],$recoveryCookie,true);check($code===200&&str_contains($oldLogin,'Invalid username or password'),'Old password still works.');
    test_login('IRDP/ODICT/MA25/0001','Birch meadow compass 74',$recoveryCookie);
    [$code,$completedPage]=http_call('/admin/recovery.php?status=COMPLETED',[],$adminCookie);check($code===200&&str_contains($completedPage,'IRDP/ODICT/MA25/0001')&&str_contains($completedPage,'Completed'),'Admin cannot see completed requests.');
    rejected(fn()=>admin_password_recovery($pdo,['action'=>'issue','request_id'=>(string)$recovery['id'],'identity_reference'=>'Closed request','identity_checked'=>'1'],$admin),'Completed request reopened by stale action.');
    // Rejection revokes its issued token. Inactive/unknown/throttled submissions stay neutral.
    [$code,$forgot]=http_call('/auth/forgot.php',[],$wrongCookie);
    [$code]=http_call('/auth/forgot.php',['csrf_token'=>token_from($forgot),'username'=>'IRDP/BTCRP/MA25/0002'],$wrongCookie,true);
    $rejectedRequest=(int)$pdo->query('SELECT id FROM password_reset_requests WHERE user_id='.$ids['IRDP/BTCRP/MA25/0002'].' ORDER BY id DESC LIMIT 1')->fetchColumn();check($rejectedRequest>0,'Second recovery request not recorded.');
    $rejectCode=admin_password_recovery($pdo,['action'=>'issue','request_id'=>(string)$rejectedRequest,'identity_reference'=>'Fictional ID check','identity_checked'=>'1'],$admin);
    [$code,$rejectPage]=http_call('/admin/recovery.php?request='.$rejectedRequest,[],$adminCookie);
    [$code]=http_call('/admin/recovery.php',['csrf_token'=>token_from($rejectPage),'request_id'=>(string)$rejectedRequest,'action'=>'reject','decision_reason'=>'Fictional identity verification failed'],$adminCookie,true);
    check($code===302&&$pdo->query('SELECT status FROM password_reset_requests WHERE id='.$rejectedRequest)->fetchColumn()==='REJECTED','Admin rejection did not persist.');
    rejected(fn()=>reset_password($pdo,$rejectCode,'Birch meadow compass 74','Birch meadow compass 74'),'Rejected request credential remained valid.');
    $pdo->exec('UPDATE users SET active=0 WHERE username="IRDP/ODICT/MA26/9101"');
    $requestCount=(int)$pdo->query('SELECT COUNT(*) FROM password_reset_requests')->fetchColumn();
    [$code,$inactive]=http_call('/auth/forgot.php',['csrf_token'=>token_from($forgot),'username'=>'IRDP/ODICT/MA26/9101'],$wrongCookie,true);check(in_array($code,[200,302],true)&&(int)$pdo->query('SELECT COUNT(*) FROM password_reset_requests')->fetchColumn()===$requestCount,'Inactive recovery queued.');
    [$code,$throttled]=http_call('/auth/forgot.php',['csrf_token'=>token_from($forgot),'username'=>'IRDP/BTCRP/MA25/0002'],$wrongCookie,true);check(in_array($code,[200,302],true)&&(int)$pdo->query('SELECT COUNT(*) FROM password_reset_requests')->fetchColumn()===$requestCount,'Throttled recovery submission queued.');
    echo "PASS: HTTP forgot-password queue, Admin authorization/identity check, duplicate protection, replacement/rejection, password reset completion and revoked sessions\n";
    check(!certificate_release_allowed($pdo,$request),'Incomplete clearance permitted certificate release.');
    // Real starts require all office reviewers and keep the period gate.
    $startCookie=__DIR__.'/feature-start.cookies';$files[]=$startCookie;
    test_login('IRDP/BTCCD/MA25/0003','MUSHI0003',$startCookie);
    $cycle=clearance_period_state($pdo)['academic_cycle'];
    [$code,$startForm]=http_call('/student/start.php',[],$startCookie);check($code===200,'Start form unavailable.');
    $requestsBefore=(int)$pdo->query('SELECT COUNT(*) FROM clearance_requests')->fetchColumn();
    $pdo->prepare('UPDATE users SET active=0 WHERE id=?')->execute([$ids['FIN001']]);
    [$code,$missingReviewer]=http_call('/student/start.php',['csrf_token'=>token_from($startForm),'academic_year'=>$cycle],$startCookie,true);
    check($code===200&&str_contains($missingReviewer,'No active officer')&&(int)$pdo->query('SELECT COUNT(*) FROM clearance_requests')->fetchColumn()===$requestsBefore,'Missing officer left a partial clearance request.');
    $pdo->prepare('UPDATE users SET active=1 WHERE id=?')->execute([$ids['FIN001']]);
    [$code]=http_call('/student/start.php',['csrf_token'=>token_from($missingReviewer),'academic_year'=>$cycle],$startCookie,true);
    check($code===302,'Normal student start failed after feature removal.');
    $started=get_clearance_for_cycle($pdo,(int)$pdo->query('SELECT id FROM students WHERE user_id='.$ids['IRDP/BTCCD/MA25/0003'])->fetchColumn(),$cycle);
    $startedStages=get_clearance_stages($pdo,(int)$started['id']);
    check(count($startedStages)===11&&$startedStages[0]['status']==='PENDING'&&count(array_filter($startedStages,fn($row)=>$row['status']==='LOCKED'))===10,'Student start did not create the eleven sequential stages.');
    foreach($startedStages as $startedStage){check(reviewer_assignment_valid($pdo,stage_context($pdo,(int)$startedStage['id']),(int)$startedStage['assigned_officer_id']),'New stage has no permitted office reviewer.');}
    $closedCookie=__DIR__.'/feature-closed.cookies';$files[]=$closedCookie;
    test_login('IRDP/ODICT/MA25/0004','MALLYA0004',$closedCookie);
    $pdo->exec('UPDATE clearance_period SET mode="MANUAL_CLOSED" WHERE id=1');
    [$code,$closedForm]=http_call('/student/start.php',[],$closedCookie);
    $requestsBefore=(int)$pdo->query('SELECT COUNT(*) FROM clearance_requests')->fetchColumn();
    [$code,$closedResult]=http_call('/student/start.php',['csrf_token'=>token_from($closedForm),'academic_year'=>$cycle],$closedCookie,true);
    check($code===200&&str_contains($closedResult,CLEARANCE_CLOSED_MESSAGE)&&(int)$pdo->query('SELECT COUNT(*) FROM clearance_requests')->fetchColumn()===$requestsBefore,'Feature removal bypassed the closed clearance period.');
    check((int)$pdo->query('SELECT COUNT(*) FROM clearance_escalations')->fetchColumn()===0&&(int)$pdo->query('SELECT COUNT(*) FROM emergency_clearances')->fetchColumn()===0&&(int)$pdo->query('SELECT COUNT(*) FROM stage_appeals')->fetchColumn()===0,'Normal clearance created a retired feature record.');
    echo "PASS: HTTP student start, complete office assignment, rollback for missing reviewers and closed-period protection\n";
    $transcriptBefore=transcript_for_student($pdo,$student,$fullRequest);
    $pdo->prepare('UPDATE clearance_requests SET status="COMPLETED",completed_at=NOW() WHERE id=?')->execute([$assignedRequest]);
    rejected(fn()=>ensure_transcript($pdo,$student,$admin,$assignedRequest),'Completed status alone bypassed missing office approvals.');
    // Complete the later-cycle fixture from previously verified office decisions.
    $pdo->prepare('UPDATE clearance_stages target INNER JOIN clearance_stages source ON source.workflow_step_id=target.workflow_step_id AND source.clearance_request_id=? SET target.status="APPROVED",target.details_json=source.details_json,target.reviewed_at=source.reviewed_at WHERE target.clearance_request_id=?')->execute([$fullRequest,$assignedRequest]);
    $pdo->prepare('INSERT INTO clearance_stages(clearance_request_id,workflow_step_id,office_id,assigned_officer_id,status,details_json,reviewed_at) SELECT ?,source.workflow_step_id,source.office_id,source.assigned_officer_id,"APPROVED",source.details_json,source.reviewed_at FROM clearance_stages source WHERE source.clearance_request_id=? AND NOT EXISTS(SELECT 1 FROM clearance_stages target WHERE target.clearance_request_id=? AND target.workflow_step_id=source.workflow_step_id)')->execute([$assignedRequest,$fullRequest,$assignedRequest]);
    rejected(fn()=>ensure_transcript($pdo,$student,$admin,$assignedRequest),'Approved stages without approval actions issued a transcript.');
    $pdo->prepare('INSERT INTO stage_actions(stage_id,officer_id,action,comments,details_json,created_at) SELECT target.id,a.officer_id,"APPROVED",a.comments,a.details_json,a.created_at FROM clearance_stages target INNER JOIN clearance_stages source ON source.workflow_step_id=target.workflow_step_id AND source.clearance_request_id=? INNER JOIN stage_actions a ON a.id=(SELECT MAX(last_action.id) FROM stage_actions last_action WHERE last_action.stage_id=source.id) WHERE target.clearance_request_id=?')->execute([$fullRequest,$assignedRequest]);
    $pdo->prepare('UPDATE clearance_stages SET assigned_officer_id=? WHERE id=?')->execute([$backup,$assignedStage]);
    // Cloned test approvals require their own receipt association.
    $clonedFinance=$pdo->query('SELECT cs.id FROM clearance_stages cs INNER JOIN workflow_steps ws ON ws.id=cs.workflow_step_id WHERE cs.clearance_request_id='.$assignedRequest.' AND ws.step_number=11')->fetchColumn();
    $sourcePayment=finance_payment_details(stage_context($pdo,$fullStages[11]));
    $sourceReceipt=$pdo->query('SELECT * FROM stage_evidence WHERE id='.(int)$sourcePayment['receipt_id'])->fetch();
    $clonedName=bin2hex(random_bytes(24)).'.pdf';$files[]=private_path('evidence',$clonedName);
    copy(private_path('evidence',$sourceReceipt['stored_name']),private_path('evidence',$clonedName));
    $pdo->prepare('INSERT INTO stage_evidence(stage_id,cycle_number,uploader_id,stored_name,original_name,mime_type,byte_size) VALUES (?,1,?,?,?,?,?)')->execute([$clonedFinance,$studentUser,$clonedName,$sourceReceipt['original_name'],$sourceReceipt['mime_type'],$sourceReceipt['byte_size']]);
    $sourcePayment['receipt_id']=(int)$pdo->lastInsertId();$sourcePayment['receipt_cycle']=1;
    $pdo->prepare('UPDATE clearance_stages SET details_json=? WHERE id=?')->execute([json_encode($sourcePayment,JSON_THROW_ON_ERROR),$clonedFinance]);
    $newCycleTranscript=ensure_transcript($pdo,$student,$admin,$assignedRequest);
    check(json_decode($newCycleTranscript['snapshot_json'],true)['approvals'][0]['officer_id']===$primary,'Transcript attributed approval to a replacement assignee instead of the actual approver.');
    check($newCycleTranscript['id']!==$transcript['id']&&(int)$newCycleTranscript['version']===(int)$transcript['version']+1&&json_decode($newCycleTranscript['snapshot_json'],true)['cycle']===$pdo->query('SELECT academic_year FROM clearance_requests WHERE id='.$assignedRequest)->fetchColumn(),'A later cycle reused the previous transcript.');
    // Migration repeatability and history preservation.
    $count=(int)$pdo->query('SELECT COUNT(*) FROM stage_actions')->fetchColumn();migrate_features($pdo);migrate_password_recovery_requests($pdo);check((int)$pdo->query('SELECT COUNT(*) FROM stage_actions')->fetchColumn()===$count,'Repeated migration changed decisions.');
    $recoveryCount=(int)$pdo->query('SELECT COUNT(*) FROM password_reset_requests')->fetchColumn();
    $pdo->prepare('DELETE FROM schema_migrations WHERE version IN (?,?)')->execute([APPLICATION_SCHEMA_VERSION,'2026_recovery_requests_v1']);
    ensure_database_ready($pdo,false);
    check(database_schema_ready($pdo)&&(int)$pdo->query('SELECT COUNT(*) FROM password_reset_requests')->fetchColumn()===$recoveryCount&&(int)$pdo->query('SELECT COUNT(*) FROM stage_actions')->fetchColumn()===$count,'Runtime upgrade lost reset history or clearance decisions.');
    check(transcript_for_student($pdo,$student,$fullRequest)===$transcriptBefore,'Migration changed an issued transcript snapshot.');
    foreach(['student_results','programme_modules','grading_rules'] as $removedTable){check($pdo->query("SHOW TABLES LIKE '".$removedTable."'")->fetchColumn()===false,'Migration recreated removed results tables.');}
    // Replace the previous academic format without changing its historical snapshot.
    $laterSnapshot=json_decode($newCycleTranscript['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
    $legacySnapshot=json_encode(['student'=>$laterSnapshot['student'],'cycle'=>$laterSnapshot['cycle'],'rows'=>[['marks'=>77]],'cumulative_gpa'=>4],JSON_THROW_ON_ERROR);
    $pdo->prepare('UPDATE transcripts SET result_source="DEMO",snapshot_json=?,snapshot_sha256=? WHERE id=?')->execute([$legacySnapshot,hash('sha256',$legacySnapshot),$newCycleTranscript['id']]);
    $legacyTranscript=$newCycleTranscript;
    $newCycleTranscript=ensure_transcript($pdo,$student,$admin,$assignedRequest);
    $oldDocument=$pdo->query('SELECT * FROM transcripts WHERE id='.(int)$legacyTranscript['id'])->fetch();
    check($newCycleTranscript['result_source']==='CLEARANCE'&&(int)$newCycleTranscript['version']===(int)$legacyTranscript['version']+1&&$oldDocument['status']==='REVOKED'&&$oldDocument['snapshot_json']===$legacySnapshot&&!str_contains($newCycleTranscript['snapshot_json'],'"marks"'),'Legacy format replacement lost history or retained marks.');
    [$code,$legacyVerification]=http_call('/transcripts/verify.php?token='.$legacyTranscript['verification_token']);
    check($code===200&&str_contains($legacyVerification,'DOCUMENT REVOKED'),'Replaced legacy token remained valid.');
    $pdo->prepare('UPDATE transcripts SET status="REVOKED",revoked_by=?,revoked_at=NOW(),revocation_reason="Fixture revocation" WHERE id=?')->execute([$admin,$newCycleTranscript['id']]);
    [$code,$revokedPage]=http_call('/transcripts/verify.php?token='.$newCycleTranscript['verification_token']);
    check($code===200&&str_contains($revokedPage,'DOCUMENT REVOKED'),'Revoked transcript remained verifiable.');
    check(ensure_transcript($pdo,$student,$admin,$assignedRequest)['status']==='REVOKED','Revoked transcript was silently reissued.');
    [$code]=http_call('/transcripts/download.php',[],$recoveryCookie);check($code===409,'Revoked transcript download was allowed.');
    echo "PASS: certificate guard, transcript versions/revocation and repeatable non-destructive migration\n";
    $times=$GLOBALS['feature_http_times'];sort($times);echo 'HTTP response median: '.round($times[(int)floor(count($times)/2)]*1000).' ms across '.count($times).' requests (including authenticated pages and uploads).'.PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'FAIL: '.$e->getMessage().PHP_EOL);$exit=1;}
finally{
    if($other&&$other->inTransaction()){$other->rollBack();}$other=null;
    if($pdo&&$pdo->inTransaction()){$pdo->rollBack();}
    if(is_resource($web)){proc_terminate($web);proc_close($web);}
    foreach(array_unique($files) as $file){if(is_file($file)){unlink($file);}}
    $sessionTarget=realpath(__DIR__.'/.feature-sessions');
    if($sessionTarget && strcasecmp($sessionTarget,realpath(__DIR__).DIRECTORY_SEPARATOR.'.feature-sessions')===0){foreach(new DirectoryIterator($sessionTarget) as $entry){if($entry->isFile()&&preg_match('/^sess_[a-zA-Z0-9,-]+$/D',$entry->getFilename())){unlink($entry->getPathname());}}rmdir($sessionTarget);}
    if($created&&$server&&preg_match('/^irdp_test_[a-f0-9]{16}$/D',$database)){$server->exec('DROP DATABASE '.$database);echo "Isolated feature database removed.\n";}
}
exit($exit);
