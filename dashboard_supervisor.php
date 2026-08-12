<?php
include 'auth.php';
requireLogin();
$user = getCurrentUser();
requireRole(['Supervisor']);

$requestsStmt = $conn->query(
    "SELECT mr.request_id, mr.title, c.category_name, mr.status, u.full_name AS requester_name,
            COALESCE(t.full_name, 'Unassigned') AS technician_name
     FROM maintenance_requests mr
     JOIN categories c ON mr.category_id = c.category_id
     JOIN users u ON mr.requester_id = u.user_id
     LEFT JOIN assignments a ON a.request_id = mr.request_id AND a.is_current = TRUE
     LEFT JOIN users t ON a.technician_id = t.user_id
     ORDER BY mr.submitted_at DESC"
);

$techniciansStmt = $conn->query(
    "SELECT user_id, full_name FROM users WHERE role_id IN (
        SELECT role_id FROM roles WHERE LOWER(role_name) IN ('maintenance officer','technician')
      ) ORDER BY full_name"
);

$summaryResult = $conn->query("SELECT status, COUNT(*) AS count FROM maintenance_requests GROUP BY status");
$statusCounts = ['Submitted' => 0, 'Assigned' => 0, 'Pending' => 0, 'Completed' => 0, 'Rejected' => 0];
while ($row = $summaryResult->fetch_assoc()) {
    $statusCounts[$row['status']] = (int) $row['count'];
}

$unassignedCount = $conn->query(
    "SELECT COUNT(*) AS count FROM maintenance_requests mr
     LEFT JOIN assignments a ON mr.request_id = a.request_id AND a.is_current = TRUE
     WHERE a.assignment_id IS NULL"
)->fetch_assoc()['count'];

$technicianCount = $conn->query(
    "SELECT COUNT(*) AS count FROM users WHERE role_id IN (
        SELECT role_id FROM roles WHERE LOWER(role_name) IN ('maintenance officer','technician')
      )"
)->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Supervisor Dashboard - WPU MRS</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header class="header">
    <div class="container header-top">
      <div class="brand">
        <h1>WPU Maintenance Request System</h1>
        <p>Supervisor dashboard for managing requests and technician assignments.</p>
      </div>
      <nav>
        <a href="dashboard_supervisor.php" class="active">Dashboard</a>
        <a href="logout.php">Logout</a>
      </nav>
    </div>
  </header>

  <main class="main-container">
    <section class="page-banner">
      <h2>Supervisor Command Panel</h2>
      <p class="secondary-text">Review all requests, assign technicians, and keep work moving efficiently.</p>
    </section>

    <div class="dashboard-container">
    <section class="dashboard-summary">
      <h2>Welcome, <?php echo e($user['full_name']); ?></h2>
      <div class="summary-cards">
        <div class="card"><strong>Unassigned Requests</strong><span><?php echo e($unassignedCount); ?></span></div>
        <div class="card"><strong>Assigned</strong><span><?php echo e($statusCounts['Assigned']); ?></span></div>
        <div class="card"><strong>Technicians</strong><span><?php echo e($technicianCount); ?></span></div>
        <div class="card"><strong>Completed</strong><span><?php echo e($statusCounts['Completed']); ?></span></div>
      </div>
    </section>

    <section class="all-requests">
      <div class="section-header">
        <h2>All Maintenance Requests</h2>
      </div>
      <div class="table-responsive">
        <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Category</th>
            <th>Status</th>
            <th>Requester</th>
            <th>Assigned Technician</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($requestsStmt->num_rows === 0): ?>
            <tr><td colspan="6">No requests found.</td></tr>
          <?php else: ?>
            <?php while ($request = $requestsStmt->fetch_assoc()): ?>
              <tr>
                <td>#<?php echo e($request['request_id']); ?></td>
                <td><?php echo e($request['title']); ?></td>
                <td><?php echo e($request['category_name']); ?></td>
                <td><?php echo e($request['status']); ?></td>
                <td><?php echo e($request['requester_name']); ?></td>
                <td><?php echo e($request['technician_name']); ?></td>
              </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
        </table>
      </div>
    </section>

    <section class="assign-job">
      <h2>Assign Request</h2>
      <form action="assign_request.php" method="POST" class="request-form">
        <label for="request_id">Request ID</label>
        <input type="text" id="request_id" name="request_id" placeholder="Enter Request ID" required>

        <label for="technician">Technician</label>
        <select id="technician" name="technician" required>
          <option value="">Select Technician</option>
          <?php while ($tech = $techniciansStmt->fetch_assoc()): ?>
            <option value="<?php echo e($tech['user_id']); ?>"><?php echo e($tech['full_name']); ?></option>
          <?php endwhile; ?>
        </select>

        <label for="due_date">Due Date</label>
        <input type="date" id="due_date" name="due_date">

        <button type="submit" class="btn-primary">Assign</button>
      </form>
    </section>

    <section class="reports">
      <h2>Reports</h2>
      <p>Use the admin report tools to generate request summaries. <a href="reports.php">Open Reports</a></p>
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
    ctx.id = 'supervisorStatusChart';
    document.querySelector('.dashboard-summary').appendChild(ctx);
    new Chart(ctx.getContext('2d'), {
      type: 'bar',
      data: { labels: statusLabels, datasets: [{ label: 'Requests', data: statusData, backgroundColor: '#36A2EB' }] },
      options: { responsive: true, plugins: { legend: { display: false } } }
    });
  })();
</script>
