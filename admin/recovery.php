<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_role('ADMIN');
header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');
$error='';$errors=[];$credential=null;
$resetUsername='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if(($_POST['action']??'')!=='reset') {throw new RuntimeException('Choose Reset password.');}
        $credential=admin_reset_officer_password($pdo,$_POST,(int)current_user()['id']);
        $_SESSION['officer_reset_result']=['password'=>$credential,'username'=>text_input($_POST,'username',100)];
        redirect('admin/recovery.php?status=COMPLETED');
    } catch(Throwable $e) {$error=page_error($e,$errors);}
}
if($_SERVER['REQUEST_METHOD']==='GET' && isset($_SESSION['officer_reset_result'])) {
    $credential=$_SESSION['officer_reset_result']['password'];
    $resetUsername=$_SESSION['officer_reset_result']['username'];
    unset($_SESSION['officer_reset_result']);
}
$selected=null;
try {
    $selectedId=optional_id($_POST['request_id']??$_GET['request']??null,'request_id');
    if($selectedId) {
        $s=$pdo->prepare('SELECT pr.*,u.username,u.full_name,u.email FROM password_reset_requests pr INNER JOIN users u ON u.id=pr.user_id INNER JOIN roles r ON r.id=u.role_id WHERE pr.id=? AND r.name="OFFICER"');
        $s->execute([$selectedId]);$selected=$s->fetch();
        if(!$selected) {throw new RuntimeException('Recovery request not found.');}
    }
} catch(Throwable $e) {$error=page_error($e,$errors);}
$filter=$_GET['status']??'OPEN';
if(!in_array($filter,['OPEN','ALL','PENDING','ISSUED','COMPLETED','REJECTED'],true)) {http_response_code(400);exit('Choose a valid recovery status.');}
$page=request_record_id($_GET['page']??'1','page');
if($page>100000){http_response_code(400);exit('Page must be from 1 to 100000.');}
$offset=($page-1)*50;
$where=$filter==='OPEN'?'WHERE pr.status IN ("PENDING","ISSUED")':($filter==='ALL'?'':'WHERE pr.status=?');
$params=in_array($filter,['OPEN','ALL'],true)?[]:[$filter];
$where=($where===''?'WHERE ':$where.' AND ').'r.name="OFFICER"';
$s=$pdo->prepare('SELECT COUNT(*) FROM password_reset_requests pr INNER JOIN users u ON u.id=pr.user_id INNER JOIN roles r ON r.id=u.role_id '.$where);$s->execute($params);$total=(int)$s->fetchColumn();
$s=$pdo->prepare('SELECT pr.*,u.username,u.full_name,u.email,u.active,r.name AS role_name,a.full_name AS handler,
    p.expires_at,p.used_at,(p.used_at IS NULL AND p.expires_at>NOW()) AS credential_valid
    FROM password_reset_requests pr INNER JOIN users u ON u.id=pr.user_id INNER JOIN roles r ON r.id=u.role_id
    LEFT JOIN users a ON a.id=pr.handled_by LEFT JOIN password_resets p ON p.id=pr.reset_id
    '.$where.' ORDER BY pr.id DESC LIMIT 50 OFFSET '.$offset);
$s->execute($params);$requests=$s->fetchAll();
$pageTitle='Password Reset Requests';require_once __DIR__.'/../includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow">ACCOUNT RECOVERY</span><h1>Password Reset Requests</h1><p class="muted">Review forgotten-password requests, verify identity and help users choose a new password.</p></div></div>
<?php if($error):?><div class="alert danger"><?=e($error)?></div><?php endif;?>
<?php if($credential):?>
<section class="panel"><div class="alert success"><strong>Password reset for <?=e($resetUsername)?>. Temporary password (shown once):</strong>
<p><code id="temporary-password" style="overflow-wrap:anywhere"><?=e($credential)?></code></p>
<p>Give this password privately to the officer. They can log in with their usual username and this temporary password, then choose a new password. Existing sessions have been signed out.</p></div></section>
<?php endif;?>
<section class="panel" id="recovery-form" style="max-width:760px">
<h2><?= $selected?'Handle request #'.(int)$selected['id']:'Admin-assisted recovery' ?></h2>
<?php if($selected):?><p><strong><?=e($selected['full_name'])?></strong> &middot; <?=e($selected['username'])?> &middot; <?=e($selected['status'])?></p><?php endif;?>
<p>Select the officer and reset their password. Give the temporary password privately to the officer. They will choose a new password after logging in. Students continue to use Forgot Password with their registered email.</p>
<?php if(!$selected || in_array($selected['status'],['PENDING','ISSUED'],true)):?>
<form method="post" action="<?=e(url('admin/recovery.php'))?>" autocomplete="off">
<?php csrf_field();?><input type="hidden" name="request_id" value="<?= (int)($selected['id']??0) ?>">
<?php if($selected):?><input type="hidden" name="username" value="<?=e($selected['username'])?>">
<?php else: ?>
<label for="officer-username">Officer</label><select id="officer-username" name="username" required><option value="">Select an officer</option>
<?php foreach($pdo->query('SELECT u.username,u.full_name FROM users u INNER JOIN roles r ON r.id=u.role_id WHERE r.name="OFFICER" AND u.active=1 ORDER BY u.full_name') as $officer):?>
<option value="<?=e($officer['username'])?>"><?=e($officer['full_name'].' ('.$officer['username'].')')?></option>
<?php endforeach;?></select>
<?php endif;?>
<button class="btn primary" name="action" value="reset" type="submit">Reset password</button>
</form>
<?php else:?><p>This request is closed.</p><?php endif;?>
<?php if($selected):?><p><a href="<?=e(url('admin/recovery.php'))?>">Back to open requests / manual recovery</a></p><?php endif;?>
</section>
<section class="panel"><div class="panel-head"><h2>Requests (<?=$total?>)</h2></div>
<form method="get" style="max-width:320px"><label for="status">Show requests</label><select id="status" name="status">
<?php foreach(['OPEN'=>'Open requests','PENDING'=>'Pending verification','ISSUED'=>'Code issued','COMPLETED'=>'Completed','REJECTED'=>'Rejected','ALL'=>'All requests'] as $value=>$label):?>
<option value="<?=e($value)?>" <?=$filter===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?>
</select><button class="btn secondary" type="submit">Show</button></form>
<div class="table-wrap"><table><thead><tr><th>Account</th><th>Requested</th><th>Status</th><th>Handled by / reference</th><th>Action</th></tr></thead><tbody>
<?php foreach($requests as $request):?>
<tr><td><strong><?=e($request['full_name'])?></strong><br><?=e($request['username'])?><br><small class="muted"><?=e($request['role_name'])?><?=!$request['active']?' / Inactive':''?><?= $request['email']?' / '.e($request['email']):'' ?></small></td>
<td><?=e($request['created_at'])?><br><small>#<?=(int)$request['id']?></small></td>
<td><strong><?=e(ucfirst(strtolower($request['status'])))?></strong>
<?php if($request['status']==='ISSUED'):?><br><small><?=$request['credential_valid']?'Expires: '.e($request['expires_at']):'Code expired or replaced; issue a replacement.'?></small><?php endif;?>
<?php if($request['completed_at']):?><br><small><?=e($request['completed_at'])?></small><?php endif;?></td>
<td><?=e($request['handler']??($request['status']==='ISSUED'?'Verified email delivery':'Awaiting review'))?><br><small><?=e($request['identity_reference'])?><?= $request['decision_reason']?e($request['decision_reason']):'' ?></small></td>
<td><a class="btn secondary small" href="<?=e(url('admin/recovery.php?request='.(int)$request['id'].'#recovery-form'))?>"><?=in_array($request['status'],['PENDING','ISSUED'],true)?'Review request':'View'?></a></td></tr>
<?php endforeach;?>
<?php if(!$requests):?><tr><td colspan="5">No password reset requests in this view.</td></tr><?php endif;?>
</tbody></table></div>
<p><?php if($page>1):?><a href="<?=e(url('admin/recovery.php?status='.$filter.'&page='.($page-1)))?>">Previous</a> &middot; <?php endif;?>Page <?=$page?><?php if($offset+50<$total):?> &middot; <a href="<?=e(url('admin/recovery.php?status='.$filter.'&page='.($page+1)))?>">Next</a><?php endif;?></p>
</section>
<?php require_once __DIR__.'/../includes/footer.php';?>
