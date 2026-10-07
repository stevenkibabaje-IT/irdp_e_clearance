<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('ADMIN', 'STUDENT');

$requestId = request_record_id($_GET['id'] ?? null);

$stmt = $pdo->prepare(
    'SELECT
        cr.*,
        s.id AS student_id,
        u.full_name,
        s.registration_number,
        s.programme,
        s.year_of_study,
        d.name AS department
     FROM clearance_requests cr
     INNER JOIN students s ON s.id = cr.student_id
     INNER JOIN users u ON u.id = s.user_id
     LEFT JOIN departments d ON d.id = s.department_id
     WHERE cr.id = ?
     LIMIT 1'
);
$stmt->execute([$requestId]);
$request = $stmt->fetch();

if (!$request) {
    http_response_code(404);
    exit('Clearance request not found.');
}

$role = current_user()['role'];
if ($role === 'STUDENT' && (int) current_user()['student_id'] !== (int) $request['student_id']) {
    http_response_code(403);
    exit('Access denied.');
}

if ($request['status'] !== 'COMPLETED' || !certificate_release_allowed($pdo, $requestId)) {
    http_response_code(409);
    exit('The certificate is available only after all 11 stages are approved.');
}

$certificate = $pdo->prepare('SELECT * FROM certificates WHERE clearance_request_id = ? LIMIT 1');
$certificate->execute([$requestId]);
$certificate = $certificate->fetch();

if (!$certificate) {
    $certificate = issue_certificate($pdo, $requestId);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($certificate['certificate_number']) ?> | IRDP Certificate</title>
    <style>
        @page { size: A4 landscape; margin: 0; }
        body { margin: 0; background: #eef5f1; font-family: Georgia, "Times New Roman", serif; color: #173626; }
        .toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 8px; padding: 15px; text-align: center; background: #075d39; }
        .toolbar a, .toolbar button { min-height: 44px; max-width: 100%; box-sizing: border-box; font: 14px Arial, sans-serif; }
        .toolbar a, .toolbar button { display: inline-block; margin: 0 4px; padding: 10px 16px; border: 0; border-radius: 7px; color: #fff; background: #0b8a4f; text-decoration: none; cursor: pointer; }
        .toolbar .icon { width: 18px; height: 18px; vertical-align: -0.2em; margin-right: 6px; }
        .toolbar .gold { color: #1e2b12; background: #f2b51d; }
        .certificate { position: relative; width: 1120px; height: 790px; margin: 25px auto; padding: 62px 85px; box-sizing: border-box; overflow: hidden; border: 12px solid #075d39; background: #fff; text-align: center; box-shadow: 0 12px 40px rgba(0,0,0,.12); }
        .certificate::before { position: absolute; inset: 23px; border: 3px solid #f2b51d; content: ""; }
        .certificate::after { position: absolute; inset: 31px; border: 1px solid #0b8a4f; content: ""; }
        .content { position: relative; z-index: 1; }
        .logo { width: 96px; height: 96px; object-fit: cover; border-radius: 50%; }
        .institution { margin: 6px 0; color: #075d39; font-size: 15px; font-weight: bold; letter-spacing: 1.5px; }
        .title { margin: 12px 0 5px; color: #075d39; font-size: 34px; letter-spacing: 1.6px; }
        .subtitle { color: #647067; font-size: 16px; }
        .name { margin: 17px 0 7px; color: #06452c; font-size: 34px; font-weight: bold; }
        .line { margin: 8px auto; max-width: 780px; font-size: 17px; line-height: 1.6; }
        .meta { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 28px 60px 0; }
        .meta div { padding: 10px; border-top: 1px solid #d7dfda; font-size: 13px; }
        .meta strong { color: #075d39; }
        .signature { position: absolute; left: 100px; bottom: 64px; z-index: 2; width: 220px; font-size: 12px; }
        .verification { position: absolute; right: 100px; bottom: 55px; z-index: 2; width: 220px; padding: 10px; border: 1px solid #d7dfda; font-family: Arial, sans-serif; font-size: 11px; }
        .verification strong { display: block; color: #075d39; margin-bottom: 4px; }
        @media print { .toolbar { display: none; } .certificate { margin: 0; box-shadow: none; } }
        @media screen and (max-width: 1180px) {
            .certificate { width: calc(100% - 32px); height: auto; margin: 16px auto; padding: clamp(28px, 5vw, 62px) clamp(24px, 6vw, 85px); border-width: 8px; overflow-wrap: anywhere; }
            .certificate::before { inset: 12px; border-width: 2px; }
            .certificate::after { inset: 18px; }
            .title { font-size: clamp(23px, 3vw, 34px); }
            .name { font-size: clamp(24px, 3vw, 34px); }
            .meta { margin: 28px 0 0; grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .signature, .verification { position: relative; left: auto; right: auto; bottom: auto; max-width: 100%; margin: 28px auto 0; }
        }
        @media screen and (max-width: 600px) {
            .toolbar a, .toolbar button { flex: 1 1 100%; margin: 0; }
            .certificate { width: calc(100% - 24px); padding: 28px 24px; border-width: 6px; }
            .logo { width: 72px; height: 72px; }
            .institution { font-size: 12px; letter-spacing: 1px; }
            .title { letter-spacing: 1px; }
            .line { font-size: 15px; }
            .meta { grid-template-columns: minmax(0, 1fr); gap: 10px; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="gold" onclick="window.print()"><?= icon('printer') ?> Print Certificate</button>
        <a href="<?= e(url('certificates/download.php?id=' . $requestId)) ?>"><?= icon('download') ?> Download PDF</a>
        <a href="<?= e(url('certificates/verify.php')) ?>"><?= icon('shield') ?> Verify Certificate</a>
    </div>

    <section class="certificate">
        <div class="content">
            <img class="logo" src="<?= e(url('assets/img/irdp-logo.jpg')) ?>" alt="IRDP logo">
            <div class="institution">INSTITUTE OF RURAL DEVELOPMENT PLANNING</div>
            <h1 class="title">STUDENT CLEARANCE CERTIFICATE</h1>
            <div class="subtitle">This is to certify that</div>
            <div class="name"><?= e($request['full_name']) ?></div>
            <div class="line">
                Registration Number: <strong><?= e($request['registration_number']) ?></strong>
            </div>
            <div class="line">
                has successfully completed all required clearance stages and is hereby cleared by the Institute.
            </div>
            <div class="meta">
                <div><strong>Certificate No.</strong><br><?= e($certificate['certificate_number']) ?></div>
                <div><strong>Programme</strong><br><?= e($request['programme'] ?: '-') ?></div>
                <div><strong>Academic Year</strong><br><?= e($request['academic_year']) ?></div>
                <div><strong>Department</strong><br><?= e($request['department'] ?: '-') ?></div>
                <div><strong>Completed</strong><br><?= e($request['completed_at'] ?: '-') ?></div>
                <div><strong>Issued</strong><br><?= e($certificate['issued_at']) ?></div>
            </div>
        </div>

        <div class="signature">
            __________________________<br>
            Authorized Officer
        </div>

        <div class="verification">
            <strong>Verification Code</strong>
            <?= e($certificate['verification_code']) ?><br><br>
            Verify at the IRDP Certificate Verification page.
        </div>
    </section>
</body>
</html>
