<?php
declare(strict_types=1);

/** Display clearance stages, decisions and follow-up actions for the student. */

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('STUDENT');

$u = current_user();
$period=clearance_period_state($pdo);
$request = get_student_clearance($pdo,(int)$u['student_id']);

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

$stages = student_clearance_stage_display(get_clearance_stages($pdo, (int) $request['id']));

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

</div>

<div class="panel student-progress-overview clearance-tracking-panel">
    <div class="panel-head">
        <h2>Clearance Progress</h2>
        <span class="<?= e(badge_class($request['status'])) ?>"><?= e(humanize_status($request['status'])) ?></span>
    </div>
    <?php clearance_progress($stages); ?>

    <?php if ($request['status'] === 'PAUSED'): ?>
        <div class="alert danger" style="margin-top:14px;">
            One or more offices require action. Find each rejected stage below and follow its instructions. Other offices can continue reviewing. For Finance, pay using the displayed control number and upload your payment receipt.
        </div>
    <?php elseif ($request['status'] === 'COMPLETED'): ?>
        <div class="alert success" style="margin-top:14px;">
            All 11 stages are approved. Your clearance is complete.
        </div>
    <?php endif; ?>

</div>

<div class="student-office-heading"><h2>Office Reviews</h2><span>Select an office to view details or respond.</span></div>
<div class="office-status-grid clearance-stage-list">
    <?php foreach ($stages as $stage): ?>
        <details class="stage-card office-status-item <?= e(strtolower($stage['status'])) ?> <?= $stage['status']==='REJECTED'?'stage-response':'' ?>" id="stage-<?= (int)$stage['id'] ?>">
            <summary class="stage-review-summary">
            <span class="stage-number office-status-number" aria-hidden="true"><?= (int) $stage['step_number'] ?></span>
            <div class="stage-main office-status-copy">
                <strong><?= e($stage['office']) ?></strong>
                <span class="office-status-badge <?= e(badge_class($stage['status'])) ?>"><?= icon($stage['status'] === 'APPROVED' ? 'check-circle' : ($stage['status'] === 'REJECTED' ? 'alert-circle' : 'clock')) ?> <?= e($stage['status']==='LOCKED' ? 'LOCKED' : humanize_status($stage['status'])) ?></span>
            </div>
            <span class="stage-details-cue"><?= icon('chevron-right') ?></span>
            </summary>
            <div class="stage-details-body">
                <p><strong>Review:</strong> <?= e($stage['title']) ?></p>
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
                    <div class="alert info">Your response and evidence were submitted to <?= e($stage['office']) ?>. Awaiting office review. Other offices can continue reviewing independently.</div>
                <?php endif; ?>
            </div>

        </details>
    <?php endforeach; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
