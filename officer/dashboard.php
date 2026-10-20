<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('OFFICER', 'SUPERVISOR');

$u = current_user();

$officeStmt = $pdo->prepare(
    'SELECT o.id, o.name
     FROM offices o
     INNER JOIN user_offices uo ON uo.office_id = o.id
     WHERE uo.user_id = ?
     ORDER BY o.id'
);
$officeStmt->execute([(int) $u['id']]);
$offices = $officeStmt->fetchAll();

$pendingStmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM clearance_stages cs
     INNER JOIN workflow_steps ws ON ws.id=cs.workflow_step_id
     INNER JOIN clearance_requests cr ON cr.id=cs.clearance_request_id
     WHERE cs.assigned_officer_id = ?
       AND (cs.status IN ("PENDING", "IN_REVIEW") OR (cs.status="REJECTED" AND ws.step_number=11))
       AND cr.status IN ("IN_PROGRESS", "PAUSED")'
);
$pendingStmt->execute([(int) $u['id']]);
$pendingCount = (int) $pendingStmt->fetchColumn();

$approvedTodayStmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM stage_actions
     WHERE officer_id = ?
       AND action = "APPROVED"
       AND DATE(created_at) = CURDATE()'
);
$approvedTodayStmt->execute([(int) $u['id']]);
$approvedToday = (int) $approvedTodayStmt->fetchColumn();

$awaitingStudentStmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM clearance_stages cs
     INNER JOIN clearance_requests cr ON cr.id=cs.clearance_request_id
     INNER JOIN workflow_steps ws ON ws.id=cs.workflow_step_id
     WHERE cs.assigned_officer_id = ? AND cs.status="REJECTED" AND ws.step_number<>11
       AND cr.status IN ("IN_PROGRESS", "PAUSED")'
);
$awaitingStudentStmt->execute([(int) $u['id']]);
$awaitingStudentCount = (int) $awaitingStudentStmt->fetchColumn();

$listStmt = $pdo->prepare(
    'SELECT
        cs.id,
        cs.clearance_request_id,
        cs.status,
        cs.comments,
        cs.resubmissions,
        o.name AS task_office,
        ws.step_number,
        ws.title,
        u.full_name,
        s.registration_number,
        cr.academic_year,
        cr.status AS request_status
     FROM clearance_stages cs
     INNER JOIN clearance_requests cr ON cr.id = cs.clearance_request_id
     INNER JOIN students s ON s.id = cr.student_id
     INNER JOIN users u ON u.id = s.user_id
     INNER JOIN workflow_steps ws ON ws.id = cs.workflow_step_id
     INNER JOIN offices o ON o.id = cs.office_id
     WHERE cs.assigned_officer_id = ?
       AND (cs.status IN ("PENDING", "IN_REVIEW") OR (cs.status="REJECTED" AND ws.step_number=11))
       AND cr.status IN ("IN_PROGRESS", "PAUSED")
     ORDER BY
        cs.actionable_at,
        ws.step_number,
        cs.id'
);
$listStmt->execute([(int) $u['id']]);
$rows = $listStmt->fetchAll();
foreach ($rows as &$task) {
    $task['finance_locked'] = (int)$task['step_number'] === 11
        && !finance_final_approval_ready($pdo, (int)$task['clearance_request_id']);
}
unset($task);
$pendingCount = count(array_filter($rows, static fn(array $row): bool => !$row['finance_locked']));
$resubmittedCount = count(array_filter($rows, static fn(array $row): bool => (int)$row['resubmissions'] > 0));
$taskView = ($_GET['view'] ?? '') === 'returned' ? 'returned' : 'all';
if ($taskView === 'returned') {
    $rows = array_values(array_filter($rows, static fn(array $row): bool => (int)$row['resubmissions'] > 0));
}

$isFinanceOfficer=clearance_fee_officer($pdo,(int)$u['id']);
$feePending=0;
if($isFinanceOfficer){$feeCount=$pdo->prepare('SELECT COUNT(*) FROM clearance_fee_payments WHERE assigned_officer_id=? AND status<>"APPROVED"');$feeCount->execute([$u['id']]);$feePending=(int)$feeCount->fetchColumn();}
$pageTitle = $u['full_name'];
require_once __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-view dashboard-officer">
<div class="dash-breadcrumb"><?= icon('home') ?> <span>My workspace</span><span aria-hidden="true">/</span><strong>Overview</strong></div>
<div class="page-heading dash-hero">
    <div>
        <span class="eyebrow">YOUR OFFICER WORKSPACE</span>
        <h1><?= e($u['full_name']) ?></h1>
        <p class="muted">
            <?= e($offices[0]['name'] ?? 'No office assigned') ?>
            <?php if (count($offices) > 1): ?> · +<?= count($offices) - 1 ?> more office(s)<?php endif; ?>
        </p>
    </div>
    <div class="dash-hero-actions"><span class="dash-cycle"><?= icon('briefcase') ?> <?= count($offices) ?> assigned <?= count($offices) === 1 ? 'office' : 'offices' ?></span><a class="btn primary" href="#assigned-tasks"><?= icon('clipboard-check') ?> Review assigned tasks</a></div>
</div>
<section class="stats-grid officer-summary" aria-label="Your clearance work summary">
    <a class="stat-card warning" href="<?= e(url('officer/dashboard.php?view=all#assigned-tasks')) ?>">
        <div class="stat-icon"><?= icon('clipboard-check') ?></div>
        <div class="officer-summary-copy"><span>Pending reviews</span><strong><?= $pendingCount ?></strong><small>Ready for your review</small></div>
    </a>
    <a class="stat-card info" href="<?= e(url('officer/dashboard.php?view=returned#assigned-tasks')) ?>">
        <div class="stat-icon"><?= icon('upload') ?></div>
        <div class="officer-summary-copy"><span>Evidence returned</span><strong><?= $resubmittedCount ?></strong><small>Student responses to check</small></div>
    </a>
    <article class="stat-card">
        <div class="stat-icon"><?= icon('check-circle') ?></div>
        <div class="officer-summary-copy"><span>Approvals today</span><strong><?= $approvedToday ?></strong><small>Decisions you approved today</small></div>
    </article>
    <article class="stat-card warning">
        <div class="stat-icon"><?= icon('users') ?></div>
        <div class="officer-summary-copy"><span>Awaiting students</span><strong><?= $awaitingStudentCount ?></strong><small>Corrections still outstanding</small></div>
    </article>
</section>
<?php if($isFinanceOfficer): ?>
<a class="officer-fee-summary" href="<?= e(url('officer/clearance_fees.php')) ?>">
    <span class="officer-fee-icon"><?= icon('receipt') ?></span>
    <div><strong>Clearance entry fees</strong><span><?= $feePending ?> payment requests awaiting Finance</span></div>
    <span class="officer-fee-action">Manage payments <?= icon('chevron-right') ?></span>
</a>
<?php endif; ?>

<div class="panel" id="assigned-tasks">
    <div class="panel-head">
        <div>
            <h2>My assigned clearance tasks</h2>
            <p class="muted">Review only the stages assigned to your office.</p>
        </div>
        <span class="dash-section-tag"><?= count($rows) ?> <?= count($rows) === 1 ? 'task' : 'tasks' ?> shown</span>
    </div>
    <nav class="task-filters" aria-label="Assigned task filters">
        <a class="notification-filter <?= $taskView === 'all' ? 'active' : '' ?>" href="<?= e(url('officer/dashboard.php?view=all#assigned-tasks')) ?>" <?= $taskView === 'all' ? 'aria-current="page"' : '' ?>>All pending <span><?= $pendingCount ?></span></a>
        <a class="notification-filter <?= $taskView === 'returned' ? 'active' : '' ?>" href="<?= e(url('officer/dashboard.php?view=returned#assigned-tasks')) ?>" <?= $taskView === 'returned' ? 'aria-current="page"' : '' ?>>Evidence returned <span><?= $resubmittedCount ?></span></a>
    </nav>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Registration No.</th>
                    <th>Stage</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><div class="dash-person"><span class="dash-person-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($row['full_name'],0,1))) ?></span><strong><?= e($row['full_name']) ?></strong></div></td>
                    <td><span class="dash-registration"><?= e($row['registration_number']) ?></span></td>
                    <td><?= e($row['step_number'] . '. ' . $row['title']) ?><br><small><?= e($row['task_office']) ?></small><?php if ((int)$row['resubmissions']>0): ?><br><span class="badge info">Resubmitted: review student evidence</span><?php endif; ?></td>
                    <td><?php if ($row['finance_locked']): ?><span class="badge muted">Locked — awaiting other offices</span><?php else: ?><span class="<?= e(badge_class($row['status'])) ?>"><?= e(humanize_status($row['status'])) ?></span><?php endif; ?></td>
                    <td>
                        <a class="btn primary small" href="<?= e(url('officer/review.php?stage=' . $row['id'])) ?>">
                            <?= $row['finance_locked'] ? 'View locked stage' : 'Review' ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$rows): ?>
                <tr>
                    <td colspan="5" class="empty"><div class="dash-empty-icon"><?= icon('check-circle') ?></div><strong><?= $taskView === 'returned' ? 'No returned evidence is waiting for your review.' : 'No clearance stages need your action right now.' ?></strong><p>Your assigned reviews will appear here when they are ready.</p></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
