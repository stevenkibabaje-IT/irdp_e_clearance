<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/adapter.php';
$actor=ar_ui_require_role('STUDENT');$u=current_user();$period=clearance_period_state($pdo);
if(get_student_clearance($pdo,(int)$u['student_id'])){redirect('student/status.php');}
$error='';$errors=[];
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 try{
  ar_ui_csrf();
  $cycle=text_input($_POST,'academic_year',20);
  if(!$period['is_open']||$cycle!==$period['academic_cycle']){throw new DomainException(CLEARANCE_CLOSED_MESSAGE);}
  $fee=clearance_fee_for_cycle($pdo,(int)$u['student_id'],(int)$period['cycle_id']);
  if(!$fee||!clearance_fee_ready($pdo,$fee)){throw new DomainException('Finance must approve the current payment receipt before clearance starts.');}
  ar_start($pdo,(int)$u['id'],(int)$fee['id']);redirect('student/status.php');
 }catch(Throwable $e){$error=page_error($e,$errors);}
}
$pageTitle='Start Clearance';
$entryFee=$period['cycle_id']?clearance_fee_for_cycle($pdo,(int)$u['student_id'],(int)$period['cycle_id']):null;
$feeApproved=clearance_fee_ready($pdo,$entryFee);
require_once __DIR__.'/../includes/header.php';
?><div class="page-heading">
    <div>
        <span class="eyebrow">NEW REQUEST</span>
        <h1>Start Clearance</h1>
        <p class="muted">One clearance per registration number. Continue the same request until all 11 offices approve.</p>
    </div>
</div>

<div class="panel" style="max-width:680px;">
    <h2>Clearance Details</h2>

    <?php if (!$period['is_open']): ?><div class="alert warning"><?= e(CLEARANCE_CLOSED_MESSAGE) ?></div><?php endif; ?>
    <?php if (!$feeApproved): ?><div class="alert info">Pay the clearance entry fee and submit your payment receipt before starting.</div><p><a class="btn primary" href="<?= e(url('student/clearance_fee.php')) ?>">Clearance Fee / Upload receipt</a></p><?php else: ?><div class="alert success">Entry fee approved by Finance: TSh <?= e(number_format((float)$entryFee['amount'],2)) ?>. You can start clearance.</div><?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <?php form_fields(['academic_year'=>['label'=>'Select academic cycle','type'=>'select','required'=>true,'options'=>[''=>'Select'] + array_column($pdo->query('SELECT label FROM clearance_cycles WHERE active=1 ORDER BY label DESC')->fetchAll(), 'label', 'label'),'value'=>$period['academic_cycle'] ?? date('Y').'/'.((int)date('Y')+1)]], $errors); ?>

        <div class="form-actions">
            <a class="btn secondary" href="<?= e(url('student/dashboard.php')) ?>">Cancel</a>
            <button class="btn primary" type="submit" <?= $period['is_open'] && $feeApproved?'':'disabled' ?>>Start Clearance</button>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
