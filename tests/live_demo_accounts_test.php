<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Command line only.');}
require_once __DIR__.'/../includes/installer.php';
require_once __DIR__.'/../includes/functions.php';

function live_check(bool $condition,string $message): void {if(!$condition){throw new RuntimeException($message);}}
function live_http(string $path,array $post=[],?string $cookie=null,bool $isPost=false): array {
    $curl=curl_init('http://127.0.0.1:18089'.$path);curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>false]);
    if($cookie){curl_setopt($curl,CURLOPT_COOKIEFILE,$cookie);curl_setopt($curl,CURLOPT_COOKIEJAR,$cookie);}
    if($isPost){curl_setopt($curl,CURLOPT_POST,true);curl_setopt($curl,CURLOPT_POSTFIELDS,$post);}
    $body=curl_exec($curl);$code=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$error=curl_error($curl);curl_close($curl);
    if($body===false){throw new RuntimeException('HTTP request failed: '.$error);}return [$code,$body];
}
function live_token(string $html): string {live_check((bool)preg_match('/name="csrf_token" value="([^"]+)"/',$html,$match),'Login CSRF token missing.');return html_entity_decode($match[1],ENT_QUOTES,'UTF-8');}

$pdo=null;$web=null;$exit=0;$files=[];
try{
    $pdo=application_database();$definitions=require __DIR__.'/../config/demo_students.php';$readme=file_get_contents(__DIR__.'/../README.md');$records=[];
    foreach($definitions as $definition){$password=demo_student_initial_password($definition);$stmt=$pdo->prepare('SELECT u.id,u.username,u.password_hash,u.full_name,u.active,u.force_password_change,s.id AS student_id,s.registration_number,s.programme FROM users u INNER JOIN students s ON s.user_id=u.id WHERE u.username=?');$stmt->execute([$definition['registration_number']]);$record=$stmt->fetch();live_check((bool)$record,'Live demo account missing: '.$definition['registration_number']);live_check(password_verify($password,$record['password_hash'])&&$record['password_hash']!==$password,'Live demo password hash mismatch: '.$definition['registration_number']);live_check((password_get_info($record['password_hash'])['algoName']??'')==='argon2id','Live demo hash is not Argon2id: '.$definition['registration_number']);live_check((int)$record['active']===1&&(int)$record['force_password_change']===0&&$record['full_name']===$definition['full_name']&&$record['programme']===$definition['programme'],'Live demo identity/status mismatch: '.$definition['registration_number']);live_check(str_contains($readme,'| '.$definition['full_name'].' | '.$definition['registration_number'].' | '.$password.' | '.$definition['programme'].' |'),'README mismatch: '.$definition['registration_number']);$records[]=$record+['password'=>$password];}
    live_check(count(array_unique(array_column($records,'username')))===5,'Live demo registration numbers are not unique.');
    $index=$pdo->query("SELECT NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clearance_requests' AND INDEX_NAME='unique_student_clearance_cycle' LIMIT 1")->fetchColumn();live_check($index!==false&&(int)$index===0,'Live student-cycle unique index is missing.');
    $log=__DIR__.'/live-demo-http.log';$files[]=$log;$sessionDirectory=__DIR__.'/.live-demo-sessions';if(!is_dir($sessionDirectory)){mkdir($sessionDirectory,0700);}
    $web=proc_open([PHP_BINARY,'-d','session.save_path='.$sessionDirectory,'-S','127.0.0.1:18089','-t',dirname(__DIR__),__DIR__.'/http_router.php'],[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,dirname(__DIR__),getenv());live_check(is_resource($web),'Live verification server failed to start.');fclose($pipes[0]);
    $ready=false;for($attempt=0;$attempt<30;$attempt++){$socket=@fsockopen('127.0.0.1',18089,$number,$error,0.2);if($socket){fclose($socket);$ready=true;break;}usleep(100000);}live_check($ready,'Live verification server was unavailable.');
    foreach($records as $index=>$record){$cookie=__DIR__.'/live-demo-'.$index.'.cookies';$files[]=$cookie;[$code,$login]=live_http('/auth/login.php',[],$cookie);live_check($code===200,'Live login page unavailable.');[$code]=live_http('/auth/login.php',['csrf_token'=>live_token($login),'username'=>$record['username'],'password'=>$record['password']],$cookie,true);live_check($code===302,'Live login failed: '.$record['username']);[$code,$dashboard]=live_http('/student/dashboard.php',[],$cookie);live_check($code===200&&str_contains($dashboard,$record['registration_number'])&&!str_contains($dashboard,'name="current_password"'),'Live dashboard/forced-password check failed: '.$record['username']);[$code,$change]=live_http('/auth/change_password.php',[],$cookie);live_check($code===200&&str_contains($change,'name="current_password"'),'Live optional password page missing: '.$record['username']);$other=$records[($index+1)%5];[$code]=live_http('/files/profile.php?student='.$other['student_id'],[],$cookie);live_check($code===403,'Live profile isolation failed: '.$record['username']);echo 'PASS: live '.$record['username']." login, dashboard and profile isolation\n";}
    echo "PASS: all five live demo credentials match README and use Argon2id; unique cycle index present\n";
}catch(Throwable $error){fwrite(STDERR,'FAIL: '.$error->getMessage().PHP_EOL);$exit=1;}
finally{if(is_resource($web)){proc_terminate($web);proc_close($web);}foreach($files as $file){if(is_file($file)){unlink($file);}}$target=realpath(__DIR__.'/.live-demo-sessions');$expected=realpath(__DIR__).DIRECTORY_SEPARATOR.'.live-demo-sessions';if($target&&strcasecmp($target,$expected)===0){foreach(new DirectoryIterator($target) as $entry){if($entry->isFile()&&preg_match('/^sess_[a-zA-Z0-9,-]+$/D',$entry->getFilename())){unlink($entry->getPathname());}}rmdir($target);}}
exit($exit);
