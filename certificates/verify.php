<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$result = null;
$error = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();

        $certificateNumber = text_input($_POST, 'certificate_number', 80);
        $verificationCode = text_input($_POST, 'verification_code', 10);
        if (!preg_match('/^IRDP-CLR-[0-9]{4}-[0-9]{6,10}$/D', $certificateNumber)) {
            throw new ValidationException(['certificate_number'=>'Enter a valid IRDP clearance certificate number.']);
        }
        if (!preg_match('/^[A-F0-9]{10}$/D', $verificationCode)) {
            throw new ValidationException(['verification_code'=>'Enter the ten-character verification code.']);
        }

        $stmt = $pdo->prepare(
            'SELECT
                c.*,
                cr.status,
                u.full_name,
                s.registration_number,
                s.programme,
                d.name AS department
             FROM certificates c
             INNER JOIN clearance_requests cr ON cr.id = c.clearance_request_id
             INNER JOIN students s ON s.id = cr.student_id
             INNER JOIN users u ON u.id = s.user_id
             LEFT JOIN departments d ON d.id = s.department_id
             WHERE c.certificate_number = ?
               AND c.verification_code = ?
             LIMIT 1'
        );
        $stmt->execute([$certificateNumber, $verificationCode]);
        $result = $stmt->fetch();

        if (!$result || $result['status'] !== 'COMPLETED' || !certificate_release_allowed($pdo, (int)$result['clearance_request_id'])) {
            throw new RuntimeException('Certificate could not be verified. Check the certificate number and verification code.');
        }
    } catch (RuntimeException $e) {
        $result = null;
        $error = page_error($e, $errors);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificate Verification | IRDP</title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="simple-page">
    <section class="simple-card">
        <img class="small-logo" src="<?= e(url('assets/img/irdp-logo.jpg')) ?>" alt="IRDP logo">
        <h2>Certificate Verification</h2>
        <p class="muted">Enter the certificate number and verification code.</p>

        <?php if ($error !== ''): ?>
            <div class="alert danger"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($result): ?>
            <div class="alert success">
                <strong>Certificate verified.</strong><br><br>
                Student: <?= e($result['full_name']) ?><br>
                Registration: <?= e($result['registration_number']) ?><br>
                Programme: <?= e($result['programme'] ?: '—') ?><br>
                Issued: <?= e($result['issued_at']) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <label for="certificate_number">Certificate Number</label>
            <input id="certificate_number" name="certificate_number" placeholder="IRDP-CLR-2026-000001" maxlength="80" value="<?= old_value('certificate_number') ?>" required>
            <?php form_error('certificate_number', $errors); ?>

            <label for="verification_code">Verification Code</label>
            <input id="verification_code" name="verification_code" placeholder="A1B2C3D4E5" maxlength="10" pattern="[A-F0-9]{10}" value="<?= old_value('verification_code') ?>" required>
            <?php form_error('verification_code', $errors); ?>

            <button class="btn primary full" type="submit">Verify Certificate</button>
        </form>

        <p style="margin:16px 0 0; text-align:center;">
            <a href="<?= e(url('auth/login.php')) ?>">Back to Login</a>
        </p>
    </section>
</body>
</html>
