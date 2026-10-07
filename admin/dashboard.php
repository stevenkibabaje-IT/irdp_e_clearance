<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('ADMIN');

$stats = [
    'students' => (int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn(),
    'officers' => (int) $pdo->query(
        'SELECT COUNT(*) FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.name = "OFFICER" AND u.active = 1'
    )->fetchColumn(),
    'offices' => (int) $pdo->query('SELECT COUNT(*) FROM offices WHERE active = 1')->fetchColumn(),
    'completed' => (int) $pdo->query('SELECT COUNT(*) FROM clearance_requests WHERE status = "COMPLETED"')->fetchColumn(),
    'in_progress' => (int) $pdo->query('SELECT COUNT(*) FROM clearance_requests WHERE status = "IN_PROGRESS"')->fetchColumn(),
    'paused' => (int) $pdo->query('SELECT COUNT(*) FROM clearance_requests WHERE status = "PAUSED"')->fetchColumn(),
    'transcripts' => (int) $pdo->query('SELECT COUNT(*) FROM transcripts')->fetchColumn(),
];
$period=clearance_period_state($pdo);

$recent = $pdo->query(
    'SELECT cr.id, cr.status, cr.created_at, u.full_name, s.registration_number
     FROM clearance_requests cr
     INNER JOIN students s ON s.id = cr.student_id
     INNER JOIN users u ON u.id = s.user_id
     ORDER BY cr.id DESC
     LIMIT 8'
)->fetchAll();

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <span class="eyebrow">ADMINISTRATION</span>
        <h1>System Dashboard</h1>
        <p class="muted">Monitor students, workflow, offices and clearance activity.</p>
    </div>
    <a class="btn gold" href="<?= e(url('admin/reports.php')) ?>">Generate PDF Report</a>
</div>

<div class="stats-grid">
    <div class="stat-card info"><div class="stat-icon"><?= icon('graduation-cap') ?></div><span>Total Students</span><strong><?= $stats['students'] ?></strong></div>
    <div class="stat-card"><div class="stat-icon"><?= icon('briefcase') ?></div><span>Active Officers</span><strong><?= $stats['officers'] ?></strong></div>
    <div class="stat-card purple"><div class="stat-icon"><?= icon('building') ?></div><span>Active Offices</span><strong><?= $stats['offices'] ?></strong></div>
    <div class="stat-card"><div class="stat-icon"><?= icon('check-circle') ?></div><span>Completed Clearances</span><strong><?= $stats['completed'] ?></strong></div>
</div>
<section class="panel"><div class="panel-head"><h2>Clearance Period Management</h2><span class="<?=e($period['is_open']?'badge success':'badge muted')?>"><?=e($period['effective_status'])?></span></div><p>Academic cycle: <strong><?=e($period['academic_cycle']?:'Not selected')?></strong> · Opens: <?=e($period['opens_at']?:'Manual')?> · Closes: <?=e($period['closes_at']?:'Manual')?></p><a class="btn primary" href="<?=e(url('admin/clearance_period.php'))?>">Manage clearance period</a></section>

<div class="stats-grid two-col">
    <div class="stat-card warning"><div class="stat-icon"><?= icon('clock') ?></div><span>In Progress</span><strong><?= $stats['in_progress'] ?></strong></div>
    <div class="stat-card warning"><div class="stat-icon"><?= icon('pause-circle') ?></div><span>Student action required</span><strong><?= $stats['paused'] ?></strong></div>
</div>

<div class="two-col">
    <section class="panel">
        <div class="panel-head">
            <h2>Quick Actions</h2>
        </div>
        <div class="quick-grid">
            <a class="quick-card" href="<?= e(url('admin/recovery.php')) ?>"><?= icon('key') ?><strong>Password Reset Requests (<?= $recoveryRequestCount ?> open)</strong></a>
            <a class="quick-card" href="<?= e(url('admin/users.php')) ?>"><?= icon('users') ?><strong>Manage Users</strong></a>
            <a class="quick-card" href="<?= e(url('admin/offices.php')) ?>"><?= icon('building') ?><strong>Manage Offices</strong></a>
            <a class="quick-card" href="<?= e(url('admin/workflow.php')) ?>"><?= icon('workflow') ?><strong>Configure Workflow</strong></a>
            <a class="quick-card" href="<?= e(url('admin/clearances.php')) ?>"><?= icon('clipboard-check') ?><strong>Monitor Clearance</strong></a>
            <a class="quick-card" href="<?= e(url('admin/reports.php')) ?>"><?= icon('chart') ?><strong>Weekly / Monthly PDF</strong></a>
            <a class="quick-card" href="<?= e(url('admin/transcripts.php')) ?>"><?= icon('file-text') ?><strong>Clearance Transcripts (<?= $stats['transcripts'] ?>)</strong></a>
        </div>
    </section>

    <section class="panel">
        <h2>Workflow Rule</h2>
        <p>
            All 11 offices review independently after the student starts clearance. A rejected stage
            requires student correction for that office; other offices can continue reviewing.
        </p>
        <div class="alert info">
            The clearance transcript is issued automatically after all 11 offices approve and all liabilities are cleared.
        </div>
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
                    <td><?= e($row['full_name']) ?></td>
                    <td><?= e($row['registration_number']) ?></td>
                    <td><span class="<?= e(badge_class($row['status'])) ?>"><?= e($row['status']) ?></span></td>
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
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
