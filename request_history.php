<?php
include 'auth.php';
requireLogin();
$user = getCurrentUser();

$requestId = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;

$requestStmt = $conn->prepare(
    "SELECT mr.request_id, mr.title, mr.description, mr.status, DATE(mr.submitted_at) AS submitted_at, c.category_name
     FROM maintenance_requests mr
     JOIN categories c ON mr.category_id = c.category_id
     WHERE mr.request_id = ? AND mr.requester_id = ?"
);
$requestStmt->bind_param('ii', $requestId, $user['user_id']);
$requestStmt->execute();
$requestResult = $requestStmt->get_result();
$request = $requestResult->fetch_assoc();

$history = [];
if ($request) {
    $historyStmt = $conn->prepare(
        "SELECT h.history_id, h.old_status, h.new_status, h.changed_at, h.remarks, u.full_name AS changed_by
         FROM request_status_history h
         LEFT JOIN users u ON h.changed_by = u.user_id
         WHERE h.request_id = ?
         ORDER BY h.changed_at ASC"
    );
    $historyStmt->bind_param('i', $requestId);
    $historyStmt->execute();
    $history = $historyStmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Request History - WPU MRS</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header class="header">
    <div class="container header-top">
      <div class="brand">
        <h1>Request History</h1>
        <p>Track status changes and review full request details.</p>
      </div>
      <nav>
        <a href="dashboard_student.php">Dashboard</a>
        <a href="logout.php">Logout</a>
      </nav>
    </div>
  </header>

  <main class="main-container">
    <section class="page-banner">
      <h2>Request Status History</h2>
      <p class="secondary-text">View request updates, timestamps, and who changed the status.</p>
    </section>

    <div class="dashboard-container">
    <?php if (!$request): ?>
      <section class="error-message">
        <p>Request not found or you do not have permission to view this request.</p>
      </section>
    <?php else: ?>
      <section class="request-details">
        <h2>Request #<?php echo e($request['request_id']); ?></h2>
        <p><strong>Title:</strong> <?php echo e($request['title']); ?></p>
        <p><strong>Category:</strong> <?php echo e($request['category_name']); ?></p>
        <p><strong>Status:</strong> <?php echo e($request['status']); ?></p>
        <p><strong>Submitted:</strong> <?php echo e($request['submitted_at']); ?></p>
        <p><strong>Description:</strong> <?php echo e($request['description']); ?></p>
      </section>

      <section class="status-history">
        <h2>Status History</h2>
        <?php if ($history->num_rows === 0): ?>
          <p>No history entries available for this request.</p>
        <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>Time</th>
                <th>From</th>
                <th>To</th>
                <th>Changed By</th>
                <th>Remarks</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $history->fetch_assoc()): ?>
                <tr>
                  <td><?php echo e($row['changed_at']); ?></td>
                  <td><?php echo e($row['old_status']); ?></td>
                  <td><?php echo e($row['new_status']); ?></td>
                  <td><?php echo e($row['changed_by'] ?? 'System'); ?></td>
                  <td><?php echo e($row['remarks']); ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </section>
    <?php endif; ?>
    </div>
  </main>
</body>
</html>
