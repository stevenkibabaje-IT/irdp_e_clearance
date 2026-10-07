<?php
require_once __DIR__ . '/bootstrap.php';

$u = current_user();
$flashes = consume_flash();
$pageTitle = $pageTitle ?? app_name();
$notificationCount = 0;
$recoveryRequestCount = 0;

if ($u) {
    $noticeStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $noticeStmt->execute([(int) $u['id']]);
    $notificationCount = (int) $noticeStmt->fetchColumn();
    if ($u['role'] === 'ADMIN') {
        $recoveryRequestCount = (int)$pdo->query('SELECT COUNT(*) FROM password_reset_requests WHERE status IN ("PENDING","ISSUED")')->fetchColumn();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="IRDP Student Clearance System">
    <title><?= e($pageTitle) ?> | IRDP</title>
    <link rel="icon" type="image/png" href="<?= e(url('assets/img/irdp-logo-web.png')) ?>">
    <link rel="preload" href="<?= e(url('assets/fonts/roboto-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css?v=' . filemtime(__DIR__.'/../assets/css/style.css'))) ?>">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content / Nenda kwenye maudhui</a>
<?php if ($u): ?>
    <div class="app-shell">
        <div class="navigation-backdrop" id="navigationBackdrop" aria-hidden="true" hidden></div>
        <aside class="sidebar" id="sidebar" aria-label="Main navigation">
            <button class="btn secondary navigation-close" id="navigationClose" type="button"><?= icon('x') ?> Close navigation</button>
            <div class="sidebar-brand">
                <img class="irdp-emblem" src="<?= e(url('assets/img/irdp-logo-web.png')) ?>" width="60" height="60" alt="Institute of Rural Development Planning (IRDP) logo">
                <div>
                    <strong>IRDP</strong>
                    <span>Student Clearance</span>
                </div>
            </div>

            <nav class="sidebar-nav" aria-label="Main navigation">
                <?php if ($u['role'] === 'STUDENT'): ?>
                    <a href="<?= e(url('student/dashboard.php')) ?>"><?= icon('home') ?> <span>Dashboard</span></a>
                    <a href="<?= e(url('student/start.php')) ?>"><?= icon('plus') ?> <span>Start Clearance</span></a>
                    <a href="<?= e(url('student/status.php')) ?>"><?= icon('clipboard-check') ?> <span>My Clearance</span></a>
                    <a href="<?= e(url('student/profile.php')) ?>"><?= icon('user') ?> <span>My Profile</span></a>
                    <a href="<?= e(url('student/notifications.php')) ?>">
                        <?= icon('bell') ?> <span>Notifications</span>
                        <?php if ($notificationCount > 0): ?><b class="nav-count"><?= $notificationCount ?></b><?php endif; ?>
                    </a>
                <?php elseif (in_array($u['role'], ['OFFICER', 'SUPERVISOR'], true)): ?>
                    <a href="<?= e(url('officer/dashboard.php')) ?>"><?= icon('home') ?> <span>Dashboard</span></a>
                    <a href="<?= e(url('officer/dashboard.php')) ?>"><?= icon('clipboard-check') ?> <span>Pending Clearance</span></a>
                <?php else: ?>
                    <a href="<?= e(url('admin/dashboard.php')) ?>"><?= icon('home') ?> <span>Dashboard</span></a>
                    <a href="<?= e(url('admin/users.php')) ?>"><?= icon('users') ?> <span>Users</span></a>
                    <a href="<?= e(url('admin/recovery.php')) ?>"><?= icon('key') ?> <span>Password Reset Requests</span><?php if ($recoveryRequestCount > 0): ?><b class="nav-count"><?= $recoveryRequestCount ?></b><?php endif; ?></a>
                    <a href="<?= e(url('admin/programmes.php')) ?>"><?= icon('book-open') ?> <span>Programmes</span></a>
                    <a href="<?= e(url('admin/clearance_period.php')) ?>"><?= icon('calendar') ?> <span>Clearance Period</span></a>
                    <a href="<?= e(url('admin/transcripts.php')) ?>"><?= icon('file-text') ?> <span>Clearance Transcripts</span></a>
                    <a href="<?= e(url('admin/offices.php')) ?>"><?= icon('building') ?> <span>Offices</span></a>
                    <a href="<?= e(url('admin/departments.php')) ?>"><?= icon('departments') ?> <span>Departments</span></a>
                    <a href="<?= e(url('admin/workflow.php')) ?>"><?= icon('workflow') ?> <span>Workflow</span></a>
                    <a href="<?= e(url('admin/clearances.php')) ?>"><?= icon('clipboard-check') ?> <span>Clearance Monitoring</span></a>
                    <a href="<?= e(url('admin/reports.php')) ?>"><?= icon('chart') ?> <span>Reports</span></a>
                <?php endif; ?>

                <?php if ($u['role'] !== 'STUDENT'): ?><a href="<?= e(url('student/notifications.php')) ?>"><?= icon('bell') ?> <span>Notifications</span></a><?php endif; ?>
                <a href="<?= e(url('auth/change_password.php')) ?>"><?= icon('key') ?> <span>Change Password</span></a>
                <form method="post" action="<?= e(url('auth/logout.php')) ?>"><?php csrf_field(); ?><button class="btn secondary full" type="submit"><?= icon('log-out') ?> Log out</button></form>
            </nav>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <button
                    class="menu-btn"
                    id="navigationToggle"
                    type="button"
                    aria-label="Open navigation"
                    aria-controls="sidebar"
                    aria-expanded="false"
                >
                    <?= icon('menu') ?>
                </button>

                <div class="topbar-title"><?= e($pageTitle) ?></div>

                <div class="user-chip">
                    <?php if ($u['role'] === 'STUDENT' && ($profileStudent = get_student($pdo, (int)$u['student_id']))): ?>
                        <a href="<?= e(url('student/profile.php')) ?>" aria-label="My profile"><img class="avatar profile-avatar" src="<?= e(profile_picture_url($profileStudent)) ?>" width="40" height="40" alt="My profile picture"></a>
                    <?php else: ?>
                        <span class="avatar"><?= e(strtoupper(substr($u['full_name'], 0, 1))) ?></span>
                    <?php endif; ?>
                    <div>
                        <strong title="<?= e($u['full_name']) ?>"><?= e($u['full_name']) ?></strong>
                        <small><?= e(ucfirst(strtolower($u['role']))) ?></small>
                    </div>
                </div>

                <form class="topbar-logout" method="post" action="<?= e(url('auth/logout.php')) ?>">
                    <?php csrf_field(); ?>
                    <button class="btn secondary" type="submit"><?= icon('log-out') ?> Log out</button>
                </form>
            </header>

            <section class="content" id="main-content" tabindex="-1">
                <?php require __DIR__.'/accessibility.php'; ?>
                <?php foreach ($flashes as $flash): ?>
                    <div class="alert <?= e($flash['type']) ?>" role="alert">
                        <?= icon(match ($flash['type']) { 'success' => 'check-circle', 'danger' => 'alert-triangle', 'warning' => 'alert-triangle', default => 'info' }) ?>
                        <?= e($flash['message']) ?>
                    </div>
                <?php endforeach; ?>
<?php else: ?>
    <main class="public-content" id="main-content" tabindex="-1">
        <?php require __DIR__.'/accessibility.php'; ?>
<?php endif; ?>
