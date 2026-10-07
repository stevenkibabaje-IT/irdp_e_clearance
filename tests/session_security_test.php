<?php
declare(strict_types=1);
// HTTP security checks use a disposable database and private, disposable sessions.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Command line only.'); }
require_once __DIR__.'/../includes/installer.php';
require_once __DIR__.'/../includes/functions.php';

function session_check(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
final class SessionTestBrowser {
    public string $cookie = '';
    public function __construct(private string $origin) {}
    public function request(string $path, ?array $post = null): array {
        $handle = curl_init($this->origin.$path);
        curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HEADER=>true, CURLOPT_TIMEOUT=>30,
            CURLOPT_FOLLOWLOCATION=>false, CURLOPT_COOKIE=>$this->cookie]);
        if ($post !== null) { curl_setopt_array($handle, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>http_build_query($post)]); }
        $raw = curl_exec($handle);
        if ($raw === false) { throw new RuntimeException(curl_error($handle)); }
        $code = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $headerLength = (int)curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        curl_close($handle);
        $headers = substr($raw, 0, $headerLength);
        if (preg_match('/^Set-Cookie:\s*(PHPSESSID=[^;]*)/mi', $headers, $match)) {
            $this->cookie = in_array($match[1], ['PHPSESSID=', 'PHPSESSID=deleted'], true) ? '' : $match[1];
        }
        return [$code, substr($raw, $headerLength), $headers];
    }
    public function login(string $username, string $password): string {
        [$code,$html] = $this->request('/auth/login.php');
        session_check($code===200 && preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $html, $match)===1, 'Login form unavailable.');
        $anonymousId = $this->id();
        [$code] = $this->request('/auth/login.php', ['csrf_token'=>$match[1], 'username'=>$username, 'password'=>$password]);
        session_check($code===302 && $anonymousId !== $this->id(), 'Login failed or session ID was not rotated.');
        return $this->token();
    }
    public function id(): string { return substr($this->cookie, strlen('PHPSESSID=')); }
    public function token(): string {
        [$code,$html] = $this->request('/auth/change_password.php');
        session_check($code===200 && preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $html, $match)===1, 'Authenticated form unavailable.');
        return $match[1];
    }
    public function state(): array {
        [$code,$body,$headers] = $this->request('/auth/session.php');
        session_check($code===200 && str_contains($headers,'application/json'), 'Session status unavailable.');
        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }
}
function age_test_session(string $directory, string $id, array $timestamps): void {
    session_check((bool)preg_match('/^[a-zA-Z0-9,-]+$/D', $id), 'Invalid test session ID.');
    $path = $directory.'/sess_'.$id;
    $encoded = file_get_contents($path);
    foreach ($timestamps as $key=>$value) {
        $encoded = preg_replace('/'.preg_quote($key,'/').'\|i:[0-9]+;/', $key.'|i:'.$value.';', $encoded, 1, $count);
        session_check($count===1, 'Missing test timestamp: '.$key);
    }
    file_put_contents($path, $encoded);
}

$dsn = $argv[1] ?? 'mysql:host=127.0.0.1;port=3306;charset=utf8mb4';
$database = 'irdp_sessions_'.bin2hex(random_bytes(8));
$temporary = sys_get_temp_dir().DIRECTORY_SEPARATOR.'irdp-sessions-'.bin2hex(random_bytes(8));
$server = null; $pdo = null; $web = null; $created = false; $exit = 0;
try {
    mkdir($temporary,0700); mkdir($temporary.'/sessions',0700);
    $server = new PDO($dsn,getenv('IRDP_TEST_USER')?:'root',getenv('IRDP_TEST_PASSWORD')?:'',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $server->exec('CREATE DATABASE '.$database.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created=true; $server->exec('USE '.$database); $pdo=ensure_database_ready($server);
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
    session_check((bool)$socket,'Test port unavailable.');
    $address=stream_socket_get_name($socket,false); fclose($socket); $port=(int)substr(strrchr($address,':'),1);
    preg_match('/(?:^|;)host=([^;]+)/',$dsn,$host); preg_match('/(?:^|;)port=(\d+)/',$dsn,$dbPort);
    $environment=getenv();
    $environment['DB_HOST']=$host[1]??'127.0.0.1'; $environment['DB_PORT']=$dbPort[1]??'3306';
    $environment['DB_NAME']=$database; $environment['DB_USER']=getenv('IRDP_TEST_USER')?:'root'; $environment['DB_PASS']=getenv('IRDP_TEST_PASSWORD')?:'';
    $web=proc_open([PHP_BINARY,'-d','session.save_path='.$temporary.'/sessions','-d','session.serialize_handler=php','-S','127.0.0.1:'.$port,'-t',dirname(__DIR__),__DIR__.'/http_router.php'],
        [0=>['pipe','r'],1=>['file',$temporary.'/http.log','a'],2=>['file',$temporary.'/http.log','a']],$pipes,dirname(__DIR__),$environment);
    session_check(is_resource($web),'HTTP server could not start.'); fclose($pipes[0]);
    for($attempt=0;$attempt<50;$attempt++) {
        $connection=@fsockopen('127.0.0.1',$port,$errno,$error,.1);
        if($connection){fclose($connection);break;} usleep(100000);
    }
    $browser=new SessionTestBrowser('http://127.0.0.1:'.$port);
    $chosen='attacker-chosen-session-12345'; $browser->cookie='PHPSESSID='.$chosen;
    [$code,,$headers]=$browser->request('/auth/login.php');
    session_check($code===200 && $browser->id()!==$chosen && !is_file($temporary.'/sessions/sess_'.$chosen),'Strict mode adopted a client-chosen session ID.');
    session_check(str_contains(strtolower($headers),'httponly') && str_contains($headers,'SameSite=Lax')
        && !preg_match('/^Set-Cookie:.*;\s*secure(?:;|\r?$)/mi',$headers),'Cookie security broke HTTP localhost.');
    echo "PASS: strict mode rejects uninitialized IDs; HttpOnly/SameSite cookies work over local HTTP\n";

    foreach ([['IRDP/ODICT/MA25/0001','KIBABAJE0001'],['FIN001','Mollel@2026'],['admin','Admin@IRDP2026']] as [$username,$password]) {
        $token=$browser->login($username,$password); $state=$browser->state();
        session_check($state['authenticated']===true && $state['idle_expires_at']-$state['server_time']>=SESSION_IDLE_SECONDS-2
            && $state['absolute_expires_at']-$state['server_time']>=SESSION_ABSOLUTE_SECONDS-2,'Login did not initialize session limits.');
        $id=$browser->id();
        age_test_session($temporary.'/sessions',$id,['last_activity_at'=>time()-120]);
        $before=$browser->state(); $again=$browser->state();
        session_check($again['idle_expires_at']===$before['idle_expires_at'],'Background polling extended idle expiry.');
        [$code,$body]=$browser->request('/auth/session.php',['action'=>'continue','csrf_token'=>str_repeat('0',64),'last_activity_at'=>(string)(time()+999999)]);
        session_check($code===419 && json_decode($body,true)['reason']==='csrf' && $browser->state()['idle_expires_at']===$before['idle_expires_at'],'Invalid CSRF renewed a session.');
        [$code,$body]=$browser->request('/auth/session.php',['action'=>'continue','csrf_token'=>$token]); $renewed=json_decode($body,true);
        session_check($code===200 && $renewed['idle_expires_at']>$before['idle_expires_at']
            && $renewed['absolute_expires_at']===$state['absolute_expires_at'],'Continuation failed or extended the absolute lifetime.');
        age_test_session($temporary.'/sessions',$id,['last_activity_at'=>time()-SESSION_IDLE_SECONDS]);
        [$code,$body]=$browser->request('/auth/session.php',['action'=>'continue','csrf_token'=>$token]);
        session_check($code===401 && json_decode($body,true)['reason']==='idle' && !is_file($temporary.'/sessions/sess_'.$id),'Expired idle session was revived.');
    }
    echo "PASS: Student, Finance and Admin expiry; read-only polling; CSRF renewal; expired sessions cannot be revived\n";

    $token=$browser->login('IRDP/ODICT/MA25/0001','KIBABAJE0001'); $id=$browser->id();
    $passwordHash=$pdo->query('SELECT password_hash FROM users WHERE username="IRDP/ODICT/MA25/0001"')->fetchColumn();
    age_test_session($temporary.'/sessions',$id,['last_activity_at'=>time()-SESSION_IDLE_SECONDS]);
    [$code,,$headers]=$browser->request('/auth/change_password.php',['csrf_token'=>$token,'current_password'=>'KIBABAJE0001','password'=>'Birch meadow compass 74','confirm_password'=>'Birch meadow compass 74']);
    session_check($code===302 && str_contains($headers,'session=idle')
        && $pdo->query('SELECT password_hash FROM users WHERE username="IRDP/ODICT/MA25/0001"')->fetchColumn()===$passwordHash,'Expired POST changed account data.');
    $token=$browser->login('IRDP/ODICT/MA25/0001','KIBABAJE0001'); $id=$browser->id();
    age_test_session($temporary.'/sessions',$id,['session_started_at'=>time()-SESSION_ABSOLUTE_SECONDS,'last_activity_at'=>time()]);
    [$code,$body]=$browser->request('/auth/session.php',['action'=>'continue','csrf_token'=>$token]);
    session_check($code===401 && json_decode($body,true)['reason']==='absolute','Active session bypassed the 8-hour maximum.');
    echo "PASS: expired POST blocked before mutation; active sessions cannot bypass absolute expiry\n";

    $token=$browser->login('IRDP/ODICT/MA25/0001','KIBABAJE0001');
    $pdo->exec('UPDATE users SET auth_version=auth_version+1 WHERE username="IRDP/ODICT/MA25/0001"');
    [$code,$body]=$browser->request('/auth/session.php');
    session_check($code===401 && json_decode($body,true)['reason']==='revoked','Status endpoint accepted a revoked account session.');
    $token=$browser->login('IRDP/ODICT/MA25/0001','KIBABAJE0001'); $id=$browser->id();
    [$code,,$headers]=$browser->request('/auth/logout.php',['csrf_token'=>$token]);
    session_check($code===302 && !is_file($temporary.'/sessions/sess_'.$id) && str_contains($headers,'SameSite=Lax'),'Logout left the authenticated session or cookie behind.');
    echo "PASS: revoked sessions and logout invalidate server state and cookies\n";
} catch(Throwable $error) {
    fwrite(STDERR,'FAIL: '.$error->getMessage().PHP_EOL); $exit=1;
} finally {
    if(is_resource($web)){proc_terminate($web);proc_close($web);} $pdo=null;
    if($created && $server && preg_match('/^irdp_sessions_[a-f0-9]{16}$/D',$database)){$server->exec('DROP DATABASE '.$database);echo "Isolated session database removed.\n";}
    $target=realpath($temporary); $expected=realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.basename($temporary);
    if($target && strcasecmp($target,$expected)===0 && preg_match('/^irdp-sessions-[a-f0-9]{16}$/D',basename($target))) {
        foreach(new DirectoryIterator($target.'/sessions') as $entry){if($entry->isFile()){unlink($entry->getPathname());}}
        rmdir($target.'/sessions'); if(is_file($target.'/http.log')){unlink($target.'/http.log');} rmdir($target);
    }
}
exit($exit);
