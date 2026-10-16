<?php
// Signed-in users work within the account toolbar; the masthead is public only.
if (current_user() !== null) { return; }
$institutionHome = $institutionHome ?? false;
?>
<header class="institution-header<?= $institutionHome ? ' institution-header--home' : '' ?>" aria-label="IRDP institutional identity">
    <?php if ($institutionHome): ?>
        <img class="institution-banner" src="<?= e(url('assets/img/irdp-institution-banner.jpg?v=' . filemtime(__DIR__ . '/../assets/img/irdp-institution-banner.jpg'))) ?>" width="4042" height="375" alt="Ministry of Finance — Institute of Rural Development Planning (IRDP). Kupanga ni Kuchagua.">
    <?php endif; ?>
    <div class="institution-header-inner">
        <img class="institution-emblem" src="<?= e(url('assets/img/tanzania-emblem.png')) ?>" width="80" height="92" alt="Coat of arms of Tanzania">
        <div class="institution-copy">
            <p class="institution-ministry">Ministry of Finance</p>
            <p class="institution-name">Institute of Rural Development Planning</p>
            <p class="institution-motto">&ldquo;Kupanga ni Kuchagua&rdquo;</p>
        </div>
        <img class="institution-logo" src="<?= e(url('assets/img/irdp-logo-web.png')) ?>" width="88" height="88" alt="IRDP logo">
    </div>
    <?php if (!$institutionHome): ?><div class="institution-service"><span><?= icon('graduation-cap') ?> Student Clearance System</span></div><?php endif; ?>
</header>
