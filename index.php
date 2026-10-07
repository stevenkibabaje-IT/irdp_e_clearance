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
    <link rel="icon" type="image/png" href="<?= e(url('assets/img/irdp-logo-web.png')) ?>">
    <link rel="preload" href="<?= e(url('assets/fonts/roboto-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css?v=' . filemtime(__DIR__.'/assets/css/style.css'))) ?>">
    <link rel="preload" as="image" href="<?= e(url('assets/img/irdp-campus-hd.jpg')) ?>">
</head>
<body class="landing">
    <a class="skip-link" href="#main-content">Skip to main content / Nenda kwenye maudhui</a>
    <div class="landing-bg" aria-hidden="true"></div>
    <div class="landing-overlay" aria-hidden="true"></div>

    <header class="landing-nav">
        <img class="irdp-emblem" src="<?= e(url('assets/img/irdp-logo-web.png')) ?>" width="64" height="64" alt="Institute of Rural Development Planning (IRDP) logo">
        <div>
            <strong>IRDP</strong>
            <span>Student Clearance System</span>
        </div>
        <nav class="landing-actions" aria-label="Main navigation">
            <a class="landing-help-link" href="#help">Help / Msaada</a>
            <a class="btn primary" href="<?= e(url('auth/login.php')) ?>">Login</a>
        </nav>
    </header>

    <main id="main-content" tabindex="-1">
    <?php require __DIR__.'/includes/accessibility.php'; ?>
    <section class="landing-hero">
        <div>
            <div class="eyebrow brand-eyebrow">Institute of Rural Development Planning</div>
            <h1>Student Clearance, <span>made simple.</span></h1>
            <p>
                Start your clearance, track progress and download your clearance transcript online.
            </p>
            <div class="landing-hero-actions">
                <a class="btn primary" href="<?= e(url('auth/login.php')) ?>">Start / Login</a>
                <a class="btn secondary" href="#help">Help / Msaada</a>
            </div>
        </div>
    </section>

    <ul class="landing-highlights" aria-label="System features">
        <li><?= icon('shield') ?> Secure</li>
        <li><?= icon('workflow') ?> 11 clearance stages</li>
        <li><?= icon('eye') ?> Track progress</li>
        <li><?= icon('download') ?> Clearance transcript</li>
    </ul>
    <details class="landing-help" id="help" lang="sw">
        <summary>Help / Msaada <span>Bonyeza kupata maelekezo</span></summary>
        <div class="landing-help-content">
        <div class="landing-section-heading">
            <div class="eyebrow brand-eyebrow">Help / Msaada</div>
            <h2 id="help-heading">Tuko hapa kukusaidia</h2>
            <p>Chagua swali hapa chini kupata maelekezo, au piga simu kwa msaada wa kutumia mfumo.</p>
        </div>
        <div class="landing-help-grid">
            <div class="landing-faq">
                <details>
                    <summary>Ninaingiaje na kuanza clearance?</summary>
                    <p>Bonyeza <a href="<?= e(url('auth/login.php')) ?>">Login</a>. Mwanafunzi aingie kwa namba yake ya usajili na nenosiri; mtumishi atumie jina lake la mtumiaji na nenosiri. Baada ya kuingia, mwanafunzi achague <strong>Start Clearance</strong> na afuate maelekezo yanayoonekana. Clearance huanza wakati kipindi chake kimefunguliwa.</p>
                </details>
                <details>
                    <summary>Nimesahau nenosiri, nifanye nini?</summary>
                    <p>Fungua <a href="<?= e(url('auth/forgot.php')) ?>">Forgot Password</a>, weka jina la mtumiaji au namba ya usajili, kisha tuma ombi. Msimamizi atahakiki ombi lako. Baada ya kuidhinishwa, endelea katika kivinjari ulichotumia kutuma ombi ili kuweka nenosiri jipya.</p>
                </details>
                <details>
                    <summary>Ninafuatiliaje hatua au kurekebisha clearance iliyokataliwa?</summary>
                    <p>Baada ya kuingia, fungua <strong>My Clearance</strong> kuona maendeleo ya hatua zote 11. Hatua ikikataliwa, soma sababu na maelekezo ya ofisi, rekebisha kilichoombwa, kisha tuma maelezo na ushahidi kupitia sehemu ya kutuma upya. Ofisi ikikubali marekebisho, hatua inayofuata itafunguliwa.</p>
                </details>
                <details>
                    <summary>Clearance Transcript inapatikana lini?</summary>
                    <p>Clearance Transcript inapatikana baada ya hatua zote 11 za clearance kuidhinishwa. Baada ya kukamilika, fungua Dashboard na bonyeza <strong>Download Clearance Transcript</strong> kupakua taarifa ya clearance yako.</p>
                </details>
            </div>
            <aside class="landing-support" aria-labelledby="support-heading">
                <span class="feature-icon"><?= icon('info') ?></span>
                <h3 id="support-heading">Bado unahitaji msaada?</h3>
                <p>Piga simu kwenye mojawapo ya namba hizi. Eleza sehemu uliyokwama ili uweze kusaidiwa.</p>
                <a class="btn secondary full" href="tel:+255659913570">Piga 0659913570</a>
                <a class="btn secondary full" href="tel:+255662632565">Piga 0662632565</a>
                <a class="landing-recovery-link" href="<?= e(url('auth/forgot.php')) ?>">Omba kurejesha nenosiri</a>
            </aside>
        </div>
        </div>
    </details>
    </main>
    <footer class="landing-footer" aria-label="Site footer">
        <div class="landing-footer-row">
            <div class="landing-footer-brand">
                <strong>IRDP Student Clearance System</strong>
                <span>&copy; <?= date('Y') ?> IRDP &middot; Kupanga ni Kuchagua</span>
            </div>
            <div class="landing-footer-contact" lang="sw">
                <span>Support / Msaada</span>
                <div class="landing-contact-numbers">
                    <a href="tel:+255659913570">0659913570</a>
                    <a href="tel:+255662632565">0662632565</a>
                </div>
            </div>
            <a href="<?= e(url('auth/forgot.php')) ?>">Forgot Password</a>
        </div>
    </footer>
    <script src="<?= e(url('assets/js/landing.js?v=' . filemtime(__DIR__.'/assets/js/landing.js'))) ?>" defer></script>
</body>
</html>
