<?php
session_start();
include 'db_connect.php';
include 'audit_log.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $report_type = $_POST['report_type'];

    switch ($report_type) {
        case 'monthly':
            $sql = "SELECT DATE_FORMAT(submitted_at, '%Y-%m') AS month, COUNT(*) AS total_requests FROM maintenance_requests GROUP BY month";
            break;
        case 'building':
            $sql = "SELECT b.building_name, COUNT(*) AS total_requests FROM maintenance_requests m JOIN buildings b ON m.building_id=b.building_id GROUP BY b.building_name";
            break;
        case 'category':
            $sql = "SELECT c.category_name, COUNT(*) AS total_requests FROM maintenance_requests m JOIN categories c ON m.category_id=c.category_id GROUP BY c.category_name";
            break;
        case 'technician':
            $sql = "SELECT u.full_name, COUNT(*) AS total_requests FROM assignments a JOIN users u ON a.technician_id=u.user_id GROUP BY u.full_name";
            break;
    }

    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        echo implode(" | ", $row) . "<br>";
    }

    // Audit log
    writeAuditLog($_SESSION['user_id'], "Generated report: $report_type", "reports", null, $_SERVER['REMOTE_ADDR']);
}
?>
