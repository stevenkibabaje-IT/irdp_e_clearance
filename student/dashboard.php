<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('STUDENT');

$u = current_user();
$student = get_student($pdo, (int) $u['student_id']);
$period=clearance_period_state($pdo);
$request = get_student_clearance($pdo,(int)$u['student_id']);
$stages = $request ? get_clearance_stages($pdo, (int) $request['id']) : [];
$entryFee = !$request && $period['cycle_id'] ? clearance_fee_for_cycle($pdo,(int)$u['student_id'],(int)$period['cycle_id']) : null;
$feeApproved = $entryFee && $entryFee['status']==='APPROVED';

$transcript=($request&&$request['status']==='COMPLETED')?transcript_for_student($pdo,(int)$u['student_id'],(int)$request['id']):null;
$transcript=($transcript&&$transcript['status']==='VALID')?$transcript:null;

$pageTitle = 'Student Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading student-dashboard-heading">
    <div>
        <span class="eyebrow">STUDENT</span>
        <h1>My Dashboard</h1>
        <p class="muted">Your clearance at a glance.</p>
    </div>

    <?php if (!$request && $period['is_open']): ?>
        <a class="btn primary" href="<?= e(url($feeApproved?'student/start.php':'student/clearance_fee.php')) ?>"><?= $feeApproved?'Start Clearance':'Clearance Fee' ?></a>
    <?php elseif (!$request): ?>
        <span class="badge muted">CLEARANCE CLOSED</span>
    <?php else: ?>
        <a class="btn primary" href="<?= e(url('student/status.php')) ?>"><?= $request['status']==='COMPLETED'?'View Clearance':'Continue Clearance' ?></a>
    <?php endif; ?>
</div>

<?php if(!$request && $period['cycle_id']): ?>
<section class="panel"><h2>Clearance entry fee</h2><p><strong>TSh <?= e(number_format((float)($entryFee['amount']??clearance_fee_amount($pdo,(int)$period['cycle_id'])),2)) ?></strong> · <?= e($entryFee?str_replace('_',' ',$entryFee['status']):'NOT REQUESTED') ?></p><p><?= $feeApproved?'Your payment is approved. You can now start clearance.':'Request a control number, pay the fee and upload your receipt. Finance approval unlocks Start Clearance.' ?></p><a class="btn secondary" href="<?= e(url('student/clearance_fee.php')) ?>">View fee / payment details</a></section>
<?php endif; ?>

    <section class="panel student-clearance-panel">
        <div class="panel-head student-clearance-head">
            <div>
                <h2>Clearance Overview</h2>
                <p class="muted student-clearance-cycle"><?= icon('calendar') ?> <?= e($request['academic_year'] ?? $period['academic_cycle'] ?? 'Academic cycle not set') ?> <span aria-hidden="true">&middot;</span> <?= e($request && $request['academic_year'] !== $period['academic_cycle'] ? 'Existing clearance' : ($period['is_open'] ? 'Clearance open' : 'Clearance closed')) ?></p>
            </div>
            <?php if ($request): ?>
                <span class="<?= e(badge_class($request['status'])) ?>"><?= e(humanize_status($request['status'])) ?></span>
            <?php endif; ?>
        </div>
        <?php if (!$period['is_open']): ?><p class="student-clearance-notice"><?= e(CLEARANCE_CLOSED_MESSAGE) ?></p><?php elseif ((!$request || $request['academic_year'] === $period['academic_cycle']) && ($period['opens_at'] || $period['closes_at'])): ?><p class="student-clearance-notice">Opening: <?= e($period['opens_at'] ?: 'Manual') ?> &middot; Closing: <?= e($period['closes_at'] ?: 'Manual') ?></p><?php endif; ?>

        <?php if (!$request): ?>
            <div class="empty">
                <p>You have not started your clearance yet.</p>
                <a class="btn primary" href="<?= e(url($feeApproved?'student/start.php':'student/clearance_fee.php')) ?>"><?= $feeApproved?'Start Clearance':'Pay Clearance Fee' ?></a>
            </div>
        <?php else: ?>
            <div class="student-clearance-layout">
                <section class="student-progress-overview" aria-labelledby="student-progress-heading">
                    <h3 id="student-progress-heading">Clearance Progress</h3>
                    <?php clearance_progress($stages); ?>
                    <?php if ($transcript): ?>
                        <a class="btn primary small student-transcript-link" href="<?= e(url('transcripts/download.php')) ?>"><?= icon('download') ?> Download Transcript</a>
                    <?php endif; ?>
                </section>
                <section class="student-office-overview" aria-labelledby="office-status-heading">
                    <div class="student-office-heading"><h3 id="office-status-heading">Office Status</h3><span><?= count($stages) ?> offices</span></div>
                    <ol class="office-status-grid">
                <?php foreach ($stages as $stage): ?>
                    <?php $financePayment = (int)$stage['step_number'] === 11 ? finance_payment_details($stage) : []; ?>
                    <li class="office-status-item <?= e(strtolower($stage['status'])) ?><?= $stage['status'] === 'REJECTED' ? ' rejection-action' : '' ?>">
                        <div class="office-status-row">
                            <span class="office-status-number" aria-hidden="true"><?= (int)$stage['step_number'] ?></span>
                            <div class="office-status-copy"><strong><?= e($stage['office']) ?></strong><span class="office-status-badge <?= e(badge_class($stage['status'])) ?>"><?= icon($stage['status'] === 'APPROVED' ? 'check-circle' : ($stage['status'] === 'REJECTED' ? 'alert-circle' : 'clock')) ?> <?= e(humanize_status($stage['status'])) ?></span></div>
                        </div>
                        <?php if ($financePayment['control_number'] ?? ''): ?>
                            <details class="office-status-details">
                                <summary><?= icon('receipt') ?> <?= $stage['status'] === 'APPROVED' ? 'Payment details' : 'Payment / Receipt' ?></summary>
                                <?php finance_payment_form($stage); ?>
                            </details>
                        <?php elseif ($stage['status'] === 'REJECTED'): ?>
                            <details class="office-status-details">
                                <summary><?= icon('alert-circle') ?> Correction / Upload Evidence</summary>
                                <p><?= e($stage['comments']) ?></p>
                                <p><?= e($stage['corrective_instructions'] ?: 'Contact the office for the correction required.') ?></p>
                                <a class="office-response-link" href="<?= e(url('student/status.php#stage-'.(int)$stage['id'])) ?>"><?= icon('upload') ?> Respond / Upload Evidence</a>
                            </details>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                    </ol>
                </section>
            </div>
        <?php endif; ?>
    </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
