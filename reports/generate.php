<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('ADMIN');
require_once __DIR__ . '/../includes/simple_pdf.php';

$type = (string) ($_GET['type'] ?? 'weekly');
if (!in_array($type, ['weekly','monthly','custom'], true)) { http_response_code(400); exit('Invalid report type.'); }
$from = (string) ($_GET['from'] ?? date('Y-m-d', strtotime('-6 days')));
$to = (string) ($_GET['to'] ?? date('Y-m-d'));

if ($type === 'weekly') {
    $from = date('Y-m-d', strtotime('-6 days'));
    $to = date('Y-m-d');
}

if ($type === 'monthly') {
    $from = date('Y-m-01');
    $to = date('Y-m-t');
}

if ($type === 'custom' && (!isset($_GET['from'], $_GET['to']))) {
    http_response_code(400);
    exit('Select both dates for a custom report.');
}

$validDate = static fn (string $date): bool => valid_date($date);

if (!$validDate($from) || !$validDate($to) || $from > $to) {
    http_response_code(400);
    exit('Invalid reporting date range.');
}

$summaryStmt = $pdo->prepare(
    'SELECT
        COUNT(*) AS total,
        SUM(status = "COMPLETED") AS completed,
        SUM(status = "IN_PROGRESS") AS in_progress,
        SUM(status = "PAUSED") AS paused,
        SUM(status = "NOT_STARTED") AS not_started,
        SUM(status = "CANCELLED") AS cancelled
     FROM clearance_requests
     WHERE DATE(COALESCE(started_at, created_at)) BETWEEN ? AND ?'
);
$summaryStmt->execute([$from, $to]);
$summary = $summaryStmt->fetch();

$officeStmt = $pdo->prepare(
    'SELECT
        o.name,
        COALESCE(SUM(CASE WHEN sa.action = "APPROVED" THEN 1 ELSE 0 END), 0) AS approved,
        COALESCE(SUM(CASE WHEN sa.action = "REJECTED" THEN 1 ELSE 0 END), 0) AS rejected,
        COUNT(sa.id) AS actions
     FROM offices o
     LEFT JOIN clearance_stages cs ON cs.office_id = o.id
     LEFT JOIN stage_actions sa ON sa.stage_id = cs.id
        AND DATE(sa.created_at) BETWEEN ? AND ?
     GROUP BY o.id
     ORDER BY o.id'
);
$officeStmt->execute([$from, $to]);
$officeRows = $officeStmt->fetchAll();

$studentStmt = $pdo->prepare(
    'SELECT
        s.registration_number,
        u.full_name,
        cr.academic_year,
        cr.status,
        cr.started_at,
        cr.completed_at,
        (
            SELECT ws.title
            FROM clearance_stages cs
            INNER JOIN workflow_steps ws ON ws.id = cs.workflow_step_id
            WHERE cs.clearance_request_id = cr.id
              AND cs.status IN ("PENDING", "REJECTED", "IN_REVIEW")
            ORDER BY ws.step_number
            LIMIT 1
        ) AS current_stage
     FROM clearance_requests cr
     INNER JOIN students s ON s.id = cr.student_id
     INNER JOIN users u ON u.id = s.user_id
     WHERE DATE(COALESCE(cr.started_at, cr.created_at)) BETWEEN ? AND ?
     ORDER BY cr.id'
);
$studentStmt->execute([$from, $to]);
$students = $studentStmt->fetchAll();

$certificateStmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM certificates c
     INNER JOIN clearance_requests cr ON cr.id = c.clearance_request_id
     WHERE DATE(c.issued_at) BETWEEN ? AND ?'
);
$certificateStmt->execute([$from, $to]);
$certificateCount = (int) $certificateStmt->fetchColumn();

$lines = [];
$lines[] = 'Reporting period: ' . $from . ' to ' . $to;
$lines[] = 'Generated: ' . date('Y-m-d H:i:s');
$lines[] = '';
$lines[] = 'SUMMARY';
$lines[] = 'Total requests: ' . (int) ($summary['total'] ?? 0);
$lines[] = 'Completed: ' . (int) ($summary['completed'] ?? 0);
$lines[] = 'In progress: ' . (int) ($summary['in_progress'] ?? 0);
$lines[] = 'Paused: ' . (int) ($summary['paused'] ?? 0);
$lines[] = 'Not started: ' . (int) ($summary['not_started'] ?? 0);
$lines[] = 'Cancelled: ' . (int) ($summary['cancelled'] ?? 0);
$lines[] = 'Certificates issued: ' . $certificateCount;
$lines[] = '';
$lines[] = 'CLEARANCE ACTIONS BY OFFICE';

foreach ($officeRows as $row) {
    $lines[] = $row['name'] .
        ' | Approved: ' . (int) $row['approved'] .
        ' | Rejected: ' . (int) $row['rejected'] .
        ' | Actions: ' . (int) $row['actions'];
}

$lines[] = '';
$lines[] = 'STUDENT CLEARANCE LIST';

foreach ($students as $student) {
    $lines[] = $student['registration_number'] .
        ' | ' . $student['full_name'] .
        ' | Year: ' . $student['academic_year'] .
        ' | ' . $student['status'] .
        ' | Started: ' . ($student['started_at'] ?: '-') .
        ' | Completed: ' . ($student['completed_at'] ?: '-') .
        ' | Current: ' . ($student['current_stage'] ?: 'Completed');
}

$filename = 'IRDP-Clearance-Report-' . $from . '-to-' . $to . '.pdf';
pdf_report('IRDP STUDENT CLEARANCE REPORT', $lines, $filename);
