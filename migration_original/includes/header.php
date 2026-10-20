<?php
require_once __DIR__ . '/adapter.php';

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
        $recoveryRequestCount = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE recovery_status IN ("PENDING","ISSUED")')->fetchColumn();
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
<body class="<?= $u ? 'authenticated-page' : 'public-page' ?>">
<a class="skip-link" href="#main-content">Skip to main content</a>
<?php if ($u): ?>
    <div class="app-shell" data-app-base="<?= e(url('')) ?>">
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
                    <span class="sidebar-section-label">STUDENT SERVICES</span>
                    <a href="<?= e(url('student/dashboard.php')) ?>"><?= icon('home') ?> <span>Dashboard</span></a>
                    <?php if (!get_student_clearance($pdo,(int)$u['student_id'])): ?>
                    <a href="<?= e(url('student/start.php')) ?>"><?= icon('plus') ?> <span>Start Clearance</span></a>
                    <a href="<?= e(url('student/clearance_fee.php')) ?>"><?= icon('receipt') ?> <span>Clearance Fee</span></a>
                    <?php endif; ?>
                    <a href="<?= e(url('student/status.php')) ?>"><?= icon('clipboard-check') ?> <span>My Clearance</span></a>
                <?php elseif (in_array($u['role'], ['OFFICER', 'SUPERVISOR'], true)): ?>
                    <span class="sidebar-section-label">MY WORK</span>
                    <a href="<?= e(url('officer/dashboard.php')) ?>"><?= icon('home') ?> <span>Dashboard</span></a>
                    <?php if(clearance_fee_officer($pdo,(int)$u['id'])): ?><a href="<?= e(url('officer/clearance_fees.php')) ?>"><?= icon('file-text') ?> <span>Clearance Fee Payments</span></a><?php endif; ?>
                <?php else: ?>
                    <a href="<?= e(url('admin/dashboard.php')) ?>"><?= icon('home') ?> <span>Dashboard</span></a>
                    <span class="sidebar-section-label">CLEARANCE</span>
                    <a href="<?= e(url('admin/clearances.php')) ?>"><?= icon('clipboard-check') ?> <span>Clearance Monitoring</span></a>
                    <a href="<?= e(url('admin/clearance_period.php')) ?>"><?= icon('calendar') ?> <span>Clearance Period</span></a>
                    <a href="<?= e(url('admin/transcripts.php')) ?>"><?= icon('file-text') ?> <span>Student Clearance Certificates</span></a>
                    <a href="<?= e(url('admin/reports.php')) ?>"><?= icon('chart') ?> <span>Reports</span></a>
                    <span class="sidebar-section-label">ADMINISTRATION</span>
                    <a href="<?= e(url('admin/users.php')) ?>"><?= icon('users') ?> <span>Users</span></a>
                    <a href="<?= e(url('admin/recovery.php')) ?>"><?= icon('key') ?> <span>Password Reset Requests</span><?php if ($recoveryRequestCount > 0): ?><b class="nav-count"><?= $recoveryRequestCount ?></b><?php endif; ?></a>
                    <a href="<?= e(url('admin/programmes.php')) ?>"><?= icon('book-open') ?> <span>Programmes</span></a>
                    <a href="<?= e(url('admin/offices.php')) ?>"><?= icon('building') ?> <span>Offices</span></a>
                    <a href="<?= e(url('admin/departments.php')) ?>"><?= icon('departments') ?> <span>Departments</span></a>
                    <a href="<?= e(url('admin/workflow.php')) ?>"><?= icon('workflow') ?> <span>Workflow</span></a>
                <?php endif; ?>

            </nav>
        </aside>

        <main class="main-content">
            <?php require __DIR__ . '/institution_header.php'; ?>
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

                <div class="topbar-title"><?= e(in_array($u['role'], ['OFFICER', 'SUPERVISOR'], true) ? $u['full_name'] : $pageTitle) ?></div>

                <a class="icon-button notification-button" href="<?= e(url('student/notifications.php')) ?>" title="Notifications" aria-label="Notifications<?= $notificationCount > 0 ? ', ' . $notificationCount . ' unread' : '' ?>">
                    <?= icon('bell') ?>
                    <?php if ($notificationCount > 0): ?><b class="notification-count" aria-hidden="true"><?= $notificationCount > 99 ? '99+' : $notificationCount ?></b><?php endif; ?>
                </a>

                <details class="account-menu">
                    <summary class="user-chip" title="Account" aria-label="Account: <?= e($u['full_name']) ?>">
                    <?php if ($u['role'] === 'STUDENT' && ($profileStudent = get_student($pdo, (int)$u['student_id']))): ?>
                        <img class="avatar profile-avatar" src="<?= e(profile_picture_url($profileStudent)) ?>" width="40" height="40" alt="My profile picture">
                    <?php else: ?>
                        <span class="avatar"><?= e(strtoupper(substr($u['full_name'], 0, 1))) ?></span>
                    <?php endif; ?>
                    <div>
                        <strong title="<?= e($u['full_name']) ?>"><?= e($u['full_name']) ?></strong>
                        <small><?= e(ucfirst(strtolower($u['role']))) ?></small>
                    </div>
                    </summary>
                    <div class="account-dropdown">
                        <strong><?= e($u['full_name']) ?></strong>
                        <?php if ($u['role'] === 'STUDENT' && $profileStudent): ?>
                            <p class="muted"><?= e($profileStudent['registration_number']) ?><br><?= e($profileStudent['department']) ?></p>
                            <a href="<?= e(url('student/profile.php')) ?>"><?= icon('user') ?> My Profile</a>
                        <?php endif; ?>
                        <a href="<?= e(url('auth/change_password.php')) ?>"><?= icon('key') ?> Change Password</a>
                        <form method="post" action="<?= e(url('auth/logout.php')) ?>">
                            <?php csrf_field(); ?>
                            <button class="account-logout" type="submit"><?= icon('log-out') ?> Log out</button>
                        </form>
                    </div>
                </details>

            </header>

            <?php require __DIR__.'/accessibility.php'; ?>
            <p class="navigation-status sr-only" id="navigationStatus" role="status" aria-live="polite"></p>
            <section class="content" id="main-content" tabindex="-1">
                <?php foreach ($flashes as $flash): ?>
                    <div class="alert <?= e($flash['type']) ?>" role="alert">
                        <?= icon(match ($flash['type']) { 'success' => 'check-circle', 'danger' => 'alert-triangle', 'warning' => 'alert-triangle', default => 'info' }) ?>
                        <?= e($flash['message']) ?>
                    </div>
                <?php endforeach; ?>
<?php else: ?>
    <?php require __DIR__ . '/institution_header.php'; ?>
    <main class="public-content" id="main-content" tabindex="-1">
        <?php require __DIR__.'/accessibility.php'; ?>
<?php endif; ?>
