<?php
require_once __DIR__ . '/auth.php';
require_csrf();

include 'db_connect.php';
include 'send_notification.php';
include 'audit_log.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $token = $_POST['token'];
    $new_password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Verify token
    $stmt = $conn->prepare("SELECT user_id FROM password_resets WHERE token=? AND expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $user_id = $row['user_id'];

        // Update password
        $stmt = $conn->prepare("UPDATE users SET password_hash=? WHERE user_id=?");
        $stmt->bind_param("si", $new_password, $user_id);
        $stmt->execute();

        // Delete used token
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE token=?");
        $stmt->bind_param("s", $token);
        $stmt->execute();

        // Notify user
        sendNotification($user_id, null, "Your password has been successfully reset.", "Dashboard");

        // Audit log
        writeAuditLog($user_id, "Reset password", "users", $user_id, $_SERVER['REMOTE_ADDR']);

        echo "Password updated successfully!";
    } else {
        echo "Invalid or expired token.";
    }
}
?>
