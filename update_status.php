<?php
session_start();
include 'db_connect.php';
include 'send_notification.php';
include 'audit_log.php';
include 'status_history.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $request_id = $_POST['request_id'];
    $status = $_POST['status'];
    $technician_id = $_SESSION['user_id'];

    $oldStatus = 'N/A';
    $statusStmt = $conn->prepare("SELECT status FROM maintenance_requests WHERE request_id=?");
    $statusStmt->bind_param("i", $request_id);
    $statusStmt->execute();
    $statusResult = $statusStmt->get_result();
    if ($statusRow = $statusResult->fetch_assoc()) {
        $oldStatus = $statusRow['status'];
    }

    if ($status === 'Completed') {
        $stmt = $conn->prepare("UPDATE maintenance_requests SET status=?, completed_at=NOW() WHERE request_id=?");
        $stmt->bind_param("si", $status, $request_id);
    } else {
        $stmt = $conn->prepare("UPDATE maintenance_requests SET status=? WHERE request_id=?");
        $stmt->bind_param("si", $status, $request_id);
    }

    if ($stmt->execute()) {
        recordStatusHistory($request_id, $oldStatus, $status, $technician_id, "Technician updated status");

        // Notify requester
        $reqStmt = $conn->prepare("SELECT requester_id FROM maintenance_requests WHERE request_id=?");
        $reqStmt->bind_param("i", $request_id);
        $reqStmt->execute();
        $reqResult = $reqStmt->get_result();
        if ($reqRow = $reqResult->fetch_assoc()) {
            sendNotification($reqRow['requester_id'], $request_id, "Your request status has been updated to $status.", "Dashboard");
        }

        // Audit log
        writeAuditLog($technician_id, "Updated request status to $status", "maintenance_requests", $request_id, $_SERVER['REMOTE_ADDR']);

        echo "Status updated successfully!";
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>
