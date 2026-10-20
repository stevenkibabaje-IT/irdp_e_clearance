<details class="accessibility-tools" id="accessibilityTools">
    <summary id="accessibilityToggle" title="Accessibility" aria-label="Accessibility" aria-controls="accessibilityPanel" aria-expanded="false">
        <span class="accessibility-toggle-symbol">
        <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
            <circle cx="12" cy="4" r="2" />
            <path d="M4 9h16M12 9v5M8 21l4-7 4 7" />
        </svg>
        </span>
        <span class="accessibility-toggle-label">Accessibility<small>Text and contrast</small></span>
    </summary>
    <section class="accessibility-panel" id="accessibilityPanel" aria-labelledby="accessibilityHeading">
    <div class="accessibility-panel-head"><div><h2 id="accessibilityHeading">Accessibility</h2><p>Text size &amp; contrast</p></div><button type="button" id="accessibilityClose" aria-label="Close accessibility"><?= icon('x') ?></button></div>
    <div class="accessibility-options">
        <div class="accessibility-size-option"><label for="accessibilityTextSize"><?= icon('type') ?> Text size</label>
        <select id="accessibilityTextSize">
            <option value="1">Normal (100%)</option>
            <option value="1.25">Large (125%)</option>
            <option value="1.5">Larger (150%)</option>
            <option value="2">Largest (200%)</option>
        </select>
        </div>
        <button type="button" class="btn secondary" id="accessibilityContrast" aria-pressed="false"><?= icon('contrast') ?> High contrast</button>
        <button type="button" class="btn secondary" id="accessibilityReset"><?= icon('rotate-ccw') ?> Reset</button>
    </div>
    <p class="sr-only" id="accessibilityStatus" role="status" aria-live="polite"></p>
    </section>
</details>
<script defer src="<?= e(url('assets/js/accessibility.js?v='.filemtime(__DIR__.'/../assets/js/accessibility.js'))) ?>"></script>
