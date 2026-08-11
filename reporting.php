<?php
require_once __DIR__ . '/auth.php';
require_role([ROLE_ADMIN, ROLE_SUPERVISOR, ROLE_TECHNICIAN, ROLE_STUDENT]);

require_once __DIR__ . '/db_connect.php';

$report_html = file_get_contents(__DIR__ . '/reporting.html');

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

if ($result = $result = $conn->query($completed_sql)) {
    $row = $result->fetch_assoc();
    $metrics['COMPLETED_REQUESTS'] = (int)($row['total'] ?? 0);
}

$chart_sql = "SELECT DATE_FORMAT(submitted_at, '%Y-%m') AS month, COUNT(*) AS total_requests FROM maintenance_requests GROUP BY month ORDER BY month LIMIT 6";
$chart_rows = '';
if ($chart_result = $conn->query($chart_sql)) {
    while ($chart_row = $chart_result->fetch_assoc()) {
        $chart_rows .= '<div class="chart-bar-row"><span>' . htmlspecialchars($chart_row['month'] ?? '', ENT_QUOTES, 'UTF-8') . '</span><div class="chart-bar"><div style="width:' . (int)$chart_row['total_requests'] * 20 . '%"></div></div></div>';
    }
}

$report_html = str_replace('{{TOTAL_REQUESTS}}', (string)$metrics['TOTAL_REQUESTS'], $report_html);
$report_html = str_replace('{{OPEN_REQUESTS}}', (string)$metrics['OPEN_REQUESTS'], $report_html);
$report_html = str_replace('{{COMPLETED_REQUESTS}}', (string)$metrics['COMPLETED_REQUESTS'], $report_html);
$report_html = str_replace('{{CHART_BARS}}', $chart_rows, $report_html);
$report_html = str_replace('{{REPORT_RESULTS}}', '<p>Reports are ready for generation.</p>', $report_html);

$nav_html = render_nav_for_role((int)$_SESSION['role_id']);
$nav_start = strpos($report_html, '<nav>');
$nav_end = strpos($report_html, '</nav>');
if ($nav_start !== false && $nav_end !== false) {
    $nav_end += strlen('</nav>');
    $report_html = substr($report_html, 0, $nav_start) . $nav_html . substr($report_html, $nav_end);
}

echo $report_html;
