<?php
declare(strict_types=1);

/** Validate credentials and redirect authenticated users to their role dashboard. */

require_once __DIR__ . '/../includes/bootstrap.php';

if (current_user()) {
    redirect(role_dashboard(current_user()['role']));
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    try {
        verify_csrf();
        $username = text_input($_POST, 'username', 100);
        $password = existing_password_input($_POST['password'] ?? null);
        rate_limit($pdo, 'login', request_ip(), 20, 15);

        if ($username === '' || $password === '') {
            throw new RuntimeException('Enter your username and password.');
        }

        $stmt = $pdo->prepare(
            'SELECT
                u.id,
                u.username,
                u.password_hash,
                u.full_name,
                u.active,
                u.auth_version,
                u.force_password_change,
                r.name AS role_name,
                s.id AS student_id
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             LEFT JOIN students s ON s.user_id = u.id
             WHERE u.username = ?
             LIMIT 1'
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || (int) $user['active'] !== 1 || !password_verify($password, $user['password_hash'])) {
            throw new RuntimeException('Invalid username or password.');
        }

        login_user($user);
        audit($pdo, 'LOGIN', (int) $user['id'], null, 'Successful login');

        redirect($user['role_name'] !== 'STUDENT' && (int)$user['force_password_change'] ? 'auth/change_password.php' : role_dashboard($user['role_name']));
    } catch (RuntimeException $e) {
        $error = user_safe_error($e);
    } catch (Throwable $e) {
        $error = 'Login could not be completed. Make sure MySQL is running and the system setup has been completed.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | IRDP Student Clearance System</title>
    <link rel="icon" type="image/png" href="<?= e(url('assets/img/irdp-logo-web.png')) ?>">
    <link rel="preload" href="<?= e(url('assets/fonts/roboto-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css?v=' . filemtime(__DIR__.'/../assets/css/style.css'))) ?>">
    <link rel="preload" as="image" href="<?= e(url('assets/img/irdp-campus-hd.jpg')) ?>">
</head>
<body class="login-page">
    <a class="skip-link" href="#main-content">Skip to login / Nenda kwenye login</a>
    <div class="login-background" aria-hidden="true"></div>
    <div class="login-overlay" aria-hidden="true"></div>

    <main class="login-layout">
        <section class="login-brand">
            <div class="login-brand-row">
                <img
                    class="login-logo irdp-emblem"
                    src="<?= e(url('assets/img/irdp-logo-web.png')) ?>"
                    width="110" height="110"
                    alt="Institute of Rural Development Planning (IRDP) logo"
                >
                <div>
                    <div class="eyebrow brand-eyebrow">Institute of Rural Development Planning</div>
                    <h2 style="margin:4px 0 0; font-size:1.75rem;">IRDP Student Clearance System</h2>
                </div>
            </div>

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
            <div class="login-mobile-brand">
                <img class="irdp-emblem" src="<?= e(url('assets/img/irdp-logo-web.png')) ?>" width="88" height="88" alt="Institute of Rural Development Planning (IRDP) logo">
                <strong>IRDP Student Clearance System</strong>
            </div>
            <h2>Login to Your Account</h2>
            <p class="muted">Enter your credentials to continue.</p>

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
                IRDP Student Clearance System · Local XAMPP version
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
</body>
</html>
