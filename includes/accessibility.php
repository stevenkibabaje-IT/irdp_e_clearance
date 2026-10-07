<details class="accessibility-tools" id="accessibilityTools">
    <summary id="accessibilityToggle" title="Accessibility / Ufikivu" aria-label="Accessibility / Ufikivu" aria-controls="accessibilityPanel" aria-expanded="false">
        <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
            <circle cx="12" cy="4" r="2" />
            <path d="M4 9h16M12 9v5M8 21l4-7 4 7" />
        </svg>
    </summary>
    <div class="accessibility-panel" id="accessibilityPanel">
    <h2>Accessibility / Ufikivu</h2>
    <div class="accessibility-options">
        <label for="accessibilityTextSize">Text size / Ukubwa wa maandishi</label>
        <select id="accessibilityTextSize">
            <option value="1">Normal (100%)</option>
            <option value="1.25">Large (125%)</option>
            <option value="1.5">Larger (150%)</option>
            <option value="2">Largest (200%)</option>
        </select>
        <button type="button" class="btn secondary" id="accessibilityContrast" aria-pressed="false">High contrast / Rangi wazi</button>
        <button type="button" class="btn secondary" id="accessibilityReset">Reset display / Rudisha mwonekano</button>
    </div>
    <p class="sr-only" id="accessibilityStatus" role="status" aria-live="polite"></p>
    </div>
</details>
<script defer src="<?= e(url('assets/js/accessibility.js?v='.filemtime(__DIR__.'/../assets/js/accessibility.js'))) ?>"></script>
