<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('ADMIN');
$error = '';
$errors = [];
$config = notification_mail_config();
$problem = notification_mail_problem($config);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $action = choice_input($_POST['action'] ?? '', ['test','process','retry'], 'action');
        if ($action === 'retry') {
            $id = positive_id($_POST['email_id'] ?? null, 'email_id');
            $s = $pdo->prepare('UPDATE email_outbox SET status="PENDING",attempts=0,available_at=NOW(),last_error=NULL WHERE id=? AND status="FAILED"');
            $s->execute([$id]);
            if (!$s->rowCount()) { throw new RuntimeException('Choose a failed email to retry.'); }
            audit($pdo,'EMAIL_RETRY_REQUESTED',(int)current_user()['id'],null,'Outbox #'.$id);
            flash('success','Email queued for another delivery attempt.');
        } else {
            if ($problem !== null) { throw new RuntimeException($problem); }
            rate_limit($pdo,'email_delivery_action',request_ip().':'.current_user()['id'],10,15);
            $testNotification = null;
            if ($action === 'test') {
                $studentId = positive_id($_POST['student_user_id'] ?? null,'student_user_id');
                $s = $pdo->prepare('SELECT u.id,u.email FROM users u INNER JOIN roles r ON r.id=u.role_id WHERE u.id=? AND r.name="STUDENT" AND u.active=1');
                $s->execute([$studentId]);
                $student = $s->fetch();
                if (!$student || !filter_var($student['email'],FILTER_VALIDATE_EMAIL)) {
                    throw new ValidationException(['student_user_id'=>'Choose a student with a valid email.']);
                }
                $testNotification = notify($pdo,$studentId,'Email notification test','Huu ni ujumbe wa majaribio. Akaunti yako sasa inaweza kupokea taarifa za clearance kupitia email hii.');
                audit($pdo,'EMAIL_TEST_REQUESTED',(int)current_user()['id'],null,'Student user #'.$studentId);
            }
            $result = process_notification_emails($pdo,3,$config,null,$testNotification);
            if ($result['problem'] !== null) { throw new RuntimeException($result['problem']); }
            flash($result['retry'] || $result['failed'] ? 'warning':'success',
                'Emails accepted by SMTP: '.$result['sent'].'. Waiting to retry: '.$result['retry'].'. Failed: '.$result['failed'].'. Check the delivery history below.');
        }
        redirect('admin/email_notifications.php');
    } catch (Throwable $e) { $error = page_error($e,$errors); }
}
$counts = array_fill_keys(['PENDING','PROCESSING','RETRY','SENT','FAILED','CANCELLED'],0);
foreach ($pdo->query('SELECT status,COUNT(*) AS total FROM email_outbox GROUP BY status') as $row) { $counts[$row['status']] = (int)$row['total']; }
$students = [];
foreach ($pdo->query('SELECT u.id,u.full_name,u.username,u.email FROM users u INNER JOIN roles r ON r.id=u.role_id WHERE r.name="STUDENT" AND u.active=1 AND u.email IS NOT NULL AND u.email<>"" ORDER BY u.full_name') as $row) {
    $students[$row['id']] = $row['full_name'].' — '.$row['username'].' ('.$row['email'].')';
}
$emails = $pdo->query('SELECT q.*,n.title,u.full_name FROM email_outbox q INNER JOIN notifications n ON n.id=q.notification_id INNER JOIN users u ON u.id=n.user_id ORDER BY q.id DESC LIMIT 50')->fetchAll();
$pageTitle = 'Email Notifications';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading"><h1>Email notifications</h1></div>
<?php if($error): ?><div class="alert danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<section class="panel">
    <h2>Delivery status</h2>
    <p>Sender: <strong><?= e($config['from_email'] ?: 'Not configured') ?></strong></p>
    <div class="alert <?= $problem === null ? 'success':'warning' ?>"><?= e($problem ?? 'Sender settings are complete. Send a test email to check delivery.') ?></div>
    <p>Pending: <?= $counts['PENDING'] ?> · Sending: <?= $counts['PROCESSING'] ?> · Retrying: <?= $counts['RETRY'] ?> · Sent: <?= $counts['SENT'] ?> · Failed: <?= $counts['FAILED'] ?></p>
    <p>Clearance continues when email delivery is unavailable. Students can always read their notifications inside the system.</p>
    <form method="post">
        <?php csrf_field(); ?><input type="hidden" name="action" value="test">
        <?php form_fields(['student_user_id'=>['label'=>'Student to receive a test email','type'=>'select','required'=>true,'options'=>[''=>'Select a student']+$students]],$errors); ?>
        <button class="btn primary" type="submit" <?= $problem !== null ? 'disabled':'' ?>>Send test email</button>
    </form>
    <form method="post" style="margin-top:16px">
        <?php csrf_field(); ?><input type="hidden" name="action" value="process">
        <button class="btn secondary" type="submit" <?= $problem !== null ? 'disabled':'' ?>>Send pending emails</button>
    </form>
</section>
<section class="panel" style="margin-top:20px">
    <h2>Recent delivery history</h2>
    <?php if(!$emails): ?><p>No emails queued yet.</p><?php endif; ?>
    <?php foreach($emails as $email): ?>
        <article style="padding:16px 0;border-bottom:1px solid #ddd">
            <strong><?= e($email['title']) ?></strong>
            <p><?= e($email['full_name']) ?> · <?= e($email['recipient']) ?></p>
            <p>Status: <?= e($email['status']) ?> · Attempts: <?= (int)$email['attempts'] ?> · Created: <?= e($email['created_at']) ?></p>
            <?php if($email['sent_at']): ?><p>Accepted by SMTP: <?= e($email['sent_at']) ?></p><?php endif; ?>
            <?php if($email['last_error']): ?><p class="muted"><?= e($email['last_error']) ?></p><?php endif; ?>
            <?php if($email['status']==='FAILED'): ?>
                <form method="post"><?php csrf_field(); ?><input type="hidden" name="action" value="retry"><input type="hidden" name="email_id" value="<?= (int)$email['id'] ?>"><button class="btn secondary" type="submit">Retry email</button></form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
