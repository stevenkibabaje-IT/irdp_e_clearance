<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';require_role('OFFICER','SUPERVISOR');
$u=current_user();
if (!clearance_fee_officer($pdo,(int)$u['id'])) { http_response_code(403);exit('Only Finance and Accounting can manage clearance fees.'); }
$error='';$errors=[];$period=clearance_period_state($pdo);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try { verify_csrf();set_clearance_fee_amount($pdo,(int)$u['id'],positive_id($_POST['cycle_id']??null,'cycle_id'),$_POST['amount']??null);flash('success','Fee saved. Existing payment requests keep their quoted amount.');redirect('officer/clearance_fees.php'); }
    catch(Throwable $e) { $error=page_error($e,$errors); }
}
$s=$pdo->prepare('SELECT fp.*,s.registration_number,u.full_name,ac.label AS academic_cycle FROM clearance_fee_payments fp INNER JOIN students s ON s.id=fp.student_id INNER JOIN users u ON u.id=s.user_id INNER JOIN academic_cycles ac ON ac.id=fp.cycle_id WHERE fp.assigned_officer_id=? ORDER BY fp.status="APPROVED",fp.requested_at,fp.id');$s->execute([$u['id']]);$payments=$s->fetchAll();
$cycles=$pdo->query('SELECT ac.id,ac.label,COALESCE(fr.amount,10000.00) AS amount FROM academic_cycles ac LEFT JOIN clearance_fee_rates fr ON fr.cycle_id=ac.id WHERE ac.active=1 ORDER BY ac.label DESC')->fetchAll();
$selected=isset($_POST['cycle_id'])?(int)$_POST['cycle_id']:(int)($period['cycle_id']??($cycles[0]['id']??0));
$pageTitle='Clearance Fee Payments';require_once __DIR__.'/../includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow">FINANCE AND ACCOUNTING</span><h1>Clearance Fee Payments</h1><p class="muted">Issue control numbers and verify the entry fee before students start clearance.</p></div></div>
<?php if($error!==''): ?><div class="alert danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<section class="panel"><h2>Set clearance entry fee</h2><p>Default fee: TSh 10,000. Changes apply to new payment requests for the selected cycle. Existing requests and clearances retain their current terms.</p>
<form method="post"><?php csrf_field(); ?>
<label for="cycle_id">Academic cycle</label><select id="cycle_id" name="cycle_id" required><?php foreach($cycles as $cycle): ?><option value="<?= (int)$cycle['id'] ?>" <?= (int)$cycle['id']===$selected?'selected':'' ?>><?= e($cycle['label']) ?> — TSh <?= e(number_format((float)$cycle['amount'],2)) ?></option><?php endforeach; ?></select><?php form_error('cycle_id',$errors); ?>
<label for="amount">New entry fee (TSh)</label><input type="number" id="amount" name="amount" min="0.01" max="<?= e(CLEARANCE_MAX_AMOUNT) ?>" step="0.01" required value="<?= old_value('amount',clearance_fee_amount($pdo,$selected)) ?>"><?php form_error('amount',$errors); ?><button class="btn primary" type="submit">Save fee amount</button>
</form></section>
<section class="panel"><h2>Assigned payment requests</h2><?php if(!$payments): ?><p class="muted">No entry-fee requests are assigned to you yet.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Student</th><th>Cycle</th><th>Fee (TSh)</th><th>Payment status</th><th>Action</th></tr></thead><tbody>
<?php foreach($payments as $fee): ?><tr><td><?= e($fee['full_name']) ?><br><small><?= e($fee['registration_number']) ?></small></td><td><?= e($fee['academic_cycle']) ?></td><td><?= e(number_format((float)$fee['amount'],2)) ?></td><td><?= e(str_replace('_',' ',$fee['status'])) ?></td><td><a class="btn secondary" href="<?= e(url('officer/clearance_fee_review.php?id='.$fee['id'])) ?>">Review payment</a></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
