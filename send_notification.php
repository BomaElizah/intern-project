<?php
include 'db_connect.php';
include 'config.php';
include 'mailer.php';

function sendNotification($user_id, $request_id, $message, $type = 'Dashboard') {
    global $conn;

    // Store notification in database
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, request_id, message, notification_type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $user_id, $request_id, $message, $type);

    if ($stmt->execute()) {
        // Send email if requested and enabled
        if (strtolower($type) === 'email' && (defined('EMAIL_ENABLED') && EMAIL_ENABLED || defined('SMTP_ENABLED') && SMTP_ENABLED)) {
            sendEmailNotification($user_id, $request_id, $message);
        }
        return true;
    } else {
        error_log("Notification Error: " . $stmt->error);
        return false;
    }
}

function sendEmailNotification($user_id, $request_id, $message) {
    global $conn;
    
    $emailStmt = $conn->prepare("SELECT university_email, full_name FROM users WHERE user_id = ?");
    $emailStmt->bind_param('i', $user_id);
    $emailStmt->execute();
    $emailResult = $emailStmt->get_result();
    
    if (!($emailRow = $emailResult->fetch_assoc())) {
        error_log("User email not found for user $user_id");
        return false;
    }

    $to = $emailRow['university_email'];
    $toName = $emailRow['full_name'] ?? '';
    $subject = "WPU Maintenance Request Update (#" . intval($request_id) . ")";
    $body = "Hello $toName,\n\n" . $message . "\n\n--\nWPU Maintenance Request System";

    if (defined('SMTP_ENABLED') && SMTP_ENABLED) {
        $sent = sendEmailSMTP($to, $toName, $subject, $body, false);
    } else {
        $sent = sendEmailSMTP($to, $toName, $subject, $body, false);
    }

    if (!$sent) {
        error_log("Email send failed to $to for request $request_id");
    }

    return $sent;
}

function sendStyledEmailNotification($user_id, $request_id, $template_type) {
    global $conn;
    
    // Include templates
    include_once 'email_templates.php';
    
    $emailStmt = $conn->prepare("SELECT university_email, full_name FROM users WHERE user_id = ?");
    $emailStmt->bind_param('i', $user_id);
    $emailStmt->execute();
    $emailResult = $emailStmt->get_result();
    
    if (!($emailRow = $emailResult->fetch_assoc())) {
        error_log("User email not found for user $user_id");
        return false;
    }

    $to = $emailRow['university_email'];
    $toName = $emailRow['full_name'] ?? '';
    
    // Generate HTML email based on template type
    $htmlBody = null;
    $subject = "WPU Maintenance Request Update";
    
    switch($template_type) {
        case 'submission':
            $htmlBody = generateRequestSubmissionEmail($request_id);
            $subject = "Request Submitted Successfully";
            break;
        case 'assignment':
            $htmlBody = generateAssignmentEmail($request_id, $toName);
            $subject = "Your Request Has Been Assigned";
            break;
        case 'status_update':
            $request = getRequestDetails($request_id);
            $htmlBody = generateStatusUpdateEmail($request_id, $request['status'] ?? '');
            $subject = "Request Status Updated";
            break;
        case 'technician_assignment':
            $htmlBody = generateTechnicianAssignmentEmail($request_id, $toName);
            $subject = "New Work Assignment";
            break;
    }

    if (!$htmlBody) {
        return false;
    }

    if (defined('SMTP_ENABLED') && SMTP_ENABLED) {
        $sent = sendEmailSMTP($to, $toName, $subject, $htmlBody, true);
    } else {
        $sent = sendEmailSMTP($to, $toName, $subject, $htmlBody, true);
    }

    if (!$sent) {
        error_log("Styled email send failed to $to for request $request_id (template: $template_type)");
    }

    return $sent;
}

// Example usage
// sendNotification(1, 10245, "Your request has been assigned to a technician.", "Email");
// sendStyledEmailNotification(1, 10245, 'assignment');
?>

