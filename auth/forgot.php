<?php
declare(strict_types=1);

/** Accept password recovery requests for administrator identity verification. */
require_once __DIR__.'/../includes/bootstrap.php';$message='';$error='';$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        verify_csrf();
        $username=text_input($_POST,'username',100);
        request_password_recovery($pdo,$username);
        if(isset($_SESSION['recovery_browser'])) {redirect('auth/reset.php');}
        $message='Your request has been submitted if the account is eligible. Keep using the browser where you originally requested recovery, or contact the administrator for help.';
    } catch(Throwable $e) {$error=page_error($e,$errors);}
}
$pageTitle='Forgot Password';require_once __DIR__.'/../includes/header.php';?>
<section class="panel" style="max-width:600px;margin:30px auto"><h1>Forgot password?</h1>
<p>Enter your username or registration number. After administrator approval, you can choose a new password in this same browser without entering a reset code.</p>
<?php if($message):?><div class="alert info"><?=e($message)?></div><?php endif;?>
<?php if($error):?><div class="alert danger"><?=e($error)?></div><?php endif;?>
<form method="post"><?php csrf_field();form_fields(['username'=>['label'=>'Username / registration number','maxlength'=>100,'required'=>true,'autocomplete'=>'username']],$errors);?><button class="btn primary" type="submit">Submit password reset request</button></form>
<p><a class="btn secondary" href="<?=e(url('auth/reset.php'))?>">I have a reset code</a></p><p><a href="<?=e(url('auth/login.php'))?>">Back to login</a></p></section>
<?php require_once __DIR__.'/../includes/footer.php';?>
