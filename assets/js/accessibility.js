// Coordinate saved display preferences and keyboard access to page controls.
(() => {
    'use strict';
    const root = document.documentElement;
    const size = document.getElementById('accessibilityTextSize');
    const contrast = document.getElementById('accessibilityContrast');
    const status = document.getElementById('accessibilityStatus');
    const scales = ['1', '1.25', '1.5', '2'];
    let preferences = { size: '1', contrast: false };
    try {
        const saved = JSON.parse(localStorage.getItem('irdp-accessibility') || '{}');
        preferences = { size: scales.includes(saved.size) ? saved.size : '1', contrast: saved.contrast === true };
    } catch (_) { /* Display controls also work when storage is unavailable. */ }
    // Synchronize CSS preferences, controls and screen-reader announcements.
    function apply(announce = false) {
        root.style.setProperty('--text-scale', preferences.size);
        root.classList.toggle('high-contrast', preferences.contrast);
        if (size) size.value = preferences.size;
        if (contrast) contrast.setAttribute('aria-pressed', String(preferences.contrast));
        if (announce && status) status.textContent = `Text size ${Number(preferences.size) * 100} percent. High contrast ${preferences.contrast ? 'on' : 'off'}.`;
    }
    function save() {
        apply(true);
        try { localStorage.setItem('irdp-accessibility', JSON.stringify(preferences)); } catch (_) {}
    }
    apply();
    size?.addEventListener('change', () => { preferences.size = scales.includes(size.value) ? size.value : '1'; save(); });
    contrast?.addEventListener('click', () => { preferences.contrast = !preferences.contrast; save(); });
    document.getElementById('accessibilityReset')?.addEventListener('click', () => { preferences = { size: '1', contrast: false }; save(); });

    const accessibilityTools = document.getElementById('accessibilityTools');
    const accessibilityToggle = document.getElementById('accessibilityToggle');
    if (accessibilityTools) accessibilityToggle?.setAttribute('aria-expanded', String(accessibilityTools.open));
    accessibilityTools?.addEventListener('toggle', () => {
        accessibilityToggle?.setAttribute('aria-expanded', String(accessibilityTools.open));
    });
    document.addEventListener('pointerdown', event => {
        if (accessibilityTools?.open && !accessibilityTools.contains(event.target)) accessibilityTools.open = false;
    });
    accessibilityTools?.addEventListener('focusout', event => {
        if (event.relatedTarget && !accessibilityTools.contains(event.relatedTarget)) accessibilityTools.open = false;
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && accessibilityTools?.open) {
            event.preventDefault();
            accessibilityTools.open = false;
            accessibilityToggle?.focus();
        }
    });

    // The navigation drawer uses the same tablet breakpoint as the shared stylesheet.
    const sidebar = document.getElementById('sidebar');
    const menu = document.getElementById('navigationToggle');
    const close = document.getElementById('navigationClose');
    const backdrop = document.getElementById('navigationBackdrop');
    const main = document.querySelector('.main-content');
    const mobile = matchMedia('(max-width: 1024px)');
    function setNavigation(opened, returnFocus = false) {
        if (!sidebar || !menu) return;
        const isOpen = mobile.matches && opened;
        sidebar.classList.toggle('open', isOpen);
        root.classList.toggle('navigation-open', isOpen);
        if (backdrop) backdrop.hidden = !isOpen;
        if (main) main.inert = isOpen;
        sidebar.inert = mobile.matches && !isOpen;
        menu.setAttribute('aria-expanded', String(isOpen));
        menu.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
        if (mobile.matches) {
            sidebar.setAttribute('aria-hidden', String(!isOpen));
            sidebar.setAttribute('role', 'dialog');
            if (isOpen) sidebar.setAttribute('aria-modal', 'true');
            else sidebar.removeAttribute('aria-modal');
        } else {
            sidebar.removeAttribute('aria-hidden');
            sidebar.removeAttribute('role');
            sidebar.removeAttribute('aria-modal');
        }
        if (isOpen) (close || sidebar.querySelector('a'))?.focus();
        else if (returnFocus && mobile.matches) menu.focus();
    }
    setNavigation(false);
    menu?.addEventListener('click', () => setNavigation(!sidebar.classList.contains('open'), true));
    close?.addEventListener('click', () => setNavigation(false, true));
    backdrop?.addEventListener('click', () => setNavigation(false, true));
    sidebar?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setNavigation(false)));
    document.addEventListener('keydown', event => {
        if (!mobile.matches || !sidebar?.classList.contains('open')) return;
        if (event.key === 'Escape' && !event.defaultPrevented) {
            event.preventDefault();
            setNavigation(false, true);
        } else if (event.key === 'Tab') {
            const controls = [...sidebar.querySelectorAll('a[href], button, input, select, textarea, [tabindex]')]
                .filter(control => !control.disabled && control.tabIndex >= 0 && control.getClientRects().length);
            const first = controls[0], last = controls[controls.length - 1];
            if (event.shiftKey && (document.activeElement === first || !sidebar.contains(document.activeElement))) {
                event.preventDefault(); last?.focus();
            } else if (!event.shiftKey && (document.activeElement === last || !sidebar.contains(document.activeElement))) {
                event.preventDefault(); first?.focus();
            }
        }
    });
    // Releasing the drawer on resize also restores scrolling and background interaction.
    mobile.addEventListener('change', () => {
        const wasInside = sidebar?.contains(document.activeElement);
        setNavigation(false, wasInside);
        if (wasInside && !mobile.matches && document.activeElement === close) sidebar.querySelector('a')?.focus();
    });
    document.querySelectorAll('a.skip-link').forEach(link => {
        link.addEventListener('click', () => document.getElementById('main-content')?.focus());
    });
    const firstError = document.querySelector('[aria-invalid="true"]');
    if (firstError) firstError.focus();
    // Give overflow tables an accessible keyboard scroll target and name.
    document.querySelectorAll('.table-wrap').forEach((wrapper, index) => {
        wrapper.setAttribute('role', 'region');
        wrapper.setAttribute('aria-label', wrapper.querySelector('caption')?.textContent || `Scrollable table ${index + 1}`);
        const hint = document.createElement('p');
        hint.className = 'table-scroll-hint';
        hint.textContent = 'Swipe or scroll horizontally to view all columns.';
        hint.hidden = true;
        wrapper.before(hint);
        const updateOverflow = () => {
            const overflows = wrapper.scrollWidth > wrapper.clientWidth + 1;
            wrapper.tabIndex = overflows ? 0 : -1;
            hint.hidden = !overflows;
        };
        updateOverflow();
        if (typeof ResizeObserver !== 'undefined') new ResizeObserver(updateOverflow).observe(wrapper);
        else window.addEventListener('resize', updateOverflow);
    });
})();
