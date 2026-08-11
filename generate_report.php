<?php
require_once __DIR__ . '/auth.php';
require_auth();
require_role([ROLE_ADMIN, ROLE_SUPERVISOR, ROLE_TECHNICIAN, ROLE_STUDENT]);
require_csrf();

session_start();
include 'db_connect.php';
include 'audit_log.php';

function report_rows_to_csv(array $rows): string
{
    $cols = [];
    if (!empty($rows)) {
        $cols = array_keys($rows[0]);
    }

    $output = fopen('php://temp', 'w+');
    if ($cols) {
        fputcsv($output, $cols);
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
    } else {
        fputcsv($output, ['empty']);
    }

    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);

    return (string)$csv;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $report_type = $_POST['report_type'] ?? 'monthly';
    $report_type = sanitize_text((string)$report_type);
    $export_format = isset($_POST['export_format']) ? strtolower(sanitize_text((string)$_POST['export_format'])) : 'html';

    if (!in_array($report_type, ['monthly', 'building', 'category', 'technician', 'status'], true)) {
        http_response_code(400);
        echo 'Unsupported report type.';
        exit;
    }

    switch ($report_type) {
        case 'monthly':
            $sql = "SELECT DATE_FORMAT(submitted_at, '%Y-%m') AS period, COUNT(*) AS total_requests FROM maintenance_requests GROUP BY period ORDER BY period ASC";
            break;
        case 'building':
            $sql = "SELECT b.building_name AS label, COUNT(*) AS total_requests FROM maintenance_requests m JOIN buildings b ON m.building_id=b.building_id GROUP BY b.building_name ORDER BY b.building_name ASC";
            break;
        case 'category':
            $sql = "SELECT c.category_name AS label, COUNT(*) AS total_requests FROM maintenance_requests m JOIN categories c ON m.category_id=c.category_id GROUP BY c.category_name ORDER BY c.category_name ASC";
            break;
        case 'technician':
            $sql = "SELECT u.full_name AS label, COUNT(*) AS total_requests FROM assignments a JOIN users u ON a.technician_id=u.user_id GROUP BY u.full_name ORDER BY u.full_name ASC";
            break;
        case 'status':
            $sql = "SELECT status AS label, COUNT(*) AS total_requests FROM maintenance_requests GROUP BY status ORDER BY status ASC";
            break;
    }

    $result = $conn->query($sql);
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    if ($export_format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . htmlspecialchars($report_type, ENT_QUOTES, 'UTF-8') . '_report.csv"');
        echo report_rows_to_csv($rows);
        exit;
    }

    if ($export_format === 'pdf') {
        if (class_exists('TCPDF') || class_exists('FPDF') || class_exists('Cpdf') || extension_loaded('pdf')) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . htmlspecialchars($report_type, ENT_QUOTES, 'UTF-8') . '_report.pdf"');
            echo 'PDF export is ready to be generated once a PDF library is available in this runtime.';
        } else {
            http_response_code(501);
            echo 'PDF export is not available in this PHP runtime because no PDF library is installed.';
        }
        exit;
    }

    echo '<!DOCTYPE html><html><head><title>Report Results</title><style>body{font-family:Arial;margin:2rem}table{border-collapse:collapse}th,td{border:1px solid #ccc;padding:.6rem}.top{margin-bottom:1rem}</style></head><body>';
    echo '<div class="top"><strong>Report:</strong> ' . htmlspecialchars(ucfirst($report_type), ENT_QUOTES, 'UTF-8') . '</div>';

    if (empty($rows)) {
        echo '<p>No records found.</p>';
    } else {
        echo '<table><thead><tr>';
        foreach (array_keys($rows[0]) as $column) {
            echo '<th>' . htmlspecialchars($column, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $value) {
                echo '<td>' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '</td>';
            }
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    echo '</body></html>';

    writeAuditLog($_SESSION['user_id'] ?? 0, "Generated report: $report_type", "reports", null, $_SERVER['REMOTE_ADDR'] ?? 'unknown');
}
?>
