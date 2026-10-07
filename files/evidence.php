<?php
declare(strict_types=1);

/** Authorize access before serving a private clearance evidence file. */
require_once __DIR__.'/../includes/bootstrap.php';require_login();
try{$id=positive_id($_GET['id']??null);$s=$pdo->prepare('SELECT * FROM stage_evidence WHERE id=?');$s->execute([$id]);$file=$s->fetch();if(!$file || !evidence_access($pdo,(int)$file['stage_id'],(int)current_user()['id'])){http_response_code(403);exit('Access denied.');}$path=private_path('evidence',$file['stored_name']);if(!is_file($path)){throw new RuntimeException('File unavailable.');}
session_write_close();
header('Content-Type: '.$file['mime_type']);header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($file['original_name']));header('Content-Length: '.filesize($path));readfile($path);
}catch(Throwable $e){http_response_code(404);exit('Evidence file unavailable.');}
