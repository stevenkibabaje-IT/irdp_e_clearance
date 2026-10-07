<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('ADMIN');

$rows = $pdo->query(
    'SELECT
        cr.*,
        u.full_name,
        s.registration_number,
        d.name AS department,
        (
            SELECT ws.title
            FROM clearance_stages cs
            INNER JOIN workflow_steps ws ON ws.id = cs.workflow_step_id
            WHERE cs.clearance_request_id = cr.id
              AND cs.status IN ("PENDING", "REJECTED", "IN_REVIEW", "ESCALATED")
            ORDER BY ws.step_number
            LIMIT 1
        ) AS current_stage,
        (
            SELECT COUNT(*)
            FROM clearance_stages cs2
            WHERE cs2.clearance_request_id = cr.id
              AND cs2.status = "APPROVED"
        ) AS approved_count
     FROM clearance_requests cr
     INNER JOIN students s ON s.id = cr.student_id
     INNER JOIN users u ON u.id = s.user_id
     LEFT JOIN departments d ON d.id = s.department_id
     ORDER BY cr.id DESC'
)->fetchAll();

$pageTitle = 'Clearance Monitoring';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <span class="eyebrow">ADMIN</span>
        <h1>Clearance Monitoring</h1>
        <p class="muted">Monitor the status and current stage of every clearance request.</p>
    </div>
</div>

<div class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Registration</th>
                    <th>Department</th>
                    <th>Progress</th>
                    <th>Current Stage</th>
                    <th>Status</th>
                    <th>Started</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e($row['full_name']) ?></td>
                    <td><?= e($row['registration_number']) ?></td>
                    <td><?= e($row['department'] ?? '—') ?></td>
                    <td><?= (int) $row['approved_count'] ?> / 11</td>
                    <td><?= e($row['current_stage'] ?? ($row['status'] === 'COMPLETED' ? 'Completed' : 'No active stage')) ?></td>
                    <td><span class="<?= e(badge_class($row['status'])) ?>"><?= e($row['status']) ?></span></td>
                    <td><?= e($row['started_at'] ?? $row['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="empty">No clearance requests yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
