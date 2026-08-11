<?php
include 'auth.php';
requireRole(['Student', 'Academic', 'Staff']);
$user = getCurrentUser();
$userId = $user['user_id'];

$categories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name");
$buildings = $conn->query("SELECT building_id, building_name FROM buildings ORDER BY building_name");
$rooms = $conn->query("SELECT room_id, room_number, building_id FROM rooms ORDER BY room_number");

$statusCounts = [
    'Submitted' => 0,
    'Assigned' => 0,
    'Pending' => 0,
    'Completed' => 0,
    'Rejected' => 0,
];

$statusStmt = $conn->prepare("SELECT status, COUNT(*) AS count FROM maintenance_requests WHERE requester_id = ? GROUP BY status");
$statusStmt->bind_param('i', $userId);
$statusStmt->execute();
$statusResult = $statusStmt->get_result();
while ($row = $statusResult->fetch_assoc()) {
    $statusCounts[$row['status']] = (int) $row['count'];
}

$requestsStmt = $conn->prepare(
    "SELECT mr.request_id, mr.title, c.category_name, mr.status, DATE(mr.submitted_at) AS submitted_at
     FROM maintenance_requests mr
     JOIN categories c ON mr.category_id = c.category_id
     WHERE mr.requester_id = ?
     ORDER BY mr.submitted_at DESC"
);
$requestsStmt->bind_param('i', $userId);
$requestsStmt->execute();
$requests = $requestsStmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Dashboard - WPU MRS</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header class="header">
    <div class="container header-top">
      <div class="brand">
        <h1>WPU Maintenance Request System</h1>
        <p>Student portal for submitting and tracking maintenance requests.</p>
      </div>
      <nav>
        <a href="dashboard_student.php" class="active">Dashboard</a>
        <a href="logout.php">Logout</a>
      </nav>
    </div>
  </header>

  <main class="main-container">
    <section class="page-banner">
      <h2>Student Maintenance Portal</h2>
      <p class="secondary-text">Submit new requests, track current work, and view request history in a polished dashboard.</p>
    </section>

    <div class="dashboard-container">
    <section class="dashboard-summary">
      <h2>Welcome, <?php echo e($user['full_name']); ?></h2>
      <div class="summary-cards">
        <div class="card"><strong>Submitted</strong><span><?php echo e($statusCounts['Submitted']); ?></span></div>
        <div class="card"><strong>Assigned</strong><span><?php echo e($statusCounts['Assigned']); ?></span></div>
        <div class="card"><strong>Pending</strong><span><?php echo e($statusCounts['Pending']); ?></span></div>
        <div class="card"><strong>Completed</strong><span><?php echo e($statusCounts['Completed']); ?></span></div>
      </div>
    </section>

    <section class="submit-request">
      <h2>Submit Maintenance Request</h2>
      <form action="submit_request.php" method="POST" class="request-form">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" placeholder="Short issue title" required>

        <label for="description">Description</label>
        <textarea id="description" name="description" rows="4" placeholder="Describe the issue in detail" required></textarea>

        <label for="building">Building</label>
        <select id="building" name="building" required>
          <option value="">Select Building</option>
          <?php while ($building = $buildings->fetch_assoc()): ?>
            <option value="<?php echo e($building['building_id']); ?>"><?php echo e($building['building_name']); ?></option>
          <?php endwhile; ?>
        </select>

        <label for="room">Room</label>
        <select id="room" name="room">
          <option value="">Select Room (optional)</option>
          <?php while ($room = $rooms->fetch_assoc()): ?>
            <option value="<?php echo e($room['room_id']); ?>"><?php echo e($room['room_number']); ?><?php echo $room['building_id'] ? ' — ' . e($room['building_id']) : ''; ?></option>
          <?php endwhile; ?>
        </select>

        <label for="category">Category</label>
        <select id="category" name="category" required>
          <option value="">Select Category</option>
          <?php while ($category = $categories->fetch_assoc()): ?>
            <option value="<?php echo e($category['category_id']); ?>"><?php echo e($category['category_name']); ?></option>
          <?php endwhile; ?>
        </select>

        <label for="priority">Priority</label>
        <select id="priority" name="priority" required>
          <option value="Low">Low</option>
          <option value="Medium">Medium</option>
          <option value="High">High</option>
          <option value="Urgent">Urgent</option>
        </select>

        <button type="submit" class="btn-primary">Submit Request</button>
      </form>
    </section>

    <section class="my-requests">
      <div class="section-header">
        <h2>My Requests</h2>
      </div>
      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Title</th>
              <th>Category</th>
              <th>Status</th>
              <th>Submitted</th>
              <th>Track</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($requests->num_rows === 0): ?>
            <tr><td colspan="6">No requests found.</td></tr>
          <?php else: ?>
            <?php while ($request = $requests->fetch_assoc()): ?>
              <tr>
                <td>#<?php echo e($request['request_id']); ?></td>
                <td><?php echo e($request['title']); ?></td>
                <td><?php echo e($request['category_name']); ?></td>
                <td><?php echo e($request['status']); ?></td>
                <td><?php echo e($request['submitted_at']); ?></td>
                <td><a href="request_history.php?request_id=<?php echo e($request['request_id']); ?>">Track</a></td>
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
