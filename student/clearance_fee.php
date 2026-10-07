<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_role('STUDENT');
$u=current_user();$period=clearance_period_state($pdo);$error='';$errors=[];
if ($period['academic_cycle'] && get_clearance_for_cycle($pdo,(int)$u['student_id'],$period['academic_cycle'])) { redirect('student/status.php'); }
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        verify_csrf();
        if (($_POST['mode']??'')==='REQUEST') { request_clearance_fee($pdo,(int)$u['id']);flash('success','Request sent to Finance. Wait for your control number.'); }
        else { process_clearance_fee($pdo,positive_id($_POST['payment_id']??null),(int)$u['id'],$_POST,upload_file_list($_FILES['evidence']??[]));flash('success','Receipt uploaded. Finance must verify the payment before Start Clearance is unlocked.'); }
        redirect('student/clearance_fee.php');
    } catch(Throwable $e) { $error=page_error($e,$errors); }
}
$fee=$period['cycle_id']?clearance_fee_for_cycle($pdo,(int)$u['student_id'],(int)$period['cycle_id']):null;
if ($fee) { $fee=clearance_fee_record($pdo,(int)$fee['id']); }
$amount=$fee['amount']??clearance_fee_amount($pdo,(int)($period['cycle_id']??0));
$receipt=$fee?clearance_fee_receipt($pdo,$fee):null;
$pageTitle='Clearance Fee';require_once __DIR__.'/../includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow">BEFORE STARTING CLEARANCE</span><h1>Clearance Fee</h1><p class="muted">Pay the entry fee, upload your receipt and wait for Finance approval.</p></div></div>
<?php if($error!==''): ?><div class="alert danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<section class="panel">
    <h2>Entry fee: TSh <?= e(number_format((float)$amount,2)) ?></h2>
    <p>Academic cycle: <strong><?= e($period['academic_cycle']??'Not yet selected') ?></strong>. This fee is separate from any other debt reviewed by Finance during clearance.</p>
    <?php if(!$period['is_open']): ?><div class="alert warning"><?= e(CLEARANCE_CLOSED_MESSAGE) ?></div><?php endif; ?>
    <?php if(!$fee): ?>
        <p>Request a control number from Finance and Accounting. Clearance starts after your payment has been verified.</p>
        <form method="post"><?php csrf_field(); ?><input type="hidden" name="mode" value="REQUEST"><button class="btn primary" type="submit" <?= $period['is_open']?'':'disabled' ?>><?= icon('file-text') ?> Request control number</button></form>
    <?php elseif($fee['status']==='REQUESTED'): ?>
        <div class="alert info">Your request is pending. Finance and Accounting will issue your control number when the officer reviews it.</div>
    <?php elseif($fee['status']==='APPROVED'): ?>
        <div class="alert success">Payment approved. Your entry fee is paid for this academic cycle.</div>
        <a class="btn primary" href="<?= e(url('student/start.php')) ?>"><?= icon('plus') ?> Start Clearance</a>
    <?php else: ?>
        <div class="detail-grid"><div><span>Control number</span><strong><?= e($fee['control_number']) ?></strong></div><div><span>Payment status</span><strong><?= e(str_replace('_',' ',$fee['status'])) ?></strong></div></div>
        <p>Pay TSh <?= e(number_format((float)$fee['amount'],2)) ?> using this control number. Upload the receipt below; uploading alone does not confirm payment.</p>
        <?php if($fee['status']==='AWAITING_REVIEW'): ?><div class="alert info">Receipt submitted. Awaiting Finance verification.</div><?php endif; ?>
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
