<?php
require_once __DIR__ . '/auth.php';
require_role([ROLE_ADMIN, ROLE_SUPERVISOR, ROLE_TECHNICIAN, ROLE_STUDENT]);

require_once __DIR__ . '/db_connect.php';

$dashboard_path = __DIR__ . '/reporting.html';
$dashboard_markup = file_get_contents($dashboard_path);

$metrics = [
    'TOTAL_REQUESTS' => 0,
    'OPEN_REQUESTS' => 0,
    'COMPLETED_REQUESTS' => 0,
];

$total_sql = "SELECT COUNT(*) AS total FROM maintenance_requests";
if ($total_result = $conn->query($total_sql)) {
    $row = $total_result->fetch_assoc();
    $metrics['TOTAL_REQUESTS'] = (int)($row['total'] ?? 0);
}

$open_sql = "SELECT COUNT(*) AS total FROM maintenance_requests WHERE status IN ('Submitted', 'Assigned', 'Pending')";
if ($open_result = $conn->query($open_sql)) {
    $row = $open_result->fetch_assoc();
    $metrics['OPEN_REQUESTS'] = (int)($row['total'] ?? 0);
}

$completed_sql = "SELECT COUNT(*) AS total FROM maintenance_requests WHERE status = 'Completed'";
if ($completed_result = $conn->query($completed_sql)) {
    $row = $completed_result->fetch_assoc();
    $metrics['COMPLETED_REQUESTS'] = (int)($row['total'] ?? 0);
}

$report_results = '';
$dashboard_markup = str_replace('{{TOTAL_REQUESTS}}', (string)$metrics['TOTAL_REQUESTS'], $dashboard_markup);
$dashboard_markup = str_replace('{{OPEN_REQUESTS}}', (string)$metrics['OPEN_REQUESTS'], $dashboard_markup);
$dashboard_markup = str_replace('{{COMPLETED_REQUESTS}}', (string)$metrics['COMPLETED_REQUESTS'], $dashboard_markup);
$dashboard_markup = str_replace('{{REPORT_RESULTS}}', $report_results, $dashboard_markup);

$nav_html = render_nav_for_role((int)$_SESSION['role_id']);
$nav_start = strpos($dashboard_markup, '<nav>');
$nav_end = strpos($dashboard_markup, '</nav>');
if ($nav_start !== false && $nav_end !== false) {
    $nav_end += strlen('</nav>');
    $dashboard_markup = substr($dashboard_markup, 0, $nav_start) . $nav_html . substr($dashboard_markup, $nav_end);
}

echo $dashboard_markup;
