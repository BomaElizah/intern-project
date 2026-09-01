<?php
/**
 * WPU Maintenance Request System — Shared helper functions.
 * Requires config/config.php ($pdo) to already be loaded.
 */

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect($path) {
    header('Location: ' . BASE_URL . '/' . ltrim($path, '/'));
    exit;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function current_user() {
    if (!is_logged_in()) return null;
    return [
        'user_id' => $_SESSION['user_id'],
        'full_name' => $_SESSION['full_name'],
        'role_id' => (int) $_SESSION['role_id'],
        'role_name' => $_SESSION['role_name'],
        'university_email' => $_SESSION['university_email'],
    ];
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return is_string($token) && hash_equals(csrf_token(), $token);
}

/** Guard a page to a set of allowed role IDs. Redirects home/login otherwise. */
function require_login() {
    if (!is_logged_in()) {
        flash('error', 'Please sign in to continue.');
        redirect('login.php');
    }
    global $pdo;
    $stmt = $pdo->prepare('SELECT is_active FROM users WHERE user_id = ?');
    $stmt->execute([(int) $_SESSION['user_id']]);
    if (!$stmt->fetchColumn()) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        redirect('login.php');
    }
}

function require_role($roleIds) {
    require_login();
    if (!in_array((int) $_SESSION['role_id'], (array) $roleIds, true)) {
        flash('error', 'You do not have permission to view that page.');
        redirect('index.php');
    }
}

function flash($type, $message) {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes() {
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/** Insert a dashboard notification (Section 6, FR-13; email is out of scope). */
function notify_user(PDO $pdo, $userId, $message, $requestId = null) {
    $stmt = $pdo->prepare(
        'INSERT INTO notifications (user_id, request_id, message, notification_type) VALUES (?, ?, ?, "Dashboard")'
    );
    $stmt->execute([$userId, $requestId, $message]);
}

/** Insert an audit log row (Section 5.1). */
function audit_log(PDO $pdo, $userId, $action, $entityType = null, $entityId = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt = $pdo->prepare(
        'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $action, $entityType, $entityId, $ip]);
}

/** Record a status change: updates the request row and appends history (7.4). */
function change_request_status(PDO $pdo, $requestId, $oldStatus, $newStatus, $changedBy, $remarks = null) {
    $pdo->prepare('UPDATE maintenance_requests SET status = ?, completed_at = CASE WHEN ? = "Completed" THEN NOW() ELSE completed_at END WHERE request_id = ?')
        ->execute([$newStatus, $newStatus, $requestId]);
    $pdo->prepare('INSERT INTO request_status_history (request_id, old_status, new_status, changed_by, remarks) VALUES (?, ?, ?, ?, ?)')
        ->execute([$requestId, $oldStatus, $newStatus, $changedBy, $remarks]);
}

function status_badge_class($status) {
    return [
        'Submitted' => 'badge-submitted',
        'Assigned' => 'badge-assigned',
        'Pending' => 'badge-pending',
        'Completed' => 'badge-completed',
        'Rejected' => 'badge-rejected',
    ][$status] ?? 'badge-submitted';
}

function priority_badge_class($priority) {
    return [
        'Low' => 'badge-low',
        'Medium' => 'badge-medium',
        'High' => 'badge-high',
        'Urgent' => 'badge-urgent',
    ][$priority] ?? 'badge-low';
}

/** Role-appropriate labels for the location fields on the submit-request form (Section 6.2-6.4). */
function location_labels($roleId) {
    if ($roleId == ROLE_STAFF) {
        return ['building' => 'Area/Location', 'room' => 'Building Number'];
    }
    // Student and Academic both use Building + Room Number wording.
    return ['building' => 'Building', 'room' => 'Room Number'];
}

function role_dashboard_path($roleId) {
    switch ((int) $roleId) {
        case ROLE_STUDENT:
        case ROLE_STAFF:
        case ROLE_ACADEMIC:
            return 'requests/dashboard.php';
        case ROLE_TECHNICIAN:
            return 'technician/dashboard.php';
        case ROLE_SUPERVISOR:
            return 'supervisor/dashboard.php';
        case ROLE_ADMIN:
            return 'admin/dashboard.php';
        default:
            return 'index.php';
    }
}

function unread_notification_count(PDO $pdo, $userId) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}
