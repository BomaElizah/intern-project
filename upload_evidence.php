<?php
include 'auth.php';
requireRole(['Maintenance Officer', 'Technician']);
include 'send_notification.php';
include 'audit_log.php';
include 'config.php';
include 'status_history.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    requireCsrf();

    $request_id = $_POST['request_id'];
    $technician_id = $_SESSION['user_id'];
    $comment = $_POST['comment'];

    // Handle file uploads with validation and safe storage
    $allowed_ext = ALLOWED_UPLOAD_EXT;
    $max_size = MAX_UPLOAD_BYTES;

    // Determine upload directory and ensure it exists
    $upload_dir = get_upload_dir();

    $errors = [];
    $savedFiles = [];

    $handleFile = function($fieldName, $stage) use ($conn, $request_id, $technician_id, $upload_dir, &$errors, &$savedFiles) {
        if (!isset($_FILES[$fieldName])) {
            return;
        }

        $file = $_FILES[$fieldName];
        
        // Validate file
        $validation = validate_upload_file($file);
        if (!$validation['valid']) {
            $errors[] = "$fieldName: " . $validation['error'];
            return;
        }

        // Generate safe filename
        $safeName = generate_safe_filename($file['name']);
        $target = $upload_dir . $safeName;
        
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            $errors[] = "Failed to move uploaded file for $fieldName.";
            return;
        }

        // Store web-accessible path in the DB (relative to project root)
        $dbPath = get_upload_url_base() . $safeName;
        $stmt = $conn->prepare("INSERT INTO attachments (request_id, uploaded_by, file_path, attachment_stage) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $request_id, $technician_id, $dbPath, $stage);
        if ($stmt->execute()) {
            $savedFiles[] = $dbPath;
            // Write audit log
            writeAuditLog($technician_id, "Uploaded evidence: $stage", "attachments", $request_id, $_SERVER['REMOTE_ADDR']);
        } else {
            $errors[] = "Failed to record attachment for $fieldName.";
            // Attempt to unlink the file we moved
            @unlink($target);
        }
    };

    // Insert technician comment
    if (!empty($comment)) {
        $stmt = $conn->prepare("INSERT INTO work_comments (request_id, technician_id, comment) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $request_id, $technician_id, $comment);
        $stmt->execute();
    }

    // Record an audit/status history entry for the evidence upload (does not change request status)
    $curStatusStmt = $conn->prepare("SELECT status FROM maintenance_requests WHERE request_id = ?");
    $curStatusStmt->bind_param("i", $request_id);
    $curStatusStmt->execute();
    $curStatusRes = $curStatusStmt->get_result();
    $currentStatus = 'Submitted';
    if ($row = $curStatusRes->fetch_assoc()) {
        $currentStatus = $row['status'];
    }

    recordStatusHistory($request_id, $currentStatus, $currentStatus, $technician_id, 'Technician uploaded evidence');

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
