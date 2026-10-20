<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';require_login();
try {
    $id=positive_id($_GET['id']??null);$s=$pdo->prepare('SELECT * FROM clearance_fee_receipts WHERE id=?');$s->execute([$id]);$file=$s->fetch();
    $fee=$file?clearance_fee_record($pdo,(int)$file['payment_id']):null;
    if (!$fee || !clearance_fee_access($pdo,$fee,(int)current_user()['id'])) { http_response_code(403);exit('Access denied.'); }
    $path=private_path('evidence',$file['stored_name']);if(!is_file($path)){throw new RuntimeException('Receipt unavailable.');}
    session_write_close();header('Content-Type: '.$file['mime_type']);header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');
    header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($file['original_name']));header('Content-Length: '.filesize($path));readfile($path);
} catch(Throwable $e) { http_response_code(404);exit('Payment receipt unavailable.'); }
