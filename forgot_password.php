<?php
include 'db_connect.php';
include 'send_notification.php';
include 'audit_log.php';
include 'rate_limit.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    include 'auth.php';
    requireCsrf();
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rlKey = 'forgot_ip_' . $clientIp;
    if (isRateLimited($rlKey, 3, 3600)) {
        echo "Too many requests. Please try again later.";
        exit;
    }

    $email = $_POST['email'];

    // Check if user exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE university_email=? AND is_active=TRUE");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $user_id = $row['user_id'];
        $token = bin2hex(random_bytes(16));
        $expiry = date("Y-m-d H:i:s", strtotime("+1 hour"));

        // Store token
        $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $token, $expiry);
        $stmt->execute();

        // Send notification (email/dashboard)
        $resetLink = "http://localhost/wpu_mrs/pages/reset_password.html?token=$token";
        sendNotification($user_id, null, "Password reset requested. Use this link: $resetLink", "Email");

        // Audit log
        writeAuditLog($user_id, "Requested password reset", "users", $user_id, $_SERVER['REMOTE_ADDR']);

        // Register rate-limit attempt
        registerAttempt($rlKey);

        echo "Password reset link has been sent to your email.";
    } else {
        // Register rate-limit attempt to prevent enumeration
        registerAttempt($rlKey);
        echo "No account found with that email.";
    }
}
?>
