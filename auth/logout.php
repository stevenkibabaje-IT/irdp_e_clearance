<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Use the Logout button.'); }
verify_csrf();

logout_user();
header('Location: ' . url('auth/login.php'));
exit;
