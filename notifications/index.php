<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_read') {
  if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    flash('error', 'Your session expired. Please try again.');
  } else {
    $pdo->prepare('UPDATE notifications SET is_read = TRUE WHERE user_id = ?')->execute([$user['user_id']]);
    flash('success', 'Notifications marked as read.');
  }
  redirect('notifications/index.php');
}

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY sent_at DESC LIMIT 100');
$stmt->execute([$user['user_id']]);
$notifications = $stmt->fetchAll();

$pageTitle = 'Notifications';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-shell container">
  <div class="page-header"><div><h1>Notifications</h1><div class="subtitle">Dashboard alerts about your requests and jobs.</div></div></div>
  <div class="card" style="max-width:800px;">
    <form method="post" style="margin-bottom:18px;">
      <input type="hidden" name="action" value="mark_read">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <button type="submit" class="btn btn-outline-nav btn-sm">Mark All as Read</button>
    </form>
    <?php if (!$notifications): ?>
      <div class="empty-state">You have no notifications yet.</div>
    <?php else: ?>
      <?php foreach ($notifications as $n): ?>
        <div style="padding:12px 0;border-bottom:1px solid var(--border);">
          <?php if ($n['request_id']): ?>
            <a href="<?= BASE_URL ?>/requests/view_request.php?id=<?= $n['request_id'] ?>"><?= e($n['message']) ?></a>
          <?php else: ?>
            <?= e($n['message']) ?>
          <?php endif; ?>
          <div class="text-muted" style="font-size:0.8rem;margin-top:4px;"><?= e(date('d M Y, g:i a', strtotime($n['sent_at']))) ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <a href="<?= BASE_URL . '/' . role_dashboard_path($user['role_id']) ?>">&larr; Back to Dashboard</a>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
