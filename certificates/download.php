<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('ADMIN', 'STUDENT');
require_once __DIR__ . '/../includes/simple_pdf.php';

$requestId = request_record_id($_GET['id'] ?? null);

$stmt = $pdo->prepare(
    'SELECT
        cr.status,
        cr.id AS request_id,
        s.id AS student_id_real,
        u.full_name,
        s.registration_number,
        s.programme,
        d.name AS department,
        cr.academic_year,
        cr.completed_at,
        c.certificate_number,
        c.verification_code,
        c.issued_at
     FROM clearance_requests cr
     INNER JOIN students s ON s.id = cr.student_id
     INNER JOIN users u ON u.id = s.user_id
     LEFT JOIN departments d ON d.id = s.department_id
     INNER JOIN certificates c ON c.clearance_request_id = cr.id
     WHERE cr.id = ?
     LIMIT 1'
);
$stmt->execute([$requestId]);
$data = $stmt->fetch();

if (!$data) {
    http_response_code(404);
    exit('Certificate not found.');
}

if (current_user()['role'] === 'STUDENT' && (int) current_user()['student_id'] !== (int) $data['student_id_real']) {
    http_response_code(403);
    exit('Access denied.');
}

if ($data['status'] !== 'COMPLETED' || !certificate_release_allowed($pdo, $requestId)) {
    http_response_code(409);
    exit('Certificate is not available yet.');
}

pdf_certificate($data, 'IRDP-Certificate-' . $data['certificate_number'] . '.pdf');
