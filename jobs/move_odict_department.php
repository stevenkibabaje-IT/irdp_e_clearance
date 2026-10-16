<?php
declare(strict_types=1);

// Run from the command line. Inspection is the default; --apply performs the move.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Command line only.'); }
require_once __DIR__ . '/../config/database.php';
$pdo = db_connect();
echo json_encode([
    'database' => $pdo->query('SELECT DATABASE()')->fetchColumn(),
    'departments' => $pdo->query('SELECT id,code,name,active FROM departments ORDER BY name')->fetchAll(),
    'programmes' => $pdo->query('SELECT programme,COUNT(*) AS students FROM students GROUP BY programme')->fetchAll(),
    'odict_departments' => $pdo->query("SELECT s.department_id,d.name,COUNT(*) AS students
        FROM students s LEFT JOIN departments d ON d.id=s.department_id
        WHERE UPPER(TRIM(s.programme))='ODICT' GROUP BY s.department_id,d.name")->fetchAll(),
    'department_reviewers' => $pdo->query("SELECT d.code,COUNT(DISTINCT u.id) AS active_reviewers
        FROM departments d LEFT JOIN users u ON u.department_id=d.id AND u.active=1
        AND EXISTS(SELECT 1 FROM roles r WHERE r.id=u.role_id AND r.name IN ('OFFICER','SUPERVISOR'))
        AND EXISTS(SELECT 1 FROM user_offices uo INNER JOIN offices o ON o.id=uo.office_id
            WHERE uo.user_id=u.id AND o.name='Head of Department' AND o.active=1)
        GROUP BY d.id,d.code")->fetchAll(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (!in_array('--apply', $argv, true)) { exit; }

$pdo->beginTransaction();
try {
    $students = $pdo->query("SELECT s.id AS student_id,s.user_id,s.department_id AS student_department_id,
        u.department_id AS user_department_id
        FROM students s INNER JOIN users u ON u.id=s.user_id
        WHERE UPPER(TRIM(s.programme))='ODICT' ORDER BY s.id FOR UPDATE")->fetchAll();
    if (!$students) { throw new RuntimeException('No ODICT students found.'); }

    $find = $pdo->prepare('SELECT id,code,name,active FROM departments WHERE code=? OR name=? FOR UPDATE');
    $departmentName = 'Department of Finance and Management Studies';
    $find->execute(['FMS', $departmentName]);
    $departments = $find->fetchAll();
    if (count($departments) > 1) { throw new RuntimeException('Conflicting FMS departments; no changes saved.'); }
    $department = $departments[0] ?? null;
    if ($department && ($department['name'] !== $departmentName || !(int)$department['active'])) {
        throw new RuntimeException('Existing FMS department does not match the active destination; no changes saved.');
    }
    $changed = array_values(array_filter($students, static fn(array $row): bool => !$department
        || (int)$row['student_department_id'] !== (int)$department['id']
        || (int)$row['user_department_id'] !== (int)$department['id']));
    if (!$changed) { $pdo->rollBack(); echo "All ODICT students already belong to FMS.\n"; exit; }

    // Preserve only the original department references, never account credentials.
    $directory = __DIR__ . '/../storage/private';
    if (!is_dir($directory) && !mkdir($directory, 0700, true)) { throw new RuntimeException('Backup directory unavailable.'); }
    $backup = $directory . '/odict-departments-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
    if (file_put_contents($backup, json_encode([
        'database' => $pdo->query('SELECT DATABASE()')->fetchColumn(),
        'previous_departments' => $changed,
        'destination_department' => $departmentName,
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false) { throw new RuntimeException('Backup could not be saved.'); }

    if (!$department) {
        $pdo->prepare('INSERT INTO departments(code,name,active) VALUES (?,?,1)')->execute(['FMS', $departmentName]);
        $department = ['id' => (int)$pdo->lastInsertId()];
    }
    $updateStudent = $pdo->prepare('UPDATE students SET department_id=? WHERE id=? AND user_id=?');
    $updateUser = $pdo->prepare('UPDATE users SET department_id=? WHERE id=?');
    foreach ($changed as $student) {
        $updateStudent->execute([$department['id'], $student['student_id'], $student['user_id']]);
        $updateUser->execute([$department['id'], $student['user_id']]);
    }
    $verify = $pdo->prepare("SELECT COUNT(*) FROM students s INNER JOIN users u ON u.id=s.user_id
        WHERE UPPER(TRIM(s.programme))='ODICT' AND (NOT(s.department_id <=> ?) OR NOT(u.department_id <=> ?))");
    $verify->execute([$department['id'], $department['id']]);
    if ((int)$verify->fetchColumn() !== 0) { throw new RuntimeException('Department verification failed.'); }
    $audit = $pdo->prepare('INSERT INTO audit_logs(action,details) VALUES (?,?)');
    $audit->execute(['ODICT_DEPARTMENT_UPDATED', json_encode([
        'department_id' => $department['id'], 'department' => $departmentName,
        'student_ids' => array_column($changed, 'student_id'), 'backup' => basename($backup),
    ], JSON_THROW_ON_ERROR)]);
    $pdo->commit();
    echo 'Updated ' . count($changed) . ' ODICT students to ' . $departmentName . ".\n";
    echo 'Original department references saved in storage/private/' . basename($backup) . ".\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
