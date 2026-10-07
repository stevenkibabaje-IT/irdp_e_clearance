<?php
declare(strict_types=1);

/** Display clearance stages, decisions and follow-up actions for the student. */

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('STUDENT');

$u = current_user();
$period=clearance_period_state($pdo);
$request = $period['academic_cycle']
    ? get_clearance_for_cycle($pdo,(int)$u['student_id'],(string)$period['academic_cycle'])
    : get_latest_clearance($pdo,(int)$u['student_id']);

if (!$request) {
    $pageTitle = 'My Clearance';
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="panel empty">
        <h2>No clearance request</h2>
        <p>Start your clearance to see the 11-stage workflow.</p>
        <a class="btn primary" href="<?= e(url('student/start.php')) ?>">Start Clearance</a>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$stages = get_clearance_stages($pdo, (int) $request['id']);
$approved = count(array_filter($stages, static fn (array $stage): bool => $stage['status'] === 'APPROVED'));
$progress = (int) round(($approved / 11) * 100);

$certificateStmt = $pdo->prepare('SELECT * FROM certificates WHERE clearance_request_id = ? LIMIT 1');
$certificateStmt->execute([(int) $request['id']]);
$certificate = $certificateStmt->fetch() ?: null;

$pageTitle = 'My Clearance';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <span class="eyebrow">CLEARANCE TRACKING</span>
        <h1>My Clearance</h1>
        <p class="muted">
            <?= e($request['academic_year']) ?> · Started <?= e($request['started_at']) ?>
        </p>
    </div>

    <?php if ($certificate): ?>
        <a class="btn gold" href="<?= e(url('certificates/certificate.php?id=' . $request['id'])) ?>">View Certificate</a>
    <?php endif; ?>
</div>

<div class="panel">
    <div class="progress-label">
        <span><strong><?= e($request['status']==='PAUSED'?'Action Required':$request['status']) ?></strong> · <?= $approved ?> of 11 stages approved</span>
        <span><?= $progress ?>%</span>
    </div>
    <div class="progress-track" role="progressbar" aria-label="Clearance progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $progress ?>">
        <div style="width: <?= $progress ?>%;"></div>
    </div>

    <?php if ($request['status'] === 'PAUSED'): ?>
        <div class="alert danger" style="margin-top:14px;">
            An office requires action. Find the stage below and follow its instructions. For Finance, pay using the displayed control number and upload your payment receipt.
        </div>
    <?php elseif ($certificate): ?>
        <div class="alert success" style="margin-top:14px;">
            All 11 stages are approved. Your certificate is available.
        </div>
    <?php endif; ?>

</div>

<div class="stage-list">
    <?php foreach ($stages as $stage): ?>
        <article class="stage-card <?= $stage['status']==='REJECTED'?'stage-response':'' ?>" id="stage-<?= (int)$stage['id'] ?>">
            <div class="stage-number"><?= (int) $stage['step_number'] ?></div>

            <div class="stage-main">
                <h3><?= e($stage['title']) ?></h3>
                <p><?= e($stage['office']) ?></p>

                <?php if ($stage['officer_name']): ?>
                    <small>Assigned officer: <?= e($stage['officer_name']) ?></small>
                <?php endif; ?>
                <a href="<?= e(url('student/resubmit.php?stage=' . $stage['id'])) ?>">View responses, evidence and review history</a>

                <?php if ($stage['comments']): ?>
                    <div class="comment <?= $stage['status'] === 'REJECTED' ? 'rejection' : '' ?>">
                        <strong><?= e($stage['status']) ?>:</strong>
                        <?= e($stage['comments']) ?>
                    </div>
                <?php endif; ?>
                <?php if ((int)$stage['step_number'] === 11): ?>
                    <?php finance_payment_form($stage); ?>
                <?php elseif ($stage['status'] === 'REJECTED'): ?>
                    <p><strong>Corrective instructions:</strong> <?= e($stage['corrective_instructions'] ?: 'Contact this office to confirm the correction required.') ?></p>
                    <?php student_evidence_form($pdo,$stage); ?>
                <?php elseif (in_array($stage['status'],['PENDING','IN_REVIEW'],true) && (int)$stage['resubmissions']>0): ?>
                    <div class="alert info">Your response and evidence were submitted to <?= e($stage['office']) ?>. Awaiting office review. The next stage will open after approval.</div>
                <?php endif; ?>
            </div>

            <span class="<?= e(badge_class($stage['status'])) ?>">
                <?= e(humanize_status($stage['status'])) ?>
            </span>
        </article>
    <?php endforeach; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
