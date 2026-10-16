<?php
declare(strict_types=1);

/** Validate a recovery credential before accepting a replacement password. */
require_once __DIR__.'/../includes/bootstrap.php';
header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');
$error='';$errors=[];$success=false;
if (($_GET['manual']??'')==='1' && $_SERVER['REQUEST_METHOD']==='GET') { unset($_SESSION['student_otp'],$_SESSION['student_reset']); }
$studentGrant=$_SESSION['student_reset']??null;
$studentFlow=is_array($studentGrant)||isset($_SESSION['student_otp']);
if ($studentFlow && !is_array($studentGrant)) { redirect('auth/verify_otp.php'); }
if (is_array($studentGrant) && ((int)($studentGrant['expires_at']??0)<=time() || !preg_match('/^[a-f0-9]{64}$/D',(string)($studentGrant['credential']??'')))) {
    unset($_SESSION['student_reset']); redirect('auth/forgot.php');
}
$browser=$studentFlow?null:($_SESSION['recovery_browser']??null);
$browserRequest=null;
if(is_array($browser)) {
    $s=$pdo->prepare('SELECT r.status,p.token_hash,p.expires_at,p.used_at FROM password_reset_requests r LEFT JOIN password_resets p ON p.id=r.reset_id WHERE r.id=?');
    $s->execute([$browser['request_id']]);$browserRequest=$s->fetch();
    if(!$browserRequest || !hash_equals((string)$browserRequest['token_hash'],hash('sha256',(string)$browser['credential']))) {$browser=null;unset($_SESSION['recovery_browser']);}
    elseif($browserRequest['status']==='ISSUED') {$_POST['credential']=$browser['credential'];}
}
if($_SERVER['REQUEST_METHOD']==='POST'){try{verify_csrf();$credential=$studentFlow?$studentGrant['credential']:text_input($_POST,'credential',64);reset_password($pdo,$credential,$_POST['password']??null,$_POST['confirm_password']??null);unset($_SESSION['student_reset'],$_SESSION['student_otp'],$_SESSION['recovery_browser']);if(current_user()){logout_user();}$success=true;}catch(Throwable $e){$error=page_error($e,$errors);}}
$pageTitle='Reset Password';require_once __DIR__.'/../includes/header.php';
?>
<section class="panel recovery-card"><div class="profile-section-heading"><span><?= icon('key') ?></span><div><h1>Choose a new password</h1></div></div>
<?php if($error):?><div class="alert danger"><?=e($error)?></div><?php endif;?>
<?php if($success):?><div class="alert success">Password updated. Existing sessions have been revoked. <a href="<?=e(url('auth/login.php'))?>">Log in</a></div>
<?php elseif($browser && $browserRequest['status']==='PENDING'):?>
<div class="alert info">Your request is awaiting administrator approval. Keep this browser open. After approval, refresh this page to choose a new password.</div><a class="btn primary" href="<?=e(url('auth/reset.php'))?>">Check approval</a>
<script>setTimeout(function(){location.reload();},15000);</script>
<?php elseif($browser && $browserRequest['status']!=='ISSUED'):?>
<div class="alert info">This request is <?=e(strtolower($browserRequest['status']))?>.</div><a href="<?=e(url('auth/forgot.php'))?>">Submit a new request</a><?php unset($_SESSION['recovery_browser']);?>
<?php else: ?>
    <p><?= $studentFlow?'Email verified. Choose your new password within 10 minutes.':($browser?'Administrator approval received. Choose your new password below within 30 minutes.':'Enter the reset code supplied by the administrator after identity verification.') ?></p>
    <form method="post" autocomplete="off">
        <?php csrf_field();
        if (!$browser&&!$studentFlow) { form_fields(['credential'=>['label'=>'Reset code','required'=>true,'maxlength'=>64,'autocomplete'=>'off']],$errors); }
        form_fields([
            'password'=>['label'=>'New password ('.PASSWORD_MIN_LENGTH.'-'.PASSWORD_MAX_LENGTH.' characters)','type'=>'password','maxlength'=>PASSWORD_MAX_LENGTH,'required'=>true,'autocomplete'=>'new-password'],
            'confirm_password'=>['label'=>'Confirm new password','type'=>'password','maxlength'=>PASSWORD_MAX_LENGTH,'required'=>true,'autocomplete'=>'new-password'],
        ],$errors); ?>
        <button class="btn primary full" type="submit"><?= icon('shield') ?> Reset password</button>
    </form>
    <div class="recovery-links"><a href="<?= e(url('auth/forgot.php')) ?>">Request password recovery</a><a href="<?= e(url('auth/login.php')) ?>">Back to login</a></div>
<?php endif; ?></section>
<script>const fragment=new URLSearchParams(location.hash.slice(1));if(fragment.has('credential')&&document.getElementById('credential')){document.getElementById('credential').value=fragment.get('credential');history.replaceState(null,'',location.pathname);}</script>
<?php require_once __DIR__.'/../includes/footer.php';?>
