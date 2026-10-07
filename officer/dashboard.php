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

$rejectedStmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM stage_actions
     WHERE officer_id = ?
       AND action = "REJECTED"'
);
$rejectedStmt->execute([(int) $u['id']]);
$rejectedCount = (int) $rejectedStmt->fetchColumn();

$listStmt = $pdo->prepare(
    'SELECT
        cs.id,
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

$pageTitle = 'Officer Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <span class="eyebrow">OFFICER</span>
        <h1>Officer Dashboard</h1>
        <p class="muted">
            <?= e($offices[0]['name'] ?? 'No office assigned') ?>
            <?php if (count($offices) > 1): ?> · +<?= count($offices) - 1 ?> more office(s)<?php endif; ?>
        </p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card warning">
        <div class="stat-icon"><?= icon('clock') ?></div>
        <span>Pending Actions</span>
        <strong><?= $pendingCount ?></strong>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><?= icon('check-circle') ?></div>
        <span>Approved Today</span>
        <strong><?= $approvedToday ?></strong>
    </div>
    <div class="stat-card info">
        <div class="stat-icon"><?= icon('building') ?></div>
        <span>Assigned Offices</span>
        <strong><?= count($offices) ?></strong>
    </div>
    <div class="stat-card purple">
        <div class="stat-icon"><?= icon('x-circle') ?></div>
        <span>Total Rejected</span>
        <strong><?= $rejectedCount ?></strong>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2>My assigned clearance tasks</h2>
            <p class="muted" style="margin:4px 0 0;">Review only the stages assigned to your office.</p>
        </div>
    </div>

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
                    <td><?= e($row['full_name']) ?></td>
                    <td><?= e($row['registration_number']) ?></td>
                    <td><?= e($row['step_number'] . '. ' . $row['title']) ?><br><small><?= e($row['task_office']) ?></small><?php if ((int)$row['resubmissions']>0): ?><br><span class="badge info">Resubmitted: review student evidence</span><?php endif; ?></td>
                    <td><span class="<?= e(badge_class($row['status'])) ?>"><?= e($row['status']) ?></span></td>
                    <td>
                        <?php if($row['request_status']==='PAUSED' && (int)$row['step_number']!==11): ?>
                            <span class="badge warning">Waiting for student correction</span>
                        <?php else: ?>
                        <a class="btn primary small" href="<?= e(url('officer/review.php?stage=' . $row['id'])) ?>">
                            Review
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$rows): ?>
                <tr>
                    <td colspan="5" class="empty">No clearance stages need your action right now.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
