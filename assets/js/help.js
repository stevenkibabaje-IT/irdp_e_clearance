// Local guidance and existing support contacts; opening help never submits a form.
(() => {
    'use strict';
    const widget = document.getElementById('supportWidget');
    if (!widget) return;
    const panel = document.getElementById('supportPanel');
    const toggle = document.getElementById('supportToggle');
    const close = document.getElementById('supportClose');
    const tabs = [...widget.querySelectorAll('[data-help-tab]')];
    const sections = {guide: 'helpGuide', faq: 'helpFaq', contact: 'helpContact'};
    function selectTab(name, focus = false) {
        if (!sections[name]) return;
        tabs.forEach(tab => {
            const selected = tab.dataset.helpTab === name;
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
            if (selected && focus) tab.focus();
        });
        Object.entries(sections).forEach(([key, id]) => { document.getElementById(id).hidden = key !== name; });
        panel.querySelector('.help-panel-body').scrollTop = 0;
        panel.scrollTop = 0;
    }
    function setOpen(open, restoreFocus = false) {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        widget.classList.toggle('open', open);
        toggle.querySelector('.help-open-icon').hidden = open;
        toggle.querySelector('.help-hide-icon').hidden = !open;
        if (open) {
            const access = document.getElementById('accessibilityTools');
            if (access) access.open = false;
            close.focus({preventScroll: true});
        } else if (restoreFocus) toggle.focus({preventScroll: true});
    }
    toggle.addEventListener('click', () => setOpen(panel.hidden, true));
    document.addEventListener('app:accessibility-open', () => setOpen(false));
    close.addEventListener('click', () => setOpen(false, true));
    widget.addEventListener('click', event => {
        const tab = event.target.closest('[data-help-tab], [data-help-select]');
        if (tab) selectTab(tab.dataset.helpTab || tab.dataset.helpSelect, true);
        // Internal guidance links may navigate normally or through the persistent frame.
        if (event.target.closest('a')?.protocol.startsWith('http')) setOpen(false);
    });
    widget.querySelector('[role="tablist"]').addEventListener('keydown', event => {
        const current = tabs.indexOf(document.activeElement);
        if (current < 0 || !['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) return;
        event.preventDefault();
        const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1
            : (current + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
        selectTab(tabs[next].dataset.helpTab, true);
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !event.defaultPrevented && !panel.hidden) {
            event.preventDefault(); setOpen(false, true);
        }
    });
    document.addEventListener('pointerdown', event => {
        if (!panel.hidden && !widget.contains(event.target)) setOpen(false);
    });
    document.addEventListener('app:navigated', () => { setOpen(false); selectTab('guide'); });
    const search = document.getElementById('helpSearch');
    const questions = [...widget.querySelectorAll('.help-faq > details')];
    search.addEventListener('input', () => {
        const term = search.value.trim().toLocaleLowerCase();
        let matches = 0;
        questions.forEach(question => {
            const match = !term || question.textContent.toLocaleLowerCase().includes(term);
            question.hidden = !match;
            if (match) matches++;
        });
        document.getElementById('helpSearchStatus').textContent = term ? `Maswali ${matches} yamepatikana.` : '';
        document.getElementById('helpNoResults').hidden = matches !== 0;
    });
    // Also allow a bookmarked help link on the public landing page.
    if (location.hash === '#help') setOpen(true);
})();
