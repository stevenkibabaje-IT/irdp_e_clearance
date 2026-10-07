<?php
declare(strict_types=1);

/** Submit corrections and evidence for office review of a rejected stage. */
require_once __DIR__.'/../includes/bootstrap.php';require_role('STUDENT');$error='';$errors=[];
try{$stageId=positive_id($_GET['stage']??$_POST['stage_id']??null,'stage_id');$stage=stage_context($pdo,$stageId);if((int)$stage['student_user_id']!==(int)current_user()['id']){http_response_code(403);exit('Access denied.');}}catch(Throwable $e){http_response_code(404);exit('Stage not found.');}
$isFinance = (int)$stage['step_number'] === 11;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf(); require_clearance_period($pdo);
        $mode = text_input($_POST,'mode',20);
        $files = upload_file_list($_FILES['evidence'] ?? []);
        if ($isFinance && $mode === 'finance_payment') {
            $number = finance_control_number($_POST['control_number'] ?? null);
            resubmit_stage($pdo,$stageId,(int)current_user()['id'],'',$files,$number);
            flash('success','Payment receipt submitted. Awaiting Finance approval.');
        } elseif (!$isFinance && $mode === 'resubmit') {
            $response = text_input($_POST,'response',4000,true);
            resubmit_stage($pdo,$stageId,(int)current_user()['id'],$response,$files);
            flash('success','Your response and evidence were submitted to the responsible office. Other offices can continue reviewing independently.');
        } else {
            throw new RuntimeException('Invalid response action.');
        }
        redirect('student/status.php#stage-'.$stageId);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $errorMessage = page_error($error,$errors);
        $error = $errorMessage;
        $stage = stage_context($pdo,$stageId);
    }
}
$s=$pdo->prepare('SELECT * FROM review_cycles WHERE stage_id=? ORDER BY cycle_number');$s->execute([$stageId]);$cycles=$s->fetchAll();$s=$pdo->prepare('SELECT * FROM stage_actions WHERE stage_id=? ORDER BY id');$s->execute([$stageId]);$actions=$s->fetchAll();$s=$pdo->prepare('SELECT * FROM stage_evidence WHERE stage_id=? ORDER BY id');$s->execute([$stageId]);$evidence=$s->fetchAll();
$pageTitle=$isFinance ? 'Finance Payment Receipt' : 'Response and Evidence';require_once __DIR__.'/../includes/header.php';?>
<section class="panel">
    <h1><?= e($stage['title']) ?></h1>
    <p>Decision: <?= e($stage['status']) ?></p>
    <?php if ($error): ?><div class="alert danger" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($isFinance): ?>
        <?php finance_payment_form($stage,$errors,true); ?>
        <a class="btn secondary" href="<?= e(url('student/status.php#stage-'.$stageId)) ?>">Back to My Clearance</a>
    <?php elseif ($stage['status']==='REJECTED'): ?>
        <div class="comment rejection">
            <strong>Rejection reason:</strong> <?= e($stage['comments']) ?><br>
            <strong>Corrective instructions:</strong> <?= e($stage['corrective_instructions'] ?: 'Contact this office to confirm the correction required.') ?>
        </div>
        <?php student_evidence_form($pdo,$stage,$errors); ?>
    <?php else: ?>
        <p><?= $stage['status']==='APPROVED' ? 'This office has approved your clearance. Track the remaining offices in My Clearance.' : 'Your stage is awaiting office review. Other offices can continue reviewing independently.' ?></p>
        <a class="btn primary" href="<?= e(url('student/status.php#stage-'.$stageId)) ?>">Back to My Clearance</a>
    <?php endif; ?>
</section>
<section class="panel">
    <h2>Submitted evidence and review history</h2>
    <?php foreach ($cycles as $cycle): ?>
        <article class="comment">
            <h3>Review cycle <?= (int)$cycle['cycle_number'] ?></h3>
            <p>Opened <?= e($cycle['opened_at'] ?: 'Not yet actionable') ?> &middot; Closed <?= e($cycle['closed_at'] ?: 'Awaiting decision') ?></p>
            <?php if ($cycle['student_response']): ?><p>Student response: <?= e($cycle['student_response']) ?></p><?php endif; ?>
            <?php foreach ($actions as $action): if ((int)$action['review_cycle']!==(int)$cycle['cycle_number']) { continue; } ?>
                <p><strong><?= e($action['action']) ?></strong> &middot; <?= e($action['created_at']) ?><br><?= e($action['comments']) ?><br>Instructions: <?= e($action['corrective_instructions']) ?></p>
            <?php endforeach; ?>
            <?php foreach ($evidence as $file): if ((int)$file['cycle_number']!==(int)$cycle['cycle_number']) { continue; } ?>
                <p><a href="<?= e(url('files/evidence.php?id='.$file['id'])) ?>"><?= e($file['original_name']) ?></a></p>
            <?php endforeach; ?>
        </article>
    <?php endforeach; ?>
</section>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
