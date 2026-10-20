<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/adapter.php';
$actor=ar_ui_require_role('STUDENT');$u=current_user();$period=clearance_period_state($pdo);$error='';$errors=[];
if(get_student_clearance($pdo,(int)$u['student_id'])){redirect('student/status.php');}
if($period['is_open']){try{ensure_student_clearance_fee($pdo,(int)$u['id']);}catch(Throwable $e){$error=page_error($e,$errors);}}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 try{
  ar_ui_csrf();
  if(($_POST['mode']??'')==='REQUEST'){ensure_student_clearance_fee($pdo,(int)$u['id']);flash('success','Your control number is ready.');}
  elseif(($_POST['mode']??'')==='RECEIPT'){
   $requestId=positive_id($_POST['payment_id']??null,'payment_id');
   $fee=clearance_fee_for_cycle($pdo,(int)$u['id'],(int)$period['cycle_id']);
   if(!$period['is_open']||!$fee||(int)$fee['id']!==$requestId||(int)$fee['payment_version']!==(int)($_POST['payment_version']??-1)||!hash_equals((string)$fee['control_number'],(string)($_POST['control_number']??''))){throw new DomainException('Payment request changed. Refresh and try again.');}
   $files=upload_file_list($_FILES['evidence']??[]);if(count($files)!==1){throw new DomainException('Choose one receipt.');}
   inspect_upload($files[0],5242880,['pdf'=>['application/pdf'],'png'=>['image/png'],'jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg']]);
   ar_submit_receipt($pdo,(int)$u['id'],$requestId,$files[0]['tmp_name'],$files[0]['name']);
   flash('success','Receipt submitted. Wait for Finance approval before starting clearance.');
  } else {throw new DomainException('Invalid payment action.');}
  redirect('student/clearance_fee.php');
 }catch(Throwable $e){$error=page_error($e,$errors);}
}
$fee=$period['cycle_id']?clearance_fee_for_cycle($pdo,(int)$u['student_id'],(int)$period['cycle_id']):null;
$amount=$fee['amount']??clearance_fee_amount($pdo,(int)($period['cycle_id']??0));
$receipt=$fee?clearance_fee_receipt($pdo,$fee):null;
$ready=clearance_fee_ready($pdo,$fee);
$pageTitle='Clearance Fee';require_once __DIR__.'/../includes/header.php';
?><div class="page-heading"><div><span class="eyebrow">BEFORE STARTING CLEARANCE</span><h1>Clearance Fee</h1><p class="muted">Your control number is prepared automatically. Upload your payment receipt to unlock Start Clearance.</p></div></div>
<?php if($error!==''): ?><div class="alert danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<section class="panel">
    <h2>Entry fee: TSh <?= e(number_format((float)$amount,2)) ?></h2>
    <p>Academic cycle: <strong><?= e($period['academic_cycle']??'Not yet selected') ?></strong>. This fee is separate from any other debt reviewed by Finance during clearance.</p>
    <?php if(!$period['is_open']): ?><div class="alert warning"><?= e(CLEARANCE_CLOSED_MESSAGE) ?></div><?php endif; ?>
    <?php if($fee): clearance_fee_control_summary($fee); endif; ?>
    <?php if(!$fee): ?>
        <p>Your control number will be prepared when clearance is open.</p>
        <form method="post"><?php csrf_field(); ?><input type="hidden" name="mode" value="REQUEST"><button class="btn primary" type="submit" <?= $period['is_open']?'':'disabled' ?>><?= icon('file-text') ?> Prepare control number</button></form>
    <?php elseif($fee['status']==='REQUESTED'): ?>
        <div class="alert info">Your control number will be prepared automatically when clearance is open.</div>
    <?php elseif($fee['status']==='APPROVED'): ?>
        <div class="alert success">Payment approved. Your entry fee is paid for this academic cycle.</div>
        <?php if($ready): ?><a class="btn primary" href="<?= e(url('student/start.php')) ?>"><?= icon('plus') ?> Start Clearance</a><?php endif; ?>
    <?php else: ?>
        <p>Payment status: <strong><?= e(str_replace('_',' ',$fee['status'])) ?></strong>.</p>
        <p>Entry fee: TSh <?= e(number_format((float)$fee['amount'],2)) ?>. Upload the receipt for the control number above to unlock Start Clearance.</p>
        <?php if($ready): ?><div class="alert success">Receipt received. Start Clearance is ready. Finance verification remains pending.</div><a class="btn primary" href="<?= e(url('student/start.php')) ?>"><?= icon('plus') ?> Start Clearance</a><?php endif; ?>
        <?php if($receipt): ?><p><a href="<?= e(url('files/clearance_fee_receipt.php?id='.$receipt['id'])) ?>"><?= icon('download') ?> <?= e($receipt['original_name']) ?></a></p><?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <?php csrf_field(); ?><input type="hidden" name="mode" value="RECEIPT"><input type="hidden" name="payment_id" value="<?= (int)$fee['id'] ?>"><input type="hidden" name="payment_version" value="<?= (int)$fee['payment_version'] ?>"><input type="hidden" name="control_number" value="<?= e($fee['control_number']) ?>">
            <label for="evidence">Payment receipt (PDF, JPG or PNG; maximum 5 MB)</label><input type="file" id="evidence" name="evidence[]" accept=".pdf,.jpg,.jpeg,.png" required><?php form_error('evidence',$errors); ?>
            <button class="btn primary" type="submit" <?= $period['is_open']?'':'disabled' ?>><?= icon('upload') ?> <?= $receipt?'Replace payment receipt':'Upload payment receipt' ?></button>
        </form>
    <?php endif; ?>
</section>
<?php if($fee): ?>
<section class="panel"><h2>Payment history</h2><div class="table-wrap"><table><thead><tr><th>Date</th><th>Action</th><th>Control number</th></tr></thead><tbody>
<?php foreach(clearance_fee_history($pdo,(int)$fee['id']) as $item): $details=json_decode($item['details_json'],true); ?><tr><td><?= e($item['created_at']) ?></td><td><?= e(str_replace('_',' ',$item['action'])) ?></td><td><?= e($details['control_number']??'Awaiting issue') ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php endif; ?>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
