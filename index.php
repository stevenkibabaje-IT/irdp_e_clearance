<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (current_user()) { redirect(role_dashboard(current_user()['role'])); }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="IRDP Student Clearance System. Start clearance, follow office reviews and download your transcript.">
    <title>IRDP Student Clearance System</title>
    <link rel="icon" type="image/png" href="<?= e(url('assets/img/irdp-logo-web.png')) ?>">
    <link rel="preload" href="<?= e(url('assets/fonts/roboto-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css?v=' . filemtime(__DIR__.'/assets/css/style.css'))) ?>">
</head>
<body class="landing">
    <a class="skip-link" href="#main-content">Skip to main content / Nenda kwenye maudhui</a>
    <?php $institutionHome = true; require __DIR__ . '/includes/institution_header.php'; ?>
    <main class="landing-main" id="main-content" tabindex="-1">
        <?php require __DIR__ . '/includes/accessibility.php'; ?>
        <section class="landing-welcome" aria-labelledby="welcome-heading">
            <div class="landing-intro">
                <span class="landing-eyebrow"><span aria-hidden="true"></span> IRDP Student Services</span>
                <h1 id="welcome-heading">Your clearance.<br><span>All in one place.</span></h1>
                <p>Start your clearance, follow each office's decision and download your clearance transcript online.</p>
                <ul class="landing-benefits" aria-label="Clearance services">
                    <li><span><?= icon('building') ?></span><div><strong>11 offices, one process</strong><small>Follow every review from your account.</small></div></li>
                    <li><span><?= icon('clipboard-check') ?></span><div><strong>Know your next step</strong><small>See progress and respond when action is needed.</small></div></li>
                    <li><span><?= icon('download') ?></span><div><strong>Your transcript, online</strong><small>Available when all offices have approved.</small></div></li>
                </ul>
            </div>
            <div class="landing-entry">
                <div class="landing-entry-mark"><?= icon('log-in') ?></div>
                <span class="eyebrow">STUDENT CLEARANCE SYSTEM</span>
                <h2>Access your account</h2>
                <p>Continue to your dashboard to manage your clearance.</p>
                <a class="btn primary full landing-login" href="<?= e(url('auth/login.php')) ?>">Login / Ingia <?= icon('chevron-right') ?></a>
                <a class="landing-forgot" href="<?= e(url('auth/forgot.php')) ?>"><?= icon('key') ?> Forgot password?</a>
                <div class="landing-entry-note"><?= icon('shield') ?><span>Students, officers and administrators use their assigned accounts.</span></div>
            </div>
        </section>
    </main>
    <?php require __DIR__ . '/includes/site_footer.php'; ?>
    <?php require __DIR__ . '/includes/help.php'; ?>
</body>
</html>
