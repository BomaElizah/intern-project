<?php
require_once __DIR__ . '/auth.php';
require_auth();
require_role([ROLE_TECHNICIAN]);
require_csrf();

session_start();
include 'db_connect.php';
include 'send_notification.php';
include 'audit_log.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $request_id = $_POST['request_id'];
    $technician_id = $_SESSION['user_id'];
    $comment = $_POST['comment'];

    // Handle file uploads
    $before_photo = $_FILES['before_photo']['name'];
    $after_photo = $_FILES['after_photo']['name'];

    $upload_dir = "../assets/uploads/";
    if (!empty($before_photo)) {
        move_uploaded_file($_FILES['before_photo']['tmp_name'], $upload_dir . $before_photo);
        $stmt = $conn->prepare("INSERT INTO attachments (request_id, uploaded_by, file_path, attachment_stage) VALUES (?, ?, ?, 'Before-Work')");
        $stmt->bind_param("iis", $request_id, $technician_id, $before_photo);
        $stmt->execute();
    }
    if (!empty($after_photo)) {
        move_uploaded_file($_FILES['after_photo']['tmp_name'], $upload_dir . $after_photo);
        $stmt = $conn->prepare("INSERT INTO attachments (request_id, uploaded_by, file_path, attachment_stage) VALUES (?, ?, ?, 'After-Work')");
        $stmt->bind_param("iis", $request_id, $technician_id, $after_photo);
        $stmt->execute();
    }

    // Insert technician comment
    if (!empty($comment)) {
        $stmt = $conn->prepare("INSERT INTO work_comments (request_id, technician_id, comment) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $request_id, $technician_id, $comment);
        $stmt->execute();
    }

    // Notify requester
    $reqStmt = $conn->prepare("SELECT requester_id FROM maintenance_requests WHERE request_id=?");
    $reqStmt->bind_param("i", $request_id);
    $reqStmt->execute();
    $reqResult = $reqStmt->get_result();
    if ($reqRow = $reqResult->fetch_assoc()) {
        sendNotification($reqRow['requester_id'], $request_id, "Technician uploaded work evidence for your request.", "Email");
    }

    // Audit log
    writeAuditLog($technician_id, "Uploaded work evidence", "attachments", $request_id, $_SERVER['REMOTE_ADDR']);

    echo "Evidence uploaded successfully!";
}
?>
