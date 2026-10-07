<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$u = current_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([(int) $u['id']]);
    redirect('student/notifications.php');
}

$stmt = $pdo->prepare(
    'SELECT * FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC, id DESC'
);
$stmt->execute([(int) $u['id']]);
$notifications = $stmt->fetchAll();

$pageTitle = 'Notifications';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <span class="eyebrow">UPDATES</span>
        <h1>Notifications</h1>
        <p class="muted">Clearance decisions and system updates.</p>
    </div>
</div>

<div class="notification-list">
    <?php if ($u['role']==='ADMIN'): ?>
        <a class="btn primary" href="<?= e(url('admin/recovery.php')) ?>">Open Password Reset Requests</a>
    <?php endif; ?>
    <?php if ($u['role']==='STUDENT'): ?>
        <a class="btn primary" href="<?= e(url('student/status.php')) ?>">Open My Clearance / submit evidence</a>
    <?php endif; ?>
    <?php if (in_array($u['role'],['OFFICER','SUPERVISOR'],true)): ?>
        <a class="btn primary" href="<?= e(url('officer/dashboard.php')) ?>">Open my assigned clearance tasks</a>
    <?php endif; ?>
    <form method="post"><?php csrf_field(); ?><button class="btn secondary" type="submit">Mark all as read</button></form>
    <?php foreach ($notifications as $notification): ?>
        <article class="notification-card" style="padding:16px;">
            <strong><?= e($notification['title']) ?></strong>
            <p><?= e($notification['message']) ?></p>
            <small class="muted"><?= e($notification['created_at']) ?></small>
        </article>
    <?php endforeach; ?>

    <?php if (!$notifications): ?>
        <div class="panel empty">No notifications yet.</div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
