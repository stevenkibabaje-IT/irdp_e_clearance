<?php
declare(strict_types=1);

/** Shared request startup: configure sessions, connect to the database and validate input. */

date_default_timezone_set('Africa/Dar_es_Salaam');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../config/application.php';
require_once __DIR__ . '/session_security.php';
start_application_session();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/installer.php';

try {
    $pdo = application_database();
} catch (Throwable $e) {
    http_response_code(500);
    exit(
        'IRDP Student Clearance System could not initialize the database. '
        . 'Make sure MySQL is running and check config/database.php.'
    );
}

require_once __DIR__ . '/functions.php';
// Expiry must be checked before CSRF validation, activity renewal or any form action.
enforce_session_security();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && !$_POST && !$_FILES) {
        http_response_code(413);
        exit('The upload exceeds the server request limit. Go back, choose a smaller file and try again.');
    }
    foreach ($_POST as $value) {
        if (!is_string($value)) { http_response_code(400); exit('Invalid form value. Refresh and submit single values.'); }
    }
    verify_csrf();
}
foreach ($_GET as $value) {
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x1F\x7F]/', $value)) { http_response_code(400); exit('Invalid request value.'); }
}
validate_session_account($pdo);
if (current_user()) {
    header('Cache-Control: private, no-store');
    // Background status checks observe expiry; only real activity renews idle time.
    if (!session_api_request()) {
        touch_session_activity();
    }
}
