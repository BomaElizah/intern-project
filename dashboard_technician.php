<?php
include 'auth.php';
requireLogin();
$user = getCurrentUser();
requireRole(['Technician', 'Maintenance Officer']);
$userId = $user['user_id'];

$jobStmt = $conn->prepare(
    "SELECT mr.request_id, mr.title, c.category_name, mr.priority, mr.status, DATE(a.due_date) AS due_date
     FROM assignments a
     JOIN maintenance_requests mr ON a.request_id = mr.request_id
     JOIN categories c ON mr.category_id = c.category_id
     WHERE a.technician_id = ? AND a.is_current = TRUE
     ORDER BY a.due_date ASC"
);
$jobStmt->bind_param('i', $userId);
$jobStmt->execute();
$assignedJobs = $jobStmt->get_result();

$assignedCount = $assignedJobs->num_rows;

$statusStmt = $conn->prepare(
    "SELECT mr.status, COUNT(*) AS count
     FROM assignments a
     JOIN maintenance_requests mr ON a.request_id = mr.request_id
     WHERE a.technician_id = ? AND a.is_current = TRUE
     GROUP BY mr.status"
);
$statusStmt->bind_param('i', $userId);
$statusStmt->execute();
$statusResult = $statusStmt->get_result();
$statusCounts = ['Assigned' => 0, 'In Progress' => 0, 'Pending' => 0, 'Completed' => 0];
while ($row = $statusResult->fetch_assoc()) {
    $statusCounts[$row['status']] = (int) $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Technician Dashboard - WPU MRS</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header class="header">
    <div class="container header-top">
      <div class="brand">
        <h1>WPU Maintenance Request System</h1>
        <p>Technician dashboard for managing assigned work and updating job progress.</p>
      </div>
      <nav>
        <a href="dashboard_technician.php" class="active">Dashboard</a>
        <a href="logout.php">Logout</a>
      </nav>
    </div>
  </header>

  <main class="main-container">
    <section class="page-banner">
      <h2>Technician Workbench</h2>
      <p class="secondary-text">Review active assignments, update job status, and upload evidence with ease.</p>
    </section>

    <div class="dashboard-container">
    <section class="dashboard-summary">
      <h2>Welcome, <?php echo e($user['full_name']); ?></h2>
      <div class="summary-cards">
        <div class="card"><strong>Assigned Jobs</strong><span><?php echo e($assignedCount); ?></span></div>
        <div class="card"><strong>In Progress</strong><span><?php echo e($statusCounts['In Progress']); ?></span></div>
        <div class="card"><strong>Pending</strong><span><?php echo e($statusCounts['Pending']); ?></span></div>
        <div class="card"><strong>Completed</strong><span><?php echo e($statusCounts['Completed']); ?></span></div>
      </div>
    </section>

    <section class="assigned-jobs">
      <div class="section-header">
        <h2>My Assigned Jobs</h2>
      </div>
      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Title</th>
              <th>Category</th>
              <th>Priority</th>
              <th>Status</th>
              <th>Due Date</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($assignedJobs->num_rows === 0): ?>
            <tr><td colspan="6">No assigned jobs at the moment.</td></tr>
          <?php else: ?>
            <?php while ($job = $assignedJobs->fetch_assoc()): ?>
              <tr>
                <td>#<?php echo e($job['request_id']); ?></td>
                <td><?php echo e($job['title']); ?></td>
                <td><?php echo e($job['category_name']); ?></td>
                <td><?php echo e($job['priority']); ?></td>
                <td><?php echo e($job['status']); ?></td>
                <td><?php echo e($job['due_date']); ?></td>
              </tr>
            <?php endwhile; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="update-progress">
      <h2>Update Job Progress</h2>
      <form action="update_status.php" method="POST" class="request-form">
        <label for="request_id">Request ID</label>
        <input type="text" id="request_id" name="request_id" placeholder="Enter Request ID" required>

        <label for="status">Status</label>
        <select id="status" name="status" required>
          <option value="Assigned">Assigned</option>
          <option value="In Progress">In Progress</option>
          <option value="Pending">Pending</option>
          <option value="Completed">Completed</option>
        </select>

        <button type="submit" class="btn-primary">Update Status</button>
      </form>
    </section>

    <section class="work-evidence">
      <h2>Upload Work Evidence</h2>
      <form action="upload_evidence.php" method="POST" enctype="multipart/form-data" class="request-form">
        <label for="request_id_evidence">Request ID</label>
        <input type="text" id="request_id_evidence" name="request_id" required>

        <label for="before_photo">Before Photo</label>
        <input type="file" id="before_photo" name="before_photo">

        <label for="after_photo">After Photo</label>
        <input type="file" id="after_photo" name="after_photo">

        <label for="comment">Technician Comment</label>
        <textarea id="comment" name="comment" rows="3" placeholder="Add notes about the work"></textarea>

        <button type="submit" class="btn-primary">Upload Evidence</button>
      </form>
    </section>
    </div>
  </main>
</body>
</html>
<script src="csrf.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
  (function(){
    const statusData = <?php echo json_encode(array_values($statusCounts)); ?>;
    const statusLabels = <?php echo json_encode(array_keys($statusCounts)); ?>;
    const ctx = document.createElement('canvas');
    ctx.id = 'technicianStatusChart';
    document.querySelector('.dashboard-summary').appendChild(ctx);
    new Chart(ctx.getContext('2d'), {
      type: 'doughnut',
      data: { labels: statusLabels, datasets: [{ data: statusData, backgroundColor: ['#36A2EB','#FF9F40','#FFCD56','#4BC0C0'] }] },
      options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });
  })();
</script>
