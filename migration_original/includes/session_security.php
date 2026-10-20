<?php
declare(strict_types=1);

/** Cookie-only sessions and server-enforced authentication lifetimes. */
function start_application_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string) SESSION_IDLE_SECONDS);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function session_api_request(): bool
{
    return defined('SESSION_API_REQUEST') && SESSION_API_REQUEST === true;
}

function session_json_response(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    echo json_encode($payload, JSON_THROW_ON_ERROR);
    exit;
}

function end_authenticated_session(string $reason): never
{
    logout_user();
    if (session_api_request()) {
        session_json_response(401, ['authenticated' => false, 'reason' => $reason]);
    }
    redirect('auth/login.php?session=' . rawurlencode($reason));
}

function enforce_session_security(): void
{
    if (!current_user()) {
        if (session_api_request()) {
            session_json_response(401, ['authenticated' => false, 'reason' => 'expired']);
        }
        return;
    }
    $started = $_SESSION['session_started_at'] ?? null;
    $lastActivity = $_SESSION['last_activity_at'] ?? null;
    // Sessions from before this feature must sign in again to establish a lifetime.
    if (!is_int($started) || !is_int($lastActivity)) {
        end_authenticated_session('expired');
    }
    $now = time();
    if ($now - $started >= SESSION_ABSOLUTE_SECONDS) {
        end_authenticated_session('absolute');
    }
    if ($now - $lastActivity >= SESSION_IDLE_SECONDS) {
        end_authenticated_session('idle');
    }
}

function touch_session_activity(): void
{
    $_SESSION['last_activity_at'] = time();
}

function authenticated_session_state(): array
{
    return [
        'authenticated' => true,
        'server_time' => time(),
        'idle_expires_at' => $_SESSION['last_activity_at'] + SESSION_IDLE_SECONDS,
        'absolute_expires_at' => $_SESSION['session_started_at'] + SESSION_ABSOLUTE_SECONDS,
    ];
}
