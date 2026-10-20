<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/adapter.php';
ar_ui_require_role('STUDENT');

$u = current_user();
$student = get_student($pdo, (int) $u['student_id']);
$period=clearance_period_state($pdo);
$request = get_student_clearance($pdo,(int)$u['student_id']);
$feeError='';
if (!$request && $period['is_open']) {
    try { ensure_student_clearance_fee($pdo,(int)$u['id']); }
    catch (Throwable $error) { $feeError=user_safe_error($error); }
}
$stages = $request ? get_clearance_stages($pdo, (int) $request['id']) : [];
$entryFee = !$request && $period['cycle_id'] ? clearance_fee_for_cycle($pdo,(int)$u['student_id'],(int)$period['cycle_id']) : null;
$feeApproved = clearance_fee_ready($pdo,$entryFee?:null);

$transcript=($request&&$request['status']==='COMPLETED')?transcript_for_student($pdo,(int)$u['student_id'],(int)$request['id']):null;
$transcript=($transcript&&$transcript['status']==='VALID')?$transcript:null;
$approvedOffices = count(array_filter($stages, static fn(array $stage): bool => $stage['status'] === 'APPROVED'));
$correctionOffices = count(array_filter($stages, static fn(array $stage): bool => $stage['status'] === 'REJECTED'));
$pendingOffices = count($stages) - $approvedOffices - $correctionOffices;

$pageTitle = 'Student Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="dashboard-view dashboard-student">
<div class="dash-breadcrumb"><?= icon('home') ?> <span>Student services</span><span aria-hidden="true">/</span><strong>Overview</strong></div>
<div class="page-heading student-dashboard-heading dash-hero">
    <div>
        <span class="eyebrow">YOUR STUDENT WORKSPACE</span>
        <h1>My Dashboard</h1>
        <p class="muted">Track your clearance, follow office reviews and see your next step.</p>
        <div class="dash-student-identity"><span><?= icon('graduation-cap') ?> <?= e($student['registration_number']) ?></span><span><?= e($student['programme'] ?: 'Student') ?></span></div>
    </div>

    <div class="dash-hero-actions">
    <span class="dash-cycle"><?= icon('calendar') ?> <?= e($request['academic_year'] ?? $period['academic_cycle'] ?? 'Academic cycle not set') ?></span>
    <?php if (!$request && $period['is_open']): ?>
        <a class="btn primary" href="<?= e(url($feeApproved?'student/start.php':'student/clearance_fee.php')) ?>"><?= $feeApproved?'Start Clearance':'Clearance Fee' ?></a>
    <?php elseif (!$request): ?>
        <span class="badge muted">CLEARANCE CLOSED</span>
    <?php else: ?>
        <a class="btn primary" href="<?= e(url('student/status.php')) ?>"><?= $request['status']==='COMPLETED'?'View Clearance':'Continue Clearance' ?></a>
    <?php endif; ?>
    </div>
</div>

<section class="stats-grid dash-student-stats" aria-label="Your office review summary">
    <article class="stat-card"><div class="stat-icon"><?= icon('check-circle') ?></div><span>Approved Offices</span><strong><?= $approvedOffices ?><small> / 11</small></strong><small>Completed office reviews</small></article>
    <article class="stat-card info"><div class="stat-icon"><?= icon('clock') ?></div><span>Awaiting Review</span><strong><?= $pendingOffices ?></strong><small><?= $request ? 'Your remaining office reviews' : 'Reviews appear after you start' ?></small></article>
    <article class="stat-card warning"><div class="stat-icon"><?= icon('alert-circle') ?></div><span>Corrections Required</span><strong><?= $correctionOffices ?></strong><small><?= $correctionOffices ? 'Open My Clearance to respond' : 'No corrections outstanding' ?></small></article>
    <article class="stat-card purple"><div class="stat-icon"><?= icon('building') ?></div><span>Clearance Offices</span><strong>11</strong><small>Every office reviews independently</small></article>
</section>

<?php if(!$request && $period['cycle_id']): ?>
<section class="panel dash-entry-fee"><div class="panel-head"><div class="dash-inline-heading"><span class="dash-period-icon"><?= icon('receipt') ?></span><div><span class="eyebrow">BEFORE YOU BEGIN</span><h2>Clearance entry fee</h2></div></div><span class="<?= e($feeApproved ? 'badge success' : 'badge info') ?>"><?= $feeApproved ? 'Receipt received' : 'Payment and receipt' ?></span></div>
<?php if($feeError): ?><div class="alert warning"><?= e($feeError) ?></div><?php endif; ?>
<p><strong>TSh <?= e(number_format((float)($entryFee['amount']??clearance_fee_amount($pdo,(int)$period['cycle_id'])),2)) ?></strong> &middot; <?= e($entryFee?str_replace('_',' ',$entryFee['status']):'NOT PREPARED') ?></p>
<?php if($entryFee): clearance_fee_control_summary($entryFee); endif; ?>
<p><?= $feeApproved?'Receipt received. You can now start clearance.':'Your control number is prepared automatically. Submit your payment receipt to unlock Start Clearance.' ?></p><a class="btn secondary" href="<?= e(url('student/clearance_fee.php')) ?>">View fee / upload receipt</a></section>
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
            <div class="empty dash-start-empty">
                <div class="dash-empty-icon"><?= icon('clipboard-check') ?></div>
                <h3>Your clearance journey starts here</h3>
                <p>You have not started your clearance yet.</p>
                <ol class="dash-onboarding"><li><span>1</span> Get your control number</li><li><span>2</span> Upload your payment receipt</li><li><span>3</span> Start your office reviews</li></ol>
                <a class="btn primary" href="<?= e(url($feeApproved?'student/start.php':'student/clearance_fee.php')) ?>"><?= $feeApproved?'Start Clearance':'Pay Clearance Fee' ?></a>
            </div>
        <?php else: ?>
            <div class="student-clearance-layout">
                <section class="student-progress-overview" aria-labelledby="student-progress-heading">
                    <h3 id="student-progress-heading">Clearance Progress</h3>
                    <?php clearance_progress($stages); ?>
                    <?php if ($transcript): ?>
                        <a class="btn primary small student-transcript-link" href="<?= e(url('transcripts/download.php')) ?>"><?= icon('download') ?> Download Student Clearance Certificate</a>
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
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
