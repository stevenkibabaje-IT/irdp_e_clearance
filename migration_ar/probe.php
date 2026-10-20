<?php
declare(strict_types=1);
require __DIR__.'/../storage/private/ar-integration/runtime.php';
// Test-only endpoint: localhost AND a random private capability. No production session.
if(!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)||!hash_equals(ar_config()['probe_key'],$_SERVER['HTTP_X_AR_PROBE']??'')){http_response_code(404);exit;}
header('Content-Type: application/json');header('Cache-Control: no-store');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
$input=json_decode(file_get_contents('php://input'),true);
if(!is_array($input)||!preg_match('/^test-[a-f0-9]{16}$/D',$input['bucket']??'')){http_response_code(400);exit;}
$allowed=ar_limit(ar_db(),$input['bucket'],12,300);
http_response_code($allowed?200:429);
echo ar_json(['allowed'=>$allowed,'sapi'=>PHP_SAPI,'server'=>$_SERVER['SERVER_SOFTWARE']??'']);
