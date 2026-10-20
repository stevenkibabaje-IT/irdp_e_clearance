<?php
declare(strict_types=1);
// Exercise login, real receipt uploads and starts against a disposable database.
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require __DIR__.'/../includes/installer.php';
require __DIR__.'/../includes/functions.php';
function entry_check(bool $condition,string $message): void {if(!$condition){throw new RuntimeException($message);}}
function entry_http(string $origin,string $route,string $cookie,?array $post=null): array {
    static $cookies=[];
    $c=curl_init($origin.$route);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIE=>$cookies[$cookie]??'']);
    if($post!==null){curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post]);}
    $response=curl_exec($c);$status=(int)curl_getinfo($c,CURLINFO_RESPONSE_CODE);$length=(int)curl_getinfo($c,CURLINFO_HEADER_SIZE);$error=curl_error($c);curl_close($c);
    if($response===false){throw new RuntimeException('HTTP request failed: '.$error);}
    $headers=substr($response,0,$length);if(preg_match('/^Set-Cookie:\s*(PHPSESSID=[^;\r\n]+)/mi',$headers,$m)){$cookies[$cookie]=$m[1];}
    return [$status,substr($response,$length)];
}
function entry_token(string $body): string {entry_check((bool)preg_match('/name="csrf_token" value="([^"]+)"/',$body,$m),'CSRF token missing.');return html_entity_decode($m[1],ENT_QUOTES);}
function entry_login(string $origin,string $cookie,string $user,string $password): void {
    [$code,$body]=entry_http($origin,'/auth/login.php',$cookie);entry_check($code===200,'Login form unavailable.');
    [$code,$body]=entry_http($origin,'/auth/login.php',$cookie,['csrf_token'=>entry_token($body),'username'=>$user,'password'=>$password]);
    $detail='';if(preg_match('/id="login-error"[^>]*>(.*?)<\/div>/s',$body,$m)){$detail=trim(strip_tags($m[1]));}
    entry_check($code===302,'Login failed for '.$user.'; HTTP '.$code.'. '.$detail);
}
$database='irdp_entry_auto_'.bin2hex(random_bytes(8));$temp=sys_get_temp_dir().'/irdp-entry-'.bin2hex(random_bytes(8));
$server=$pdo=$web=null;$created=false;$exit=0;$stored=[];
try{
    mkdir($temp,0700);mkdir($temp.'/sessions',0700);
    $server=new PDO($argv[1]??'mysql:host=127.0.0.1;port=3306;charset=utf8mb4',getenv('IRDP_TEST_USER')?:'root',getenv('IRDP_TEST_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $server->exec("SET time_zone='+03:00'");$server->exec('CREATE DATABASE '.$database.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;$server->exec('USE '.$database);$pdo=ensure_database_ready($server);
    $cycle=(int)$pdo->query('SELECT id FROM academic_cycles WHERE label="2026/2027"')->fetchColumn();
    $pdo->prepare('UPDATE clearance_period SET mode="MANUAL_OPEN",cycle_id=? WHERE id=1')->execute([$cycle]);
    $students=$pdo->query('SELECT s.*,u.username FROM students s JOIN users u ON u.id=s.user_id ORDER BY s.id LIMIT 3')->fetchAll();
    $finance=(int)$pdo->query('SELECT id FROM users WHERE username="FIN001"')->fetchColumn();
    // Preserve the quoted amount of a legacy request when it receives an automatic number.
    $pdo->prepare('INSERT INTO clearance_fee_payments(student_id,cycle_id,assigned_officer_id,amount) VALUES (?,?,?,7500)')->execute([$students[2]['id'],$cycle,$finance]);
    $legacyId=(int)$pdo->lastInsertId();
    migrate_clearance_fees($pdo);migrate_clearance_fees($pdo);
    entry_check(clearance_fee_record($pdo,$legacyId)['control_number']===null,'Migration fabricated a number before login.');
    $env=getenv();$env['DB_HOST']='127.0.0.1';$env['DB_PORT']='3306';$env['DB_NAME']=$database;$env['DB_USER']=getenv('IRDP_TEST_USER')?:'root';$env['DB_PASS']=getenv('IRDP_TEST_PASSWORD')?:'';$env['IRDP_SMTP_ENABLED']='0';
    if(isset($argv[1])&&preg_match('/(?:^|;)port=([0-9]+)/',$argv[1],$match)){$env['DB_PORT']=$match[1];}
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);entry_check((bool)$socket,'Test port unavailable.');$address=stream_socket_get_name($socket,false);fclose($socket);$origin='http://'.$address;
    $web=proc_open([PHP_BINARY,'-d','session.save_path="'.str_replace('\\','/',$temp).'/sessions"','-d','upload_max_filesize=6M','-d','post_max_size=32M','-S',$address,'-t',dirname(__DIR__),__DIR__.'/http_router.php'],[0=>['pipe','r'],1=>['file',$temp.'/http.log','a'],2=>['file',$temp.'/http.log','a']],$pipes,dirname(__DIR__),$env);
    entry_check(is_resource($web),'Test web server unavailable.');fclose($pipes[0]);
    for($attempt=0;$attempt<50;$attempt++){$s=@stream_socket_client('tcp://'.$address,$errno,$error,.1);if($s){fclose($s);break;}usleep(100000);}
    $cookie=$temp.'/student.cookies';entry_login($origin,$cookie,$students[0]['username'],'KIBABAJE0001');
    $fee=clearance_fee_for_cycle($pdo,(int)$students[0]['id'],$cycle);$id=(int)($fee['id']??0);
    entry_check($id>0 && $fee['status']==='AWAITING_PAYMENT' && preg_match('/^[0-9]{18}$/D',$fee['control_number'])===1 && $fee['control_generated_at'],'Login did not generate a control number with a timestamp.');
    entry_check(!clearance_fee_ready($pdo,$fee),'Control number alone unlocked clearance.');
    $snapshot=$fee;
    foreach(['/student/dashboard.php','/student/clearance_fee.php'] as $route){[$code,$body]=entry_http($origin,$route,$cookie);entry_check($code===200&&str_contains($body,$fee['control_number'])&&str_contains($body,'<span>Day</span>')&&str_contains($body,'<span>Date</span>')&&str_contains($body,'Time (EAT)')&&str_contains($body,'Demo payment reference'),'Control number or generation details missing.');}
    entry_login($origin,$temp.'/second-session.cookies',$students[0]['username'],'KIBABAJE0001');
    entry_check(clearance_fee_for_cycle($pdo,(int)$students[0]['id'],$cycle)===$snapshot,'Repeated login changed the control number, timestamp or quote.');
    entry_login($origin,$temp.'/other.cookies',$students[1]['username'],'MFINANGA0002');
    $other=clearance_fee_for_cycle($pdo,(int)$students[1]['id'],$cycle);entry_check($other['control_number']!==$fee['control_number'],'Students received the same control number.');
    entry_login($origin,$temp.'/legacy.cookies',$students[2]['username'],'MUSHI0003');$legacy=clearance_fee_record($pdo,$legacyId);
    entry_check($legacy['control_number']&&$legacy['control_generated_at']&&(float)$legacy['amount']===7500.0,'Legacy request lost its quote or did not receive a number.');
    echo "PASS: automatic controls on login, unique numbers, stable repeated logins, timestamp display and legacy request upgrade.\n";
    [$code,$form]=entry_http($origin,'/student/start.php',$cookie);
    [$code]=entry_http($origin,'/student/start.php',$cookie,['csrf_token'=>entry_token($form),'academic_year'=>'2026/2027']);entry_check($code===200&&!get_student_clearance($pdo,(int)$students[0]['id']),'Clearance started without a receipt.');
    [$code,$form]=entry_http($origin,'/student/clearance_fee.php',$cookie);
    $upload=['csrf_token'=>entry_token($form),'mode'=>'RECEIPT','payment_id'=>(string)$id,'payment_version'=>(string)$fee['payment_version'],'control_number'=>$fee['control_number']];
    [$code,$body]=entry_http($origin,'/student/clearance_fee.php',$cookie,$upload);entry_check($code===200&&str_contains($body,'evidence-error'),'Missing receipt was accepted.');
    $pdf=$temp.'/receipt.pdf';file_put_contents($pdf,"%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");
    [$code,$otherForm]=entry_http($origin,'/student/clearance_fee.php',$temp.'/other.cookies');
    [$code]=entry_http($origin,'/student/clearance_fee.php',$temp.'/other.cookies',array_replace($upload,['csrf_token'=>entry_token($otherForm)])+['evidence[0]'=>new CURLFile($pdf,'application/pdf','receipt.pdf')]);
    entry_check(clearance_fee_record($pdo,$id)['receipt_id']===null,'Another student submitted this receipt.');
    [$code]=entry_http($origin,'/student/clearance_fee.php',$cookie,$upload+['evidence[0]'=>new CURLFile($pdf,'application/pdf','receipt.pdf')]);entry_check($code===302,'Receipt upload failed.');
    $fee=clearance_fee_record($pdo,$id);entry_check($fee['status']==='AWAITING_REVIEW'&&!$fee['approved_by']&&!$fee['approved_at']&&clearance_fee_ready($pdo,$fee),'Receipt did not unlock clearance separately from payment verification.');
    foreach(['/student/dashboard.php','/student/clearance_fee.php','/student/start.php'] as $route){[$code,$body]=entry_http($origin,$route,$cookie);entry_check($code===200&&str_contains($body,'Start Clearance'),'Start action missing after receipt submission.');}
    [$code,$form]=entry_http($origin,'/student/start.php',$cookie);
    [$code]=entry_http($origin,'/student/start.php',$cookie,['csrf_token'=>entry_token($form),'academic_year'=>'2026/2027']);
    $request=get_student_clearance($pdo,(int)$students[0]['id']);entry_check($code===302&&$request&&count(get_clearance_stages($pdo,(int)$request['id']))===11,'Receipt did not allow an actual start before Finance approval.');
    entry_check(clearance_fee_record($pdo,$id)['status']==='AWAITING_REVIEW','Start falsely approved the payment.');
    entry_check(!certificate_release_allowed($pdo,(int)$request['id']),'Receipt alone completed clearance.');
    $blocked=false;try{process_clearance_fee($pdo,$id,$finance,['mode'=>'CONTROL','payment_version'=>(string)$fee['payment_version'],'control_number'=>'991122334455']);}catch(RuntimeException $e){$blocked=true;}entry_check($blocked,'Finance changed the entry control after clearance started.');
    process_clearance_fee($pdo,$id,$finance,['mode'=>'APPROVE','payment_version'=>(string)$fee['payment_version'],'confirm_payment'=>'1']);entry_check(clearance_fee_record($pdo,$id)['status']==='APPROVED','Finance could not verify a receipt after clearance started.');
    $before=(int)$pdo->query('SELECT COUNT(*) FROM clearance_fee_payments')->fetchColumn();
    entry_login($origin,$temp.'/existing.cookies',$students[0]['username'],'KIBABAJE0001');entry_check((int)$pdo->query('SELECT COUNT(*) FROM clearance_fee_payments')->fetchColumn()===$before,'Existing clearance generated another fee.');
    echo "PASS: receipt ownership and requirement, Start Clearance before verification, eleven office stages, locked payment evidence and later Finance approval.\n";
    $stageMap=[];foreach(get_clearance_stages($pdo,(int)$request['id']) as $s){$stageMap[(int)$s['step_number']]=$s;}
    foreach([6=>['DISP001','Mushi@2026'],7=>['EPM001','Mgimwa@2026']] as $step=>[$username,$password]){
        $officeCookie=$temp.'/office-'.$step.'.cookies';entry_login($origin,$officeCookie,$username,$password);
        $stageId=(int)$stageMap[$step]['id'];$route='/officer/review.php?stage='.$stageId;
        [$code,$body]=entry_http($origin,$route,$officeCookie);
        entry_check($code===200&&str_contains($body,'name="decision"')&&!str_contains($body,'name="amount"')&&!str_contains($body,'name="control_number"'),'An unrelated payment field remains in an office form.');
        [$code]=entry_http($origin,$route,$officeCookie,['csrf_token'=>entry_token($body),'review_cycle'=>'1','action'=>'PAYMENT_REQUESTED','control_number'=>'991122334455']);
        entry_check($code===200&&stage_context($pdo,$stageId)['status']==='PENDING','Non-Finance office accepted a payment request.');
        [$code,$body]=entry_http($origin,$route,$officeCookie);
        if($step===6){
            [$code]=entry_http($origin,$route,$officeCookie,['csrf_token'=>entry_token($body),'review_cycle'=>'1','action'=>'REJECTED','decision'=>'NOT_CLEARED','comments'=>'A clearance requirement is outstanding.','corrective_instructions'=>'Contact the office and submit the required correction.']);
            entry_check($code===302&&stage_context($pdo,$stageId)['status']==='REJECTED','Dispensary could not request a correction.');
            $responseRoute='/student/resubmit.php?stage='.$stageId;[$code,$body]=entry_http($origin,$responseRoute,$cookie);
            [$code]=entry_http($origin,$responseRoute,$cookie,['csrf_token'=>entry_token($body),'mode'=>'resubmit','response'=>'The requested correction is complete.']);
            entry_check($code===302&&stage_context($pdo,$stageId)['review_cycle']==2,'Dispensary correction could not be resubmitted.');
            [$code,$body]=entry_http($origin,$route,$officeCookie);
            $action='APPROVED_CORRECTION';$reviewCycle='2';
        }else{$action='APPROVED';$reviewCycle='1';}
        [$code]=entry_http($origin,$route,$officeCookie,['csrf_token'=>entry_token($body),'review_cycle'=>$reviewCycle,'action'=>$action,'decision'=>'CLEARED','comments'=>'Office requirements are satisfied.','corrective_instructions'=>'']);
        $saved=stage_context($pdo,$stageId);$details=json_decode($saved['details_json'],true);
        entry_check($code===302&&$saved['status']==='APPROVED'&&$details['decision']==='CLEARED'&&!array_key_exists('amount',$details),'Office decision did not work without a payment amount.');
    }
    entry_check((int)$pdo->query('SELECT COUNT(*) FROM stage_actions WHERE stage_id='.(int)$stageMap[6]['id'])->fetchColumn()===2,'Dispensary rejection history was lost.');
    entry_check(stage_context($pdo,(int)$stageMap[1]['id'])['status']==='PENDING'&&!certificate_release_allowed($pdo,(int)$request['id']),'Office simplification changed another office or completed clearance early.');
    echo "PASS: Dispensary and departmental forms omit payment fields; rejection, resubmission, approvals, office authority and history remain functional.\n";
    $finalStage=stage_context($pdo,(int)$stageMap[11]['id']);
    entry_check(!finance_final_approval_ready($pdo,(int)$request['id']),'Finance approval unlocked before the other offices approved.');
    foreach(['APPROVED_NO_DEBT','APPROVED','PAYMENT_REQUESTED'] as $earlyAction){
        $blocked=false;
        try{process_stage_decision($pdo,(int)$finalStage['id'],(int)$finalStage['assigned_officer_id'],['action'=>$earlyAction,'review_cycle'=>(string)$finalStage['review_cycle']],review_fields(11));}
        catch(RuntimeException $e){$blocked=str_contains($e->getMessage(),'final approver');}
        entry_check($blocked&&stage_context($pdo,(int)$finalStage['id'])['status']===$finalStage['status'],'Early Finance approval was not blocked safely.');
    }
    foreach(get_clearance_stages($pdo,(int)$request['id']) as $row){
        $stage=stage_context($pdo,(int)$row['id']);if($stage['status']==='APPROVED'){continue;}
        $step=(int)$stage['step_number'];$input=['action'=>$step===11?'APPROVED_NO_DEBT':'APPROVED','review_cycle'=>(string)$stage['review_cycle'],'comments'=>'Office requirements satisfied.','corrective_instructions'=>''];
        foreach(review_fields($step) as [$key,$label,$type]){if($type!=='control_number'){$input[$key]=$type==='number'?'0':($type==='asset'?'AVAILABLE':'CLEARED');}}
        process_stage_decision($pdo,(int)$stage['id'],(int)$stage['assigned_officer_id'],$input,review_fields($step));
    }
    $finalStage=stage_context($pdo,(int)$finalStage['id']);
    entry_check($finalStage['status']==='APPROVED'&&$finalStage['request_status']==='COMPLETED','Final Finance approval did not complete clearance.');
    foreach(['PAYMENT_REQUESTED','APPROVED_NO_DEBT'] as $lockedAction){
        $blocked=false;
        try{process_stage_decision($pdo,(int)$finalStage['id'],(int)$finalStage['assigned_officer_id'],['action'=>$lockedAction,'review_cycle'=>(string)$finalStage['review_cycle'],'control_number'=>'991122334455'],review_fields(11));}
        catch(RuntimeException $e){$blocked=true;}
        entry_check($blocked&&stage_context($pdo,(int)$finalStage['id'])['details_json']===$finalStage['details_json'],'Approved Finance payment was editable.');
    }
    echo "PASS: Finance waits for the other ten approvals, completes clearance last, and locks subsequent payment changes.\n";
    $doc=transcript_for_student($pdo,(int)$students[0]['id'],(int)$request['id']);
    entry_check($doc&&$doc['status']==='VALID'&&hash_equals($doc['snapshot_sha256'],hash('sha256',$doc['snapshot_json'])),'Certificate issuance lost its valid snapshot.');
    [$code,$pdfBody]=entry_http($origin,'/transcripts/download.php',$cookie);
    entry_check($code===200&&str_starts_with($pdfBody,'%PDF-')&&str_contains($pdfBody,'Student Clearance Certificate')&&!str_contains($pdfBody,'CLEARANCE TRANSCRIPT'),'Certificate download did not use the requested document name.');
    [$code,$verifyBody]=entry_http($origin,'/transcripts/verify.php?token='.$doc['verification_token'],$cookie);
    entry_check($code===200&&str_contains($verifyBody,'DOCUMENT VERIFIED')&&str_contains($verifyBody,'Student Clearance Certificate'),'Existing document verification route no longer works.');
    echo "PASS: all eleven approvals issue a Student Clearance Certificate; PDF download, snapshot and verification remain compatible.\n";
    $identity=issue_certificate($pdo,(int)$request['id']);
    [$code,$oldPdf]=entry_http($origin,'/certificates/download.php?id='.$request['id'],$cookie);
    entry_check($code===200&&str_starts_with($oldPdf,'%PDF-'),'Legacy certificate download stopped working after document merge.');
    [$code,$oldForm]=entry_http($origin,'/certificates/verify.php',$cookie);
    $oldInput=['csrf_token'=>entry_token($oldForm),'certificate_number'=>$identity['certificate_number'],'verification_code'=>$identity['verification_code']];
    [$code,$oldVerified]=entry_http($origin,'/certificates/verify.php',$cookie,$oldInput);
    entry_check($code===200&&str_contains($oldVerified,'Certificate verified.'),'Legacy certificate code verification stopped working.');
    [$code]=entry_http($origin,'/certificates/download.php?id='.$request['id'],$temp.'/other.cookies');
    entry_check($code===403,'Another student downloaded the unified certificate.');
    $pdo->prepare('UPDATE transcripts SET status="REVOKED" WHERE id=?')->execute([$doc['id']]);
    [$code,$oldVerified]=entry_http($origin,'/certificates/verify.php',$cookie,$oldInput);
    entry_check($code===200&&!str_contains($oldVerified,'Certificate verified.'),'Revoked document verified through the legacy code route.');
    [$code]=entry_http($origin,'/certificates/download.php?id='.$request['id'],$cookie);
    entry_check($code===409,'Revoked document downloaded through legacy route.');
    [$code,$revoked]=entry_http($origin,'/transcripts/verify.php?token='.$doc['verification_token'],$cookie);
    entry_check($code===200&&str_contains($revoked,'DOCUMENT REVOKED'),'Revocation lost on token verification route.');
    $pdo->prepare('UPDATE transcripts SET status="VALID" WHERE id=?')->execute([$doc['id']]);
    entry_check(count($pdo->query('SHOW TABLES')->fetchAll())===28,'Fresh installation did not use 28 tables.');
    echo "PASS: 28-table installation preserves legacy downloads/codes, ownership checks and revocation on both verification routes.\n";
    $pdo->exec('UPDATE clearance_period SET mode="MANUAL_CLOSED" WHERE id=1');
    $fourth=$pdo->query('SELECT u.username,s.id FROM students s JOIN users u ON u.id=s.user_id ORDER BY s.id LIMIT 3,1')->fetch();
    entry_login($origin,$temp.'/closed.cookies',$fourth['username'],'MALLYA0004');entry_check(clearance_fee_for_cycle($pdo,(int)$fourth['id'],$cycle)===null,'Closed period generated a new payment.');
    entry_login($origin,$temp.'/admin.cookies','admin','Admin@IRDP2026');entry_check((int)$pdo->query('SELECT COUNT(*) FROM clearance_fee_payments')->fetchColumn()===$before,'Staff login generated a student fee.');
    echo "PASS: closed periods, staff logins and existing clearances retain their restrictions.\n";
}catch(Throwable $e){fwrite(STDERR,'FAIL: '.$e->getMessage()."\n");if(is_file($temp.'/http.log')){fwrite(STDERR,implode("\n",array_slice(file($temp.'/http.log',FILE_IGNORE_NEW_LINES),-14))."\n");}$exit=1;}
finally{
    if(is_resource($web)){proc_terminate($web);proc_close($web);}
    if($pdo){if($pdo->inTransaction()){$pdo->rollBack();}foreach($pdo->query('SELECT stored_name FROM clearance_fee_receipts') as $row){private_delete('evidence',$row['stored_name']);}}
    $pdo=null;if($created&&$server&&preg_match('/^irdp_entry_auto_[a-f0-9]{16}$/D',$database)){$server->exec('DROP DATABASE '.$database);}
    $target=realpath($temp);
    if($target&&strcasecmp(dirname($target),realpath(sys_get_temp_dir()))===0&&preg_match('/^irdp-entry-[a-f0-9]{16}$/D',basename($target))){
        foreach(new DirectoryIterator($target.'/sessions') as $file){if($file->isFile()){unlink($file->getPathname());}}rmdir($target.'/sessions');foreach(new DirectoryIterator($target) as $file){if($file->isFile()){unlink($file->getPathname());}}rmdir($target);
    }
}
exit($exit);
