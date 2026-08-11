<?php
session_start();
include 'db_connect.php';
include 'audit_log.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}

$summary = [];
$summaryStmt = $conn->prepare("SELECT COUNT(*) AS total_requests FROM maintenance_requests");
$summaryStmt->execute();
$summary['total_requests'] = $summaryStmt->get_result()->fetch_assoc()['total_requests'];

$summaryStmt = $conn->prepare("SELECT COUNT(*) AS open_requests FROM maintenance_requests WHERE status IN ('Submitted', 'Assigned', 'Pending')");
$summaryStmt->execute();
$summary['open_requests'] = $summaryStmt->get_result()->fetch_assoc()['open_requests'];

$summaryStmt = $conn->prepare("SELECT COUNT(*) AS completed_requests FROM maintenance_requests WHERE status = 'Completed'");
$summaryStmt->execute();
$summary['completed_requests'] = $summaryStmt->get_result()->fetch_assoc()['completed_requests'];

$reportRows = [];
$reportSql = "SELECT status, COUNT(*) AS total FROM maintenance_requests GROUP BY status ORDER BY status";
$reportResult = $conn->query($reportSql);
while ($row = $reportResult->fetch_assoc()) {
    $reportRows[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports - WPU MRS</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header class="header">
    <h1>WPU Maintenance Request System</h1>
    <nav>
      <a href="dashboard_student.html">Dashboard</a>
      <a href="notifications.php">Notifications</a>
      <a href="reports.php" class="active">Reports</a>
      <a href="index.html">Logout</a>
    </nav>
  </header>

  <main class="dashboard-container">
    <section>
      <h2>System Summary</h2>
      <div class="summary-grid">
        <div class="summary-card"><h3>Total Requests</h3><p><?php echo (int) $summary['total_requests']; ?></p></div>
        <div class="summary-card"><h3>Open Requests</h3><p><?php echo (int) $summary['open_requests']; ?></p></div>
        <div class="summary-card"><h3>Completed Requests</h3><p><?php echo (int) $summary['completed_requests']; ?></p></div>
      </div>
      <table>
        <thead><tr><th>Status</th><th>Count</th></tr></thead>
        <tbody>
          <?php foreach ($reportRows as $row): ?>
            <tr><td><?php echo htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo (int) $row['total']; ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  </main>
</body>
</html>
