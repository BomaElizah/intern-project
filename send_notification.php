<?php
include 'db_connect.php';
include 'config.php';
include 'mailer.php';

function sendNotification($user_id, $request_id, $message, $type = 'Dashboard') {
    global $conn;

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, request_id, message, notification_type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $user_id, $request_id, $message, $type);

    if ($stmt->execute()) {
        // Optionally send an email when requested and enabled in config
        if (defined('EMAIL_ENABLED') && EMAIL_ENABLED && strtolower($type) === 'email') {
            $emailStmt = $conn->prepare("SELECT university_email, full_name FROM users WHERE user_id = ?");
            $emailStmt->bind_param('i', $user_id);
            $emailStmt->execute();
            $emailResult = $emailStmt->get_result();
            if ($emailRow = $emailResult->fetch_assoc()) {
                $to = $emailRow['university_email'];
                $subject = "WPU Maintenance Request Update (#" . intval($request_id) . ")";
                $body = "Hello " . ($emailRow['full_name'] ?? '') . ",\n\n" . $message . "\n\n" . "--\nWPU Maintenance";

                // If SMTP is enabled and PHPMailer is available, use it for reliable delivery
                if (defined('SMTP_ENABLED') && SMTP_ENABLED) {
                    $sent = sendEmailSMTP($to, $emailRow['full_name'] ?? '', $subject, $body);
                    if (!$sent) {
                        error_log("PHPMailer failed to send to $to for request $request_id");
                    }
                } else {
                    $fromHeader = 'From: ' . (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : '') . ' <' . (defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : '') . '>' . "\r\n" . 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
                    $mailSent = @mail($to, $subject, $body, $fromHeader);
                    if (!$mailSent) {
                        error_log("Email send failed to $to for notification (request $request_id)");
                    }
                }
            }
        }

        return true;
    } else {
        error_log("Notification Error: " . $stmt->error);
        return false;
    }
}

// Example usage
// sendNotification(1, 10245, "Your request has been assigned to a technician.", "Email");
?>
