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
$unreadCount = count(array_filter($notifications, static fn(array $notice): bool => !(bool)$notice['is_read']));
$totalCount = count($notifications);
$view = ($_GET['view'] ?? 'all') === 'unread' ? 'unread' : 'all';
$groups = [];
foreach ($notifications as $notice) {
    if ($view === 'unread' && $notice['is_read']) { continue; }
    $timestamp = strtotime((string)$notice['created_at']);
    $dateKey = $timestamp !== false ? date('Y-m-d', $timestamp) : 'earlier';
    $notice['display_time'] = $timestamp !== false ? date('H:i', $timestamp) : '';
    $notice['iso_time'] = $timestamp !== false ? date('c', $timestamp) : '';
    $groups[$dateKey][] = $notice;
}
$actionUrl = match ($u['role']) {
    'ADMIN' => 'admin/recovery.php',
    'STUDENT' => 'student/status.php',
    default => 'officer/dashboard.php',
};
$actionLabel = match ($u['role']) {
    'ADMIN' => 'Password reset requests',
    'STUDENT' => 'My Clearance',
    default => 'Assigned tasks',
};

$pageTitle = 'Notifications';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <span class="eyebrow">UPDATES</span>
        <h1>Notifications</h1>
        <p class="muted"><?= $unreadCount > 0 ? 'You have ' . $unreadCount . ' unread update' . ($unreadCount === 1 ? '' : 's') . '.' : 'You are up to date.' ?> Clearance decisions and account updates appear here.</p>
    </div>
    <a class="btn secondary" href="<?= e(url($actionUrl)) ?>"><?= icon('clipboard-check') ?> <?= e($actionLabel) ?></a>
</div>

<section class="notifications-feed" aria-label="Account notifications">
    <div class="notifications-toolbar">
        <nav class="notification-filters" aria-label="Filter notifications">
            <a class="notification-filter <?= $view === 'all' ? 'active' : '' ?>" href="<?= e(url('student/notifications.php')) ?>" <?= $view === 'all' ? 'aria-current="page"' : '' ?>>All updates <span><?= $totalCount ?></span></a>
            <a class="notification-filter <?= $view === 'unread' ? 'active' : '' ?>" href="<?= e(url('student/notifications.php?view=unread')) ?>" <?= $view === 'unread' ? 'aria-current="page"' : '' ?>>Unread <span><?= $unreadCount ?></span></a>
        </nav>
        <form method="post" action="<?= e(url('student/notifications.php')) ?>"><?php csrf_field(); ?><button class="btn secondary small" type="submit" <?= $unreadCount === 0 ? 'disabled' : '' ?>><?= icon('check-circle') ?> Mark all as read</button></form>
    </div>
    <div class="notification-list">
        <?php foreach ($groups as $dateKey => $notices):
            $dateLabel = match ($dateKey) {
                date('Y-m-d') => 'Today',
                date('Y-m-d', strtotime('-1 day')) => 'Yesterday',
                'earlier' => 'Earlier',
                default => date('d M Y', strtotime($dateKey)),
            };
        ?>
        <section class="notification-group" aria-labelledby="notice-date-<?= e($dateKey) ?>">
            <h2 class="notification-date" id="notice-date-<?= e($dateKey) ?>"><?= e($dateLabel) ?><span><?= count($notices) ?> update<?= count($notices) === 1 ? '' : 's' ?></span></h2>
            <div class="notification-group-items">
                <?php foreach ($notices as $notification):
                    $kind = preg_match('/approved|completed|cleared/i', $notification['title']) ? 'success'
                        : (preg_match('/reject|action required|correction required/i', $notification['title']) ? 'attention'
                        : (preg_match('/payment|receipt|control number|fee/i', $notification['title']) ? 'payment' : 'info'));
                    $noticeIcon = match ($kind) { 'success' => 'check-circle', 'attention' => 'alert-circle', 'payment' => 'receipt', default => 'bell' };
                ?>
                <article class="notification-card <?= $notification['is_read'] ? 'is-read' : 'is-unread' ?>" aria-labelledby="notice-<?= (int)$notification['id'] ?>">
                    <span class="notification-icon <?= e($kind) ?>"><?= icon($noticeIcon) ?></span>
                    <div class="notification-body">
                        <div class="notification-card-heading"><h3 id="notice-<?= (int)$notification['id'] ?>"><?= e($notification['title']) ?></h3><?php if (!$notification['is_read']): ?><span class="notification-new">New</span><?php endif; ?></div>
                        <p><?= e(notification_email_message($notification['message'])) ?></p>
                        <div class="notification-meta"><?= icon('clock') ?><?php if ($notification['iso_time']): ?><time datetime="<?= e($notification['iso_time']) ?>"><?= e($dateLabel) ?> &middot; <?= e($notification['display_time']) ?></time><?php endif; ?><span class="notification-read-state"><?= $notification['is_read'] ? 'Read' : 'Unread' ?></span></div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>
        <?php if (!$groups): ?>
            <div class="notification-empty"><span><?= icon($view === 'unread' ? 'check-circle' : 'bell') ?></span><h2><?= $view === 'unread' ? 'You are all caught up' : 'No notifications yet' ?></h2><p><?= $view === 'unread' ? 'There are no unread updates. You can view your earlier notifications under All updates.' : 'Your clearance decisions and account updates will appear here.' ?></p></div>
        <?php endif; ?>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
