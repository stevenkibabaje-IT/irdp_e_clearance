<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/mail.php';

/** Queue with the clearance transaction; a rollback removes the email too. */
function queue_notification_email(PDO $pdo, int $notificationId): void
{
    $pdo->prepare('INSERT IGNORE INTO email_outbox(notification_id,recipient)
        SELECT n.id,u.email FROM notifications n
        INNER JOIN users u ON u.id=n.user_id INNER JOIN roles r ON r.id=u.role_id
        WHERE n.id=? AND r.name="STUDENT" AND u.active=1 AND u.email IS NOT NULL AND u.email<>""')
        ->execute([$notificationId]);
}

/** Translate built-in legacy templates still waiting in the outbox; preserve user-entered remarks. */
function notification_email_message(string $message): string
{
    $message=preg_replace_callback('/Ofisi ya ([^\r\n]+?) imeidhinisha hatua hii ya clearance yako\./u',static fn($m)=>'The '.$m[1].' office has approved this clearance stage.',$message);
    $message=preg_replace_callback('/Ofisi ya ([^\r\n]+?) imekataa hatua hii\. Rekebisha yaliyoelekezwa kabla ya kutuma tena\./u',static fn($m)=>'The '.$m[1].' office has rejected this stage. Complete the requested corrections before resubmitting.',$message);
    $message=preg_replace_callback('/Ofisi ya ([^\r\n]+?) imeidhinisha hatua ya mwisho iliyobaki\. Ofisi zote 11 zimeidhinisha clearance yako\./u',static fn($m)=>'The '.$m[1].' office has approved the final remaining stage. All 11 offices have approved your clearance.',$message);
    $message=preg_replace_callback('/Receipt ya malipo kwa control number ([0-9]+) imewasilishwa\. Finance itahakiki malipo kabla ya kuidhinisha hatua hii\./u',static fn($m)=>'A payment receipt for control number '.$m[1].' has been submitted. Finance will verify payment before approving this stage.',$message);
    $message=preg_replace_callback('/Marekebisho na ushahidi vimewasilishwa kwa ofisi hii kwa review cycle ([0-9]+)\. Ofisi itahakiki kabla ya kutoa uamuzi mpya\./u',static fn($m)=>'Corrections and evidence have been submitted to this office for review cycle '.$m[1].'. The office will review the submission before making a new decision.',$message);
    $message=strtr($message,[
        'Ofisi: '=>'Office: ', 'Uamuzi: '=>'Decision: ', 'Hali: '=>'Status: ',
        'Maelezo ya ofisi: '=>'Office comments: ', 'Maelekezo: '=>'Instructions: ',
        'Hatua inayofuata: '=>'Next step: ', 'Kiasi cha kulipa kwa malazi: '=>'Accommodation amount due: ',
        'Deni jingine la malazi: '=>'Other accommodation debt: ', 'Deni: '=>'Outstanding debt: ',
        'Vifaa vinavyokosekana: '=>'Missing items: ',
        'Finance imeomba malipo ya deni jingine kabla ya kuidhinisha hatua yake.'=>'Finance requires payment of another outstanding debt before approving its stage.',
        'Finance imethibitisha huna deni jingine.'=>'Finance has confirmed that you have no other outstanding debt.',
        'Receipt yako imehakikiwa na malipo yameidhinishwa.'=>'Your receipt has been verified and payment has been approved.',
        'Fungua My Clearance, rekebisha tatizo, kisha tumia Submit evidence to office kutuma maelezo na ushahidi kwa ofisi hii.'=>'Open My Clearance, complete the corrections, then use Submit evidence to office to send your explanation and evidence to this office.',
        'Lipa kwa control number iliyotolewa, kisha pakia receipt kwenye My Clearance ili Finance ihakiki.'=>'Pay using the issued control number, then upload the receipt in My Clearance for Finance to verify.',
        'Fungua Dashboard na uchague Download Clearance Transcript.'=>'Open Dashboard and select Download Clearance Transcript.',
        'Fungua My Clearance kufuatilia maamuzi ya ofisi zilizobaki.'=>'Open My Clearance to follow decisions from the remaining offices.',
    ]);
    return str_replace(['Download Clearance Transcript','clearance transcript','Clearance Transcript'],['Download Student Clearance Certificate','Student Clearance Certificate','Student Clearance Certificate'],$message);
}

/** Email decisions use English summaries; complete original remarks remain in My Clearance. */
function notification_email_body(string $title, string $message): string
{
    $english = notification_email_message($message);
    $summaries = [
        'Clearance stage approved' => "Decision: APPROVED\nAn office has approved this clearance stage. Follow the decisions from the remaining offices in My Clearance.",
        'Clearance stage rejected' => "Decision: REJECTED\nAn office has rejected this clearance stage. Read the office comments and corrective instructions in My Clearance, complete the corrections, then select Submit evidence to office.",
        'Finance payment required' => "Decision: PAYMENT REQUIRED\nFinance requires payment of an outstanding debt. Open My Clearance to view the amount, control number and payment instructions, then upload your receipt for verification.",
        'Clearance completed' => "Status: COMPLETED\nAll 11 offices have approved your clearance. Open Dashboard and select Download Student Clearance Certificate.",
    ];
    foreach ($summaries as $event => $summary) {
        if ($title !== $event && !str_starts_with($title, $event . ' — ')) { continue; }
        // Context precedes the first blank line; officer-entered text is never copied.
        $context = [];
        $firstBlock = preg_split('/\R\h*\R/u', $english, 2)[0];
        foreach (explode("\n", $firstBlock) as $line) {
            $line = trim($line);
            if (preg_match('/^(?:Office: [^\r\n]+|Stage: (?:[1-9]|10|11) \/ 11|Registration number: [A-Za-z0-9\/-]+|Academic cycle: [0-9]{4}\/[0-9]{4})$/D', $line)) {
                $context[] = $line;
            }
        }
        return ($context ? implode("\n", $context) . "\n\n" : '') . $summary
            . "\n\nView the complete office response and clearance details in My Clearance:\n"
            . rtrim(APPLICATION_ORIGIN, '/') . '/student/status.php';
    }
    return $english;
}

function send_notification_email(array $row, array $config): void
{
    require_once __DIR__ . '/vendor/phpmailer/src/Exception.php';
    require_once __DIR__ . '/vendor/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/vendor/phpmailer/src/SMTP.php';
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    // Classify failures without printing or storing SMTP transcripts or credentials.
    $failureCode = 0;
    $mail->SMTPDebug = 3;
    $mail->Debugoutput = static function (string $line, int $level) use (&$failureCode): void {
        if (str_contains(strtolower($line), 'certificate verify failed')) { $failureCode = 495; }
        elseif (str_contains($line, 'Could not authenticate')) { $failureCode = 535; }
        elseif (preg_match('/SERVER -> CLIENT: (4[0-9]{2}|5[0-9]{2})\b/', $line, $match)) { $failureCode = (int)$match[1]; }
        elseif ($failureCode === 0 && (str_contains($line, 'Failed to connect') || str_contains($line, 'Connection failed'))) { $failureCode = 503; }
    };
    $mail->isSMTP();
    $mail->Host = $config['host'];
    $mail->Port = (int)$config['port'];
    $mail->SMTPAuth = $config['username'] !== '';
    $mail->Username = $config['username'];
    $mail->Password = $config['host'] === 'smtp.gmail.com' ? str_replace(' ', '', $config['password']) : $config['password'];
    $mail->SMTPSecure = $config['encryption'] === 'none' ? '' : $config['encryption'];
    $mail->SMTPAutoTLS = $config['encryption'] !== 'none';
    $mail->Timeout = max(1, min(30, (int)$config['timeout']));
    $mail->getSMTPInstance()->Timelimit = $mail->Timeout;
    $mail->CharSet = 'UTF-8';
    $mail->addCustomHeader('Content-Language', 'en');
    $mail->setFrom($config['from_email'], $config['from_name']);
    $mail->addAddress($row['recipient'], $row['full_name']);
    $mail->Subject = 'IRDP e-Clearance: ' . str_replace(["\r", "\n"], ' ', $row['title']);
    $mail->MessageID = '<irdp-notification-' . $row['notification_id'] . '-' . hash('sha256', APPLICATION_ORIGIN) . '@' . substr(strrchr($config['from_email'], '@'), 1) . '>';
    $mail->Body = "Hello " . $row['full_name'] . ",\n\n" . notification_email_body($row['title'], $row['message'])
        . "\n\nSign in to view more details:\n" . rtrim(APPLICATION_ORIGIN, '/')
        . "/student/notifications.php\n\nIRDP e-Clearance\nThis is an automated message from the system.";
    try { $mail->send(); }
    catch (\PHPMailer\PHPMailer\Exception $e) {
        $error = $mail->getSMTPInstance()->getError();
        throw new RuntimeException('SMTP delivery failed.', $failureCode ?: (int)($error['smtp_code'] ?? 0));
    }
}

/** One worker at a time; SMTP failures never enter clearance request transactions. */
function process_notification_emails(PDO $pdo, int $limit = 20, ?array $config = null, ?callable $sender = null, ?int $notificationId = null): array
{
    if ($pdo->inTransaction()) { throw new LogicException('Send emails only after committing application changes.'); }
    $config ??= notification_mail_config();
    $result = ['sent'=>0, 'retry'=>0, 'failed'=>0, 'cancelled'=>0, 'problem'=>notification_mail_problem($config)];
    if ($result['problem'] !== null) { return $result; }
    $lockName = 'irdp_email_' . substr(hash('sha256', (string)$pdo->query('SELECT DATABASE()')->fetchColumn()), 0, 32);
    $lock = $pdo->prepare('SELECT GET_LOCK(?,0)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) { $result['problem']='Another email worker is running.'; return $result; }
    try {
        $pdo->exec('UPDATE email_outbox SET status=IF(attempts>=5,"FAILED","RETRY"),available_at=NOW(),last_error="Previous email worker stopped before recording delivery." WHERE status="PROCESSING" AND last_attempt_at<DATE_SUB(NOW(),INTERVAL 15 MINUTE)');
        $rows = $pdo->query('SELECT q.*,n.title,n.message,u.full_name,u.email AS current_email,u.active,r.name AS role_name
            FROM email_outbox q INNER JOIN notifications n ON n.id=q.notification_id
            INNER JOIN users u ON u.id=n.user_id INNER JOIN roles r ON r.id=u.role_id
            WHERE q.status IN ("PENDING","RETRY") AND q.available_at<=NOW() AND q.attempts<5
            ' . ($notificationId !== null ? 'AND q.notification_id=' . $notificationId : '') . '
            ORDER BY q.id LIMIT ' . max(1, min(100, $limit)))->fetchAll();
        foreach ($rows as $row) {
            if (!(int)$row['active'] || $row['role_name'] !== 'STUDENT'
                || strcasecmp($row['recipient'], (string)$row['current_email']) !== 0
                || !filter_var($row['recipient'], FILTER_VALIDATE_EMAIL)) {
                $pdo->prepare('UPDATE email_outbox SET status="CANCELLED",last_error="Recipient account or email changed." WHERE id=?')->execute([$row['id']]);
                $result['cancelled']++;
                continue;
            }
            $pdo->prepare('UPDATE email_outbox SET status="PROCESSING",attempts=attempts+1,last_attempt_at=NOW(),last_error=NULL WHERE id=?')->execute([$row['id']]);
            try {
                ($sender ?? 'send_notification_email')($row, $config);
                $pdo->prepare('UPDATE email_outbox SET status="SENT",sent_at=NOW(),last_error=NULL WHERE id=?')->execute([$row['id']]);
                $result['sent']++;
            } catch (Throwable $e) {
                $attempts = (int)$row['attempts'] + 1;
                $status = $attempts >= 5 ? 'FAILED' : 'RETRY';
                $delay = min(3600, 60 * (2 ** ($attempts - 1)));
                $pdo->prepare('UPDATE email_outbox SET status=?,available_at=DATE_ADD(NOW(),INTERVAL ' . $delay . ' SECOND),last_error=? WHERE id=?')
                    ->execute([$status, match ((int)$e->getCode()) {
                        530,534,535 => 'SMTP authentication failed. Check the sender email and App Password.',
                        495 => 'SMTP certificate verification failed. Configure a trusted CA bundle for PHP.',
                        503 => 'SMTP connection failed. Check the network, host and port.',
                        default => 'SMTP delivery failed. Check sender credentials, connection and recipient.',
                    }, $row['id']]);
                $result[strtolower($status)]++;
            }
        }
    } finally {
        $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
    }
    return $result;
}

function update_student_notification_email(PDO $pdo, int $userId, array $input): void
{
    $email = email_value(text_input($input, 'email', 160));
    $password = existing_password_input($input['current_password'] ?? null, 'current_password');
    $pdo->beginTransaction();
    try {
        lock_account_creation($pdo);
        $s = $pdo->prepare('SELECT u.* FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND r.name="STUDENT" AND u.active=1 FOR UPDATE');
        $s->execute([$userId]);
        $user = $s->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new ValidationException(['current_password'=>'Enter your correct current password.']);
        }
        if (strcasecmp((string)$user['email'], (string)$email) !== 0) {
            unique_account_email($pdo, $email);
            invalidate_student_password_otps($pdo,$userId);
            $pdo->prepare('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')->execute([$userId]);
            $pdo->prepare('UPDATE users SET email=?,email_verified_at=NULL WHERE id=?')->execute([$email, $userId]);
            $pdo->prepare('UPDATE students SET email=? WHERE user_id=?')->execute([$email, $userId]);
            $pdo->prepare('UPDATE email_outbox q JOIN notifications n ON n.id=q.notification_id SET q.status="CANCELLED",q.last_error="Student updated their email address." WHERE n.user_id=? AND q.status IN ("PENDING","RETRY","FAILED")')->execute([$userId]);
            audit($pdo, 'NOTIFICATION_EMAIL_UPDATED', $userId);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}
