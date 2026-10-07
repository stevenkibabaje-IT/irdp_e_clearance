<?php
$sessionState = authenticated_session_state();
?>
<dialog class="session-warning" id="sessionWarning" aria-labelledby="sessionWarningTitle" aria-describedby="sessionWarningMessage"
        data-server-time="<?= $sessionState['server_time'] ?>"
        data-idle-expires-at="<?= $sessionState['idle_expires_at'] ?>"
        data-absolute-expires-at="<?= $sessionState['absolute_expires_at'] ?>"
        data-warning-seconds="<?= SESSION_WARNING_SECONDS ?>"
        data-status-url="<?= e(url('auth/session.php')) ?>"
        data-login-url="<?= e(url('auth/login.php')) ?>"
        data-csrf-token="<?= e(csrf_token()) ?>">
    <div class="session-warning-icon"><?= icon('clock') ?></div>
    <h2 id="sessionWarningTitle">Your session is about to expire</h2>
    <p id="sessionWarningMessage">You have been inactive. Continue your session to keep working on this page.</p>
    <p class="session-warning-countdown">Time remaining: <strong id="sessionWarningCountdown"></strong></p>
    <p class="danger-text" id="sessionWarningError" role="status"></p>
    <div class="session-warning-actions">
        <button class="btn primary" id="sessionContinue" type="button">Continue session / Endelea</button>
        <form method="post" action="<?= e(url('auth/logout.php')) ?>">
            <?php csrf_field(); ?>
            <button class="btn secondary" type="submit">Log out</button>
        </form>
    </div>
</dialog>
<script defer src="<?= e(url('assets/js/session.js?v=' . filemtime(__DIR__ . '/../assets/js/session.js'))) ?>"></script>
