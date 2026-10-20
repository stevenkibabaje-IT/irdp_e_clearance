<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(403); exit; }
require_once __DIR__.'/../includes/installer.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/reporting.php';
function report_check(bool $ok,string $message): void { if (!$ok) { throw new RuntimeException($message); } }
function report_http(string $origin,string $path,string &$cookie,?array $post=null): array {
    $curl=curl_init($origin.$path);
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_COOKIE=>$cookie]);
    if($post!==null){curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post]);}
    $raw=curl_exec($curl); $code=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE); $length=(int)curl_getinfo($curl,CURLINFO_HEADER_SIZE);
    if($raw===false){throw new RuntimeException(curl_error($curl));} curl_close($curl);
    $headers=substr($raw,0,$length); if(preg_match('/^Set-Cookie:\s*(PHPSESSID=[^;\r\n]+)/mi',$headers,$m)){$cookie=$m[1];}
    return [$code,substr($raw,$length),$headers];
}
$database='irdp_reports_'.bin2hex(random_bytes(8)); $server=null; $created=false; $exit=0; $web=null; $temp=null;
try {
    $server=new PDO($argv[1]??'mysql:host=127.0.0.1;port=3306;charset=utf8mb4',getenv('IRDP_TEST_USER')?:'root',getenv('IRDP_TEST_PASSWORD')?:'',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    $server->exec('CREATE DATABASE '.$database.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $created=true;
    $server->exec('USE '.$database); $pdo=ensure_database_ready($server);
    $tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $student=$pdo->query('SELECT * FROM students ORDER BY id LIMIT 1')->fetch();
    $admin=(int)$pdo->query('SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name="ADMIN" LIMIT 1')->fetchColumn();
    $pdo->exec('INSERT IGNORE INTO academic_cycles(label) VALUES ("2040/2041")');
    $pdo->prepare('INSERT INTO clearance_requests(student_id,academic_year,status,started_at,completed_at) VALUES (?,"2040/2041","COMPLETED","2040-06-01 10:00:00","2040-06-03 10:00:00")')->execute([$student['id']]);
    $request=(int)$pdo->lastInsertId(); $office=0;
    foreach ($pdo->query('SELECT * FROM workflow_steps ORDER BY step_number')->fetchAll() as $step) {
        $officer=find_office_reviewer($pdo,(int)$step['office_id'],(int)$step['step_number']===7?(int)$student['department_id']:0);
        $office=(int)$step['office_id'];
        $details=['finance_mode'=>'NO_OTHER_DEBT','decision'=>'CLEARED','debt'=>'0','recovered'=>'0'];
        if ((int)$step['step_number']!==11) {
            $details=[];
            foreach(review_fields((int)$step['step_number']) as [$key,$label,$type]) { $details[$key]=$type==='number'?'0':($type==='asset'?'AVAILABLE':'CLEARED'); }
        }
        $pdo->prepare('INSERT INTO clearance_stages(clearance_request_id,workflow_step_id,office_id,assigned_officer_id,status,started_at,reviewed_at,details_json) VALUES (?,?,?,?,"APPROVED","2040-06-01 10:00:00","2040-06-03 10:00:00",?)')->execute([$request,$step['id'],$office,$officer,json_encode($details)]);
        $stage=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO review_cycles(stage_id,cycle_number,opened_at,closed_at) VALUES (?,1,"2040-06-01 10:00:00","2040-06-03 10:00:00")')->execute([$stage]);
        $pdo->prepare('INSERT INTO stage_actions(stage_id,officer_id,action,created_at) VALUES (?,?,"APPROVED","2040-06-03 10:00:00")')->execute([$stage,$officer]);
    }
    issue_certificate($pdo,$request);
    $pdo->prepare('UPDATE transcripts SET issued_at="2040-06-03 10:00:00" WHERE clearance_request_id=? AND certificate_number IS NOT NULL')->execute([$request]);
    audit($pdo,'LOGIN_SUCCESS',$admin,null,'Report test login');
    audit($pdo,'CLEARANCE_OPENED',$admin,null,json_encode(['after'=>['academic_cycle'=>'2040/2041','mode'=>'MANUAL_OPEN','effective_status'=>'OPEN'],'reason'=>'Report fixture']));
    $pdo->prepare('INSERT INTO clearance_requests(student_id,academic_year,status,started_at) VALUES (?,"2041/2042","PAUSED","2041-06-01 10:00:00")')->execute([$student['id']]);
    $rejectedRequest=(int)$pdo->lastInsertId();
    $step=$pdo->query('SELECT * FROM workflow_steps WHERE step_number=1')->fetch();
    $pdo->prepare('INSERT INTO clearance_stages(clearance_request_id,workflow_step_id,office_id,status,started_at,reviewed_at,comments,corrective_instructions) VALUES (?,?,?,"REJECTED","2041-06-01 10:00:00","2041-06-02 10:00:00","Missing book","Return the book")')->execute([$rejectedRequest,$step['id'],$step['office_id']]);
    $rejections=clearance_report_data($pdo,['report'=>'rejected','year'=>'2041/2042'])['rows'];
    report_check(count($rejections)===1&&$rejections[0]['Reason']==='Missing book'&&$rejections[0]['Corrective_instructions']==='Return the book','Rejected report lost reasons or corrective instructions.');
    $pdo->exec('INSERT IGNORE INTO academic_cycles(label) VALUES ("2042/2043")');
    $cycle=(int)$pdo->query('SELECT id FROM academic_cycles WHERE label="2042/2043"')->fetchColumn();
    $finance=(int)$pdo->query('SELECT u.id FROM users u JOIN user_offices uo ON uo.user_id=u.id JOIN workflow_steps ws ON ws.office_id=uo.office_id WHERE ws.step_number=11 LIMIT 1')->fetchColumn();
    $pdo->prepare('INSERT INTO clearance_fee_payments(student_id,cycle_id,assigned_officer_id,amount,status,control_number) VALUES (?,?,?,10000,"APPROVED","991234567890")')->execute([$student['id'],$cycle,$finance]);
    $payment=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO clearance_fee_receipts(payment_id,payment_version,control_number,uploader_id,stored_name,original_name,mime_type,byte_size) VALUES (?,1,"991234567890",?,"report-fixture.pdf","proof.pdf","application/pdf",64)')->execute([$payment,$student['user_id']]);
    $receipt=(int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE clearance_fee_payments SET receipt_id=?,approved_by=?,approved_at=NOW() WHERE id=?')->execute([$receipt,$finance,$payment]);
    $payments=clearance_report_data($pdo,['report'=>'finance','year'=>'2042/2043'])['rows'];
    report_check(count($payments)===1&&$payments[0]['Receipt']==='proof.pdf'&&$payments[0]['Control_number']==='991234567890'&&$payments[0]['Payment_status']==='APPROVED','Finance report omitted approved receipt before clearance started.');
    $base=['year'=>'2040/2041','from'=>'2040-06-01','to'=>'2040-06-30'];
    foreach (clearance_report_catalog() as $kind=>$title) {
        $result=clearance_report_data($pdo,['report'=>$kind]);
        report_check(count($result['columns'])>0,'Missing columns for '.$kind);
        if ($kind!=='access') {
            foreach ([$base,['programme'=>$student['programme'],'department'=>(string)$student['department_id'],'office'=>(string)$office,'status'=>'COMPLETED'],['year'=>"' OR 1=1 --"]] as $filters) {
                clearance_report_data($pdo,['report'=>$kind]+$filters);
            }
        } else { clearance_report_data($pdo,['report'=>$kind,'from'=>'2000-01-01','to'=>'2099-12-31']); }
        echo 'PASS: '.$title." queries, metadata and filters\n";
    }
    $status=clearance_report_data($pdo,['report'=>'status']+$base)['rows'];
    $counts=[]; foreach($status as $row){$counts[$row['Level'].':'.$row['Status']]=(int)$row['Total'];}
    report_check(($counts['Request:COMPLETED']??0)===1&&($counts['Office stage:APPROVED']??0)===11,'Request/stage status counts are mixed.');
    $time=clearance_report_data($pdo,['report'=>'processing']+$base)['rows'];
    report_check(count($time)===1&&(float)$time[0]['Average_hours']===48.0,'Elapsed clearance hours incorrect.');
    $officers=clearance_report_data($pdo,['report'=>'officers']+$base)['rows'];
    report_check(array_sum(array_column($officers,'Decisions'))===11,'Officer decisions double-counted.');
    foreach ($officers as $row) { report_check((float)$row['Average_elapsed_hours']===48.0,'Officer elapsed hours incorrect.'); }
    $cert=clearance_report_data($pdo,['report'=>'certificates']+$base)['rows'];
    report_check(count($cert)===1&&$cert[0]['Snapshot_integrity']==='Legacy certificate record','Legacy certificate incorrectly marked verified.');
    report_check(count(clearance_report_data($pdo,['report'=>'completed','year'=>'2098/2099'])['rows'])===0,'Unknown year leaked results.');
    foreach ([['from'=>'2040-02-31'],['from'=>'2041-01-01','to'=>'2040-01-01'],['report'=>'bad'],['report'=>'access','office'=>'1'],['status'=>'APPROVED'],['office'=>'1 OR 1=1']] as $bad) {
        $blocked=false; try {clearance_report_data($pdo,$bad);} catch(InvalidArgumentException $e){$blocked=true;}
        report_check($blocked,'Invalid filter accepted.');
    }
    report_check(clearance_report_csv_cell('=1+1')==="'=1+1"&&clearance_report_csv_cell("\t@SUM(1)")==="'\t@SUM(1)",'CSV formula protection failed.');
    report_check($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)===$tables,'Reporting changed database tables.');
    echo 'PASS: accurate status totals, 48-hour processing times, officer counts, legacy certificate state, invalid filters, CSV protection and unchanged '.count($tables)." tables.\n";
    $temp=sys_get_temp_dir().'/irdp-reports-'.bin2hex(random_bytes(8)); mkdir($temp); mkdir($temp.'/sessions');
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error); report_check((bool)$socket,'Test port unavailable.');
    $address=stream_socket_get_name($socket,false); fclose($socket); $origin='http://'.$address;
    $env=getenv();$env['DB_HOST']='127.0.0.1';$env['DB_PORT']='3306';$env['DB_NAME']=$database;$env['DB_USER']=getenv('IRDP_TEST_USER')?:'root';$env['DB_PASS']=getenv('IRDP_TEST_PASSWORD')?:'';$env['IRDP_SMTP_ENABLED']='0';
    if(isset($argv[1])&&preg_match('/(?:^|;)port=([0-9]+)/',$argv[1],$m)){$env['DB_PORT']=$m[1];}
    $web=proc_open([PHP_BINARY,'-d','session.save_path="'.str_replace('\\','/',$temp).'/sessions"','-S',$address,'-t',dirname(__DIR__),__DIR__.'/http_router.php'],[0=>['pipe','r'],1=>['file',$temp.'/http.log','a'],2=>['file',$temp.'/http.log','a']],$pipes,dirname(__DIR__),$env);
    report_check(is_resource($web),'Web fixture failed.'); fclose($pipes[0]);
    for($i=0;$i<50;$i++){ $ready=@stream_socket_client('tcp://'.$address,$errno,$error,.1);if($ready){fclose($ready);break;}usleep(100000); }
    $cookie=''; [$code]=report_http($origin,'/reports/generate.php?report=finance&format=csv',$cookie);
    report_check($code===302,'Anonymous report access allowed.');
    foreach (['admin'=>'Admin@IRDP2026','FIN001'=>'Mollel@2026'] as $username=>$password) {
        $cookie=''; [$code,$body]=report_http($origin,'/auth/login.php',$cookie);
        report_check((bool)preg_match('/name="csrf_token" value="([^"]+)"/',$body,$m),'Login token missing.');
        [$code]=report_http($origin,'/auth/login.php',$cookie,['csrf_token'=>$m[1],'username'=>$username,'password'=>$password]);
        report_check($code===302,'Fixture login failed.');
        if($username!=='admin'){
            foreach(['/admin/reports.php','/reports/generate.php?report=finance&format=csv'] as $path){[$code]=report_http($origin,$path,$cookie);report_check($code===403,'Officer accessed admin reports.');}
            continue;
        }
        foreach(clearance_report_catalog() as $kind=>$title){
            [$code,$body]=report_http($origin,'/admin/reports.php?report='.$kind,$cookie);
            report_check($code===200&&str_contains($body,e($title))&&str_contains($body,'Download CSV'),'Report page failed: '.$kind);
            [$code,$body,$headers]=report_http($origin,'/reports/generate.php?report='.$kind.'&format=csv',$cookie);
            report_check($code===200&&str_contains($headers,'text/csv')&&str_starts_with($body,"\xEF\xBB\xBF"),'CSV download failed: '.$kind);
            [$code,$body]=report_http($origin,'/reports/generate.php?report='.$kind.'&format=pdf',$cookie);
            report_check($code===200&&str_starts_with($body,'%PDF-'),'PDF download failed: '.$kind);
        }
        [$code]=report_http($origin,'/reports/generate.php?report=bad',$cookie); report_check($code===400,'Invalid export did not return 400.');
        [$code,$body]=report_http($origin,'/reports/generate.php?type=weekly',$cookie); report_check($code===200&&str_starts_with($body,'%PDF-'),'Legacy weekly PDF failed.');
    }
    echo "PASS: all 14 report pages, CSV and PDF downloads, legacy PDF, validation and admin-only HTTP access.\n";
} catch(Throwable $e) { fwrite(STDERR,'FAIL: '.$e->getMessage()."\n"); $exit=1; }
finally {
    if(is_resource($web)){proc_terminate($web);proc_close($web);}
    if($created&&$server&&preg_match('/^irdp_reports_[a-f0-9]{16}$/D',$database)){$server->exec('DROP DATABASE '.$database);}
    if($temp&&is_dir($temp)&&realpath(dirname($temp))===realpath(sys_get_temp_dir())&&preg_match('/^irdp-reports-[a-f0-9]{16}$/D',basename($temp))){
        foreach(new DirectoryIterator($temp.'/sessions') as $file){if($file->isFile()){unlink($file->getPathname());}}rmdir($temp.'/sessions');
        foreach(new DirectoryIterator($temp) as $file){if($file->isFile()){unlink($file->getPathname());}}rmdir($temp);
    }
}
exit($exit);
