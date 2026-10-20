<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/adapter.php';
if (current_user()) { redirect(role_dashboard(current_user()['role'])); }
$error=''; $username=''; $sessionNotice='';
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    try {
        foreach($_POST as $value){if(!is_string($value)){throw new DomainException('Invalid form value.');}}
        ar_ui_csrf();
        $username=trim((string)($_POST['username']??''));
        $password=(string)($_POST['password']??'');
        $actor=ar_ui_sign_in($username,$password);
        redirect($actor['force_password_change']?'auth/change_password.php':role_dashboard($actor['role']));
    } catch (DomainException $exception) { $error=$exception->getMessage(); }
      catch (Throwable $exception) { error_log('Original UI isolated login: '.get_class($exception)); $error='Login could not be completed.'; }
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | IRDP Student Clearance System</title>
    <link rel="icon" type="image/png" href="<?= e(url('assets/img/irdp-logo-web.png')) ?>">
    <link rel="preload" href="<?= e(url('assets/fonts/roboto-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css?v=' . filemtime(__DIR__.'/../assets/css/style.css'))) ?>">
</head>
<body class="login-page">
    <a class="skip-link" href="#main-content">Skip to login</a>
    <?php $institutionHome = true; require __DIR__ . '/../includes/institution_header.php'; ?>

    <main class="login-layout">
        <section class="login-brand">
            <div class="eyebrow brand-eyebrow">Student e-Clearance</div>

            <h1>Welcome Back!</h1>
            <p>
                Access your account and continue with student clearance services
                in a secure, transparent and organized workflow.
            </p>

            <div class="hero-pills">
                <span>Secure</span>
                <span>Fast</span>
                <span>Transparent</span>
                <span>Reliable</span>
            </div>
        </section>

        <section class="login-card" id="main-content" tabindex="-1">
            <?php require __DIR__.'/../includes/accessibility.php'; ?>
            <h2>Login to Your Account</h2>
            <p class="muted">Enter your credentials to continue.</p>

            <?php if ($sessionNotice !== ''): ?>
                <div class="alert warning" role="status"><?= e($sessionNotice) ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert danger" role="alert" id="login-error">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(url('auth/login.php')) ?>" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <label for="username">Username / Registration Number</label>
                <input
                    id="username"
                    name="username"
                    type="text"
                    value="<?= e($username) ?>"
                    placeholder="e.g. IRDP/ODICT/MA26/0002"
                    autocomplete="username"
                    required
                    <?= $error !== '' ? 'aria-invalid="true" aria-describedby="login-error"' : '' ?>
                >

                <label for="password">Password</label>
                <div class="password-wrap">
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        required
                    >
                    <button class="password-toggle" type="button" id="togglePassword" aria-controls="password" aria-pressed="false" aria-label="Show password" title="Show password">
                        <svg class="eye-open" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                        <svg class="eye-closed" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="m3 3 18 18M10.6 5.1A12 12 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-3 3.8M6.3 6.3A18 18 0 0 0 2 12s3.5 7 10 7a12 12 0 0 0 5.7-1.7M9.9 9.9a3 3 0 0 0 4.2 4.2" />
                        </svg>
                    </button>
                </div>

                <div class="form-row">
                    <a href="<?= e(url('auth/forgot.php')) ?>">Forgot password?</a>
                </div>

                <button class="btn primary full" type="submit">
                    Login
                </button>
            </form>

            <p class="muted" style="margin:16px 0 0; font-size:0.6875rem; text-align:center;">
                IRDP Student Clearance System
            </p>
        </section>
    </main>

    <script>
        const passwordInput = document.getElementById('password');
        const togglePassword = document.getElementById('togglePassword');

        togglePassword.addEventListener('click', function () {
            const visible = passwordInput.type === 'text';
            passwordInput.type = visible ? 'password' : 'text';
            togglePassword.setAttribute('aria-pressed', String(!visible));
            togglePassword.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
            togglePassword.title = visible ? 'Show password' : 'Hide password';
        });
    </script>
<?php require __DIR__ . '/../includes/help.php'; ?>
</body>
</html>
