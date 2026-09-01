<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_role([ROLE_SUPERVISOR]);

$user = current_user();

$stats = $pdo->query(
    "SELECT COUNT(*) AS total,
            SUM(status = 'Submitted') AS submitted,
            SUM(status = 'Assigned') AS assigned,
            SUM(status = 'Pending') AS pending,
            SUM(status = 'Completed') AS completed,
            SUM(status = 'Rejected') AS rejected
     FROM maintenance_requests"
)->fetch();

$unassigned = $pdo->query(
    "SELECT r.request_id, r.title, r.priority, r.submitted_at, b.building_name, c.category_name
     FROM maintenance_requests r
     JOIN buildings b ON b.building_id = r.building_id
     JOIN categories c ON c.category_id = r.category_id
     WHERE r.status = 'Submitted'
     ORDER BY FIELD(r.priority,'Urgent','High','Medium','Low'), r.submitted_at
     LIMIT 8"
)->fetchAll();

$activePage = 'dashboard';
$pageTitle = 'Supervisor Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="requester-dashboard supervisor-dashboard">
  <aside class="requester-rail">
    <nav class="requester-rail-nav" aria-label="Supervisor dashboard">
      <a class="active" href="<?= BASE_URL ?>/supervisor/dashboard.php">Overview</a>
      <a href="<?= BASE_URL ?>/supervisor/all_requests.php">View All Requests</a>
      <a href="<?= BASE_URL ?>/supervisor/reports.php">Generate Reports</a>
    </nav>
  </aside>
  <main class="requester-main">
    <div class="page-header"><div><h1>Supervisor Dashboard</h1><div class="subtitle">Campus-wide view of maintenance requests.</div></div></div>
      <div class="stat-grid">
        <div class="stat-card"><div class="stat-value"><?= (int) $stats['total'] ?></div><div class="stat-label">Total Requests</div></div>
        <div class="stat-card gold"><div class="stat-value"><?= (int) $stats['submitted'] ?></div><div class="stat-label">Awaiting Assignment</div></div>
        <div class="stat-card"><div class="stat-value"><?= (int) $stats['assigned'] + (int) $stats['pending'] ?></div><div class="stat-label">In Progress</div></div>
        <div class="stat-card"><div class="stat-value"><?= (int) $stats['completed'] ?></div><div class="stat-label">Completed</div></div>
        <div class="stat-card"><div class="stat-value"><?= (int) $stats['rejected'] ?></div><div class="stat-label">Rejected</div></div>
      </div>

      <div class="card">
        <div class="flex-between">
          <h2 class="mt-0">Requests Awaiting Assignment</h2>
          <a href="<?= BASE_URL ?>/supervisor/all_requests.php">View all requests &rarr;</a>
        </div>
        <?php if (!$unassigned): ?>
          <div class="empty-state">Nothing waiting on assignment right now. Great work!</div>
        <?php else: ?>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Title</th><th>Location</th><th>Category</th><th>Priority</th><th>Submitted</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($unassigned as $r): ?>
                  <tr>
                    <td><?= e($r['title']) ?></td>
                    <td><?= e($r['building_name']) ?></td>
                    <td><?= e($r['category_name']) ?></td>
                    <td><span class="badge <?= priority_badge_class($r['priority']) ?>"><?= e($r['priority']) ?></span></td>
                    <td><?= e(date('d M Y', strtotime($r['submitted_at']))) ?></td>
                    <td><a href="<?= BASE_URL ?>/supervisor/all_requests.php?assign=<?= $r['request_id'] ?>">Assign &rarr;</a></td>
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
