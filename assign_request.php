<?php
include 'auth.php';
requireRole(['Supervisor']);
include 'send_notification.php';
include 'audit_log.php';
include 'status_history.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    requireCsrf();
    $request_id = $_POST['request_id'];
    $technician_id = $_POST['technician'];
    $due_date = $_POST['due_date'];
    $supervisor_id = $_SESSION['user_id'];

    // Mark any current assignment as no longer current
    $closeStmt = $conn->prepare("UPDATE assignments SET is_current = FALSE WHERE request_id = ? AND is_current = TRUE");
    $closeStmt->bind_param("i", $request_id);
    $closeStmt->execute();

    // Capture request status before assignment
    $statusStmt = $conn->prepare("SELECT status FROM maintenance_requests WHERE request_id = ?");
    $statusStmt->bind_param("i", $request_id);
    $statusStmt->execute();
    $statusResult = $statusStmt->get_result();
    $oldStatus = 'Submitted';
    if ($statusRow = $statusResult->fetch_assoc()) {
        $oldStatus = $statusRow['status'];
    }

    $stmt = $conn->prepare("INSERT INTO assignments (request_id, technician_id, assigned_by, due_date, is_current) VALUES (?, ?, ?, ?, TRUE)");
    $stmt->bind_param("iiis", $request_id, $technician_id, $supervisor_id, $due_date);

    if ($stmt->execute()) {
        $update = $conn->prepare("UPDATE maintenance_requests SET status='Assigned' WHERE request_id=?");
        $update->bind_param("i", $request_id);
        $update->execute();

        $remarks = "Assigned to technician ID $technician_id";
        recordStatusHistory($request_id, $oldStatus, 'Assigned', $supervisor_id, $remarks);

        // Notify technician
        sendNotification($technician_id, $request_id, "You have been assigned a new request.", "Dashboard");

        // Notify requester
        $reqStmt = $conn->prepare("SELECT requester_id FROM maintenance_requests WHERE request_id=?");
        $reqStmt->bind_param("i", $request_id);
        $reqStmt->execute();
        $reqResult = $reqStmt->get_result();
        if ($reqRow = $reqResult->fetch_assoc()) {
            sendNotification($reqRow['requester_id'], $request_id, "Your request has been assigned to a technician.", "Email");
        }

        // Audit log
        writeAuditLog($supervisor_id, "Assigned request", "assignments", $request_id, $_SERVER['REMOTE_ADDR']);

        echo "Request assigned successfully!";
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>
