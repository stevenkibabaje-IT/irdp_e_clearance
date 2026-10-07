<?php
declare(strict_types=1);

/** Review an assigned stage, record office decisions and advance or return the request. */

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('OFFICER', 'SUPERVISOR');

$u = current_user();
$stageId = request_record_id($_GET['stage'] ?? $_POST['stage_id'] ?? null, 'stage_id');
$error = '';
$errors = [];

$stageStmt = $pdo->prepare(
    'SELECT
        cs.*,
        cr.student_id,
        cr.status AS request_status,
        cr.academic_year,
        ws.step_number,
        ws.title,
        u.full_name,
        s.registration_number,
        s.programme,
        s.year_of_study,
        s.department_id,
        s.user_id AS student_user_id,
        d.name AS department,
        o.name AS office
     FROM clearance_stages cs
     INNER JOIN clearance_requests cr ON cr.id = cs.clearance_request_id
     INNER JOIN workflow_steps ws ON ws.id = cs.workflow_step_id
     INNER JOIN students s ON s.id = cr.student_id
     INNER JOIN users u ON u.id = s.user_id
     LEFT JOIN departments d ON d.id = s.department_id
     INNER JOIN offices o ON o.id = cs.office_id
     WHERE cs.id = ?
       AND cs.assigned_officer_id = ?
     LIMIT 1'
);
$stageStmt->execute([$stageId, (int) $u['id']]);
$stage = $stageStmt->fetch();

if (!$stage) {
    http_response_code(404);
    exit('Stage not found or not assigned to you.');
}

if (!reviewer_assignment_valid($pdo, $stage, (int)$u['id'])) {
    http_response_code(403);
    exit('You do not have authority to review this office and department.');
}

$isFinance = (int)$stage['step_number'] === 11;
if (!in_array($stage['status'], ['PENDING', 'IN_REVIEW'], true) && !($isFinance && $stage['status'] === 'REJECTED')) {
    http_response_code(409);
    exit('This stage has already been approved or is not available for review.');
}

$detailFields = review_fields((int)$stage["step_number"]);

$existingDetails = json_decode((string) ($stage['details_json'] ?? ''), true);
$existingDetails = is_array($existingDetails) ? $existingDetails : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        process_stage_decision($pdo,$stageId,(int)$u['id'],$_POST,$detailFields);
        flash('success', $isFinance
            ? (($_POST['action']??'') === 'REJECTED' ? 'Control number sent to the student. Awaiting a payment receipt.' : 'Payment receipt approved. Clearance is complete.')
            : (($_POST['action']??'')==='REJECTED'?'Stage rejected. Awaiting student response and evidence.':'Stage approved. The student can continue to the next clearance stage.'));
        redirect('officer/dashboard.php');
    } catch (Throwable $e) {
        $error=page_error($e,$errors);
    }
}
$historyStmt=$pdo->prepare('SELECT rc.*,sa.action,sa.comments,sa.corrective_instructions,sa.created_at AS decided_at FROM review_cycles rc LEFT JOIN stage_actions sa ON sa.stage_id=rc.stage_id AND sa.review_cycle=rc.cycle_number WHERE rc.stage_id=? ORDER BY rc.cycle_number,sa.id');
$historyStmt->execute([$stageId]);$history=$historyStmt->fetchAll();
$evidenceStmt=$pdo->prepare('SELECT * FROM stage_evidence WHERE stage_id=? ORDER BY cycle_number,id');$evidenceStmt->execute([$stageId]);$evidence=$evidenceStmt->fetchAll();
$currentEvidence=array_filter($evidence,static fn(array $file):bool=>(int)$file['cycle_number']===(int)$stage['review_cycle']);
$currentResponse='';
foreach($history as $item) {
    if((int)$item['cycle_number']===(int)$stage['review_cycle']) {
        $currentResponse=trim((string)$item['student_response']);
    }
}
$canAcceptCorrection=!$isFinance && (int)$stage['resubmissions']>0 && (int)$stage['review_cycle']>=2 && ($currentEvidence || $currentResponse!=='');
$approvalAction=$canAcceptCorrection ? ($currentEvidence ? 'APPROVED_EVIDENCE' : 'APPROVED_CORRECTION') : 'APPROVED';
$approvalLabel=$canAcceptCorrection ? ($currentEvidence ? 'Approve evidence and continue' : 'Approve correction and continue') : 'Approve';
$financeReceiptReady = $isFinance && finance_receipt_available($pdo, $stage);
if ($isFinance && !$financeReceiptReady) { $currentEvidence = []; }
$pageTitle = 'Review Clearance';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <span class="eyebrow">STAGE <?= (int) $stage['step_number'] ?> OF 11</span>
        <h1><?= e($stage['title']) ?></h1>
        <p class="muted">
            <?= e($stage['full_name']) ?> · <?= e($stage['registration_number']) ?>
        </p>
    </div>
    <span class="<?= e(badge_class($stage['status'])) ?>"><?= e($stage['status']) ?></span>
</div>

<?php if ($error !== ''): ?>
    <div class="alert danger"><?= e($error) ?></div>
<?php endif; ?>

<?php if ((int)$stage['resubmissions']>0): ?>
    <section class="panel">
        <h2><?= $isFinance ? 'Payment receipt — verify before deciding' : 'Student resubmission — review before deciding' ?></h2>
        <?php foreach ($history as $item): if ((int)$item['cycle_number']!==(int)$stage['review_cycle']) { continue; } ?>
            <p><strong>Student response:</strong> <?= e($item['student_response']) ?></p>
        <?php endforeach; ?>
        <h3><?= $isFinance ? 'Payment receipt for the current control number' : 'Supporting evidence for this submission' ?></h3>
        <?php if ($currentEvidence): ?>
            <ul><?php foreach ($currentEvidence as $file): ?><li><a href="<?= e(url('files/evidence.php?id='.$file['id'])) ?>"><?= e($file['original_name']) ?></a> (<?= e(strtoupper(pathinfo($file['original_name'],PATHINFO_EXTENSION))) ?>, <?= (int)ceil((int)$file['byte_size']/1024) ?> KB)</li><?php endforeach; ?></ul>
        <?php else: ?><p class="muted"><?= $isFinance ? 'No receipt is awaiting review for the current payment request.' : 'The student submitted an explanation without attached files.' ?></p><?php endif; ?>
        <p class="muted"><?= $isFinance ? 'Open the receipt and confirm payment for the control number below before approving. Approval completes clearance.' : 'Approve when the correction and office requirements are satisfied. Approval opens the next clearance stage.' ?></p>
    </section>
<?php endif; ?>

<div class="two-col">
    <section class="panel">
        <h2>Student Details</h2>
        <div class="detail-grid">
            <div><span>Name</span><strong><?= e($stage['full_name']) ?></strong></div>
            <div><span>Registration</span><strong><?= e($stage['registration_number']) ?></strong></div>
            <div><span>Programme</span><strong><?= e($stage['programme']) ?></strong></div>
            <div><span>Department</span><strong><?= e($stage['department'] ?? 'Not set') ?></strong></div>
            <div><span>Academic Year</span><strong><?= e($stage['academic_year']) ?></strong></div>
            <div><span>Office</span><strong><?= e($stage['office']) ?></strong></div>
        </div>

        <?php if ($stage['comments'] && !$isFinance): ?>
            <div class="comment rejection" style="margin-top:18px;">
                <strong>Previous rejection reason:</strong><br>
                <?= e($stage['comments']) ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Office Review</h2>
        <?php if ($isFinance): ?>
            <p class="muted">Enter the control number and select Reject to request payment from the student. After the student uploads a receipt, verify it and select Approve or Reject.</p>
            <?php if (!$financeReceiptReady): ?><div class="alert info">A payment receipt must be submitted before you can approve.</div><?php endif; ?>
        <?php else: ?>
            <p class="muted">Complete the fields that apply to your office, then approve or reject the stage.</p>
        <?php endif; ?>
        <?php if($canAcceptCorrection): ?>
            <div class="alert info">The fields below show the previous office findings. After verifying the student's response and any attached files, choose <strong><?= e($approvalLabel) ?></strong> only when all office requirements have been resolved. This clears remaining amounts and missing-item findings for this review and opens the next stage. The previous rejection stays in the history.</div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="stage_id" value="<?= $stageId ?>"><input type="hidden" name="review_cycle" value="<?= (int)$stage["review_cycle"] ?>">

            <?php foreach ($detailFields as [$key, $label, $type]): ?>
                <label for="<?= e($key) ?>"><?= e($label) ?></label>

                <?php if ($type === 'asset'): ?>
                    <select id="<?= e($key) ?>" name="<?= e($key) ?>">
                        <option value="AVAILABLE" <?= old_value($key, (string)($existingDetails[$key] ?? '')) === 'AVAILABLE' ? 'selected' : '' ?>>Available</option>
                        <option value="MISSING" <?= old_value($key, (string)($existingDetails[$key] ?? '')) === 'MISSING' ? 'selected' : '' ?>>Missing</option>
                    </select>
                <?php elseif ($type === 'decision'): ?>
                    <select id="<?= e($key) ?>" name="<?= e($key) ?>">
                        <option value="CLEARED" <?= old_value($key, (string)($existingDetails[$key] ?? '')) === 'CLEARED' ? 'selected' : '' ?>>Cleared</option>
                        <option value="NOT_CLEARED" <?= old_value($key, (string)($existingDetails[$key] ?? '')) === 'NOT_CLEARED' ? 'selected' : '' ?>>Not Cleared</option>
                    </select>
                <?php elseif ($type === 'control_number'): ?>
                    <input id="control_number" name="control_number" type="text" inputmode="numeric" pattern="[0-9]{6,30}" minlength="6" maxlength="30" required autocomplete="off" value="<?= old_value($key, (string)($existingDetails[$key] ?? '')) ?>">
                <?php else: ?>
                    <input
                        id="<?= e($key) ?>"
                        type="number"
                        name="<?= e($key) ?>"
                        min="0"
                        max="<?= e(CLEARANCE_MAX_AMOUNT) ?>"
                        data-clearance-amount="true"
                        step="0.01"
                        required
                        value="<?= old_value($key, (string)($existingDetails[$key] ?? "0")) ?>"
                    >
                <?php endif; ?>
                <?php form_error($key, $errors); ?>
            <?php endforeach; ?>

            <?php if (!$isFinance): ?>
            <label for="corrective_instructions">Corrective instructions (required when rejecting)</label><textarea id="corrective_instructions" name="corrective_instructions" maxlength="4000"><?= old_value("corrective_instructions", (string)($stage["corrective_instructions"] ?? "")) ?></textarea><?php form_error("corrective_instructions", $errors); ?><label for="comments">Comments / Rejection Reason</label>
            <textarea id="comments" name="comments" maxlength="4000" placeholder="Add notes or, when rejecting, explain what must be resolved."><?= old_value("comments", (string)($stage["comments"] ?? "")) ?></textarea><?php form_error("comments", $errors); ?>

            <?php endif; ?>

            <div class="form-actions">
                <button
                    class="btn danger"
                    type="submit"
                    name="action"
                    value="REJECTED"
                    onclick="<?= $isFinance ? "return confirmAction('Send this control number to the student and request a payment receipt?');" : "document.getElementById('comments').required=true;document.getElementById('corrective_instructions').required=true;return confirmAction('Reject this stage? The entire clearance will be paused.');" ?>"
                >
                    <?= icon('x-circle') ?> Reject
                </button>
                <button class="btn primary" type="submit" name="action" value="<?= e($approvalAction) ?>" <?= $isFinance && !$financeReceiptReady ? 'disabled' : '' ?> <?= !$isFinance ? "onclick=\"document.getElementById('comments').required=false;document.getElementById('corrective_instructions').required=false;\"" : '' ?>>
                    <?= icon('check-circle') ?> <?= e($approvalLabel) ?>
                </button>
            </div>
        </form>
    </section>
</div>
<section class="panel"><h2>Student responses and review history</h2><?php foreach($history as $item):?><article class="comment"><strong>Cycle <?=(int)$item['cycle_number']?></strong><p><?=e($item['student_response'])?></p><?php if($item['action']):?><p><?=e($item['action'])?>: <?=e($item['comments'])?><br><?=e($item['corrective_instructions'])?></p><?php endif;?></article><?php endforeach;?><?php foreach($evidence as $file):?><p>Cycle <?=(int)$file['cycle_number']?>: <a href="<?=e(url('files/evidence.php?id='.$file['id']))?>"><?=e($file['original_name'])?></a></p><?php endforeach;?></section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
