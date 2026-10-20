<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/adapter.php';
ar_ui_require_role('OFFICER','SUPERVISOR');
$u=current_user();
$offices=ai_rows($pdo,"SELECT o.id,o.name FROM organisation_units o JOIN office_memberships m ON m.office_id=o.id WHERE m.user_id=? AND o.kind='OFFICE' ORDER BY o.id",[$u['id']]);
$rows=ai_rows($pdo,"SELECT s.id,s.status,s.comments,s.resubmissions,o.name task_office,s.step_number,s.title,u.full_name,u.registration_number,c.label academic_year,r.status request_status FROM clearance_stages s JOIN clearance_requests r ON r.id=s.request_id JOIN users u ON u.id=r.student_user_id JOIN clearance_cycles c ON c.id=r.cycle_id JOIN organisation_units o ON o.id=s.office_id WHERE s.row_kind='INSTANCE' AND s.assigned_officer_id=? AND (s.status IN ('PENDING','IN_REVIEW') OR (s.status='REJECTED' AND s.step_number=11)) AND r.status IN ('IN_PROGRESS','PAUSED') ORDER BY s.actionable_at,s.step_number,s.id",[$u['id']]);
$pendingCount=count($rows);
$approvedToday=(int)ar_row($pdo,"SELECT COUNT(*) total FROM activity_log WHERE actor_id=? AND family='STAGE_DECISION' AND event_type='APPROVED' AND DATE(created_at)=CURDATE()",[$u['id']])['total'];
$awaitingStudentCount=(int)ar_row($pdo,"SELECT COUNT(*) total FROM clearance_stages s JOIN clearance_requests r ON r.id=s.request_id WHERE s.assigned_officer_id=? AND s.row_kind='INSTANCE' AND s.status='REJECTED' AND s.step_number<>11 AND r.status IN ('IN_PROGRESS','PAUSED')",[$u['id']])['total'];
$resubmittedCount=count(array_filter($rows,static fn(array $row):bool=>(int)$row['resubmissions']>0));
$taskView=($_GET['view']??'')==='returned'?'returned':'all';
if($taskView==='returned'){$rows=array_values(array_filter($rows,static fn(array $row):bool=>(int)$row['resubmissions']>0));}
$isFinanceOfficer=clearance_fee_officer($pdo,(int)$u['id']);
$feePending=$isFinanceOfficer?(int)ar_row($pdo,"SELECT COUNT(*) total FROM clearance_requests WHERE entry_officer_id=? AND entry_status<>'APPROVED'",[$u['id']])['total']:0;
$pageTitle=$u['full_name'];
require_once __DIR__.'/../includes/header.php';
?><div class="dashboard-view dashboard-officer">
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
                    <td><span class="<?= e(badge_class($row['status'])) ?>"><?= e(humanize_status($row['status'])) ?></span></td>
                    <td>
                        <a class="btn primary small" href="<?= e(url('officer/review.php?stage=' . $row['id'])) ?>">
                            Review
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
