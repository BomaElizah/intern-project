<?php
// Security utilities for input validation and output encoding
include 'db_connect.php';

// Input Validation Functions
function validate_email($email) {
    if (empty($email) || strlen($email) > 254) {
        return false;
    }
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_password($password) {
    // Requirements: at least 8 characters, 1 uppercase, 1 lowercase, 1 number, 1 special char
    if (strlen($password) < 8) {
        return ['valid' => false, 'error' => 'Password must be at least 8 characters'];
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        return ['valid' => false, 'error' => 'Password must contain at least one uppercase letter'];
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        return ['valid' => false, 'error' => 'Password must contain at least one lowercase letter'];
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        return ['valid' => false, 'error' => 'Password must contain at least one number'];
    }
    
    if (!preg_match('/[!@#$%^&*()_+=\-\[\]{};:\'",.<>?\\\\/]/', $password)) {
        return ['valid' => false, 'error' => 'Password must contain at least one special character'];
    }
    
    return ['valid' => true];
}

function validate_full_name($name) {
    $name = trim($name);
    if (strlen($name) < 2 || strlen($name) > 100) {
        return false;
    }
    // Allow letters, spaces, hyphens, apostrophes
    return preg_match('/^[a-zA-Z\s\-\']+$/', $name) === 1;
}

function validate_request_title($title) {
    $title = trim($title);
    return strlen($title) >= 3 && strlen($title) <= 200;
}

function validate_request_description($description) {
    $description = trim($description);
    return strlen($description) >= 10 && strlen($description) <= 5000;
}

function validate_integer($value) {
    return filter_var($value, FILTER_VALIDATE_INT) !== false;
}

function validate_date($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function sanitize_string($string, $maxLength = null) {
    $string = trim($string);
    if ($maxLength && strlen($string) > $maxLength) {
        $string = substr($string, 0, $maxLength);
    }
    return $string;
}

function sanitize_textarea($text) {
    return htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');
}

// Output Encoding Functions
function e($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function eattr($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function ejson($value) {
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APO | JSON_HEX_QUOT);
}

function eurl($url) {
    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}

// Cross-Site Request Forgery Protection
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
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrf() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verifyCsrfToken($token)) {
        http_response_code(403);
        echo 'Invalid CSRF token.';
        exit;
    }
}

function getCsrfTokenInput() {
    return '<input type="hidden" name="csrf_token" value="' . e(generateCsrfToken()) . '">';
}

// Rate Limiting for Sensitive Operations
function checkRateLimit($key, $max_attempts = 5, $window_seconds = 900) {
    global $conn;
    
    $now = time();
    $window_start = $now - $window_seconds;
    
    // Use database for persistent rate limiting
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as attempt_count FROM rate_limits 
         WHERE rate_key = ? AND attempt_time > FROM_UNIXTIME(?)"
    );
    $stmt->bind_param("si", $key, $window_start);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $attempts = $row['attempt_count'] ?? 0;
    
    if ($attempts >= $max_attempts) {
        return false;
    }
    
    return true;
}

function recordRateLimitAttempt($key) {
    global $conn;
    
    $now = time();
    $stmt = $conn->prepare("INSERT INTO rate_limits (rate_key, attempt_time) VALUES (?, FROM_UNIXTIME(?))");
    $stmt->bind_param("si", $key, $now);
    $stmt->execute();
}

function resetRateLimit($key) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM rate_limits WHERE rate_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
}

// Validation result helper
function validation_error($message) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function validation_success($data = []) {
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

// Log sensitive operations
function logSecurityEvent($event_type, $user_id, $details, $ip_address) {
    global $conn;
    
    $stmt = $conn->prepare(
        "INSERT INTO security_audit_log (event_type, user_id, details, ip_address) 
         VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("siss", $event_type, $user_id, $details, $ip_address);
    $stmt->execute();
}
?>
