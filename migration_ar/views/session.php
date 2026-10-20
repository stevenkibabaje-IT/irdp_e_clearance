<dialog id="sessionWarning" aria-labelledby="sessionWarningTitle" aria-describedby="sessionWarningMessage"
 data-server-time="<?=time()?>" data-idle-expires-at="<?=$_SESSION['last']+1800?>" data-absolute-expires-at="<?=$_SESSION['started']+28800?>" data-warning-seconds="60"
 data-status-url="index.php?page=session" data-login-url="index.php?page=login" data-csrf-token="<?=h($_SESSION['csrf'])?>">
 <h2 id="sessionWarningTitle">Your session is about to expire</h2>
 <p id="sessionWarningMessage">Continue your session to keep working.</p>
 <p>Time remaining: <strong id="sessionWarningCountdown"></strong></p>
 <p id="sessionWarningError" role="status"></p>
 <button id="sessionContinue" type="button">Continue session</button>
</dialog>
<script src="session.js" defer></script>
