<?php
session_start();
include 'db_connect.php';
include 'audit_log.php';
include 'auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    requireCsrf();
}

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

    // CSV export if requested
    $export = $_POST['export'] ?? null;
    if ($export === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="report_' . $report_type . '.csv"');
        $out = fopen('php://output', 'w');
        $first = true;
        while ($row = $result->fetch_assoc()) {
            if ($first) {
                fputcsv($out, array_keys($row));
                $first = false;
            }
            fputcsv($out, array_values($row));
        }
        fclose($out);
        exit;
    } else {
        while ($row = $result->fetch_assoc()) {
            echo implode(" | ", $row) . "<br>";
        }
    }

    // Audit log
    writeAuditLog($_SESSION['user_id'], "Generated report: $report_type", "reports", null, $_SERVER['REMOTE_ADDR']);
}
?>
