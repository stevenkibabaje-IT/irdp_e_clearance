<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect(role_dashboard(current_user()['role']));
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IRDP Student Clearance System</title>
    <link rel="preload" href="<?= e(url('assets/fonts/roboto-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css?v=' . filemtime(__DIR__.'/assets/css/style.css'))) ?>">
    <link rel="preload" as="image" href="<?= e(url('assets/img/irdp-campus-hd.jpg')) ?>">
</head>
<body class="landing">
    <a class="skip-link" href="#main-content">Skip to main content / Nenda kwenye maudhui</a>
    <div class="landing-bg" aria-hidden="true"></div>
    <div class="landing-overlay" aria-hidden="true"></div>

    <header class="landing-nav">
        <img src="<?= e(url('assets/img/irdp-logo.jpg')) ?>" alt="IRDP logo">
        <div>
            <strong>IRDP</strong>
            <span>Student Clearance System</span>
        </div>
        <a class="btn primary" href="<?= e(url('auth/login.php')) ?>">Login</a>
    </header>

    <main id="main-content" tabindex="-1">
    <?php require __DIR__.'/includes/accessibility.php'; ?>
    <section class="landing-hero">
        <div>
            <div class="eyebrow brand-eyebrow">Institute of Rural Development Planning</div>
            <h1>Student Clearance, <span>made simple.</span></h1>
            <p>
                Follow the official clearance sequence online, track every decision,
                resolve rejected stages and access your certificate after final approval.
            </p>
            <a class="btn primary" href="<?= e(url('auth/login.php')) ?>">Start / Login</a>
        </div>
    </section>

    <section class="feature-grid">
        <div><span class="feature-icon"><?= icon('shield') ?></span><h3>Secure</h3><p>Role-based access, sessions and password hashing.</p></div>
        <div><span class="feature-icon"><?= icon('workflow') ?></span><h3>Trackable</h3><p>Students see all 11 clearance stages and progress.</p></div>
        <div><span class="feature-icon"><?= icon('eye') ?></span><h3>Transparent</h3><p>Approvals, rejections and comments are recorded.</p></div>
        <div><span class="feature-icon"><?= icon('certificate') ?></span><h3>Certificate</h3><p>Certificate is issued when every stage is approved.</p></div>
    </section>
    </main>
</body>
</html>
