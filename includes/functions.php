<?php
/**
 * TrackShield - Shared helper functions.
 */

require_once __DIR__ . '/../config/database.php';

const EMERGENCY_STATUSES = [
    'Submitted',
    'Under Verification',
    'Assigned',
    'Response Team Dispatched',
    'In Progress',
    'Resolved',
    'Rejected',
];

/** Statuses that a response team is allowed to set. */
const RESPONSE_TEAM_STATUSES = ['Response Team Dispatched', 'In Progress', 'Resolved'];

/** Statuses considered "open / active". */
const ACTIVE_STATUSES = ['Submitted', 'Under Verification', 'Assigned', 'Response Team Dispatched', 'In Progress'];

const PRIORITIES = ['Critical', 'High', 'Medium'];

const TEAM_AVAILABILITY = ['Available', 'Busy', 'Off Duty'];

/* ---------------------------------------------------------------
 * Output / URL helpers
 * ------------------------------------------------------------- */

/** Escape a value for safe HTML output (XSS protection). */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Build an absolute application URL. */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Read and trim a request value. */
function input(string $key, string $default = '', string $source = 'POST'): string
{
    $arr = $source === 'GET' ? $_GET : $_POST;
    $val = $arr[$key] ?? $default;
    return is_string($val) ? trim($val) : $default;
}

function format_dt(?string $dt, string $format = 'd M Y, h:i A'): string
{
    if (!$dt) {
        return '—';
    }
    $ts = strtotime($dt);
    return $ts ? date($format, $ts) : '—';
}

function time_ago(?string $dt): string
{
    if (!$dt) {
        return '';
    }
    $diff = time() - strtotime($dt);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . ' min ago';
    if ($diff < 86400)  return floor($diff / 3600) . ' hr ago';
    if ($diff < 604800) return floor($diff / 86400) . ' day(s) ago';
    return format_dt($dt, 'd M Y');
}

/* ---------------------------------------------------------------
 * Flash messages (rendered as toast notifications)
 * ------------------------------------------------------------- */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------------------------------------------------------------
 * CSRF protection
 * ------------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/** Abort the request when the CSRF token is missing or invalid. */
function verify_csrf(): void
{
    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        flash('error', 'Your session expired or the form was tampered with. Please try again.');
        redirect(ltrim(str_replace(BASE_URL, '', strtok($_SERVER['REQUEST_URI'] ?? '/', '?')), '/') ?: 'index.php');
    }
}

/* ---------------------------------------------------------------
 * Validation
 * ------------------------------------------------------------- */

function valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 150;
}

function valid_phone(string $phone): bool
{
    return (bool) preg_match('/^\+?[0-9]{10,15}$/', $phone);
}

/** Returns an error message, or null when the password is strong enough. */
function password_error(string $password): ?string
{
    if (strlen($password) < 8)                  return 'Password must be at least 8 characters long.';
    if (strlen($password) > 72)                 return 'Password must be 72 characters or fewer.';
    if (!preg_match('/[A-Z]/', $password))      return 'Password must contain an uppercase letter.';
    if (!preg_match('/[a-z]/', $password))      return 'Password must contain a lowercase letter.';
    if (!preg_match('/[0-9]/', $password))      return 'Password must contain a number.';
    if (!preg_match('/[^A-Za-z0-9]/', $password)) return 'Password must contain a special character.';
    return null;
}

function str_limit(string $value, int $max): bool
{
    return mb_strlen($value) <= $max;
}

/* ---------------------------------------------------------------
 * Badges
 * ------------------------------------------------------------- */

function status_class(string $status): string
{
    return [
        'Submitted'                => 'status-submitted',
        'Under Verification'       => 'status-verification',
        'Assigned'                 => 'status-assigned',
        'Response Team Dispatched' => 'status-dispatched',
        'In Progress'              => 'status-progress',
        'Resolved'                 => 'status-resolved',
        'Rejected'                 => 'status-rejected',
    ][$status] ?? 'status-submitted';
}

function status_badge(string $status): string
{
    return '<span class="badge ' . status_class($status) . '">' . e($status) . '</span>';
}

function priority_badge(string $priority): string
{
    $cls = ['Critical' => 'priority-critical', 'High' => 'priority-high', 'Medium' => 'priority-medium'][$priority] ?? 'priority-medium';
    return '<span class="badge ' . $cls . '">' . e($priority) . '</span>';
}

function availability_badge(string $a): string
{
    $cls = ['Available' => 'status-resolved', 'Busy' => 'priority-high', 'Off Duty' => 'status-rejected'][$a] ?? '';
    return '<span class="badge ' . $cls . '">' . e($a) . '</span>';
}

/* ---------------------------------------------------------------
 * Domain helpers
 * ------------------------------------------------------------- */

/** Generate a unique emergency reference number, e.g. TS-20261116-7F3A9C. */
function generate_reference(PDO $pdo): string
{
    $stmt = $pdo->prepare('SELECT 1 FROM emergencies WHERE reference_number = ?');
    do {
        $ref = 'TS-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $stmt->execute([$ref]);
    } while ($stmt->fetchColumn());
    return $ref;
}

function get_categories(PDO $pdo): array
{
    return $pdo->query('SELECT id, category_name, description FROM emergency_categories ORDER BY id')->fetchAll();
}

function notify(PDO $pdo, int $userId, ?int $emergencyId, string $message): void
{
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, emergency_id, message) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $emergencyId, mb_substr($message, 0, 255)]);
}

/** Notify every active administrator. */
function notify_admins(PDO $pdo, ?int $emergencyId, string $message, ?int $exceptUserId = null): void
{
    $ids = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        if ((int)$id !== $exceptUserId) {
            notify($pdo, (int)$id, $emergencyId, $message);
        }
    }
}

function unread_notification_count(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

/** Record a status-history row. */
function log_update(PDO $pdo, int $emergencyId, int $userId, ?string $prev, string $new, ?string $remarks): void
{
    $stmt = $pdo->prepare('INSERT INTO emergency_updates (emergency_id, updated_by, previous_status, new_status, remarks)
                           VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$emergencyId, $userId, $prev, $new, $remarks !== '' ? $remarks : null]);
}

/**
 * Change an emergency's status, log the history, update assignment / team
 * state and notify the reporting passenger. Caller handles the transaction.
 */
function change_status(PDO $pdo, array $emergency, string $newStatus, int $byUserId, string $remarks = ''): void
{
    $id   = (int) $emergency['id'];
    $prev = $emergency['status'];

    if ($newStatus !== $prev) {
        $pdo->prepare('UPDATE emergencies SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
    }
    log_update($pdo, $id, $byUserId, $prev, $newStatus, $remarks);

    if (in_array($newStatus, ['Resolved', 'Rejected'], true) && $newStatus !== $prev) {
        // Close the active assignment and free the team if it has nothing else to do.
        $stmt = $pdo->prepare("SELECT team_id FROM emergency_assignments WHERE emergency_id = ? AND assignment_status = 'Active'");
        $stmt->execute([$id]);
        $teamIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $pdo->prepare("UPDATE emergency_assignments SET assignment_status = 'Completed', completed_at = NOW()
                       WHERE emergency_id = ? AND assignment_status = 'Active'")->execute([$id]);
        foreach ($teamIds as $tid) {
            refresh_team_availability($pdo, (int) $tid);
        }
    }

    if ($newStatus !== $prev) {
        $msg = match ($newStatus) {
            'Under Verification' => "Your emergency {$emergency['reference_number']} is being verified by railway authorities.",
            'Assigned'           => "A response team has been assigned to emergency {$emergency['reference_number']}.",
            'Response Team Dispatched' => "A response team has been dispatched for emergency {$emergency['reference_number']}.",
            'In Progress'        => "Response for emergency {$emergency['reference_number']} is now in progress.",
            'Resolved'           => "Emergency {$emergency['reference_number']} has been resolved. Thank you for reporting.",
            'Rejected'           => "Emergency {$emergency['reference_number']} was rejected after review.",
            default              => "Status of emergency {$emergency['reference_number']} changed to {$newStatus}.",
        };
        notify($pdo, (int) $emergency['user_id'], $id, $msg);
    } elseif ($remarks !== '') {
        notify($pdo, (int) $emergency['user_id'], $id, "New update on emergency {$emergency['reference_number']}.");
    }
}

/** Mark a team Busy when it has active assignments, otherwise Available (unless Off Duty). */
function refresh_team_availability(PDO $pdo, int $teamId): void
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM emergency_assignments WHERE team_id = ? AND assignment_status = 'Active'");
    $stmt->execute([$teamId]);
    $active = (int) $stmt->fetchColumn();
    $pdo->prepare("UPDATE response_teams SET availability = ? WHERE id = ? AND availability <> 'Off Duty'")
        ->execute([$active > 0 ? 'Busy' : 'Available', $teamId]);
}

/** Fetch a single emergency with category & reporter details. */
function find_emergency(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT e.*, c.category_name, u.full_name AS reporter_name, u.email AS reporter_email
                           FROM emergencies e
                           JOIN emergency_categories c ON c.id = e.category_id
                           JOIN users u ON u.id = e.user_id
                           WHERE e.id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function emergency_history(PDO $pdo, int $emergencyId): array
{
    $stmt = $pdo->prepare('SELECT h.*, u.full_name, u.role FROM emergency_updates h
                           JOIN users u ON u.id = h.updated_by
                           WHERE h.emergency_id = ? ORDER BY h.created_at DESC, h.id DESC');
    $stmt->execute([$emergencyId]);
    return $stmt->fetchAll();
}

/** Current active assignment (with team info) or null. */
function active_assignment(PDO $pdo, int $emergencyId): ?array
{
    $stmt = $pdo->prepare("SELECT a.*, t.team_name, t.team_leader, t.phone AS team_phone, t.specialization, u.full_name AS assigned_by_name
                           FROM emergency_assignments a
                           JOIN response_teams t ON t.id = a.team_id
                           JOIN users u ON u.id = a.assigned_by
                           WHERE a.emergency_id = ?
                           ORDER BY (a.assignment_status = 'Active') DESC, a.assigned_at DESC LIMIT 1");
    $stmt->execute([$emergencyId]);
    return $stmt->fetch() ?: null;
}

/**
 * Validate and store an uploaded incident image.
 * Returns [filename|null, error|null].
 */
function handle_image_upload(string $field): array
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, $file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
            ? 'Image is too large (max 5 MB).' : 'Image upload failed. Please try again.'];
    }
    if ($file['size'] > UPLOAD_MAX_BYTES) {
        return [null, 'Image is too large (max 5 MB).'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return [null, 'Invalid upload.'];
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) {
        return [null, 'Only JPG, PNG, GIF or WEBP images are allowed.'];
    }

    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) {
        return [null, 'Upload directory is not writable.'];
    }
    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name)) {
        return [null, 'Could not save the uploaded image.'];
    }
    @chmod(UPLOAD_DIR . $name, 0644);
    return [$name, null];
}

/** Safe public URL for a stored upload (filename validated against our own pattern). */
function upload_url(?string $filename): ?string
{
    if (!$filename || !preg_match('/^[a-f0-9]{32}\.(jpg|png|gif|webp)$/', $filename)) {
        return null;
    }
    return url('uploads/emergencies/' . $filename);
}

/** Simple pagination helper: returns [page, perPage, offset]. */
function paginate(int $perPage = 10): array
{
    $page = max(1, (int)($_GET['page'] ?? 1));
    return [$page, $perPage, ($page - 1) * $perPage];
}

/** Render pagination links preserving current query string. */
function pagination_links(int $total, int $page, int $perPage): string
{
    $pages = (int) ceil($total / $perPage);
    if ($pages <= 1) {
        return '';
    }
    $q = $_GET;
    $html = '<nav class="pagination" aria-label="Pagination">';
    for ($i = 1; $i <= $pages; $i++) {
        $q['page'] = $i;
        $html .= '<a class="' . ($i === $page ? 'active' : '') . '" href="?' . e(http_build_query($q)) . '">' . $i . '</a>';
    }
    return $html . '</nav>';
}

/** Inline SVG icon set (stroke icons, 24x24). */
function icon(string $name, int $size = 20): string
{
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'alert'     => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
        'list'      => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'bell'      => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>',
        'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'team'      => '<path d="M12 2 4 5v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V5l-8-3Z"/><path d="m9 12 2 2 4-4"/>',
        'tag'       => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8Z"/><path d="M7 7h.01"/>',
        'chart'     => '<path d="M3 3v18h18"/><path d="M7 16v-4M12 16V8M17 16v-7"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'check'     => '<path d="M20 6 9 17l-5-5"/>',
        'clock'     => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'phone'     => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/>',
        'map'       => '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'train'     => '<rect x="4" y="3" width="16" height="13" rx="3"/><path d="M4 11h16M8 16l-2 5M16 16l2 5M8 7h8"/><circle cx="8.5" cy="13.5" r=".5"/><circle cx="15.5" cy="13.5" r=".5"/>',
        'menu'      => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'search'    => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'activity'  => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'file'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M16 13H8M16 17H8"/>',
        'x'         => '<path d="M18 6 6 18M6 6l12 12"/>',
        'eye'       => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
    ];
    $p = $paths[$name] ?? $paths['alert'];
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}
