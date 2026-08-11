<?php
include 'db_connect.php';

function sendNotification($user_id, $request_id, $message, $type = 'Dashboard') {
    global $conn;

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, request_id, message, notification_type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $user_id, $request_id, $message, $type);

    if ($stmt->execute()) {
        return true;
    } else {
        error_log("Notification Error: " . $stmt->error);
        return false;
    }
}

// Example usage
// sendNotification(1, 10245, "Your request has been assigned to a technician.", "Email");
?>
