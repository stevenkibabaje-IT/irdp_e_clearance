<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('STUDENT');

$u = current_user();
$student = get_student($pdo, (int) $u['student_id']);
$period=clearance_period_state($pdo);
$request = $period['academic_cycle']
    ? get_clearance_for_cycle($pdo,(int)$u['student_id'],(string)$period['academic_cycle'])
    : get_latest_clearance($pdo,(int)$u['student_id']);
$stages = $request ? get_clearance_stages($pdo, (int) $request['id']) : [];
$completed = count(array_filter($stages, static fn (array $stage): bool => $stage['status'] === 'APPROVED'));
$total = max(11, count($stages));
$progress = (int) round(($completed / $total) * 100);

$transcript=($request&&$request['status']==='COMPLETED')?transcript_for_student($pdo,(int)$u['student_id'],(int)$request['id']):null;
$transcript=($transcript&&$transcript['status']==='VALID')?$transcript:null;

$pageTitle = 'Student Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <span class="eyebrow">STUDENT</span>
        <h1>Welcome, <?= e($student['full_name'] ?? $u['full_name']) ?></h1>
        <a href="<?= e(url('student/profile.php')) ?>"><img class="profile-avatar" src="<?= e(profile_picture_url($student)) ?>" width="80" height="80" alt="My profile picture"></a>
        <p class="muted">
            <?= e($student['registration_number'] ?? '') ?> ·
            <?= e($student['department'] ?? 'Department not set') ?>
        </p>
    </div>

    <?php if (!$request && $period['is_open']): ?>
        <a class="btn primary" href="<?= e(url('student/start.php')) ?>">Start Clearance</a>
    <?php elseif (!$request): ?>
        <span class="badge muted">CLEARANCE CLOSED</span>
    <?php else: ?>
        <a class="btn primary" href="<?= e(url('student/status.php')) ?>"><?= $request['status']==='COMPLETED'?'View Clearance':'Continue Clearance' ?></a>
    <?php endif; ?>
</div>

<section class="panel">
    <div class="panel-head"><h2>Clearance Status</h2><span class="<?= e($period['is_open']?'badge success':'badge muted') ?>"><?= e($period['is_open']?'CLEARANCE OPEN':'CLEARANCE CLOSED') ?></span></div>
    <?php if($period['is_open']): ?><p>Academic cycle: <strong><?= e($period['academic_cycle']) ?></strong>. Opening: <?= e($period['opens_at']?:'Manual') ?> · Closing: <?= e($period['closes_at']?:'Manual') ?></p><?php else: ?><p><?= e(CLEARANCE_CLOSED_MESSAGE) ?></p><?php endif; ?>
    <?php if($transcript): ?><a class="btn gold" href="<?= e(url('transcripts/download.php')) ?>"><?= icon('download') ?> Download Clearance Transcript</a><?php elseif($request&&$request['status']!=='COMPLETED'): ?><p class="muted">Clearance transcript access will be available after successful completion of the clearance process.</p><?php endif; ?>
</section>

<?php foreach ($stages as $financeStage): if ((int)$financeStage['step_number'] !== 11 || !isset(finance_payment_details($financeStage)['control_number'])) { continue; } ?>
    <section class="panel">
        <?php finance_payment_form($financeStage); ?>
    </section>
<?php endforeach; ?>

<?php foreach ($stages as $rejectedStage): if ($rejectedStage['status'] !== 'REJECTED' || (int)$rejectedStage['step_number'] === 11) { continue; } ?>
    <section class="panel rejection-action">
        <h2>Action required: <?= e($rejectedStage['office']) ?></h2>
        <p><strong><?= e($rejectedStage['title']) ?> was rejected.</strong> <?= e($rejectedStage['comments']) ?></p>
        <p><?= e($rejectedStage['corrective_instructions'] ?: 'Contact the office for the correction required.') ?></p>
        <a class="btn primary" href="<?= e(url('student/status.php#stage-'.(int)$rejectedStage['id'])) ?>">Respond / Upload Evidence</a>
    </section>
<?php endforeach; ?>

<div class="stats-grid">
    <div class="stat-card info">
        <div class="stat-icon"><?= icon('clipboard-check') ?></div>
        <span>Current Status</span>
        <strong><?= e($request && $request['status']==='PAUSED'?'Action Required':($request['status'] ?? 'NOT_STARTED')) ?></strong>
    </div>

    <div class="stat-card">
        <div class="stat-icon"><?= icon('check-circle') ?></div>
        <span>Completed Stages</span>
        <strong><?= $completed ?> / <?= $total ?></strong>
    </div>

    <div class="stat-card warning">
        <div class="stat-icon"><?= icon('clock') ?></div>
        <span>Pending Actions</span>
        <strong><?= count(array_filter($stages, static fn (array $stage): bool => in_array($stage['status'], ['PENDING', 'IN_REVIEW'], true))) ?></strong>
    </div>

    <div class="stat-card purple">
        <div class="stat-icon"><?= icon('chart') ?></div>
        <span>Overall Progress</span>
        <strong><?= $progress ?>%</strong>
    </div>
</div>

<div class="two-col">
    <section class="panel">
        <div class="panel-head">
            <h2>Clearance Progress</h2>
            <?php if ($request): ?>
                <span class="<?= e(badge_class($request['status'])) ?>"><?= e($request['status']) ?></span>
            <?php endif; ?>
        </div>

        <?php if (!$request): ?>
            <div class="empty">
                <p>You have not started your clearance yet.</p>
                <a class="btn primary" href="<?= e(url('student/start.php')) ?>">Start Clearance</a>
            </div>
        <?php else: ?>
            <div class="progress-label">
                <span><?= $completed ?> of <?= $total ?> stages approved</span>
                <span><?= $progress ?>%</span>
            </div>
            <div class="progress-track" role="progressbar" aria-label="Clearance progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $progress ?>">
                <div style="width: <?= $progress ?>%;"></div>
            </div>

            <div class="timeline" style="margin-top:12px;">
                <?php foreach ($stages as $stage): ?>
                    <div class="timeline-item <?= strtolower($stage['status']) ?>">
                        <div class="dot"><?= (int) $stage['step_number'] ?></div>
                        <div>
                            <strong><?= e($stage['title']) ?></strong>
                            <small>
                                <?= e($stage['office']) ?> ·
                                <span class="<?= e(badge_class($stage['status'])) ?>"><?= e($stage['status']) ?></span>
                            </small>

                            <?php if ($stage['status'] === 'REJECTED'): ?>
                                <p class="danger-text">Reason: <?= e($stage['comments']) ?></p>
                                <a class="btn primary small" href="<?= e(url('student/status.php#stage-'.(int)$stage['id'])) ?>">Respond / Upload Evidence</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section>
        <div class="panel">
            <h2>Quick Actions</h2>
            <div class="quick-grid">
                <a class="quick-card" href="<?= e(url('student/status.php')) ?>"><?= icon('clipboard-check') ?><strong>My Clearance</strong><br><small>Track all stages</small></a>
                <a class="quick-card" href="<?= e(url('student/notifications.php')) ?>"><?= icon('bell') ?><strong>Notifications</strong><br><small>View decisions</small></a>
                <a class="quick-card" href="<?= e(url('student/notifications.php')) ?>"><?= icon('info') ?><strong>Updates</strong><br><small>Recent activity</small></a>
            </div>
        </div>
    </section>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
