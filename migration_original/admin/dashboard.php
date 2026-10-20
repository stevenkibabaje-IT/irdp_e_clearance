<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/adapter.php';
ar_ui_require_role('ADMIN');
$stats=[
 'students'=>(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='STUDENT'")->fetchColumn(),
 'officers'=>(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('OFFICER','SUPERVISOR') AND active=1")->fetchColumn(),
 'offices'=>(int)$pdo->query("SELECT COUNT(*) FROM organisation_units WHERE kind='OFFICE' AND active=1")->fetchColumn(),
 'completed'=>(int)$pdo->query("SELECT COUNT(*) FROM clearance_requests WHERE started_at IS NOT NULL AND status='COMPLETED'")->fetchColumn(),
 'in_progress'=>(int)$pdo->query("SELECT COUNT(*) FROM clearance_requests WHERE started_at IS NOT NULL AND status='IN_PROGRESS'")->fetchColumn(),
 'paused'=>(int)$pdo->query("SELECT COUNT(*) FROM clearance_requests WHERE started_at IS NOT NULL AND status='PAUSED'")->fetchColumn(),
 'transcripts'=>(int)$pdo->query("SELECT COUNT(*) FROM documents WHERE kind='CLEARANCE_CERTIFICATE'")->fetchColumn(),
];
$period=clearance_period_state($pdo);
$requestTotal=$stats['completed']+$stats['in_progress']+$stats['paused'];
$completionRate=$requestTotal?(int)round($stats['completed']/$requestTotal*100):0;
$recent=ai_rows($pdo,'SELECT r.id,r.status,r.clearance_created_at created_at,u.full_name,u.registration_number FROM clearance_requests r JOIN users u ON u.id=r.student_user_id WHERE r.started_at IS NOT NULL ORDER BY r.id DESC LIMIT 8');
$pageTitle='Admin Dashboard';
require_once __DIR__.'/../includes/header.php';
?><div class="dashboard-view dashboard-admin">
<div class="dash-breadcrumb"><?= icon('home') ?> <span>Administration</span><span aria-hidden="true">/</span><strong>Overview</strong></div>
<div class="page-heading dash-hero">
    <div>
        <span class="eyebrow">YOUR ADMINISTRATION WORKSPACE</span>
        <h1>System Dashboard</h1>
        <p class="muted">A clear view of your institution. Keep people, offices and clearance moving.</p>
    </div>
    <div class="dash-hero-actions">
        <span class="dash-cycle"><?= icon('calendar') ?> <?= e($period['academic_cycle'] ?: 'No cycle selected') ?></span>
        <a class="btn gold" href="<?= e(url('admin/reports.php')) ?>"><?= icon('download') ?> Generate PDF Report</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card info"><div class="stat-icon"><?= icon('graduation-cap') ?></div><span>Total Students</span><strong><?= $stats['students'] ?></strong><small>Registered in the system</small></div>
    <div class="stat-card"><div class="stat-icon"><?= icon('briefcase') ?></div><span>Active Officers</span><strong><?= $stats['officers'] ?></strong><small>Supporting student clearance</small></div>
    <div class="stat-card purple"><div class="stat-icon"><?= icon('building') ?></div><span>Active Offices</span><strong><?= $stats['offices'] ?></strong><small>Independent review stages</small></div>
    <div class="stat-card"><div class="stat-icon"><?= icon('check-circle') ?></div><span>Completed Clearances</span><strong><?= $stats['completed'] ?></strong><small>All office reviews approved</small></div>
</div>
<section class="panel dash-period">
    <span class="dash-period-icon"><?= icon('calendar-clock') ?></span>
    <div class="dash-period-copy"><div class="dash-inline-heading"><h2>Clearance Period Management</h2><span class="<?=e($period['is_open']?'badge success':'badge muted')?>"><?=e($period['effective_status'])?></span></div><p><strong><?=e($period['academic_cycle']?:'Not selected')?></strong> <span aria-hidden="true">&middot;</span> Opens: <?=e($period['opens_at']?:'Manual')?> <span aria-hidden="true">&middot;</span> Closes: <?=e($period['closes_at']?:'Manual')?></p></div>
    <a class="btn secondary" href="<?=e(url('admin/clearance_period.php'))?>">Manage period <?= icon('chevron-right') ?></a>
</section>

<div class="dash-admin-grid">
    <section class="panel">
        <div class="panel-head">
            <h2>Quick Actions</h2>
            <span class="dash-section-tag">Administration</span>
        </div>
        <div class="quick-grid">
            <a class="quick-card" href="<?= e(url('admin/recovery.php')) ?>"><?= icon('key') ?><strong>Password Reset Requests (<?= $recoveryRequestCount ?> open)</strong></a>
            <a class="quick-card" href="<?= e(url('admin/users.php')) ?>"><?= icon('users') ?><strong>Manage Users</strong></a>
            <a class="quick-card" href="<?= e(url('admin/offices.php')) ?>"><?= icon('building') ?><strong>Manage Offices</strong></a>
            <a class="quick-card" href="<?= e(url('admin/workflow.php')) ?>"><?= icon('workflow') ?><strong>Configure Workflow</strong></a>
            <a class="quick-card" href="<?= e(url('admin/clearances.php')) ?>"><?= icon('clipboard-check') ?><strong>Monitor Clearance</strong></a>
            <a class="quick-card" href="<?= e(url('admin/reports.php')) ?>"><?= icon('chart') ?><strong>Weekly / Monthly PDF</strong></a>
            <a class="quick-card" href="<?= e(url('admin/transcripts.php')) ?>"><?= icon('file-text') ?><strong>Student Clearance Certificates (<?= $stats['transcripts'] ?>)</strong></a>
        </div>
    </section>

    <section class="panel dash-activity">
        <div class="panel-head"><h2>Clearance Activity</h2><span class="dash-section-tag"><?= $requestTotal ?> requests</span></div>
        <div class="dash-activity-overview">
            <div class="dash-completion-ring" style="--completion: <?= $completionRate ?>%" role="img" aria-label="<?= $completionRate ?> percent of active and completed requests are completed"><div><strong><?= $completionRate ?>%</strong><span>Completed</span></div></div>
            <div class="dash-activity-legend">
                <div><span class="dash-dot dash-dot-green"></span><span>Completed</span><strong><?= $stats['completed'] ?></strong></div>
                <div><span class="dash-dot dash-dot-blue"></span><span>In Progress</span><strong><?= $stats['in_progress'] ?></strong></div>
                <div><span class="dash-dot dash-dot-amber"></span><span>Student action required</span><strong><?= $stats['paused'] ?></strong></div>
            </div>
        </div>
        <div class="dash-workflow-note"><span><?= icon('workflow') ?></span><div><h3>Workflow Rule</h3><p>All 11 offices review independently. A rejected stage requires a correction for that office; other reviews continue.</p><p>The Student Clearance Certificate is issued automatically after all 11 offices approve and all liabilities are cleared.</p></div></div>
    </section>
</div>

<section class="panel">
    <div class="panel-head">
        <h2>Recent Clearance Requests</h2>
        <a href="<?= e(url('admin/clearances.php')) ?>">View all</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Student</th><th>Registration</th><th>Status</th><th>Created</th></tr>
            </thead>
            <tbody>
            <?php foreach ($recent as $row): ?>
                <tr>
                    <td><div class="dash-person"><span class="dash-person-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($row['full_name'],0,1))) ?></span><strong><?= e($row['full_name']) ?></strong></div></td>
                    <td><span class="dash-registration"><?= e($row['registration_number']) ?></span></td>
                    <td><span class="<?= e(badge_class($row['status'])) ?>"><?= e(humanize_status($row['status'])) ?></span></td>
                    <td><?= e($row['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recent): ?>
                <tr><td colspan="4" class="empty">No clearance requests yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
