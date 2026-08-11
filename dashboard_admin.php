<?php
include 'auth.php';
requireLogin();
$user = getCurrentUser();
requireRole(['Administrator']);

$userStmt = $conn->query(
    "SELECT u.user_id, u.full_name, u.university_email, r.role_name, u.is_active
     FROM users u
     LEFT JOIN roles r ON u.role_id = r.role_id
     ORDER BY u.full_name"
);

$categoryStmt = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name");

$requestStmt = $conn->query(
    "SELECT mr.request_id, mr.title, c.category_name, mr.status, u.full_name AS requester_name
     FROM maintenance_requests mr
     JOIN categories c ON mr.category_id = c.category_id
     JOIN users u ON mr.requester_id = u.user_id
     ORDER BY mr.submitted_at DESC"
);

$userCounts = $conn->query("SELECT
    COUNT(*) AS total_users,
    SUM(u.is_active = TRUE) AS active_users,
    SUM(r.role_name = 'Administrator') AS admins,
    SUM(r.role_name = 'Supervisor') AS supervisors
  FROM users u
  LEFT JOIN roles r ON u.role_id = r.role_id")->fetch_assoc();

$categoryCount = $conn->query("SELECT COUNT(*) AS count FROM categories")->fetch_assoc()['count'];
$requestCount = $conn->query("SELECT COUNT(*) AS count FROM maintenance_requests")->fetch_assoc()['count'];

$statusCounts = [
    'Submitted' => 0,
    'Assigned' => 0,
    'Pending' => 0,
    'Completed' => 0,
    'Rejected' => 0,
];

$statusResult = $conn->query("SELECT status, COUNT(*) AS count FROM maintenance_requests GROUP BY status");
while ($row = $statusResult->fetch_assoc()) {
    $statusCounts[$row['status']] = (int) $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - WPU MRS</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header class="header">
    <div class="container header-top">
      <div class="brand">
        <h1>WPU Maintenance Request System</h1>
        <p>Admin dashboard for user management, request oversight, and system summaries.</p>
      </div>
      <nav>
        <a href="dashboard_admin.php" class="active">Dashboard</a>
        <a href="logout.php">Logout</a>
      </nav>
    </div>
  </header>

  <main class="main-container">
    <section class="page-banner">
      <h2>Administrator Control Center</h2>
      <p class="secondary-text">Monitor system performance, review user activity, and manage request operations.</p>
    </section>

    <div class="dashboard-container">
    <section class="dashboard-summary">
      <h2>Welcome, <?php echo e($user['full_name']); ?></h2>
      <div class="summary-cards">
        <div class="card"><strong>Total Requests</strong><span><?php echo e($requestCount); ?></span></div>
        <div class="card"><strong>Active Users</strong><span><?php echo e($userCounts['active_users']); ?></span></div>
        <div class="card"><strong>Categories</strong><span><?php echo e($categoryCount); ?></span></div>
        <div class="card"><strong>Open Requests</strong><span><?php echo e($statusCounts['Submitted'] + $statusCounts['Assigned'] + $statusCounts['Pending']); ?></span></div>
      </div>
    </section>

    <section class="manage-users">
      <h2>Manage Users</h2>
      <table>
        <thead>
          <tr>
            <th>User ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Active</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($userRow = $userStmt->fetch_assoc()): ?>
            <tr>
              <td><?php echo e($userRow['user_id']); ?></td>
              <td><?php echo e($userRow['full_name']); ?></td>
              <td><?php echo e($userRow['university_email']); ?></td>
              <td><?php echo e($userRow['role_name']); ?></td>
              <td><?php echo e($userRow['is_active'] ? 'Yes' : 'No'); ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </section>

    <section class="manage-categories admin-categories">
      <div class="section-header">
        <h2>Categories</h2>
        <form action="manage_categories.php" method="POST" class="inline-form">
          <input type="text" name="category_name" placeholder="Add new category" required>
          <button type="submit" class="btn-primary small">Add</button>
        </form>
      </div>
      <ul class="category-list">
        <?php while ($category = $categoryStmt->fetch_assoc()): ?>
          <li><?php echo e($category['category_name']); ?></li>
        <?php endwhile; ?>
      </ul>
    </section>

    <section class="all-requests">
      <div class="section-header">
        <h2>All Requests</h2>
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
          </tr>
        </thead>
        <tbody>
          <?php if ($requestStmt->num_rows === 0): ?>
            <tr><td colspan="5">No requests found.</td></tr>
          <?php else: ?>
            <?php while ($request = $requestStmt->fetch_assoc()): ?>
              <tr>
                <td>#<?php echo e($request['request_id']); ?></td>
                <td><?php echo e($request['title']); ?></td>
                <td><?php echo e($request['category_name']); ?></td>
                <td><?php echo e($request['status']); ?></td>
                <td><?php echo e($request['requester_name']); ?></td>
              </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
        </table>
      </div>
    </section>
  </div>
  </main>
</body>
</html>
