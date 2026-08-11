<?php
require_once __DIR__ . '/auth.php';
require_role([ROLE_ADMIN, ROLE_SUPERVISOR, ROLE_TECHNICIAN, ROLE_STUDENT]);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/audit_log.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $report_type = $_POST['report_type'] ?? 'monthly';

    $sql = '';
    switch ($report_type) {
        case 'monthly':
            $sql = "SELECT DATE_FORMAT(submitted_at, '%Y-%m') AS period, COUNT(*) AS total_requests FROM maintenance_requests GROUP BY period ORDER BY period";
            break;
        case 'building':
            $sql = "SELECT b.building_name AS period, COUNT(*) AS total_requests FROM maintenance_requests m JOIN buildings b ON m.building_id=b.building_id GROUP BY b.building_name ORDER BY b.building_name";
            break;
        case 'category':
            $sql = "SELECT c.category_name AS period, COUNT(*) AS total_requests FROM maintenance_requests m JOIN categories c ON m.category_id=c.category_id GROUP BY c.category_name ORDER BY c.category_name";
            break;
        case 'technician':
            $sql = "SELECT u.full_name AS period, COUNT(*) AS total_requests FROM assignments a JOIN users u ON a.technician_id=u.user_id GROUP BY u.full_name ORDER BY u.full_name";
            break;
        default:
            $sql = "SELECT 'All Requests' AS period, COUNT(*) AS total_requests FROM maintenance_requests";
            break;
    }

    $result = $conn->query($sql);
    $rows = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    echo '<!DOCTYPE html>';
    echo '<html lang="en">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>Generated Report - WPU MRS</title>';
    echo '<link rel="stylesheet" href="style.css">';
    echo '</head>';
    echo '<body>';
    echo '<header class="header"><h1>WPU Maintenance Request System</h1><nav>';
    echo render_nav_for_role((int)$_SESSION['role_id']);
    echo '</nav></header>';
    echo '<main class="dashboard-container">';
    echo '<section class="report-panel">';
    echo '<h2>Generated Report</h2>';
    echo '<p>Report type: <strong>' . htmlspecialchars($report_type, ENT_QUOTES, 'UTF-8') . '</strong></p>';
    echo '<table>';
    echo '<thead><tr><th>Period</th><th>Request Count</th></tr></thead>';
    echo '<tbody>';

    if (!empty($rows)) {
        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['period'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . (int)($row['total_requests'] ?? 0) . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="2">No report data found.</td></tr>';
    }

    echo '</tbody></table>';
    echo '<p><a href="generate_report.php" class="btn-secondary">Back to Reports</a></p>';
    echo '</section></main></body></html>';

    writeAuditLog($_SESSION['user_id'], "Generated report: $report_type", "reports", null, $_SERVER['REMOTE_ADDR']);
    exit;
}

$report_template = file_get_contents(__DIR__ . '/reporting.html');
$dashboard_path = __DIR__ . '/dashboard_' . role_id_to_name((int)$_SESSION['role_id']) . '.php';

$total_sql = "SELECT COUNT(*) AS total FROM maintenance_requests";
$open_sql = "SELECT COUNT(*) AS total FROM maintenance_requests WHERE status IN ('Submitted', 'Assigned', 'Pending')";
$completed_sql = "SELECT COUNT(*) AS total FROM maintenance_requests WHERE status = 'Completed'";

$metrics = [
    'TOTAL_REQUESTS' => 0,
    'OPEN_REQUESTS' => 0,
    'COMPLETED_REQUESTS' => 0,
];

if ($result = $conn->query($total_sql)) {
    $row = $result->fetch_assoc();
    $metrics['TOTAL_REQUESTS'] = (int)($row['total'] ?? 0);
}

if ($result = $conn->query($open_sql)) {
    $row = $result->fetch_assoc();
    $metrics['OPEN_REQUESTS'] = (int)($row['total'] ?? 0);
}

if ($result = $conn->query($completed_sql)) {
    $row = $result->fetch_assoc();
    $metrics['COMPLETED_REQUESTS'] = (int)($row['total'] ?? 0);
}

$report_template = str_replace('{{TOTAL_REQUESTS}}', (string)$metrics['TOTAL_REQUESTS'], $report_template);
$report_template = str_replace('{{OPEN_REQUESTS}}', (string)$metrics['OPEN_REQUESTS'], $report_template);
$report_template = str_replace('{{COMPLETED_REQUESTS}}', (string)$metrics['COMPLETED_REQUESTS'], $report_template);
$report_template = str_replace('{{REPORT_RESULTS}}', '<p>Ready for report generation.</p>', $report_template);

$nav_html = render_nav_for_role((int)$_SESSION['role_id']);
$nav_start = strpos($report_template, '<nav>');
$nav_end = strpos($report_template, '</nav>');
if ($nav_start !== false && $nav_end !== false) {
    $nav_end += strlen('</nav>');
    $report_template = substr($report_template, 0, $nav_start) . $nav_html . substr($report_template, $nav_end);
}

echo $report_template;
?>
