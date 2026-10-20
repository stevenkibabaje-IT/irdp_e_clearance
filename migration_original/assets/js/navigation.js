// Keep the application frame in place while PHP authorizes and renders each view.
(() => {
    'use strict';
    const shell = document.querySelector('.app-shell');
    if (!shell || !window.fetch || !window.DOMParser) return;
    const content = document.getElementById('main-content');
    const status = document.getElementById('navigationStatus');
    const base = new URL(shell.dataset.appBase, location.origin).pathname.replace(/\/$/, '');
    const routes = new Set([
        'admin/dashboard.php', 'admin/users.php', 'admin/recovery.php', 'admin/programmes.php',
        'admin/clearance_period.php', 'admin/transcripts.php', 'admin/offices.php',
        'admin/departments.php', 'admin/workflow.php', 'admin/clearances.php',
        'admin/reports.php', 'admin/import.php', 'admin/email_notifications.php',
        'officer/dashboard.php', 'officer/review.php', 'officer/clearance_fees.php',
        'officer/clearance_fee_review.php', 'supervisor/dashboard.php',
        'student/dashboard.php', 'student/start.php', 'student/clearance_fee.php',
        'student/status.php', 'student/profile.php', 'student/notifications.php',
        'student/resubmit.php', 'student/results.php', 'auth/change_password.php'
    ]);
    let controller = null;
    let submitting = false;
    let renderedUrl = location.href;

    function supported(url) {
        return url.origin === location.origin && url.pathname.startsWith(base + '/')
            && routes.has(url.pathname.slice(base.length + 1));
    }
    function markActive() {
        document.querySelectorAll('.sidebar-nav a, .notification-button').forEach(link => {
            const active = new URL(link.href).pathname === location.pathname;
            link.classList.toggle('active', active);
            if (active) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
        });
    }
    function revealHash(url) {
        let target = content;
        if (url.hash) {
            try { target = document.getElementById(decodeURIComponent(url.hash.slice(1))) || content; }
            catch (_) { /* Malformed hashes still open the requested view. */ }
        }
        if (target === content) window.scrollTo({top: 0, behavior: 'instant'});
        else {
            // An office link reveals its evidence form inside the compact review list.
            for (let ancestor = target; ancestor && ancestor !== content; ancestor = ancestor.parentElement) {
                if (ancestor.tagName === 'DETAILS') ancestor.open = true;
            }
            target.setAttribute('tabindex', '-1');
            target.scrollIntoView({block: 'start', behavior: 'instant'});
        }
        target.focus({preventScroll: true});
    }
    async function loadView(url, {method = 'GET', body, historyMode = 'push'} = {}) {
        if (submitting) return;
        controller?.abort();
        const request = new AbortController();
        controller = request;
        submitting = method === 'POST';
        shell.classList.add('is-loading');
        content.setAttribute('aria-busy', 'true');
        status.textContent = method === 'POST' ? 'Saving changes…' : 'Loading…';
        const timeout = setTimeout(() => request.abort(), 30000);
        try {
            const response = await fetch(url, {
                method, body, credentials: 'same-origin', cache: 'no-store',
                signal: request.signal, headers: {'X-Requested-With': 'IRDP-Navigation'}
            });
            const finalUrl = new URL(response.url);
            // Expired sessions and password changes return to the full sign-in page.
            if (!supported(finalUrl)) { location.assign(finalUrl.href); return; }
            if (!response.headers.get('content-type')?.includes('text/html')) throw new Error('Unexpected response');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const nextContent = page.querySelector('.app-shell #main-content');
            if (!nextContent || !response.ok) throw new Error('View unavailable');
            if (controller !== request) return;
            if (!finalUrl.hash && !response.redirected) finalUrl.hash = url.hash;
            if (historyMode === 'push' && finalUrl.href !== location.href) history.pushState(null, '', finalUrl);
            else if (historyMode === 'replace' || finalUrl.href !== location.href) history.replaceState(null, '', finalUrl);
            renderedUrl = finalUrl.href;
            document.dispatchEvent(new CustomEvent('app:before-render'));
            content.replaceChildren(...nextContent.childNodes);
            document.title = page.title;
            // Refresh counts, available menus and the current user's picture after saving.
            for (const selector of ['.sidebar-nav', '.topbar-title', '.account-menu', '.notification-button', '#supportContext']) {
                const current = document.querySelector(selector);
                const next = page.querySelector(selector);
                if (current && next) {
                    current.innerHTML = next.innerHTML;
                    if (selector === '.notification-button') current.setAttribute('aria-label', next.getAttribute('aria-label'));
                }
            }
            markActive();
            document.querySelector('.account-menu')?.removeAttribute('open');
            // Only view-local scripts run again; session and accessibility listeners persist.
            content.querySelectorAll('script:not([src])').forEach(inert => {
                const script = document.createElement('script');
                script.textContent = inert.textContent;
                inert.replaceWith(script);
            });
            document.dispatchEvent(new CustomEvent('app:navigated', {
                detail: {session: page.getElementById('sessionWarning')?.dataset}
            }));
            revealHash(finalUrl);
            content.querySelector('[aria-invalid="true"]')?.focus();
            status.textContent = document.querySelector('.topbar-title').textContent + ' loaded.';
        } catch (_) {
            if (controller !== request) return;
            if (method === 'GET') { location.assign(url.href); return; }
            // Never replay a write: its outcome may be unknown after a dropped connection.
            const error = document.createElement('div');
            error.className = 'alert danger';
            error.setAttribute('role', 'alert');
            error.textContent = 'Could not confirm the result. Refresh this view to check whether your changes were saved before submitting again. ';
            const refresh = document.createElement('a');
            refresh.href = renderedUrl;
            refresh.textContent = 'Refresh view';
            error.append(refresh);
            content.prepend(error);
            status.textContent = 'Could not confirm whether changes were saved.';
            document.dispatchEvent(new CustomEvent('app:submit-error'));
        } finally {
            clearTimeout(timeout);
            if (controller === request) {
                submitting = false;
                shell.classList.remove('is-loading');
                content.removeAttribute('aria-busy');
            }
        }
    }
    document.addEventListener('click', event => {
        const account = document.querySelector('.account-menu');
        if (account?.open && (!account.contains(event.target) || event.target.closest('a'))) account.open = false;
        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey
            || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;
        const url = new URL(link.href);
        if (!supported(url)) return;
        const current = new URL(renderedUrl);
        if (url.pathname === current.pathname && url.search === current.search && url.hash) return;
        event.preventDefault();
        void loadView(url);
    });
    document.addEventListener('submit', event => {
        const form = event.target;
        const submitter = event.submitter;
        const target = submitter?.getAttribute('formtarget') || form.target;
        if (event.defaultPrevented || (target && target !== '_self')) return;
        const url = new URL(submitter?.getAttribute('formaction') || form.getAttribute('action') || location.href, location.href);
        const method = (submitter?.getAttribute('formmethod') || form.method || 'GET').toUpperCase();
        if (!supported(url) || !['GET', 'POST'].includes(method)) return;
        event.preventDefault();
        if (submitting) return;
        const data = new FormData(form);
        if (submitter?.name) data.append(submitter.name, submitter.value);
        if (method === 'GET') {
            url.search = new URLSearchParams(data).toString();
            void loadView(url);
        } else {
            void loadView(url, {method, body: data});
        }
    });
    window.addEventListener('popstate', () => {
        const url = new URL(location.href);
        const previous = new URL(renderedUrl);
        if (submitting) { location.reload(); return; }
        if (url.pathname === previous.pathname && url.search === previous.search) {
            renderedUrl = url.href;
            if (url.hash) revealHash(url);
            return;
        }
        if (supported(url)) void loadView(url, {historyMode: 'replace'});
        else location.reload();
    });
    document.addEventListener('keydown', event => {
        const account = document.querySelector('.account-menu');
        if (event.key === 'Escape' && account?.open) {
            account.open = false;
            account.querySelector('summary').focus();
        }
    });
    window.addEventListener('hashchange', () => { if (location.hash) revealHash(new URL(location.href)); });
    markActive();
    if (location.hash) revealHash(new URL(location.href));
})();
