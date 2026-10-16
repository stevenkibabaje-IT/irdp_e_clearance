<?php
declare(strict_types=1);

// Inspect by default. --apply configures the FMS departmental review account.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Command line only.'); }
require_once __DIR__ . '/../config/application.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
$pdo = db_connect();
$department = $pdo->query("SELECT id,name FROM departments WHERE code='FMS' AND active=1")->fetch();
$office = $pdo->query('SELECT o.id,o.name FROM workflow_steps ws INNER JOIN offices o ON o.id=ws.office_id
    WHERE ws.step_number=7 AND ws.active=1 AND o.active=1')->fetch();
if (!$department || !$office) { fwrite(STDERR, "Active FMS department and departmental office are required.\n"); exit(1); }
$departmentId = (int)$department['id'];
$officeId = (int)$office['id'];
if (!in_array('--apply', $argv, true)) {
    $reviewer = find_office_reviewer($pdo, $officeId, $departmentId);
    echo json_encode(['department'=>$department['name'], 'office'=>$office['name'],
        'reviewer_id'=>$reviewer, 'proposed_username'=>'FMS001'], JSON_PRETTY_PRINT) . PHP_EOL;
    exit;
}

$password = null;
$pdo->beginTransaction();
try {
    lock_account_creation($pdo);
    $lock = $pdo->prepare('SELECT id FROM departments WHERE id=? AND active=1 FOR UPDATE');
    $lock->execute([$departmentId]);
    if (!$lock->fetchColumn()) { throw new RuntimeException('FMS is no longer active.'); }
    $reviewer = find_office_reviewer($pdo, $officeId, $departmentId);
    if (!$reviewer) {
        $find = $pdo->prepare('SELECT u.*,r.name AS role_name FROM users u INNER JOIN roles r ON r.id=u.role_id WHERE u.username=? FOR UPDATE');
        $find->execute(['FMS001']);
        $existing = $find->fetch();
        if ($existing) {
            if (!(int)$existing['active'] || $existing['role_name'] !== 'OFFICER' || (int)$existing['department_id'] !== $departmentId) {
                throw new RuntimeException('FMS001 belongs to a different or inactive account. No changes saved.');
            }
            $reviewer = (int)$existing['id'];
        } else {
            $role = (int)$pdo->query("SELECT id FROM roles WHERE name='OFFICER'")->fetchColumn();
            if (!$role) { throw new RuntimeException('Officer role is not configured.'); }
            $password = 'Fms!' . bin2hex(random_bytes(12)) . 'A7';
            password_policy($password, $password);
            $pdo->prepare('INSERT INTO users(username,password_hash,full_name,role_id,department_id,active,force_password_change)
                VALUES (?,?,?,?,?,1,1)')->execute(['FMS001', hash_new_password($password), 'Head of Department FMS', $role, $departmentId]);
            $reviewer = (int)$pdo->lastInsertId();
        }
        $pdo->prepare('INSERT IGNORE INTO user_offices(user_id,office_id) VALUES (?,?)')->execute([$reviewer,$officeId]);
    }

    $pending = $pdo->prepare('SELECT cs.id,cs.clearance_request_id,cs.assigned_officer_id,cs.original_officer_id,
        cs.assignment_status,cs.assignment_source FROM clearance_stages cs
        INNER JOIN clearance_requests cr ON cr.id=cs.clearance_request_id
        INNER JOIN students s ON s.id=cr.student_id
        INNER JOIN workflow_steps ws ON ws.id=cs.workflow_step_id
        WHERE s.department_id=? AND ws.step_number=7 AND cs.office_id=?
        AND cr.status IN ("IN_PROGRESS","PAUSED") AND cs.status IN ("PENDING","IN_REVIEW","REJECTED")
        AND NOT(cs.assigned_officer_id <=> ?) ORDER BY cr.id,cs.id FOR UPDATE');
    $pending->execute([$departmentId,$officeId,$reviewer]);
    $stages = $pending->fetchAll();
    if ($stages) {
        $directory = PRIVATE_STORAGE;
        if (!is_dir($directory) && !mkdir($directory,0700,true)) { throw new RuntimeException('Assignment backup directory unavailable.'); }
        $backup = $directory . '/fms-review-assignments-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
        if (file_put_contents($backup,json_encode($stages,JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),LOCK_EX) === false) {
            throw new RuntimeException('Assignment backup failed.');
        }
        $update = $pdo->prepare('UPDATE clearance_stages SET assigned_officer_id=?,assignment_status="ASSIGNED",
            assignment_source="ORIGINAL",original_officer_id=COALESCE(original_officer_id,?) WHERE id=?');
        foreach ($stages as $stage) { $update->execute([$reviewer,$reviewer,$stage['id']]); }
    }
    if (find_office_reviewer($pdo,$officeId,$departmentId) !== $reviewer
        || !office_authority($pdo,$reviewer,$officeId,$departmentId)) {
        throw new RuntimeException('FMS review authority verification failed.');
    }
    $account = user_record($pdo,$reviewer);
    if ($password !== null && !password_verify($password,$account['password_hash'])) { throw new RuntimeException('Password verification failed.'); }
    $pdo->prepare('INSERT INTO audit_logs(action,details) VALUES (?,?)')->execute(['FMS_HEAD_ASSIGNED',json_encode([
        'user_id'=>$reviewer,'department_id'=>$departmentId,'office_id'=>$officeId,
        'account_created'=>$password !== null,'reassigned_stage_ids'=>array_column($stages,'id'),
    ],JSON_THROW_ON_ERROR)]);
    $pdo->commit();
    echo json_encode(['username'=>$account['username'],'name'=>$account['full_name'],
        'department'=>$department['name'],'office'=>$office['name'],'active'=>(bool)$account['active'],
        'force_password_change'=>(bool)$account['force_password_change'],
        'temporary_password'=>$password,'reassigned_stages'=>count($stages)],JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $error) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR,$error->getMessage() . PHP_EOL);
    exit(1);
}
