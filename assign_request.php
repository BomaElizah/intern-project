<?php
session_start();
include 'db_connect.php';
include 'send_notification.php';
include 'audit_log.php';
include 'status_history.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $request_id = $_POST['request_id'];
    $technician_id = $_POST['technician'];
    $due_date = $_POST['due_date'];
    $supervisor_id = $_SESSION['user_id'];

    $oldStatus = 'N/A';
    $statusStmt = $conn->prepare("SELECT status FROM maintenance_requests WHERE request_id=?");
    $statusStmt->bind_param("i", $request_id);
    $statusStmt->execute();
    $statusResult = $statusStmt->get_result();
    if ($statusRow = $statusResult->fetch_assoc()) {
        $oldStatus = $statusRow['status'];
    }

    $currentAssignment = null;
    $currentStmt = $conn->prepare("SELECT assignment_id, technician_id FROM assignments WHERE request_id=? AND is_current=TRUE LIMIT 1");
    $currentStmt->bind_param("i", $request_id);
    $currentStmt->execute();
    $currentResult = $currentStmt->get_result();
    if ($currentAssignment = $currentResult->fetch_assoc()) {
        if ($currentAssignment['technician_id'] == $technician_id) {
            $stmt = $conn->prepare("UPDATE assignments SET due_date=? WHERE assignment_id=?");
            $stmt->bind_param("si", $due_date, $currentAssignment['assignment_id']);
            $reassignRemark = 'Updated assignment due date for current technician.';
            $actionLabel = 'Updated assignment due date';
        } else {
            $deactivate = $conn->prepare("UPDATE assignments SET is_current=FALSE WHERE assignment_id=?");
            $deactivate->bind_param("i", $currentAssignment['assignment_id']);
            $deactivate->execute();

            $stmt = $conn->prepare("INSERT INTO assignments (request_id, technician_id, assigned_by, due_date, is_current) VALUES (?, ?, ?, ?, TRUE)");
            $stmt->bind_param("iiis", $request_id, $technician_id, $supervisor_id, $due_date);
            $reassignRemark = 'Supervisor reassigned request from previous technician.';
            $actionLabel = 'Reassigned request';

            sendNotification($currentAssignment['technician_id'], $request_id, "This request has been reassigned to another technician.", "Dashboard");
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO assignments (request_id, technician_id, assigned_by, due_date, is_current) VALUES (?, ?, ?, ?, TRUE)");
        $stmt->bind_param("iiis", $request_id, $technician_id, $supervisor_id, $due_date);
        $reassignRemark = 'Supervisor assigned request.';
        $actionLabel = 'Assigned request';
    }

    if ($stmt->execute()) {
        $update = $conn->prepare("UPDATE maintenance_requests SET status='Assigned' WHERE request_id=?");
        $update->bind_param("i", $request_id);
        $update->execute();

        recordStatusHistory($request_id, $oldStatus, 'Assigned', $supervisor_id, $reassignRemark);

        // Notify technician
        sendNotification($technician_id, $request_id, "You have been assigned a new request.", "Dashboard");

        // Notify requester
        $reqStmt = $conn->prepare("SELECT requester_id FROM maintenance_requests WHERE request_id=?");
        $reqStmt->bind_param("i", $request_id);
        $reqStmt->execute();
        $reqResult = $reqStmt->get_result();
        if ($reqRow = $reqResult->fetch_assoc()) {
            $requesterMessage = ($actionLabel === 'Reassigned request')
                ? "Your request has been reassigned to a different technician."
                : "Your request has been assigned to a technician.";
            sendNotification($reqRow['requester_id'], $request_id, $requesterMessage, "Email");
        }

        // Audit log
        writeAuditLog($supervisor_id, $actionLabel, "assignments", $request_id, $_SERVER['REMOTE_ADDR']);

        echo "Request assigned successfully!";
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>
