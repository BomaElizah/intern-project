<?php
session_start();
include 'db_connect.php';
include 'status_history.php';

$request_id = null;
$historyResult = null;
$error = null;

if (isset($_GET['request_id'])) {
    $request_id = intval($_GET['request_id']);

    if ($request_id <= 0) {
        $error = 'Please provide a valid request ID.';
    } else {
        $stmt = $conn->prepare("SELECT request_id, title, description, status, submitted_at FROM maintenance_requests WHERE request_id=?");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $requestResult = $stmt->get_result();

        $requestRow = $requestResult->fetch_assoc();
        if ($requestRow) {
            $historyResult = getRequestStatusHistory($request_id);
        } else {
            $error = 'Request not found.';
        }
    }
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
    <h1>Request History</h1>
    <nav>
      <a href="dashboard_student.html">Back to Dashboard</a>
      <a href="index.html">Logout</a>
    </nav>
  </header>

  <main class="dashboard-container">
    <?php if ($error): ?>
      <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php elseif ($historyResult): ?>
      <section class="request-summary">
        <h2>Request #<?php echo htmlspecialchars($requestRow['request_id']); ?></h2>
        <p><strong>Title:</strong> <?php echo htmlspecialchars($requestRow['title']); ?></p>
        <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($requestRow['description'])); ?></p>
        <p><strong>Current Status:</strong> <?php echo htmlspecialchars($requestRow['status']); ?></p>
        <p><strong>Submitted:</strong> <?php echo htmlspecialchars($requestRow['submitted_at']); ?></p>
      </section>

      <section class="status-history">
        <h2>Status History</h2>
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Old Status</th>
              <th>New Status</th>
              <th>Changed By</th>
              <th>Remarks</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($historyRow = $historyResult->fetch_assoc()): ?>
              <?php $userName = getUserFullName($historyRow['changed_by']); ?>
              <tr>
                <td><?php echo htmlspecialchars($historyRow['changed_at']); ?></td>
                <td><?php echo htmlspecialchars($historyRow['old_status']); ?></td>
                <td><?php echo htmlspecialchars($historyRow['new_status']); ?></td>
                <td><?php echo htmlspecialchars($userName); ?></td>
                <td><?php echo htmlspecialchars($historyRow['remarks']); ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </section>
    <?php else: ?>
      <section class="track-request">
        <h2>Track Request</h2>
        <form action="view_request_history.php" method="GET">
          <label for="request_id">Request ID</label>
          <input type="text" id="request_id" name="request_id" placeholder="Enter Request ID" required>
          <button type="submit" class="btn-primary">View History</button>
        </form>
      </section>
    <?php endif; ?>
  </main>
</body>
</html>
