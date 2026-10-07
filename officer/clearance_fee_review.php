<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';require_role('OFFICER','SUPERVISOR');
$u=current_user();$id=request_record_id($_GET['id']??null);$fee=clearance_fee_record($pdo,$id);$error='';$errors=[];
if (!$fee || (int)$fee['assigned_officer_id']!==(int)$u['id'] || !clearance_fee_officer($pdo,(int)$u['id'])) { http_response_code(403);exit('Payment not assigned to you or Finance authority is missing.'); }
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try { verify_csrf();process_clearance_fee($pdo,$id,(int)$u['id'],$_POST);flash('success',($_POST['mode']??'')==='APPROVE'?'Payment verified and approved. The student can now start clearance.':'Control number sent. Wait for the student payment receipt.');redirect('officer/clearance_fee_review.php?id='.$id); }
    catch(Throwable $e) { $error=page_error($e,$errors); }
    $fee=clearance_fee_record($pdo,$id);
}
$receipt=clearance_fee_receipt($pdo,$fee);$pageTitle='Review Clearance Fee';require_once __DIR__.'/../includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow">ENTRY FEE</span><h1>Review Clearance Fee</h1><p class="muted"><?= e($fee['full_name']) ?> · <?= e($fee['registration_number']) ?></p></div><a class="btn secondary" href="<?= e(url('officer/clearance_fees.php')) ?>">Payment requests</a></div>
<?php if($error!==''): ?><div class="alert danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<section class="panel"><h2>Payment details</h2><div class="detail-grid"><div><span>Academic cycle</span><strong><?= e($fee['academic_cycle']) ?></strong></div><div><span>Amount (TSh)</span><strong><?= e(number_format((float)$fee['amount'],2)) ?></strong></div><div><span>Control number</span><strong><?= e($fee['control_number']??'Not issued') ?></strong></div><div><span>Status</span><strong><?= e(str_replace('_',' ',$fee['status'])) ?></strong></div></div><p>This entry fee unlocks Start Clearance. Other Finance debts are reviewed separately during clearance.</p>
<?php if($fee['status']==='APPROVED'): ?><div class="alert success">Entry fee approved on <?= e($fee['approved_at']) ?>.</div><?php else: ?>
<form method="post"><?php csrf_field(); ?><input type="hidden" name="mode" value="CONTROL"><input type="hidden" name="payment_version" value="<?= (int)$fee['payment_version'] ?>"><label for="control_number">Control number</label><input id="control_number" name="control_number" type="text" inputmode="numeric" pattern="[0-9]{6,30}" minlength="6" maxlength="30" required autocomplete="off" value="<?= old_value('control_number',(string)($fee['control_number']??'')) ?>"><?php form_error('control_number',$errors); ?><p class="muted">Changing this number requires a new receipt. Previous receipts remain in the payment history.</p><button class="btn secondary" type="submit"><?= icon('file-text') ?> <?= $fee['control_number']?'Update control number':'Send control number' ?></button></form>
<?php endif; ?></section>
<section class="panel"><h2>Verify payment receipt</h2>
<?php if($receipt): ?><p><a href="<?= e(url('files/clearance_fee_receipt.php?id='.$receipt['id'])) ?>"><?= icon('download') ?> <?= e($receipt['original_name']) ?></a></p><?php else: ?><p class="muted">No receipt is available for the current control number.</p><?php endif; ?>
<?php if($fee['status']!=='APPROVED'): ?><form method="post"><?php csrf_field(); ?><input type="hidden" name="mode" value="APPROVE"><input type="hidden" name="payment_version" value="<?= (int)$fee['payment_version'] ?>"><label><input type="checkbox" name="confirm_payment" value="1" required <?= $receipt?'':'disabled' ?>> I verified payment of TSh <?= e(number_format((float)$fee['amount'],2)) ?> for control number <?= e($fee['control_number']??'not issued') ?>.</label><button class="btn primary" type="submit" <?= $receipt?'':'disabled' ?>><?= icon('check-circle') ?> Approve payment</button></form><?php endif; ?>
</section>
<section class="panel"><h2>Payment history</h2><div class="table-wrap"><table><thead><tr><th>Date</th><th>Action</th><th>Officer / student</th><th>Control number</th><th>Receipt</th></tr></thead><tbody>
<?php foreach(clearance_fee_history($pdo,$id) as $item): $details=json_decode($item['details_json'],true); ?><tr><td><?= e($item['created_at']) ?></td><td><?= e(str_replace('_',' ',$item['action'])) ?></td><td><?= e($item['full_name']) ?></td><td><?= e($details['control_number']??'Not issued') ?></td><td><?php if($details['receipt_id']??null): ?><a href="<?= e(url('files/clearance_fee_receipt.php?id='.$details['receipt_id'])) ?>">Download receipt</a><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
