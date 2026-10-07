<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/installer.php';

$message = '';
$error = '';

try {
    ensure_database_ready();
    $message = 'Database and system configuration are ready.';
} catch (Throwable $e) {
    $error = $e->getMessage();
}
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

        <?php if ($error !== ''): ?>
            <div class="alert danger">
                <strong>System preparation failed.</strong><br>
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php else: ?>
            <div class="alert success">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>

            <h2>IRDP Student Clearance System</h2>
            <p class="muted">
                The application now prepares its database automatically when it starts.
                You normally do not need to open this page.
            </p>

            <a class="btn primary full" href="auth/login.php">Continue to Login</a>
        <?php endif; ?>
    </section>
</body>
</html>
