<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/adapter.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') {http_response_code(405);exit;}
ar_ui_csrf();
$_SESSION=[];
session_regenerate_id(true);
redirect('auth/login.php');
