<?php
declare(strict_types=1);

// The copied interface must never load the old installer or its 30-table database.
ini_set('display_errors','0');
require_once __DIR__.'/../../storage/private/ar-integration/application.php';
require_once __DIR__.'/icons.php';
require_once __DIR__.'/clearance_progress.php';
require_once __DIR__.'/forms_ar.php';
date_default_timezone_set('Africa/Dar_es_Salaam');
ini_set('display_errors', '0');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('IRDP_AR_ORIGINAL_UI');
session_save_path(__DIR__.'/../../storage/private/ar-integration/sessions');
session_set_cookie_params([
    'lifetime'=>0,
    'path'=>'/irdp_e_clearance/migration_original/',
    'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off',
    'httponly'=>true,
    'samesite'=>'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$pdo=ar_db();
$arUiUser=null;
if (isset($_SESSION['ar_uid'])) {
    $arUiUser=ar_row($pdo,'SELECT * FROM users WHERE id=? AND active=1',[(int)$_SESSION['ar_uid']]);
    if (!$arUiUser || (int)$arUiUser['auth_version']!==(int)($_SESSION['ar_version']??-1)
        || time()-(int)($_SESSION['ar_started']??0)>=28800
        || time()-(int)($_SESSION['ar_last']??0)>=1800) {
        $_SESSION=[];
        $arUiUser=null;
    } elseif (!str_ends_with($_SERVER['SCRIPT_NAME']??'','/auth/session.php')) { $_SESSION['ar_last']=time(); }
}
$_SESSION['ar_csrf']??=bin2hex(random_bytes(32));

function e(mixed $value): string { return htmlspecialchars((string)($value??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function url(string $path=''): string { return '/irdp_e_clearance/migration_original/'.ltrim($path,'/'); }
function csrf_token(): string { return $_SESSION['ar_csrf']; }
function current_user(): ?array {
    global $arUiUser;
    if (!$arUiUser) { return null; }
    return ['id'=>(int)$arUiUser['id'],'username'=>$arUiUser['username'],'full_name'=>$arUiUser['full_name'],
        'role'=>$arUiUser['role'],'student_id'=>$arUiUser['role']==='STUDENT'?(int)$arUiUser['id']:null];
}
function role_dashboard(string $role): string { return match($role) {
    'STUDENT'=>'student/dashboard.php', 'OFFICER','SUPERVISOR'=>'officer/dashboard.php',
    'ADMIN'=>'admin/dashboard.php', default=>'auth/login.php',
}; }
function redirect(string $path): never { header('Location: '.url($path),true,303); exit; }
function ar_ui_csrf(): void {
    if (!is_string($_POST['csrf_token']??null) || !hash_equals(csrf_token(),$_POST['csrf_token'])) {
        http_response_code(419); throw new DomainException('Your form session expired. Refresh and try again.');
    }
}
function ar_ui_require_role(string ...$roles): array {
    global $arUiUser;
    if (!$arUiUser) { redirect('auth/login.php'); }
    if ($arUiUser['force_password_change'] && !str_ends_with($_SERVER['SCRIPT_NAME']??'','/auth/change_password.php')) { redirect('auth/change_password.php'); }
    if (!in_array($arUiUser['role'],$roles,true)) { http_response_code(403); exit('Access denied.'); }
    return $arUiUser;
}
function ar_ui_sign_in(string $username,string $password): array {
    global $pdo,$arUiUser;
    $username=trim($username);
    if ($username==='' || $password==='') { throw new DomainException('Enter your username and password.'); }
    $verified=ar_login($pdo,$username,$password,$_SERVER['REMOTE_ADDR']??'unknown');
    if (!$verified) { throw new DomainException('Invalid username or password.'); }
    $actor=ar_row($pdo,'SELECT * FROM users WHERE id=? AND active=1',[$verified['id']]);
    if (!$actor) { throw new DomainException('Invalid username or password.'); }
    ar_transaction($pdo,fn()=>ai_audit($pdo,(int)$actor['id'],'LOGIN'));
    session_regenerate_id(true);
    $_SESSION=['ar_uid'=>(int)$actor['id'],'ar_version'=>(int)$actor['auth_version'],
        'ar_started'=>time(),'ar_last'=>time(),'ar_csrf'=>bin2hex(random_bytes(32))];
    $arUiUser=$actor;
    return $actor;
}

const SESSION_WARNING_SECONDS=60;
const CLEARANCE_CLOSED_MESSAGE='Clearance is currently closed. Please check again when the official clearance period is announced.';
function app_name(): string { return 'IRDP Student Clearance System'; }
function csrf_field(): void { echo '<input type="hidden" name="csrf_token" value="'.e(csrf_token()).'">'; }
function consume_flash(): array { $items=$_SESSION['ar_flash']??[]; unset($_SESSION['ar_flash']); return $items; }
function authenticated_session_state(): array { return ['server_time'=>time(),'idle_expires_at'=>($_SESSION['ar_last']??time())+1800,'absolute_expires_at'=>($_SESSION['ar_started']??time())+28800]; }
function humanize_status(string $status): string { return match($status){'NOT_STARTED'=>'Not Started','IN_PROGRESS'=>'In Progress','PAUSED'=>'Action required','IN_REVIEW'=>'Under Review',default=>ucwords(strtolower(str_replace('_',' ',$status)))}; }
function badge_class(string $status): string { return match($status){'APPROVED','COMPLETED'=>'badge success','REJECTED','PAUSED'=>'badge danger','PENDING','IN_REVIEW','IN_PROGRESS'=>'badge warning',default=>'badge muted'}; }
function profile_picture_url(array $student): string { return url('files/profile.php?student='.(int)$student['id']); }
function get_student(PDO $pdo,int $studentId): ?array {
    $row=ar_row($pdo,"SELECT u.*,p.name programme,d.name department FROM users u LEFT JOIN programmes p ON p.id=u.programme_id LEFT JOIN organisation_units d ON d.id=u.department_id AND d.kind='DEPARTMENT' WHERE u.id=? AND u.role='STUDENT'",[$studentId]);
    return $row?array_merge($row,['user_id'=>$row['id']]):null;
}
function get_student_clearance(PDO $pdo,int $studentId): ?array {
    $row=ar_row($pdo,'SELECT r.*,c.label academic_year FROM clearance_requests r JOIN clearance_cycles c ON c.id=r.cycle_id WHERE r.student_user_id=? AND r.started_at IS NOT NULL ORDER BY r.id DESC LIMIT 1',[$studentId]);
    return $row?:null;
}
function get_clearance_stages(PDO $pdo,int $requestId): array {
    return ai_rows($pdo,"SELECT s.*,o.name office,u.full_name officer_name FROM clearance_stages s JOIN organisation_units o ON o.id=s.office_id LEFT JOIN users u ON u.id=s.assigned_officer_id WHERE s.request_id=? AND s.row_kind='INSTANCE' ORDER BY s.step_number",[$requestId]);
}
function clearance_period_state(PDO $pdo): array {
    $row=ar_row($pdo,'SELECT c.*,c.label academic_cycle,c.id cycle_id FROM clearance_cycles c WHERE selected_slot=1');
    if(!$row){return ['cycle_id'=>null,'academic_cycle'=>null,'is_open'=>false,'effective_status'=>'CLOSED','opens_at'=>null,'closes_at'=>null];}
    $now=date('Y-m-d H:i:s');$row['is_open']=$row['active']&&($row['mode']==='MANUAL_OPEN'||($row['mode']==='SCHEDULED'&&$row['opens_at']&&$row['closes_at']&&$row['opens_at']<=$now&&$now<$row['closes_at']));
    $row['effective_status']=$row['is_open']?'OPEN':'CLOSED';return $row;
}
function clearance_fee_amount(PDO $pdo,int $cycle): string { return (string)(ar_row($pdo,'SELECT fee_amount FROM clearance_cycles WHERE id=?',[$cycle])['fee_amount']??'10000.00'); }
function clearance_fee_for_cycle(PDO $pdo,int $student,int $cycle): ?array {
    $row=ar_row($pdo,'SELECT id,student_user_id,cycle_id,entry_officer_id assigned_officer_id,entry_amount amount,entry_status status,control_number,automatic_control_number,control_generated_at,receipt_id,payment_version,approved_by,approved_at FROM clearance_requests WHERE student_user_id=? AND cycle_id=? ORDER BY id DESC LIMIT 1',[$student,$cycle]);
    return $row?:null;
}
function ensure_student_clearance_fee(PDO $pdo,int $actor): ?array {
    $period=clearance_period_state($pdo);if(!$period['is_open']||get_student_clearance($pdo,$actor)){return null;}
    ar_prepare_payment($pdo,$actor,(int)$period['cycle_id']);return clearance_fee_for_cycle($pdo,$actor,(int)$period['cycle_id']);
}
function clearance_fee_ready(PDO $pdo,?array $fee): bool {return $fee&&$fee['status']==='APPROVED'&&$fee['receipt_id']&&$fee['approved_by']&&$fee['approved_at'];}
function clearance_fee_control_summary(array $fee): void {
    if(!$fee['control_number']){return;}$date=$fee['control_generated_at']?new DateTimeImmutable($fee['control_generated_at'],new DateTimeZone('Africa/Dar_es_Salaam')):null;
    echo '<div class="detail-grid"><div><span>Control number</span><strong>'.e($fee['control_number']).'</strong></div>';
    if($date){foreach(['Day'=>$date->format('l'),'Date'=>$date->format('d F Y'),'Time (EAT)'=>$date->format('H:i:s')] as $label=>$value){echo '<div><span>'.e($label).'</span><strong>'.e($value).'</strong></div>';}}
    echo '</div><p class="muted">Demo payment reference.</p>';
}
function transcript_for_student(PDO $pdo,int $studentId,?int $requestId=null): ?array {
    return ar_row($pdo,"SELECT * FROM documents WHERE student_user_id=? AND request_id=? AND kind='CLEARANCE_CERTIFICATE' ORDER BY id DESC LIMIT 1",[$studentId,$requestId]);
}
function finance_payment_form(array $stage,array $errors=[],bool $allowReceiptReplacement=false): void {
    $details=finance_payment_details($stage);if(!isset($details['control_number'])){return;}
    echo '<p>Control number: <strong>'.e($details['control_number']).'</strong></p>';
    if($stage['status']==='REJECTED'){echo '<form method="post" action="'.e(url('student/resubmit.php?stage='.(int)$stage['id'])).'" enctype="multipart/form-data">';csrf_field();echo '<input type="hidden" name="mode" value="finance_payment"><input type="hidden" name="stage_id" value="'.(int)$stage['id'].'"><input type="hidden" name="row_version" value="'.(int)$stage['row_version'].'"><input type="hidden" name="control_number" value="'.e($details['control_number']).'"><label>Payment receipt (PDF, JPG or PNG; maximum 5 MB)<input type="file" name="evidence[]" accept=".pdf,.jpg,.jpeg,.png" required></label><button class="btn primary" type="submit">Submit payment receipt</button></form>';}
}
function student_evidence_form(PDO $pdo,array $stage,array $errors=[]): void {
    if($stage['status']!=='REJECTED'){return;}
    echo '<form method="post" action="'.e(url('student/resubmit.php?stage='.(int)$stage['id'])).'" enctype="multipart/form-data">';csrf_field();echo '<input type="hidden" name="mode" value="resubmit"><input type="hidden" name="stage_id" value="'.(int)$stage['id'].'"><input type="hidden" name="row_version" value="'.(int)$stage['row_version'].'"><label>Your response<textarea name="response" maxlength="2000" required></textarea></label><label>Evidence (up to five files)<input type="file" name="evidence[]" multiple accept=".pdf,.jpg,.jpeg,.png"></label><button class="btn primary" type="submit">Submit correction</button></form>';
}
function clearance_fee_officer(PDO $pdo,int $actor): bool {return (bool)ar_row($pdo,"SELECT m.user_id FROM office_memberships m JOIN clearance_stages s ON s.office_id=m.office_id AND s.row_kind='TEMPLATE' AND s.step_number=11 WHERE m.user_id=?",[$actor]);}
function clearance_fee_receipt(PDO $pdo,array $fee): ?array {return $fee['receipt_id']?ar_row($pdo,"SELECT * FROM documents WHERE id=? AND request_id=? AND kind='ENTRY_RECEIPT'",[$fee['receipt_id'],$fee['id']]):null;}
function clearance_fee_history(PDO $pdo,int $requestId): array {
    return ai_rows($pdo,"SELECT e.created_at,e.event_type action,e.payload details_json FROM activity_log e WHERE e.request_id=? AND e.family='PAYMENT' ORDER BY e.id",[$requestId]);
}
