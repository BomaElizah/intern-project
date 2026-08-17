<?php
session_start();
include 'db_connect.php';
include 'audit_log.php';
include 'auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    requireCsrf();

    $report_type = $_POST['report_type'] ?? 'category';
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $building = !empty($_POST['building']) ? intval($_POST['building']) : null;
    $category = !empty($_POST['category']) ? intval($_POST['category']) : null;
    $technician = !empty($_POST['technician']) ? intval($_POST['technician']) : null;
    $status = !empty($_POST['status']) ? $_POST['status'] : null;
    $format = $_POST['format'] ?? 'html'; // html, csv, json

    $where = [];
    $params = [];
    $types = '';

    if ($start_date) { $where[] = "mr.submitted_at >= ?"; $params[] = $start_date . ' 00:00:00'; $types .= 's'; }
    if ($end_date) { $where[] = "mr.submitted_at <= ?"; $params[] = $end_date . ' 23:59:59'; $types .= 's'; }
    if ($building) { $where[] = "mr.building_id = ?"; $params[] = $building; $types .= 'i'; }
    if ($category) { $where[] = "mr.category_id = ?"; $params[] = $category; $types .= 'i'; }
    if ($status) { $where[] = "mr.status = ?"; $params[] = $status; $types .= 's'; }

    // Build SQL per report type
    switch ($report_type) {
        case 'monthly':
            $sql = "SELECT DATE_FORMAT(mr.submitted_at, '%Y-%m') AS period, 
                    COUNT(*) AS total_requests,
                    SUM(CASE WHEN status='Completed' THEN 1 ELSE 0 END) as completed,
                    AVG(TIMESTAMPDIFF(HOUR, mr.submitted_at, mr.completed_at)) as avg_hours
                    FROM maintenance_requests mr";
            break;
        case 'building':
            $sql = "SELECT b.building_name AS label, COUNT(*) AS total_requests,
                    SUM(CASE WHEN mr.status='Completed' THEN 1 ELSE 0 END) as completed
                    FROM maintenance_requests mr 
                    JOIN buildings b ON mr.building_id=b.building_id";
            break;
        case 'category':
            $sql = "SELECT c.category_name AS label, COUNT(*) AS total_requests,
                    SUM(CASE WHEN mr.status='Completed' THEN 1 ELSE 0 END) as completed
                    FROM maintenance_requests mr 
                    JOIN categories c ON mr.category_id=c.category_id";
            break;
        case 'technician':
            $sql = "SELECT u.full_name AS label, COUNT(*) AS total_requests,
                    SUM(CASE WHEN mr.status='Completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN a.is_current=TRUE THEN 1 ELSE 0 END) as active
                    FROM assignments a 
                    JOIN users u ON a.technician_id=u.user_id 
                    JOIN maintenance_requests mr ON a.request_id=mr.request_id";
            break;
        case 'status':
            $sql = "SELECT mr.status AS label, COUNT(*) AS total_requests,
                    AVG(TIMESTAMPDIFF(HOUR, mr.submitted_at, NOW())) as avg_hours_open
                    FROM maintenance_requests mr";
            break;
        case 'priority':
            $sql = "SELECT mr.priority AS label, COUNT(*) AS total_requests,
                    SUM(CASE WHEN mr.status='Completed' THEN 1 ELSE 0 END) as completed
                    FROM maintenance_requests mr";
            break;
        default:
            $sql = "SELECT c.category_name AS label, COUNT(*) AS total_requests,
                    SUM(CASE WHEN mr.status='Completed' THEN 1 ELSE 0 END) as completed
                    FROM maintenance_requests mr 
                    JOIN categories c ON mr.category_id=c.category_id";
            break;
    }

    // Append WHERE
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    // Group and order
    if ($report_type === 'monthly') {
        $sql .= ' GROUP BY period ORDER BY period ASC';
    } else {
        $sql .= ' GROUP BY label ORDER BY total_requests DESC';
    }

    // Prepare and bind
    $stmt = $conn->prepare($sql);
    if ($stmt === false) { error_log('Report SQL prepare error: ' . $conn->error); echo 'Error preparing report.'; exit; }
    if (!empty($params)) {
        $bindNames = [];
        $bindNames[] = $types;
        for ($i = 0; $i < count($params); $i++) {
            $bindNames[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bindNames);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // Build rows
    $rows = [];
    while ($r = $result->fetch_assoc()) { $rows[] = $r; }

    // Audit log
    writeAuditLog($_SESSION['user_id'] ?? null, "Generated report: $report_type", 'reports', null, $_SERVER['REMOTE_ADDR']);

    if ($format === 'csv' || ($_POST['export'] ?? null) === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="report_' . $report_type . '.csv"');
        $out = fopen('php://output', 'w');
        $first = true;
        foreach ($rows as $row) {
            if ($first) { fputcsv($out, array_keys($row)); $first = false; }
            fputcsv($out, array_values($row));
        }
        fclose($out);
        exit;
    }

    if ($format === 'json' || (isset($_POST['ajax']) && $_POST['ajax'] === '1')) {
        header('Content-Type: application/json');
        echo json_encode(['rows' => $rows]);
        exit;
    }

    // Default: simple HTML
    foreach ($rows as $row) { echo implode(" | ", $row) . "<br>"; }
}
?>
