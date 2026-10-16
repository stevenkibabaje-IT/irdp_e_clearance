<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/bootstrap.php';
header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');
if (isset($_SESSION['student_reset'])) { redirect('auth/reset.php'); }
$recovery=$_SESSION['student_otp']??null;
$error='';$errors=[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        verify_csrf();
        if (!is_array($recovery) || !isset($recovery['challenge'],$recovery['email'],$recovery['requested_at'])) { throw new RuntimeException('Request an email OTP first.'); }
        $action=choice_input($_POST['action']??'verify',['verify','resend'],'action');
        if ($action==='resend') {
            if (time()-(int)$recovery['requested_at']<STUDENT_OTP_RESEND_SECONDS) { throw new RuntimeException('Please wait 60 seconds before requesting another OTP.'); }
            $_SESSION['student_otp']=['challenge'=>request_student_password_otp($pdo,$recovery['email']),'email'=>$recovery['email'],'requested_at'=>time()];
            redirect_to_student_otp();
        }
        $credential=verify_student_password_otp($pdo,$recovery['challenge'],$_POST['otp']??null);
        session_regenerate_id(true);
        $_SESSION['csrf_token']=bin2hex(random_bytes(32));
        $_SESSION['student_reset']=['credential'=>$credential,'expires_at'=>time()+STUDENT_OTP_MINUTES*60];
        unset($_SESSION['student_otp']);
        redirect('auth/reset.php');
    } catch (Throwable $exception) { $error=page_error($exception,$errors); }
}
$pageTitle='Verify Email OTP'; require_once __DIR__.'/../includes/header.php';
?>
<section class="panel recovery-card" aria-labelledby="otp-heading">
    <div class="profile-section-heading"><span><?= icon('shield') ?></span><div><h1 id="otp-heading">Enter OTP</h1><?php if(is_array($recovery)):?><p><?= e($recovery['email']) ?></p><?php endif;?></div></div>
    <?php if ($error&&!$errors): ?><div class="alert danger" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if (!is_array($recovery)): ?>
        <p>Request an OTP using the email saved in your student account.</p><a class="btn primary" href="<?= e(url('auth/forgot.php')) ?>"><?= icon('mail') ?> Request OTP</a>
    <?php else: ?>
        <p role="status"><?= e(STUDENT_OTP_REQUEST_MESSAGE) ?></p>
        <form method="post" action="<?= e(url('auth/verify_otp.php')) ?>">
            <?php csrf_field(); ?><input type="hidden" name="action" value="verify">
            <label for="otp">Email OTP</label>
            <input id="otp" name="otp" class="otp-input" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required aria-describedby="otp-help<?= isset($errors['otp'])?' otp-error':'' ?>" <?= isset($errors['otp'])?'aria-invalid="true"':'' ?>>
            <?php form_error('otp',$errors); ?>
            <p class="muted" id="otp-help">Six digits. Valid for <?= STUDENT_OTP_MINUTES ?> minutes.</p>
            <button class="btn primary full" type="submit"><?= icon('check-circle') ?> Verify OTP</button>
        </form>
        <?php $remaining=max(0,STUDENT_OTP_RESEND_SECONDS-(time()-(int)$recovery['requested_at'])); ?>
        <form class="otp-resend" method="post" action="<?= e(url('auth/verify_otp.php')) ?>">
            <?php csrf_field(); ?><input type="hidden" name="action" value="resend">
            <button class="btn secondary" id="resendOtp" type="submit" <?= $remaining?'disabled':'' ?>><?= icon('mail') ?> Resend OTP</button>
            <small id="resendHint" class="muted"><?= $remaining?'You can request another code after 60 seconds.':'You can request a new code if needed.' ?></small>
        </form>
        <script>setTimeout(function(){const b=document.getElementById('resendOtp');if(b){b.disabled=false;document.getElementById('resendHint').textContent='You can request a new code if needed.';}},<?= $remaining*1000 ?>);</script>
    <?php endif; ?>
    <div class="recovery-links"><a href="<?= e(url('auth/forgot.php')) ?>">Use another email</a><a href="<?= e(url('auth/login.php')) ?>">Back to login</a></div>
</section>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
