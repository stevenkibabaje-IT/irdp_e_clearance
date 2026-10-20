<?php
declare(strict_types=1);

/** Serve stored profile pictures through the application access checks. */
require_once __DIR__.'/../includes/bootstrap.php';require_login();
try{$id=positive_id($_GET['student']??null,'student');$student=get_student($pdo,$id);if(!$student){throw new RuntimeException('Student not found.');}$user=current_user();$allowed=(int)$student['user_id']===(int)$user['id'] || $user['role']==='ADMIN';
if(!$allowed){$s=$pdo->prepare('SELECT cs.id FROM clearance_stages cs INNER JOIN clearance_requests cr ON cr.id=cs.clearance_request_id WHERE cr.student_id=? AND cs.assigned_officer_id=?');$s->execute([$id,$user['id']]);foreach($s->fetchAll() as $stage){if(evidence_access($pdo,(int)$stage['id'],(int)$user['id'])){$allowed=true;break;}}}
if(!$allowed){http_response_code(403);exit('Access denied.');}
session_write_close();
header('Cache-Control: private, no-cache');header('X-Content-Type-Options: nosniff');
$hasPicture=$student['profile_file'] && is_file(private_path('profiles',$student['profile_file']));
$etag='"'.hash('sha256',$id.'|'.($hasPicture?$student['profile_file']:'default')).'"';
header('ETag: '.$etag);
if(trim((string)($_SERVER['HTTP_IF_NONE_MATCH']??''))===$etag){http_response_code(304);exit;}
if($student['profile_file'] && is_file($path=private_path('profiles',$student['profile_file']))){header('Content-Type: image/jpeg');readfile($path);}else{header('Content-Type: image/svg+xml');echo '<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 256 256"><rect width="256" height="256" fill="#e7f1ec"/><circle cx="128" cy="90" r="43" fill="#54856b"/><path d="M42 235c0-65 38-97 86-97s86 32 86 97" fill="#54856b"/></svg>';}
}catch(Throwable $e){http_response_code(404);exit('Profile unavailable.');}
