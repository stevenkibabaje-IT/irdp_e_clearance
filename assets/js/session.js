// The server owns session expiry. Background checks never renew the idle deadline.
(() => {
    'use strict';
    const dialog = document.getElementById('sessionWarning');
    if (!dialog) return;
    const continueButton = document.getElementById('sessionContinue');
    const message = document.getElementById('sessionWarningMessage');
    const countdown = document.getElementById('sessionWarningCountdown');
    const error = document.getElementById('sessionWarningError');
    const warningSeconds = Number(dialog.dataset.warningSeconds);
    let idleExpires = Number(dialog.dataset.idleExpiresAt) * 1000;
    let absoluteExpires = Number(dialog.dataset.absoluteExpiresAt) * 1000;
    // A monotonic clock prevents the browser's wall-clock changes affecting the countdown.
    let serverOffset = Number(dialog.dataset.serverTime) * 1000 - performance.now();
    let lastRenewal = performance.now();
    let lastCheck = performance.now();
    let inFlight = null;
    let leaving = false;
    let previousFocus = null;

    function signIn(reason = 'expired') {
        if (leaving) return;
        leaving = true;
        const destination = new URL(dialog.dataset.loginUrl, location.href);
        destination.searchParams.set('session', ['idle', 'absolute', 'revoked'].includes(reason) ? reason : 'expired');
        location.replace(destination.href);
    }
    function closeWarning() {
        if (!dialog.open) return;
        dialog.close();
        if (previousFocus?.isConnected) previousFocus.focus();
        previousFocus = null;
        error.textContent = '';
    }
    function updateWarning() {
        const now = performance.now() + serverOffset;
        const deadline = Math.min(idleExpires, absoluteExpires);
        const remaining = Math.max(0, Math.ceil((deadline - now) / 1000));
        const absolute = absoluteExpires <= idleExpires;
        if (remaining <= warningSeconds) {
            message.textContent = absolute
                ? 'Your session is reaching its 8-hour limit. You will need to log in again to continue.'
                : 'You have been inactive. Continue your session to keep working on this page.';
            continueButton.hidden = absolute;
            countdown.textContent = `${Math.floor(remaining / 60)}:${String(remaining % 60).padStart(2, '0')}`;
            if (!dialog.open) {
                previousFocus = document.activeElement;
                dialog.showModal();
            }
        } else {
            closeWarning();
        }
        return {remaining, reason: absolute ? 'absolute' : 'idle'};
    }
    async function checkSession(renew = false) {
        if (leaving) return false;
        if (inFlight) {
            const completed = await inFlight;
            if (!renew || !completed || leaving) return completed;
        }
        lastCheck = performance.now();
        if (renew) lastRenewal = performance.now();
        inFlight = (async () => {
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 10000);
            try {
                const options = {method: renew ? 'POST' : 'GET', credentials: 'same-origin', cache: 'no-store', signal: controller.signal};
                if (renew) options.body = new URLSearchParams({action: 'continue', csrf_token: dialog.dataset.csrfToken});
                const response = await fetch(dialog.dataset.statusUrl, options);
                const state = await response.json();
                if (response.status === 401 || response.status === 419) {
                    signIn(state.reason);
                    return false;
                }
                if (!response.ok || state.authenticated !== true
                    || !Number.isFinite(state.server_time) || !Number.isFinite(state.idle_expires_at)
                    || !Number.isFinite(state.absolute_expires_at)) throw new Error('Session check failed.');
                serverOffset = state.server_time * 1000 - performance.now();
                idleExpires = state.idle_expires_at * 1000;
                absoluteExpires = state.absolute_expires_at * 1000;
                error.textContent = '';
                updateWarning();
                return true;
            } catch (_) {
                if (dialog.open) error.textContent = 'Could not contact the server. Check your connection and try again before the session expires.';
                return false;
            } finally {
                clearTimeout(timeout);
            }
        })();
        try { return await inFlight; }
        finally { inFlight = null; }
    }
    // Only trusted user input sends a throttled heartbeat. Timers and GET polling do not.
    function activity(event) {
        if (!event.isTrusted || dialog.open || leaving || document.hidden) return;
        if (performance.now() - lastRenewal >= 60000) {
            lastRenewal = performance.now();
            void checkSession(true);
        }
    }
    ['pointerdown', 'pointermove', 'keydown', 'input', 'wheel'].forEach(name => {
        document.addEventListener(name, activity, {passive: true});
    });
    continueButton.addEventListener('click', async () => {
        continueButton.disabled = true;
        try { await checkSession(true); }
        finally { continueButton.disabled = false; }
    });
    // Escape must not hide an expiring session warning.
    dialog.addEventListener('cancel', event => event.preventDefault());
    function resume() {
        if (!document.hidden && !leaving) void checkSession();
    }
    window.addEventListener('focus', resume);
    window.addEventListener('pageshow', resume);
    document.addEventListener('visibilitychange', resume);
    setInterval(async () => {
        if (leaving) return;
        const state = updateWarning();
        if (state.remaining === 0) {
            const confirmed = await checkSession();
            if (!confirmed && !leaving) signIn(state.reason);
        } else if (!document.hidden && performance.now() - lastCheck >= 60000) {
            await checkSession();
        }
    }, 1000);
    updateWarning();
})();
