<?php
require_once __DIR__ . '/auth.php';
require_auth();
require_role([ROLE_TECHNICIAN, ROLE_SUPERVISOR, ROLE_ADMIN]);
require_csrf();

session_start();
include 'db_connect.php';
include 'send_notification.php';
include 'audit_log.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $request_id = $_POST['request_id'];
    $status = $_POST['status'];
    $technician_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("UPDATE maintenance_requests SET status=? WHERE request_id=?");
    $stmt->bind_param("si", $status, $request_id);

    if ($stmt->execute()) {
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
