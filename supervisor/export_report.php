<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_role([ROLE_SUPERVISOR, ROLE_ADMIN]);

$format = strtolower($_GET['format'] ?? '');
if (!in_array($format, ['pdf', 'word', 'excel'], true)) {
    http_response_code(400);
    exit('Unsupported export format.');
}

$overall = $pdo->query(
    "SELECT COUNT(*) AS total,
            SUM(status = 'Submitted') + SUM(status='Assigned') + SUM(status='Pending') AS open_requests,
            SUM(status = 'Rejected') AS rejected,
            SUM(status = 'Completed') AS completed,
            AVG(CASE WHEN status = 'Completed' THEN TIMESTAMPDIFF(HOUR, submitted_at, completed_at) END) AS avg_hours
     FROM maintenance_requests"
)->fetch();
$byMonth = $pdo->query("SELECT DATE_FORMAT(submitted_at, '%Y-%m') AS period, COUNT(*) AS total, SUM(status='Completed') AS completed FROM maintenance_requests GROUP BY period ORDER BY period DESC LIMIT 12")->fetchAll();
$byBuilding = $pdo->query("SELECT b.building_name, COUNT(*) AS total, SUM(r.status='Completed') AS completed FROM maintenance_requests r JOIN buildings b ON b.building_id = r.building_id GROUP BY b.building_name ORDER BY total DESC")->fetchAll();
$byCategory = $pdo->query("SELECT c.category_name, COUNT(*) AS total, SUM(r.status='Completed') AS completed FROM maintenance_requests r JOIN categories c ON c.category_id = r.category_id GROUP BY c.category_name ORDER BY total DESC")->fetchAll();
$byTechnician = $pdo->query("SELECT u.full_name, COUNT(*) AS total, SUM(r.status='Completed') AS completed, AVG(CASE WHEN r.status='Completed' THEN TIMESTAMPDIFF(HOUR, r.submitted_at, r.completed_at) END) AS avg_hours FROM assignments a JOIN users u ON u.user_id = a.technician_id JOIN maintenance_requests r ON r.request_id = a.request_id GROUP BY u.full_name ORDER BY total DESC")->fetchAll();
$byStatus = $pdo->query("SELECT status, COUNT(*) AS total FROM maintenance_requests GROUP BY status")->fetchAll();

$label = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$number = static function ($value) {
    return (int) $value;
};

$sections = [
    ['Requests by Month', ['Period', 'Total', 'Completed'], array_map(static function ($row) { return [$row['period'], (int) $row['total'], (int) $row['completed']]; }, $byMonth)],
    ['Requests by Building', ['Building', 'Total', 'Completed'], array_map(static function ($row) { return [$row['building_name'], (int) $row['total'], (int) $row['completed']]; }, $byBuilding)],
    ['Requests by Category', ['Category', 'Total', 'Completed'], array_map(static function ($row) { return [$row['category_name'], (int) $row['total'], (int) $row['completed']]; }, $byCategory)],
    ['Requests by Technician', ['Technician', 'Assigned', 'Completed', 'Avg. Resolution'], array_map(static function ($row) { return [$row['full_name'], (int) $row['total'], (int) $row['completed'], $row['avg_hours'] ? number_format($row['avg_hours'], 1) . 'h' : '-']; }, $byTechnician)],
    ['Requests by Status', ['Status', 'Total'], array_map(static function ($row) { return [$row['status'], (int) $row['total']]; }, $byStatus)],
];

if ($format === 'word' || $format === 'excel') {
    $filename = 'wpu-maintenance-report-' . date('Y-m-d') . '.' . ($format === 'word' ? 'doc' : 'xls');
    header('Content-Type: application/' . ($format === 'word' ? 'msword' : 'vnd.ms-excel'));
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo '<!doctype html><html><head><meta charset="UTF-8"><style>body{font-family:Arial;color:#202531}h1{color:#0B1D6B}h2{color:#0B1D6B;margin-top:24px}table{border-collapse:collapse;width:100%;margin-bottom:18px}th{background:#0B1D6B;color:#fff;text-align:left}th,td{border:1px solid #cfd5e3;padding:7px} .metric{display:inline-block;margin-right:28px;font-weight:bold}</style></head><body>';
    echo '<h1>WPU Maintenance Request Report</h1><p>Generated ' . $label(date('d M Y, g:i a')) . '</p>';
    echo '<p><span class="metric">Total: ' . $number($overall['total']) . '</span><span class="metric">Open: ' . $number($overall['open_requests']) . '</span><span class="metric">Completed: ' . $number($overall['completed']) . '</span><span class="metric">Rejected: ' . $number($overall['rejected']) . '</span><span class="metric">Average resolution: ' . ($overall['avg_hours'] ? $label(number_format($overall['avg_hours'], 1) . ' hours') : '-') . '</span></p>';
    foreach ($sections as [$title, $headers, $rows]) {
        echo '<h2>' . $label($title) . '</h2><table><thead><tr>';
        foreach ($headers as $header) echo '<th>' . $label($header) . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) { echo '<tr>'; foreach ($row as $cell) echo '<td>' . $label($cell) . '</td>'; echo '</tr>'; }
        echo '</tbody></table>';
    }
    echo '</body></html>';
    exit;
}

$pdfEscape = static function ($value) {
    $value = preg_replace('/[^\x20-\x7E]/', '?', (string) $value);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
};
$lines = [
    'WPU MAINTENANCE REQUEST REPORT',
    'Generated: ' . date('Y-m-d H:i'),
    '',
    'SUMMARY',
    'Total requests: ' . $number($overall['total']),
    'Open requests: ' . $number($overall['open_requests']),
    'Completed requests: ' . $number($overall['completed']),
    'Rejected requests: ' . $number($overall['rejected']),
    'Average resolution: ' . ($overall['avg_hours'] ? number_format($overall['avg_hours'], 1) . ' hours' : '-'),
];
foreach ($sections as [$title, $headers, $rows]) {
    $lines[] = '';
    $lines[] = strtoupper($title);
    $lines[] = implode(' | ', $headers);
    foreach ($rows as $row) $lines[] = implode(' | ', array_map('strval', $row));
}

$pages = array_chunk($lines, 48);
$objects = [];
$objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
$objects[] = '<< /Type /Pages /Kids [' . implode(' ', array_map(static function ($index) { return (4 + $index * 2) . ' 0 R'; }, array_keys($pages))) . '] /Count ' . count($pages) . ' >>';
foreach ($pages as $index => $pageLines) {
    $content = "BT /F1 10 Tf 42 760 Td 13 TL\n";
    foreach ($pageLines as $line) $content .= '(' . $pdfEscape($line) . ") Tj T*\n";
    $content .= 'ET';
    $objects[] = '<< /Length ' . strlen($content) . ' >>\nstream\n' . $content . '\nendstream';
    $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 ' . (3 + count($pages) * 2) . ' 0 R >> >> /Contents ' . (3 + $index * 2) . ' 0 R >>';
}
$objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
$pdf = "%PDF-1.4\n";
$offsets = [0];
foreach ($objects as $index => $object) { $offsets[] = strlen($pdf); $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n"; }
$xref = strlen($pdf);
$pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
for ($index = 1; $index <= count($objects); $index++) $pdf .= sprintf('%010d 00000 n \n', $offsets[$index]);
$pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="wpu-maintenance-report-' . date('Y-m-d') . '.pdf"');
echo $pdf;
