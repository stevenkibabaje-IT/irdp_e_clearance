<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

$checks = [];
$checks['PHP version'] = PHP_VERSION;
$checks['PHP 8+'] = version_compare(PHP_VERSION, '8.0.0', '>=') ? 'PASS' : 'FAIL';

try {
    $pdo = db_connect(false);
    $checks['MySQL server connection'] = 'PASS';

    $pdo->exec('CREATE DATABASE IF NOT EXISTS irdp_e_clearance');
    $db = db_connect(true);
    $checks['irdp_e_clearance database'] = 'PASS';

    $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $required = [
        'roles', 'users', 'students', 'offices', 'workflow_steps',
        'clearance_requests', 'clearance_stages', 'stage_actions',
        'notifications', 'certificates', 'audit_logs',
    ];

    $missing = array_values(array_diff($required, $tables));
    $checks['Core database tables'] = $missing ? 'MISSING: ' . implode(', ', $missing) : 'PASS';
} catch (Throwable $e) {
    $checks['MySQL server connection'] = 'FAIL: ' . $e->getMessage();
}

$checks['Logo asset'] = is_file(__DIR__ . '/assets/img/irdp-logo.jpg') ? 'PASS' : 'MISSING';
$checks['Campus image asset'] = is_file(__DIR__ . '/assets/img/irdp-campus.jpg') ? 'PASS' : 'MISSING';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IRDP System Diagnostics</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="simple-page">
    <section class="simple-card">
        <img class="small-logo" src="assets/img/irdp-logo.jpg" alt="IRDP logo">
        <h2>IRDP System Diagnostics</h2>
        <p class="muted">Use this page only when troubleshooting the local XAMPP installation.</p>

        <?php foreach ($checks as $name => $result): ?>
            <div class="panel" style="margin-bottom:10px; box-shadow:none;">
                <strong><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></strong><br>
                <span class="muted"><?= htmlspecialchars((string) $result, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endforeach; ?>

        <a class="btn primary full" href="index.php">Back to System</a>
    </section>
</body>
</html>
