<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/adapter.php';
header('Content-Type: application/json; charset=utf-8');
if (!current_user()) {http_response_code(401);echo ar_json(['authenticated'=>false,'reason'=>'expired']);exit;}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try { ar_ui_csrf(); } catch (Throwable) {http_response_code(419);echo ar_json(['authenticated'=>true,'reason'=>'csrf']);exit;}
    if (($_POST['action']??'')!=='continue') {http_response_code(400);echo ar_json(['authenticated'=>true,'reason'=>'invalid']);exit;}
    $_SESSION['ar_last']=time();
} elseif ($_SERVER['REQUEST_METHOD']!=='GET') {http_response_code(405);exit;}
echo ar_json(['authenticated'=>true]+authenticated_session_state());
