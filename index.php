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
            <p>Fuata hatua za kuingia, kulipia ada, kuanza clearance na kufuatilia maamuzi ya ofisi zote 11. Chagua swali hapa chini au piga simu kwa msaada.</p>
        </div>
        <div class="landing-help-grid">
            <div class="landing-faq">
                <details>
                    <summary>Ninaingiaje kwenye mfumo?</summary>
                    <p>Bonyeza <a href="<?= e(url('auth/login.php')) ?>">Login</a>. Mwanafunzi atumie namba yake yote ya usajili, pamoja na alama za <strong>/</strong>, na nenosiri lake. Mtumishi atumie username na nenosiri alilopewa. Baada ya kuingia, utaelekezwa kwenye Dashboard ya akaunti yako. Mwanafunzi anaweza kubadilisha nenosiri kwa hiari kupitia <strong>My Profile &rarr; Change Password</strong>.</p>
                </details>
                <details>
                    <summary>Ninalipaje ada na kuanza clearance?</summary>
                    <div class="landing-faq-answer">
                        <ol>
                            <li>Hakikisha Dashboard inaonyesha <strong>CLEARANCE OPEN</strong> na academic cycle unayotaka. Admin ndiye anayefungua kipindi cha clearance.</li>
                            <li>Fungua <strong>Clearance Fee</strong> na bonyeza <strong>Request control number</strong>. Finance and Accounting atatoa namba ya malipo.</li>
                            <li>Lipa kiasi kinachoonyeshwa kwa control number uliyopewa. Ada ya kawaida ni <strong>TSh 10,000</strong>; Finance anaweza kuweka kiwango cha cycle husika.</li>
                            <li>Kwenye <strong>Clearance Fee</strong>, chagua receipt moja ya PDF, JPG au PNG, hadi <strong>5 MB</strong>, kisha bonyeza <strong>Upload payment receipt</strong>.</li>
                            <li>Subiri Finance ahakiki na aidhinishe malipo. <strong>Kupakia receipt peke yake hakufungui Start Clearance.</strong></li>
                            <li>Malipo yakiwa <strong>APPROVED</strong>, bonyeza <strong>Start Clearance</strong>, chagua academic cycle iliyofunguliwa, kisha tuma ombi. Ofisi zote 11 zitaanza <strong>PENDING</strong>.</li>
                        </ol>
                        <p>Kama ombi la clearance la cycle hiyo tayari lipo, endelea kupitia <strong>Continue Clearance / My Clearance</strong>. Walioanza tayari au waliomaliza hawaombwi ada hii kwa ombi hilo, na hulipi ada ya kuanza mara mbili katika cycle moja.</p>
                    </div>
                </details>
                <details>
                    <summary>Clearance imefungwa au Start Clearance haipatikani?</summary>
                    <p>Ujumbe wa <strong>CLEARANCE CLOSED</strong> unamaanisha kipindi hakijafunguliwa na Admin au muda wake umeisha. Unaweza kuingia na kuona taarifa zako, lakini kuomba control number ya ada, kupakia receipt au kutuma marekebisho kunahitaji kipindi kiwe wazi. Hata ada ikiwa imeidhinishwa, kuanza clearance kunasubiri kipindi kifunguliwe kwa cycle hiyo. Kipindi kikiwa wazi na bado hujaanza, angalia <strong>Clearance Fee</strong> na subiri malipo yawe <strong>APPROVED</strong>. Ukiona <strong>Continue Clearance</strong>, ombi tayari lipo; fungua <strong>My Clearance</strong>.</p>
                </details>
                <details>
                    <summary>Ninafuatiliaje maendeleo ya clearance?</summary>
                    <p>Fungua <strong>My Clearance</strong> kuona hali ya ofisi zote 11, au <strong>Notifications</strong> kuona taarifa za maamuzi. <strong>PENDING / IN_REVIEW</strong> maana yake ofisi bado inafanya review; <strong>APPROVED</strong> maana yake ofisi imeidhinisha; <strong>REJECTED</strong> inahitaji marekebisho yako. <strong>PAUSED / Action Required</strong> huonyesha kwamba kuna ofisi inayohitaji hatua yako. Kila ofisi, ikiwemo Finance, inafanya review kwa mpangilio wowote bila kusubiri nyingine.</p>
                </details>
                <details>
                    <summary>Ofisi ikikataa clearance, natuma marekebisho vipi?</summary>
                    <div class="landing-faq-answer">
                        <ol>
                            <li>Fungua <strong>My Clearance</strong>, tafuta ofisi yenye <strong>REJECTED</strong> na soma sababu pamoja na <strong>Corrective instructions</strong>.</li>
                            <li>Rekebisha kilichoombwa, kisha jaza <strong>Response / explanation</strong>. Unaweza kuambatanisha ushahidi wa PDF, JPG au PNG, hadi faili <strong>5</strong>, kila moja isizidi <strong>5 MB</strong>.</li>
                            <li>Bonyeza <strong>Submit evidence to office</strong>, kisha subiri officer wa ofisi hiyo ahakiki na aidhinishe marekebisho.</li>
                        </ol>
                        <p>Kutuma maelezo au ushahidi hakuidhinishi hatua moja kwa moja. Rekebisha kila ofisi iliyokataa; approvals zilizopo zinahifadhiwa na ofisi nyingine zinaendelea kufanya review. Kutuma marekebisho kunahitaji kipindi cha clearance kiwe wazi.</p>
                    </div>
                </details>
                <details>
                    <summary>Malipo ya Finance ndani ya clearance ni yale yale ya ada?</summary>
                    <p>Hatua ya Finance inakagua <strong>madeni mengine</strong>, tofauti na ada ya kuanza clearance. Bila deni jingine, Finance anaweza kuidhinisha kupitia <strong>Approve &mdash; no other debt</strong> bila kuomba receipt nyingine. Deni likiwepo, Finance atatoa control number; lipa kwa namba hiyo, kisha pakia receipt moja ya PDF, JPG au PNG, hadi <strong>5 MB</strong>, kwenye <strong>Finance payment</strong> ndani ya Dashboard au My Clearance kupitia <strong>Upload payment receipt</strong>. Hatua inabaki <strong>PENDING</strong> ikisubiri Finance ahakiki na aidhinishe. Finance hatumii Reject.</p>
                    <p>Kabla ya approval, receipt ikihitaji kusahihishwa tumia <strong>View or replace payment receipt &rarr; Replace payment receipt</strong>. Finance akibadilisha control number, pakia receipt mpya ya namba mpya. Receipt za zamani zinahifadhiwa kwenye history; kupakia receipt pekee hakukamilishi clearance.</p>
                </details>
                <details>
                    <summary>Clearance Transcript inapatikana lini?</summary>
                    <p>Clearance inakuwa <strong>COMPLETED</strong> baada ya ofisi zote <strong>11</strong> kuidhinisha na madeni au mahitaji yote kutimizwa. Fungua <strong>Dashboard</strong> na bonyeza <strong>Download Clearance Transcript</strong>. Transcript ina taarifa za mwanafunzi, programme, academic cycle na approvals za kila ofisi; haina marks, grades au GPA. Ikiwa haionekani, angalia ofisi zilizosalia kwenye <strong>My Clearance</strong>.</p>
                </details>
                <details>
                    <summary>Nimesahau nenosiri, nifanye nini?</summary>
                    <p>Fungua <a href="<?= e(url('auth/forgot.php')) ?>">Forgot Password</a>, weka username au namba yote ya usajili, kisha bonyeza <strong>Submit password reset request</strong>. Endelea kwenye kivinjari ulichotumia kutuma ombi wakati Admin anahakiki utambulisho wako. Kwenye ukurasa wa Reset Password, tumia <strong>Check approval</strong> au refresh. Baada ya approval, weka na urudie nenosiri jipya ndani ya dakika <strong>30</strong>, kisha bonyeza <strong>Reset password</strong> na uingie tena. Katika kivinjari hicho huhitaji kuandika reset code.</p>
                    <p>Ukipewa reset code na Admin, fungua <a href="<?= e(url('auth/reset.php')) ?>">Reset Password</a> na utumie code hiyo kuweka nenosiri jipya. Ombi likikataliwa au code ikiisha muda, wasiliana na Admin au tuma ombi jipya kupitia Forgot Password.</p>
                </details>
                <details>
                    <summary>Officer na Admin wanafanya hatua zipi?</summary>
                    <p>Officer afungue <strong>Review</strong> kwenye ombi alilopangiwa, ahakiki mahitaji ya ofisi yake, kisha achague <strong>Approve</strong> au <strong>Reject</strong> yenye sababu na maelekezo ya marekebisho. Resubmission ihakikiwe kabla ya kutumia <strong>Approve evidence and continue</strong> au <strong>Approve correction and continue</strong>.</p>
                    <p>Finance ashughulikie ada ya kuanza kupitia <strong>Clearance Fee Payments &rarr; Review payment</strong>: atoe control number kupitia <strong>Send control number</strong>, afungue receipt, athibitishe malipo, kisha achague <strong>Approve payment</strong>. Madeni mengine ashughulikie kwenye Review ya hatua ya Finance. Admin asimamie academic cycle na kufungua au kufunga kipindi kupitia <strong>Clearance Period</strong>, afuatilie maombi kupitia <strong>Clearance Monitoring</strong>, na ahakiki maombi ya nenosiri kwenye <strong>Password Reset Requests</strong>.</p>
                </details>
                <details>
                    <summary>Session ikiisha au nikitaka kutumia akaunti mbili?</summary>
                    <p>Onyo la session likionekana, bonyeza <strong>Continue session / Endelea</strong> kuendelea. Session inaisha baada ya dakika <strong>30</strong> bila shughuli au saa <strong>8</strong> tangu login; ukomo wa saa 8 unahitaji kuingia tena. Kutumia akaunti mbili kwa wakati mmoja, tumia browser tofauti au dirisha la Incognito / Private kwa akaunti ya pili. Tabs za kawaida kwenye browser moja hutumia login ileile. Ukimaliza, bonyeza <strong>Log out</strong>.</p>
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
