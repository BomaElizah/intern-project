<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_role([ROLE_ADMIN]);

$user = current_user();

$userCounts = $pdo->query(
    "SELECT r.role_name, COUNT(u.user_id) AS total
     FROM roles r LEFT JOIN users u ON u.role_id = r.role_id
     GROUP BY r.role_name ORDER BY r.role_id"
)->fetchAll();

$requestStats = $pdo->query(
    "SELECT COUNT(*) AS total, SUM(status='Completed') AS completed FROM maintenance_requests"
)->fetch();

$recentLogs = $pdo->query(
    "SELECT l.*, u.full_name FROM audit_logs l LEFT JOIN users u ON u.user_id = l.user_id
     ORDER BY l.created_at DESC LIMIT 8"
)->fetchAll();

$activePage = 'dashboard';
$pageTitle = 'Admin Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="requester-dashboard admin-dashboard">
  <aside class="requester-rail">
    <nav class="requester-rail-nav" aria-label="Administrator dashboard">
      <a class="active" href="<?= BASE_URL ?>/admin/dashboard.php">Overview</a>
      <a href="<?= BASE_URL ?>/admin/manage_users.php">Manage Users</a>
      <a href="<?= BASE_URL ?>/admin/manage_categories.php">Manage Categories</a>
      <a href="<?= BASE_URL ?>/admin/system_settings.php">System Settings</a>
      <a href="<?= BASE_URL ?>/admin/audit_logs.php">Audit Logs</a>
    </nav>
  </aside>
  <main class="requester-main">
    <div class="page-header"><div><h1>Administrator Dashboard</h1><div class="subtitle">System-wide oversight of users, categories, and activity.</div></div></div>
      <div class="stat-grid">
        <?php foreach ($userCounts as $rc): ?>
          <div class="stat-card"><div class="stat-value"><?= (int) $rc['total'] ?></div><div class="stat-label"><?= e($rc['role_name']) ?>s</div></div>
        <?php endforeach; ?>
        <div class="stat-card gold"><div class="stat-value"><?= (int) $requestStats['total'] ?></div><div class="stat-label">Total Requests</div></div>
      </div>

      <div class="card">
        <div class="flex-between">
          <h2 class="mt-0">Recent Audit Activity</h2>
          <a href="<?= BASE_URL ?>/admin/audit_logs.php">View full log &rarr;</a>
        </div>
        <?php if (!$recentLogs): ?>
          <div class="empty-state">No activity recorded yet.</div>
        <?php else: ?>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>When</th><th>User</th><th>Action</th></tr></thead>
              <tbody>
                <?php foreach ($recentLogs as $l): ?>
                  <tr><td><?= e(date('d M Y, g:i a', strtotime($l['created_at']))) ?></td><td><?= e($l['full_name'] ?? 'System') ?></td><td><?= e($l['action']) ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <div class="card">
        <h2 class="mt-0">Quick Links</h2>
        <p><a href="<?= BASE_URL ?>/supervisor/reports.php">View Reports &amp; Analytics &rarr;</a></p>
        <p><a href="<?= BASE_URL ?>/supervisor/all_requests.php">View All Maintenance Requests &rarr;</a></p>
      </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
