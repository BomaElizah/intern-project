<?php
include 'db_connect.php';
include 'notification_mail.php';

function sendNotification($user_id, $request_id, $message, $type = 'Dashboard') {
    global $conn;

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, request_id, message, notification_type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $user_id, $request_id, $message, $type);

    if (!$stmt->execute()) {
        error_log("Notification Error: " . $stmt->error);
        return false;
    }

    if ($type === 'Email') {
        $userStmt = $conn->prepare("SELECT university_email, full_name FROM users WHERE user_id=?");
        $userStmt->bind_param("i", $user_id);
        $userStmt->execute();
        $userResult = $userStmt->get_result();

        if ($userResult && $userResult->num_rows > 0) {
            $userRow = $userResult->fetch_assoc();
            $email = $userRow['university_email'];
            $name = $userRow['full_name'];
            $settings = getNotificationMailSettings();
            $mailContent = buildNotificationEmailContent($message, $request_id);

            $headers = [];
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-type: text/html; charset=UTF-8';
            $headers[] = 'From: ' . $settings['from_name'] . ' <' . $settings['from'] . '>';
            $headers[] = 'Reply-To: ' . $settings['reply_to'];

            $body = str_replace('{{name}}', $name, $mailContent['body']);
            $success = mail($email, $mailContent['subject'], $body, implode("\r\n", $headers));
            if (!$success) {
                error_log("Email delivery failed for notification to {$email}");
            }
        }
    }

    return true;
}
?>
