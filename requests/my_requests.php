<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_role([ROLE_STUDENT, ROLE_STAFF, ROLE_ACADEMIC]);

$user = current_user();

$status = $_GET['status'] ?? '';
$sql = "SELECT r.*, b.building_name, rm.room_number, c.category_name
        FROM maintenance_requests r
        JOIN buildings b ON b.building_id = r.building_id
        LEFT JOIN rooms rm ON rm.room_id = r.room_id
        JOIN categories c ON c.category_id = r.category_id
        WHERE r.requester_id = ?";
$params = [$user['user_id']];
if ($status && in_array($status, ['Submitted', 'Assigned', 'Pending', 'Completed', 'Rejected'], true)) {
    $sql .= ' AND r.status = ?';
    $params[] = $status;
}
$sql .= ' ORDER BY r.submitted_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$activePage = 'my_requests';
$pageTitle = 'My Requests';
require __DIR__ . '/../includes/header.php';
?>
<div class="requester-dashboard">
  <aside class="requester-rail">
    <nav class="requester-rail-nav" aria-label="Requester dashboard">
      <a href="<?= BASE_URL ?>/requests/dashboard.php">Overview</a>
      <a class="active" href="<?= BASE_URL ?>/requests/my_requests.php">My Request</a>
      <a href="<?= BASE_URL ?>/requests/submit_request.php">Submit Request</a>
    </nav>
  </aside>
  <main class="requester-main">
    <div class="page-header"><div><h1>My Requests</h1><div class="subtitle">Every request you've submitted, and where it stands.</div></div></div>
      <div class="card">
        <form method="get" class="filters">
          <select name="status" onchange="this.form.submit()">
            <option value="">All statuses</option>
            <?php foreach (['Submitted', 'Assigned', 'Pending', 'Completed', 'Rejected'] as $s): ?>
              <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <?php if (!$requests): ?>
          <div class="empty-state">No requests found for this filter.</div>
        <?php else: ?>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Title</th><th>Location</th><th>Category</th><th>Priority</th><th>Status</th><th>Submitted</th><th>Completed</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($requests as $r): ?>
                  <tr>
                    <td><?= e($r['title']) ?></td>
                    <td><?= e($r['building_name']) ?><?= $r['room_number'] ? ' / ' . e($r['room_number']) : '' ?></td>
                    <td><?= e($r['category_name']) ?></td>
                    <td><span class="badge <?= priority_badge_class($r['priority']) ?>"><?= e($r['priority']) ?></span></td>
                    <td><span class="badge <?= status_badge_class($r['status']) ?>"><?= e($r['status']) ?></span></td>
                    <td><?= e(date('d M Y', strtotime($r['submitted_at']))) ?></td>
                    <td><?= $r['completed_at'] ? e(date('d M Y', strtotime($r['completed_at']))) : '—' ?></td>
                    <td><a href="<?= BASE_URL ?>/requests/view_request.php?id=<?= $r['request_id'] ?>">Track</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
