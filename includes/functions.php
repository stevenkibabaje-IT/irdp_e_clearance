<?php
declare(strict_types=1);

/** Shared helpers for URLs, sessions, permissions, notifications and clearance workflow operations. */
require_once __DIR__ . '/../config/application.php';
require_once __DIR__ . '/session_security.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/clearance_workflow.php';
require_once __DIR__ . '/finance_payments.php';
require_once __DIR__ . '/clearance_fees.php';
require_once __DIR__ . '/authentication.php';
require_once __DIR__ . '/uploads.php';
require_once __DIR__ . '/imports.php';
require_once __DIR__ . '/forms.php';
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/clearance_progress.php';
require_once __DIR__ . '/accounts.php';
require_once __DIR__ . '/email_notifications.php';
require_once __DIR__ . '/clearance_period.php';
require_once __DIR__ . '/transcripts.php';

// Escape dynamic values before placing them in HTML text or attributes.
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function app_base_path(): string
{
    static $base = null;

    if ($base !== null) {
        return $base;
    }

    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/irdp_e_clearance/index.php');
    $parts = explode('/', trim($script, '/'));
    $folders = ['auth', 'student', 'officer', 'admin', 'certificates', 'reports', 'supervisor', 'files', 'transcripts'];

    if (count($parts) > 1 && in_array($parts[count($parts) - 2] ?? '', $folders, true)) {
        array_pop($parts);
        array_pop($parts);
    } else {
        array_pop($parts);
    }

    $base = '/' . implode('/', $parts);
    $base = $base === '/' ? '' : rtrim($base, '/');
    return $base;
}

function url(string $path = ''): string
{
    return app_base_path() . '/' . ltrim($path, '/');
}

function profile_picture_url(array $student): string
{
    return url('files/profile.php?student=' . (int)$student['id'] . '&v=' . rawurlencode($student['profile_file'] ?: 'default'));
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

// Require the form token to match the session before allowing a state change.
function verify_csrf(): void
{
    $sessionToken = $_SESSION['csrf_token'] ?? null;
    $submittedToken = $_POST['csrf_token'] ?? null;

    if (!is_string($sessionToken) || !is_string($submittedToken) || strlen($submittedToken) !== 64 || !hash_equals($sessionToken, $submittedToken)) {
        if (session_api_request()) {
            session_json_response(419, ['authenticated' => true, 'reason' => 'csrf']);
        }
        http_response_code(419);
        exit('Your form session expired. Refresh the page and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function consume_flash(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION = [];

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'username' => (string) $user['username'],
        'full_name' => (string) $user['full_name'],
        'role' => (string) $user['role_name'],
        'student_id' => !empty($user['student_id']) ? (int) $user['student_id'] : null,
        'auth_version' => (int) ($user['auth_version'] ?? 1),
    ];

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['session_started_at'] = time();
    touch_session_activity();
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'] ?? '',
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

function require_login(): void
{
    if (!current_user()) {
        redirect('auth/login.php');
    }
}

function require_role(string ...$roles): void
{
    require_login();

    if (!in_array(current_user()['role'], $roles, true)) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function audit(PDO $pdo, string $action, ?int $userId = null, ?int $requestId = null, string $details = ''): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO audit_logs (user_id, clearance_request_id, action, details, created_at)
         VALUES (?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $userId ?? (current_user()['id'] ?? null),
        $requestId,
        $action,
        $details,
    ]);
}

function notify(PDO $pdo, int $userId, string $title, string $message): int
{
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) { $pdo->beginTransaction(); }
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO notifications (user_id, title, message, is_read, created_at)
             VALUES (?, ?, ?, 0, NOW())'
        );
        $stmt->execute([$userId, $title, $message]);
        $notificationId = (int)$pdo->lastInsertId();
        queue_notification_email($pdo, $notificationId);
        if ($ownTransaction) { $pdo->commit(); }
        return $notificationId;
    } catch (Throwable $e) {
        if ($ownTransaction && $pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}

function humanize_status(string $status): string
{
    return match (strtoupper($status)) {
        'NOT_STARTED' => 'Not Started',
        'IN_PROGRESS' => 'In Progress',
        'PAUSED' => 'Action required',
        'COMPLETED' => 'Completed',
        'CANCELLED' => 'Cancelled',
        'ESCALATED' => 'Escalated',
        'ON_HOLD' => 'On Hold',
        'LOCKED' => 'Locked',
        'PENDING' => 'Pending',
        'IN_REVIEW' => 'Under Review',
        'APPROVED' => 'Approved',
        'REJECTED' => 'Rejected',
        'AVAILABLE' => 'Available',
        'UNAVAILABLE' => 'Unavailable',
        'ON_LEAVE' => 'On Leave',
        'SUSPENDED' => 'Suspended',
        'INACTIVE' => 'Inactive',
        'PRIORITY_REQUESTED' => 'Priority Requested',
        'UNDER_REVIEW' => 'Under Review',
        'PRIORITY_APPROVED' => 'Priority Approved',
        'PRIORITY_REJECTED' => 'Priority Rejected',
        'RESOLVED' => 'Resolved',
        default => ucfirst(strtolower(str_replace('_', ' ', $status))),
    };
}

function find_office_reviewer(PDO $pdo, int $officeId, int $departmentId = 0): ?int
{
    $stmt = $pdo->prepare(
        'SELECT u.id FROM users u
         INNER JOIN user_offices uo ON uo.user_id = u.id
         INNER JOIN roles r ON r.id = u.role_id
         INNER JOIN offices o ON o.id = uo.office_id
         WHERE uo.office_id = ? AND u.active = 1 AND o.active = 1
           AND r.name IN ("OFFICER", "SUPERVISOR")
           AND (? = 0 OR u.department_id = ?)
         ORDER BY u.id LIMIT 1'
    );
    $stmt->execute([$officeId, $departmentId, $departmentId]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int)$id;
}

// The caller holds the clearance request lock and owns the transaction.
function route_clearance_stage(PDO $pdo, int $requestId, int $stageId, int $actorId): void
{
    $stage = stage_context($pdo, $stageId, true);
    if ((int)$stage['clearance_request_id'] !== $requestId) {
        throw new RuntimeException('Invalid clearance stage.');
    }
    if (!in_array($stage['request_status'], ['IN_PROGRESS', 'PAUSED'], true)
        || !in_array($stage['status'], ['PENDING', 'IN_REVIEW'], true)) {
        return;
    }
    if (reviewer_assignment_valid($pdo, $stage, (int)$stage['assigned_officer_id'])) {
        return;
    }
    $reviewer = find_office_reviewer($pdo, (int)$stage['office_id'], stage_department($stage));
    if ($reviewer === null) {
        throw new RuntimeException('No active officer is assigned to this office and department. Contact the administrator.');
    }
    $pdo->prepare('UPDATE clearance_stages SET assigned_officer_id=?,assignment_status="ASSIGNED",assignment_source="ORIGINAL",original_officer_id=COALESCE(original_officer_id,?) WHERE id=?')->execute([$reviewer, $reviewer, $stageId]);
    audit($pdo, 'STAGE_OFFICER_ASSIGNED', $actorId, $requestId, 'Stage '.$stageId.' assigned to office officer '.$reviewer);
}

function validate_review_details(array $fields, array $input): array
{
    $details = [];
    foreach ($fields as [$key, $label, $type]) {
        $value = text_input($input, $key, $type === 'number' ? 15 : ($type === 'control_number' ? 30 : 20));
        if ($type === 'number') {
            $value = clearance_amount($value, $key, $label);
        } elseif ($type === 'asset') {
            $value = choice_input($value, ['AVAILABLE', 'MISSING'], $key);
        } elseif ($type === 'decision') {
            $value = choice_input($value, ['CLEARED', 'NOT_CLEARED'], $key);
        } elseif ($type === 'control_number') {
            $value = finance_control_number($value);
        } else {
            throw new LogicException('Unsupported review field type.');
        }
        $details[$key] = $value;
    }
    return $details;
}

// Lock the review target and check its expected state before recording a decision.
function lock_review_stage(PDO $pdo, int $requestId, int $stageId, int $officerId, string $expectedStatus, bool $allowFinancePaymentRequest = false): array
{
    if(!$pdo->inTransaction()){throw new LogicException('Stage review requires a transaction.');}
    $stage=stage_context($pdo,$stageId,true);
    $financePaymentRequest = $allowFinancePaymentRequest && (int)$stage['step_number'] === 11
        && $stage['status'] === 'REJECTED' && $expectedStatus === 'REJECTED';
    if((int)$stage['clearance_request_id']!==$requestId || !in_array($stage['request_status'],['IN_PROGRESS','PAUSED'],true)
       || $stage['status']!==$expectedStatus || (!$financePaymentRequest && !in_array($stage['status'],['PENDING','IN_REVIEW'],true))
       || (int)$stage['assigned_officer_id']!==$officerId || !reviewer_assignment_valid($pdo,$stage,$officerId)) {throw new RuntimeException('This stage changed or you are not its assigned office reviewer. Refresh the dashboard.');}
    return $stage;
}

function badge_class(string $status): string
{
    return match (strtoupper($status)) {
        'APPROVED', 'COMPLETED' => 'badge success',
        'REJECTED', 'PAUSED' => 'badge danger',
        'PENDING', 'IN_REVIEW', 'IN_PROGRESS', 'ESCALATED', 'PRIORITY_REQUESTED', 'UNDER_REVIEW', 'PRIORITY_APPROVED' => 'badge warning',
        'UNAVAILABLE', 'ON_HOLD' => 'badge danger',
        default => 'badge muted',
    };
}

function app_name(): string
{
    return 'IRDP Student Clearance System';
}

function role_dashboard(string $role): string
{
    return match ($role) {
        'STUDENT' => 'student/dashboard.php',
        'OFFICER' => 'officer/dashboard.php',
        'SUPERVISOR' => 'officer/dashboard.php',
        default => 'admin/dashboard.php',
    };
}

function get_student(PDO $pdo, int $studentId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT s.*, d.name AS department, u.email AS user_email, u.full_name
         FROM students s
         INNER JOIN users u ON u.id = s.user_id
         LEFT JOIN departments d ON d.id = s.department_id
         WHERE s.id = ?
         LIMIT 1'
    );
    $stmt->execute([$studentId]);
    return $stmt->fetch() ?: null;
}

function get_latest_clearance(PDO $pdo, int $studentId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM clearance_requests
         WHERE student_id = ? AND status <> "CANCELLED"
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$studentId]);
    return $stmt->fetch() ?: null;
}

function get_clearance_for_cycle(PDO $pdo, int $studentId, string $academicCycle): ?array
{
    $stmt=$pdo->prepare('SELECT * FROM clearance_requests WHERE student_id=? AND academic_year=? LIMIT 1');
    $stmt->execute([$studentId,$academicCycle]);
    return $stmt->fetch()?:null;
}

function get_clearance_stages(PDO $pdo, int $requestId): array
{
    $stmt = $pdo->prepare(
        'SELECT cs.*, ws.step_number, ws.title, o.name AS office,
                u.full_name AS officer_name
         FROM clearance_stages cs
         INNER JOIN workflow_steps ws ON ws.id = cs.workflow_step_id
         INNER JOIN offices o ON o.id = cs.office_id
         LEFT JOIN users u ON u.id = cs.assigned_officer_id
         WHERE cs.clearance_request_id = ?
         ORDER BY ws.step_number'
    );
    $stmt->execute([$requestId]);
    return $stmt->fetchAll();
}

function issue_certificate(PDO $pdo, int $requestId): array
{
    if (!certificate_release_allowed($pdo, $requestId)) { throw new RuntimeException('Certificate release checks have not passed.'); }
    $existing = $pdo->prepare('SELECT * FROM certificates WHERE clearance_request_id = ? LIMIT 1');
    $existing->execute([$requestId]);
    $certificate = $existing->fetch();

    if ($certificate) {
        return $certificate;
    }

    $certificateNumber = 'IRDP-CLR-' . date('Y') . '-' . str_pad((string) $requestId, 6, '0', STR_PAD_LEFT);
    $verificationCode = strtoupper(bin2hex(random_bytes(5)));

    $stmt = $pdo->prepare(
        'INSERT INTO certificates (clearance_request_id, certificate_number, verification_code, issued_at)
         VALUES (?, ?, ?, NOW())'
    );
    $stmt->execute([$requestId, $certificateNumber, $verificationCode]);

    $id = (int) $pdo->lastInsertId();
    $result = $pdo->prepare('SELECT * FROM certificates WHERE id = ?');
    $result->execute([$id]);
    return $result->fetch();
}
