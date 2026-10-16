<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/../includes/installer.php';
require __DIR__ . '/../includes/demo_account_definitions.php';

try {
    $db = db_connect();
    if (!database_schema_ready($db)) { throw new RuntimeException('Original schema is not ready.'); }
    if (count($db->query('SHOW TABLES')->fetchAll()) !== 30) { throw new RuntimeException('Expected 30 application tables.'); }
    $accounts = documented_demo_accounts();
    $verifiedPasswords = 0;
    $requiresPasswordChange = [];
    foreach ($accounts as $account) {
        $query = $db->prepare('SELECT u.*,r.name AS role,s.registration_number FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN students s ON s.user_id=u.id WHERE u.username=?');
        $query->execute([$account['username']]);
        $user = $query->fetch();
        if (!$user || !$user['active'] || $user['role'] !== $account['role']) {
            throw new RuntimeException('Missing, inactive or incorrect role: ' . $account['username']);
        }
        if ($account['password'] !== null) {
            if (!password_verify($account['password'], $user['password_hash'])) { throw new RuntimeException('Changed documented password: ' . $account['username']); }
            $verifiedPasswords++;
        } else {
            echo 'NOTE: current password is not published; password check skipped for ' . $account['username'] . PHP_EOL;
        }
        if ($account['email'] !== '' && strcasecmp($account['email'], (string)$user['email']) !== 0) {
            throw new RuntimeException('Documented email mismatch: ' . $account['username']);
        }
        $requiresPasswordChange[$account['username']] = $user['role'] !== 'STUDENT' && (bool)$user['force_password_change'];
        if ($account['role'] === 'STUDENT' && $user['registration_number'] !== $account['username']) {
            throw new RuntimeException('Student identity mismatch: ' . $account['username']);
        }
        $officeAssignments = ['FMS001'=>[7,'FMS'], 'EPM001'=>[7,'EPM'], 'FIN001'=>[11,null]];
        if (isset($officeAssignments[$account['username']])) {
            [$step, $department] = $officeAssignments[$account['username']];
            $query = $db->prepare('SELECT d.code FROM users u LEFT JOIN departments d ON d.id=u.department_id JOIN user_offices uo ON uo.user_id=u.id JOIN offices o ON o.id=uo.office_id AND o.active=1 JOIN workflow_steps ws ON ws.office_id=uo.office_id AND ws.active=1 WHERE u.id=? AND ws.step_number=?');
            $query->execute([$user['id'],$step]);$assignment=$query->fetch();
            if (!$assignment || ($department !== null && $assignment['code'] !== $department)) { throw new RuntimeException('Incorrect departmental/Finance authority: ' . $account['username']); }
        }
    }
    echo 'PASS: all ' . count($accounts) . ' documented accounts are active; ' . $verifiedPasswords . " published passwords match; emails, FMS/EPM/Finance assignments and schema verified.\n";
    // Optional Apache checks create normal LOGIN audit records; no emails are sent.
    foreach (array_slice($argv, 1) as $username) {
        if (!isset($accounts[$username])) { throw new RuntimeException('Unknown documented username.'); }
        $account = $accounts[$username];
        if ($account['password'] === null) { throw new RuntimeException('Cannot run HTTP login: current password is not published for ' . $username); }
        $base = 'http://127.0.0.1/Irdp_e_clearance';
        $curl = curl_init();
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 20, CURLOPT_COOKIEFILE => '', CURLOPT_FOLLOWLOCATION => false]);
        try {
            curl_setopt($curl, CURLOPT_URL, $base . '/auth/login.php');
            $login = curl_exec($curl);
            if ($login === false || !preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $login, $token)) { throw new RuntimeException('Login form unavailable.'); }
            curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['username' => $username, 'password' => $account['password'], 'csrf_token' => $token[1]])]);
            $response = curl_exec($curl);
            if ($response === false || (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 302) { throw new RuntimeException('HTTP login failed: ' . $username); }
            $rolePath = strtolower($account['role']);
            curl_setopt_array($curl, [CURLOPT_HTTPGET => true, CURLOPT_URL => $base . '/' . $rolePath . '/dashboard.php']);
            $dashboard = curl_exec($curl);
            if ($requiresPasswordChange[$username]) {
                if ($dashboard === false || (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 302 || !preg_match('~^Location:[^\r\n]*/auth/change_password\.php\s*$~mi',$dashboard)) { throw new RuntimeException('Mandatory password change did not block the dashboard: ' . $username); }
                curl_setopt($curl, CURLOPT_URL, $base . '/auth/change_password.php');
                $dashboard = curl_exec($curl);
                if ($dashboard === false || (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 200 || !str_contains($dashboard, 'name="current_password"')) { throw new RuntimeException('Required password-change form unavailable: ' . $username); }
                echo 'PASS: HTTP login, mandatory password-change form and blocked dashboard: ' . $username . PHP_EOL;
            } else {
                if ($dashboard === false || (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 200 || !str_contains($dashboard, 'auth/logout.php')) { throw new RuntimeException('Authenticated dashboard failed: ' . $username); }
                echo 'PASS: HTTP login and ' . $rolePath . ' dashboard: ' . $username . PHP_EOL;
            }
            if (!preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $dashboard, $logoutToken)) { throw new RuntimeException('Logout CSRF token missing.'); }
            curl_setopt_array($curl, [CURLOPT_URL => $base . '/auth/logout.php', CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['csrf_token' => $logoutToken[1]])]);
            if (curl_exec($curl) === false || (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 302) { throw new RuntimeException('Logout failed: ' . $username); }
            curl_setopt_array($curl, [CURLOPT_HTTPGET => true, CURLOPT_URL => $base . '/' . $rolePath . '/dashboard.php']);
            curl_exec($curl);
            if ((int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 302) { throw new RuntimeException('Dashboard remained accessible after logout.'); }
        } finally { curl_close($curl); }
    }
} catch (Throwable $error) { fwrite(STDERR, 'FAIL: ' . $error->getMessage() . PHP_EOL); exit(1); }
