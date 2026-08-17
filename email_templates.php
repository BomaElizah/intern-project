<?php
// Email templates and helper functions for notifications
include 'db_connect.php';

function getRequestDetails($request_id) {
    global $conn;
    $stmt = $conn->prepare(
        "SELECT mr.*, 
                u.full_name as requester_name, 
                b.building_name, 
                c.category_name,
                r.room_number
         FROM maintenance_requests mr
         LEFT JOIN users u ON mr.requester_id = u.user_id
         LEFT JOIN buildings b ON mr.building_id = b.building_id
         LEFT JOIN categories c ON mr.category_id = c.category_id
         LEFT JOIN rooms r ON mr.room_id = r.room_id
         WHERE mr.request_id = ?"
    );
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function generateRequestSubmissionEmail($request_id) {
    $request = getRequestDetails($request_id);
    if (!$request) return null;

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 20px; border-radius: 5px 5px 0 0; }
        .content { background: #f9f9f9; padding: 20px; }
        .footer { background: #2c3e50; color: white; padding: 10px 20px; text-align: center; border-radius: 0 0 5px 5px; }
        .field { margin: 10px 0; }
        .field-label { font-weight: bold; color: #2c3e50; }
        .btn { display: inline-block; background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Maintenance Request Submitted</h2>
        </div>
        <div class="content">
            <p>Dear {requester_name},</p>
            <p>Your maintenance request has been successfully submitted. We have received it and will process it shortly.</p>
            
            <h3>Request Details</h3>
            <div class="field">
                <span class="field-label">Request ID:</span> {request_id}
            </div>
            <div class="field">
                <span class="field-label">Title:</span> {title}
            </div>
            <div class="field">
                <span class="field-label">Category:</span> {category_name}
            </div>
            <div class="field">
                <span class="field-label">Building:</span> {building_name}
            </div>
            <div class="field">
                <span class="field-label">Room:</span> {room_number}
            </div>
            <div class="field">
                <span class="field-label">Priority:</span> {priority}
            </div>
            <div class="field">
                <span class="field-label">Status:</span> {status}
            </div>
            <div class="field">
                <span class="field-label">Description:</span><br>
                {description}
            </div>
            
            <p>You can track the status of your request on the student portal.</p>
        </div>
        <div class="footer">
            <p>WPU Maintenance Request System | Do not reply to this email</p>
        </div>
    </div>
</body>
</html>
HTML;

    // Replace placeholders
    return strtr($html, [
        '{requester_name}' => htmlspecialchars($request['requester_name'] ?? 'User'),
        '{request_id}' => htmlspecialchars($request['request_id']),
        '{title}' => htmlspecialchars($request['title']),
        '{category_name}' => htmlspecialchars($request['category_name'] ?? 'N/A'),
        '{building_name}' => htmlspecialchars($request['building_name'] ?? 'N/A'),
        '{room_number}' => htmlspecialchars($request['room_number'] ?? 'N/A'),
        '{priority}' => htmlspecialchars($request['priority']),
        '{status}' => htmlspecialchars($request['status']),
        '{description}' => nl2br(htmlspecialchars($request['description'])),
    ]);
}

function generateAssignmentEmail($request_id, $technician_name) {
    $request = getRequestDetails($request_id);
    if (!$request) return null;

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #27ae60; color: white; padding: 20px; border-radius: 5px 5px 0 0; }
        .content { background: #f9f9f9; padding: 20px; }
        .footer { background: #2c3e50; color: white; padding: 10px 20px; text-align: center; border-radius: 0 0 5px 5px; }
        .field { margin: 10px 0; }
        .field-label { font-weight: bold; color: #27ae60; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Request Assigned to Technician</h2>
        </div>
        <div class="content">
            <p>Dear {requester_name},</p>
            <p>Good news! Your maintenance request has been assigned to our team.</p>
            
            <h3>Assignment Details</h3>
            <div class="field">
                <span class="field-label">Request ID:</span> {request_id}
            </div>
            <div class="field">
                <span class="field-label">Title:</span> {title}
            </div>
            <div class="field">
                <span class="field-label">Assigned to:</span> {technician_name}
            </div>
            <div class="field">
                <span class="field-label">Status:</span> Assigned
            </div>
            
            <p>A technician will contact you shortly with more information about your request. You can track updates on the student portal.</p>
        </div>
        <div class="footer">
            <p>WPU Maintenance Request System</p>
        </div>
    </div>
</body>
</html>
HTML;

    return strtr($html, [
        '{requester_name}' => htmlspecialchars($request['requester_name'] ?? 'User'),
        '{request_id}' => htmlspecialchars($request['request_id']),
        '{title}' => htmlspecialchars($request['title']),
        '{technician_name}' => htmlspecialchars($technician_name),
    ]);
}

function generateStatusUpdateEmail($request_id, $new_status) {
    $request = getRequestDetails($request_id);
    if (!$request) return null;

    $statusMessages = [
        'Submitted' => 'Your request has been received.',
        'Assigned' => 'Your request has been assigned to a technician.',
        'In Progress' => 'Work has started on your request.',
        'Pending' => 'Your request is pending further action.',
        'Completed' => 'Your request has been completed! The issue has been resolved.',
        'Rejected' => 'Unfortunately, your request could not be processed.',
    ];

    $statusMessage = $statusMessages[$new_status] ?? "Your request status has been updated to: $new_status";

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #3498db; color: white; padding: 20px; border-radius: 5px 5px 0 0; }
        .content { background: #f9f9f9; padding: 20px; }
        .footer { background: #2c3e50; color: white; padding: 10px 20px; text-align: center; border-radius: 0 0 5px 5px; }
        .field { margin: 10px 0; }
        .field-label { font-weight: bold; color: #3498db; }
        .status-banner { background: #ecf0f1; padding: 15px; border-left: 4px solid #3498db; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Request Status Updated</h2>
        </div>
        <div class="content">
            <p>Dear {requester_name},</p>
            
            <div class="status-banner">
                <strong>{status_message}</strong>
            </div>
            
            <h3>Request Details</h3>
            <div class="field">
                <span class="field-label">Request ID:</span> {request_id}
            </div>
            <div class="field">
                <span class="field-label">Title:</span> {title}
            </div>
            <div class="field">
                <span class="field-label">New Status:</span> {new_status}
            </div>
            
            <p>Log into the student portal to view more details about your request.</p>
        </div>
        <div class="footer">
            <p>WPU Maintenance Request System</p>
        </div>
    </div>
</body>
</html>
HTML;

    return strtr($html, [
        '{requester_name}' => htmlspecialchars($request['requester_name'] ?? 'User'),
        '{request_id}' => htmlspecialchars($request['request_id']),
        '{title}' => htmlspecialchars($request['title']),
        '{new_status}' => htmlspecialchars($new_status),
        '{status_message}' => $statusMessage,
    ]);
}

function generateTechnicianAssignmentEmail($request_id, $technician_name) {
    $request = getRequestDetails($request_id);
    if (!$request) return null;

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #e74c3c; color: white; padding: 20px; border-radius: 5px 5px 0 0; }
        .content { background: #f9f9f9; padding: 20px; }
        .footer { background: #2c3e50; color: white; padding: 10px 20px; text-align: center; border-radius: 0 0 5px 5px; }
        .field { margin: 10px 0; }
        .field-label { font-weight: bold; color: #e74c3c; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>New Work Assignment</h2>
        </div>
        <div class="content">
            <p>Hello,</p>
            <p>You have been assigned a new maintenance request.</p>
            
            <h3>Request Details</h3>
            <div class="field">
                <span class="field-label">Request ID:</span> {request_id}
            </div>
            <div class="field">
                <span class="field-label">Title:</span> {title}
            </div>
            <div class="field">
                <span class="field-label">Category:</span> {category_name}
            </div>
            <div class="field">
                <span class="field-label">Building:</span> {building_name}
            </div>
            <div class="field">
                <span class="field-label">Priority:</span> {priority}
            </div>
            <div class="field">
                <span class="field-label">Description:</span><br>
                {description}
            </div>
            
            <p>Please log into the technician portal to accept this assignment and view full details.</p>
        </div>
        <div class="footer">
            <p>WPU Maintenance Request System</p>
        </div>
    </div>
</body>
</html>
HTML;

    return strtr($html, [
        '{request_id}' => htmlspecialchars($request['request_id']),
        '{title}' => htmlspecialchars($request['title']),
        '{category_name}' => htmlspecialchars($request['category_name'] ?? 'N/A'),
        '{building_name}' => htmlspecialchars($request['building_name'] ?? 'N/A'),
        '{priority}' => htmlspecialchars($request['priority']),
        '{description}' => nl2br(htmlspecialchars($request['description'])),
    ]);
}
?>
