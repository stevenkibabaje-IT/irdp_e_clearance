<?php
require_once __DIR__ . '/help_context.php';
[$helpTitle, $helpSteps] = page_help_context();
?>
<aside class="help-widget" id="supportWidget" lang="en" aria-label="System help">
    <section class="help-panel" id="supportPanel" role="dialog" aria-labelledby="supportTitle" hidden>
        <header class="help-panel-head">
            <span class="help-symbol"><?= icon('headset') ?></span>
            <div><h2 id="supportTitle">Need help?</h2><p>Find guidance and support.</p></div>
            <button class="help-close" id="supportClose" type="button" aria-label="Close help" title="Close help"><?= icon('x') ?></button>
        </header>
        <div class="help-tabs" role="tablist" aria-label="Choose help category">
            <button type="button" role="tab" id="helpTabGuide" aria-controls="helpGuide" aria-selected="true" tabindex="0" data-help-tab="guide"><?= icon('book-open') ?> Guide</button>
            <button type="button" role="tab" id="helpTabFaq" aria-controls="helpFaq" aria-selected="false" tabindex="-1" data-help-tab="faq"><?= icon('help-circle') ?> Questions</button>
            <button type="button" role="tab" id="helpTabContact" aria-controls="helpContact" aria-selected="false" tabindex="-1" data-help-tab="contact"><?= icon('phone') ?> Contact</button>
        </div>
        <div class="help-panel-body">
            <section id="helpGuide" role="tabpanel" aria-labelledby="helpTabGuide" tabindex="0">
                <div id="supportContext">
                    <p class="help-kicker">Help for this page</p>
                    <h3><?= e($helpTitle) ?></h3>
                    <ol class="help-steps"><?php foreach ($helpSteps as $helpStep): ?><li><?= e($helpStep) ?></li><?php endforeach; ?></ol>
                </div>
                <div class="help-next"><p>Still have a question?</p><button type="button" class="btn secondary" data-help-select="faq">View frequently asked questions <?= icon('chevron-right') ?></button></div>
            </section>
            <section id="helpFaq" role="tabpanel" aria-labelledby="helpTabFaq" tabindex="0" hidden>
                <label class="help-search-label" for="helpSearch">Search help</label>
                <input type="search" id="helpSearch" placeholder="For example: payment, pending, password" autocomplete="off">
                <p id="helpSearchStatus" class="help-search-status" role="status" aria-live="polite"></p>
                <?php require __DIR__ . '/help_faq.php'; ?>
                <p id="helpNoResults" hidden>No matching questions. Try another search or open Contact.</p>
            </section>
            <section id="helpContact" role="tabpanel" aria-labelledby="helpTabContact" tabindex="0" hidden>
                <p class="help-kicker">Contact support</p><h3>Speak to the support team</h3>
                <p class="muted">Tell us which page you are using and the step you need help with.</p>
                <a class="help-contact-row" href="tel:+255659913570"><span class="help-contact-icon"><?= icon('phone') ?></span><span><strong>0659 913 570</strong><small>Call for support</small></span><?= icon('chevron-right') ?></a>
                <a class="help-contact-row" href="tel:+255662632565"><span class="help-contact-icon"><?= icon('phone') ?></span><span><strong>0662 632 565</strong><small>Call for support</small></span><?= icon('chevron-right') ?></a>
                <p class="help-contact-note"><?= icon('shield') ?> Never share your password.</p>
            </section>
        </div>
    </section>
    <button class="help-toggle" id="supportToggle" type="button" aria-controls="supportPanel" aria-expanded="false" aria-label="Need help?" title="Need help?">
        <span class="help-toggle-symbol"><span class="help-open-icon"><?= icon('headset') ?></span><span class="help-hide-icon" hidden><?= icon('x') ?></span></span>
        <span class="help-toggle-label">Need help?<small>Guide and support</small></span>
    </button>
</aside>
<script src="<?= e(url('assets/js/help.js?v=' . filemtime(__DIR__ . '/../assets/js/help.js'))) ?>" defer></script>
