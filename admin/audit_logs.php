<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_role([ROLE_ADMIN]);

$user = current_user();

$search = trim($_GET['q'] ?? '');
$sql = "SELECT l.*, u.full_name FROM audit_logs l LEFT JOIN users u ON u.user_id = l.user_id";
$params = [];
if ($search !== '') {
    $sql .= ' WHERE l.action LIKE ? OR u.full_name LIKE ?';
    $params = ["%$search%", "%$search%"];
}
$sql .= ' ORDER BY l.created_at DESC LIMIT 500';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$activePage = 'audit';
$pageTitle = 'Audit Logs';
require __DIR__ . '/../includes/header.php';
?>
<div class="requester-dashboard admin-dashboard">
  <aside class="requester-rail">
    <nav class="requester-rail-nav" aria-label="Administrator dashboard">
      <a href="<?= BASE_URL ?>/admin/dashboard.php">Overview</a>
      <a href="<?= BASE_URL ?>/admin/manage_users.php">Manage Users</a>
      <a href="<?= BASE_URL ?>/admin/manage_categories.php">Manage Categories</a>
      <a href="<?= BASE_URL ?>/admin/system_settings.php">System Settings</a>
      <a class="active" href="<?= BASE_URL ?>/admin/audit_logs.php">Audit Logs</a>
    </nav>
  </aside>
  <main class="requester-main">
    <div class="page-header"><div><h1>Audit Logs</h1><div class="subtitle">Security and accountability trail (Section 5.1).</div></div></div>
      <div class="card">
        <form method="get" class="filters">
          <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by user or action...">
          <button type="submit" class="btn btn-outline-nav btn-sm">Search</button>
        </form>
        <?php if (!$logs): ?>
          <div class="empty-state">No matching audit log entries.</div>
        <?php else: ?>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>IP Address</th></tr></thead>
              <tbody>
                <?php foreach ($logs as $l): ?>
                  <tr>
                    <td><?= e(date('d M Y, g:i a', strtotime($l['created_at']))) ?></td>
                    <td><?= e($l['full_name'] ?? 'System') ?></td>
                    <td><?= e($l['action']) ?></td>
                    <td><?= $l['entity_type'] ? e($l['entity_type']) . ($l['entity_id'] ? ' #' . $l['entity_id'] : '') : '—' ?></td>
                    <td><?= e($l['ip_address'] ?? '—') ?></td>
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
