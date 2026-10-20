<?php
declare(strict_types=1);

/** Start student password recovery using the registered email only. */
require_once __DIR__.'/../includes/bootstrap.php';
header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');
$error='';$errors=[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        verify_csrf();
        $email=email_value(text_input($_POST,'email',160));
        $challenge=request_student_password_otp($pdo,$email);
        unset($_SESSION['student_reset'],$_SESSION['recovery_browser']);
        $_SESSION['student_otp']=['challenge'=>$challenge,'email'=>$email,'requested_at'=>time()];
        redirect_to_student_otp();
    } catch(Throwable $e) {$error=page_error($e,$errors);}
}
$pageTitle='Forgot Password';require_once __DIR__.'/../includes/header.php';?>
<section class="panel recovery-card" aria-labelledby="recovery-heading">
    <div class="profile-section-heading"><span><?= icon('mail') ?></span><div><h1 id="recovery-heading">Forgot password?</h1></div></div>
    <p>Enter your registered email to receive an OTP.</p>
    <?php if ($error&&!$errors): ?><div class="alert danger" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e(url('auth/forgot.php')) ?>">
        <?php csrf_field(); form_fields(['email'=>['label'=>'Registered email','type'=>'email','maxlength'=>160,'required'=>true,'autocomplete'=>'email']],$errors); ?>
        <button class="btn primary full" type="submit">Send OTP</button>
    </form>
    <p class="muted">For staff accounts or if you cannot access your registered email, contact support through Need help for identity-verified recovery.</p>
    <div class="recovery-links"><a href="<?= e(url('auth/login.php')) ?>"><?= icon('log-in') ?> Back to login</a></div>
</section>
<?php require_once __DIR__.'/../includes/footer.php';?>
