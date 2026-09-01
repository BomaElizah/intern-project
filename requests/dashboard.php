<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_role([ROLE_STUDENT, ROLE_STAFF, ROLE_ACADEMIC]);

$user = current_user();

$stmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(status = 'Submitted') AS submitted,
        SUM(status = 'Assigned') AS assigned,
        SUM(status = 'Pending') AS pending,
        SUM(status = 'Completed') AS completed,
        SUM(status = 'Rejected') AS rejected
     FROM maintenance_requests WHERE requester_id = ?"
);
$stmt->execute([$user['user_id']]);
$stats = $stmt->fetch();

$recent = $pdo->prepare(
    'SELECT r.*, b.building_name, c.category_name FROM maintenance_requests r
     JOIN buildings b ON b.building_id = r.building_id
     JOIN categories c ON c.category_id = r.category_id
     WHERE requester_id = ? ORDER BY submitted_at DESC LIMIT 5'
);
$recent->execute([$user['user_id']]);
$recentRequests = $recent->fetchAll();

$activePage = 'dashboard';
$pageTitle = 'My Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="requester-dashboard">
  <aside class="requester-rail">
    <nav class="requester-rail-nav" aria-label="Requester dashboard">
      <a class="active" href="<?= BASE_URL ?>/requests/dashboard.php">Overview</a>
      <a href="<?= BASE_URL ?>/requests/my_requests.php">My Request</a>
      <a href="<?= BASE_URL ?>/requests/submit_request.php">Submit Request</a>
    </nav>
  </aside>
  <main class="requester-main">
    <div class="requester-welcome">
      <div>
        <h1>Welcome, <?= e($user['full_name']) ?></h1>
        <div class="subtitle"><?= e($user['role_name']) ?> Dashboard</div>
      </div>
    </div>
      <div class="stat-grid">
        <div class="stat-card"><div class="stat-icon">#</div><div><div class="stat-value"><?= (int) $stats['total'] ?></div><div class="stat-label">Total Request</div></div></div>
        <div class="stat-card gold"><div class="stat-icon">O</div><div><div class="stat-value"><?= (int) $stats['submitted'] + (int) $stats['assigned'] + (int) $stats['pending'] ?></div><div class="stat-label">Open</div></div></div>
        <div class="stat-card completed"><div class="stat-icon">&#10003;</div><div><div class="stat-value"><?= (int) $stats['completed'] ?></div><div class="stat-label">Completed</div></div></div>
        <div class="stat-card rejected"><div class="stat-icon">!</div><div><div class="stat-value"><?= (int) $stats['rejected'] ?></div><div class="stat-label">Rejected</div></div></div>
      </div>

      <div class="card dashboard-requests-card">
        <div class="flex-between">
          <div>
            <h2 class="mt-0">Recent Requests</h2>
            <p class="text-muted dashboard-card-subtitle">Keep track of your latest maintenance requests.</p>
          </div>
          <a class="btn btn-outline-nav btn-sm" href="<?= BASE_URL ?>/requests/my_requests.php">View My Requests</a>
        </div>
        <?php if (!$recentRequests): ?>
          <div class="empty-state">You haven't submitted any maintenance requests yet.</div>
        <?php else: ?>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Title</th><th>Location</th><th>Category</th><th>Priority</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($recentRequests as $r): ?>
                  <tr>
                    <td><?= e($r['title']) ?></td>
                    <td><?= e($r['building_name']) ?></td>
                    <td><?= e($r['category_name']) ?></td>
                    <td><span class="badge <?= priority_badge_class($r['priority']) ?>"><?= e($r['priority']) ?></span></td>
                    <td><span class="badge <?= status_badge_class($r['status']) ?>"><?= e($r['status']) ?></span></td>
                    <td><?= e(date('d M Y', strtotime($r['submitted_at']))) ?></td>
                    <td><a href="<?= BASE_URL ?>/requests/view_request.php?id=<?= $r['request_id'] ?>">Track</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
  </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
