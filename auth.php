<?php
// Security headers applied for PHP pages that include this file
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer-when-downgrade');

// Content Security Policy (kept permissive for existing inline scripts and CDN usage)
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data:; connect-src 'self'; font-src 'self' https://fonts.gstatic.com;");

// HSTS when running over HTTPS
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');
}

session_start();
include 'db_connect.php';

function requireLogin() {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.html');
        exit;
    }
}

function getCurrentUser() {
    global $conn;

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    if (!empty($_SESSION['full_name']) && !empty($_SESSION['role_name'])) {
        return [
            'user_id' => $_SESSION['user_id'],
            'full_name' => $_SESSION['full_name'],
            'role_id' => $_SESSION['role_id'] ?? null,
            'role_name' => $_SESSION['role_name'],
        ];
    }

    $stmt = $conn->prepare(
        "SELECT u.user_id, u.full_name, u.role_id, COALESCE(r.role_name, '') AS role_name
         FROM users u
         LEFT JOIN roles r ON u.role_id = r.role_id
         WHERE u.user_id = ?"
    );
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $_SESSION['full_name'] = $row['full_name'];
        $_SESSION['role_id'] = $row['role_id'];
        $_SESSION['role_name'] = $row['role_name'];

        return [
            'user_id' => $row['user_id'],
            'full_name' => $row['full_name'],
            'role_id' => $row['role_id'],
            'role_name' => $row['role_name'],
        ];
    }

    return null;
}

function requireRole($allowedRoles = []) {
    $user = getCurrentUser();
    if (!$user) {
        header('Location: login.html');
        exit;
    }

    $roleName = strtolower($user['role_name'] ?? '');
    foreach ((array) $allowedRoles as $allowed) {
        if (strtolower($allowed) === $roleName) {
            return true;
        }
    }

    header('HTTP/1.1 403 Forbidden');
    echo 'Access denied.';
    exit;
}

function e($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// CSRF helpers
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        if (function_exists('random_bytes')) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } else {
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        }
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrf() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verifyCsrfToken($token)) {
        header('HTTP/1.1 400 Bad Request');
        echo 'Invalid CSRF token.';
        exit;
    }
}
