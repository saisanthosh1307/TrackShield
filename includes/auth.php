<?php
/**
 * TrackShield - Session & role-based access control.
 */

require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('TRACKSHIELDSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL === '' ? '/' : BASE_URL . '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Security headers for every PHP page.
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

const SESSION_IDLE_TIMEOUT = 1800; // 30 minutes

// Expire idle sessions.
if (!empty($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > SESSION_IDLE_TIMEOUT) {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('info', 'Your session expired due to inactivity. Please log in again.');
    } else {
        $_SESSION['last_activity'] = time();
    }
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

/** Currently logged-in user (re-read from DB once per request). */
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (is_logged_in()) {
            $stmt = db()->prepare('SELECT id, full_name, email, phone, role, status FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
        }
    }
    return $user;
}

function dashboard_path(string $role): string
{
    return match ($role) {
        'admin'    => 'admin/dashboard.php',
        'response' => 'response/dashboard.php',
        default    => 'passenger/dashboard.php',
    };
}

/** Log a user in: regenerate the session ID to prevent fixation. */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']       = (int) $user['id'];
    $_SESSION['role']          = $user['role'];
    $_SESSION['last_activity'] = time();
    unset($_SESSION['csrf_token']);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Guard a page. Pass one or more roles allowed to view it.
 * Deactivated accounts are logged out immediately.
 */
function require_role(string ...$roles): array
{
    $user = current_user();
    if (!$user) {
        flash('error', 'Please log in to continue.');
        redirect('login.php');
    }
    if ($user['status'] !== 'active') {
        logout_user();
        session_start();
        flash('error', 'Your account has been deactivated. Contact the administrator.');
        redirect('login.php');
    }
    if ($roles && !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        flash('error', 'You do not have permission to access that page.');
        redirect(dashboard_path($user['role']));
    }
    return $user;
}

/** For response-team users: their team row. */
function current_team(int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM response_teams WHERE user_id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}
