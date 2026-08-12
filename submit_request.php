<?php
include 'auth.php';
requireLogin();
include 'send_notification.php';
include 'audit_log.php';
include 'status_history.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $building = $_POST['building'];
    $room = isset($_POST['room']) && $_POST['room'] !== '' ? intval($_POST['room']) : null;
    $category = $_POST['category'];
    $priority = $_POST['priority'];
    $requester_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("INSERT INTO maintenance_requests (requester_id, title, description, building_id, room_id, category_id, priority, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Submitted')");
    $stmt->bind_param("issiiis", $requester_id, $title, $description, $building, $room, $category, $priority);

    if ($stmt->execute()) {
        $request_id = $conn->insert_id;

        recordStatusHistory($request_id, 'N/A', 'Submitted', $requester_id, 'Request created');

        // Notify requester
        sendNotification($requester_id, $request_id, "Your request has been submitted.", "Dashboard");

        // Audit log
        writeAuditLog($requester_id, "Submitted maintenance request", "maintenance_requests", $request_id, $_SERVER['REMOTE_ADDR']);

        echo "Request submitted successfully!";
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>
