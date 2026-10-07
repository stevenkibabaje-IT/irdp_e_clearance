<?php
declare(strict_types=1);

define('SESSION_API_REQUEST', true);
require_once __DIR__ . '/../includes/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    session_json_response(405, ['error' => 'Use GET to check or POST to continue the session.']);
}
if ($method === 'POST') {
    if (($_POST['action'] ?? '') !== 'continue') {
        session_json_response(422, ['error' => 'Choose a valid session action.']);
    }
    // Bootstrap already verified the deadline, CSRF token and account validity.
    touch_session_activity();
}
session_json_response(200, authenticated_session_state());
