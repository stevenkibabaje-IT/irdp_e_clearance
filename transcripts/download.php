<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('STUDENT', 'ADMIN');
require_once __DIR__ . '/../includes/simple_pdf.php';

$studentId = current_user()['role'] === 'STUDENT'
    ? (int)current_user()['student_id']
    : request_record_id($_GET['student'] ?? null, 'student');
if (!transcript_available($pdo, $studentId)) {
    http_response_code(409);
    exit('Complete the required clearance process to access your transcript.');
}
try {
    $doc = ensure_transcript($pdo, $studentId, (int)current_user()['id']);
} catch (RuntimeException $e) {
    http_response_code(409);
    exit(user_safe_error($e));
}
if ($doc['status'] !== 'VALID') {
    http_response_code(409);
    exit('This transcript has been revoked. Contact the administrator.');
}
if (!hash_equals($doc['snapshot_sha256'], hash('sha256', $doc['snapshot_json']))) {
    http_response_code(409);
    exit('Transcript snapshot could not be verified. Contact the administrator.');
}
$doc['snapshot'] = json_decode($doc['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
$doc['student_id'] = $studentId;
$doc['verification_url'] = rtrim(APPLICATION_ORIGIN, '/') . '/transcripts/verify.php?token=' . $doc['verification_token'];
academic_audit($pdo, 'TRANSCRIPT_DOWNLOADED', (int)current_user()['id'], (int)$doc['student_id'], (int)$doc['id'], null, ['document' => $doc['document_number']], '', (int)$doc['clearance_request_id']);
pdf_transcript($doc, 'IRDP-Clearance-Transcript-' . str_replace('/', '-', $doc['document_number']) . '.pdf');
