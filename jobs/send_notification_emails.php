<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Command line only.'); }
require_once __DIR__ . '/../includes/installer.php';
require_once __DIR__ . '/../includes/functions.php';
date_default_timezone_set('Africa/Dar_es_Salaam');
try {
    $result = process_notification_emails(application_database());
    echo json_encode($result, JSON_THROW_ON_ERROR), "\n";
    exit($result['problem'] !== null || $result['failed'] > 0 || $result['retry'] > 0 ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, "Email worker failed. Check database availability and SMTP configuration.\n");
    exit(1);
}
