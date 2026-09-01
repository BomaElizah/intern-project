<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_role([ROLE_ADMIN]);

$user = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
  if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    $errors[] = 'Your session expired. Please try again.';
    $action = '';
  }

    if ($action === 'save_settings') {
        $stmt = $pdo->prepare('UPDATE app_settings SET setting_value = ? WHERE setting_key = ?');
        foreach (['site_name', 'support_email', 'max_upload_mb', 'request_backup_frequency'] as $key) {
            if (isset($_POST[$key])) $stmt->execute([trim($_POST[$key]), $key]);
        }
        audit_log($pdo, $user['user_id'], 'Updated system settings');
        flash('success', 'Settings saved.');
        redirect('admin/system_settings.php');
    } elseif ($action === 'add_building') {
        $name = trim($_POST['building_name'] ?? '');
        $code = trim($_POST['building_code'] ?? '') ?: null;
        if ($name === '') {
            $errors[] = 'Building name is required.';
        } else {
            $pdo->prepare('INSERT INTO buildings (building_name, building_code) VALUES (?, ?)')->execute([$name, $code]);
            audit_log($pdo, $user['user_id'], 'Added building: ' . $name, 'buildings', (int) $pdo->lastInsertId());
            flash('success', 'Building added.');
            redirect('admin/system_settings.php');
        }
    } elseif ($action === 'add_room') {
        $buildingId = (int) ($_POST['building_id'] ?? 0);
        $roomNumber = trim($_POST['room_number'] ?? '');
        $roomType = trim($_POST['room_type'] ?? '') ?: null;
        if (!$buildingId || $roomNumber === '') {
            $errors[] = 'Building and room number are required.';
        } else {
            $pdo->prepare('INSERT INTO rooms (building_id, room_number, room_type) VALUES (?, ?, ?)')->execute([$buildingId, $roomNumber, $roomType]);
            audit_log($pdo, $user['user_id'], 'Added room: ' . $roomNumber, 'rooms', (int) $pdo->lastInsertId());
            flash('success', 'Room added.');
            redirect('admin/system_settings.php');
        }
    }
}

$settings = $pdo->query('SELECT setting_key, setting_value FROM app_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$buildings = $pdo->query('SELECT * FROM buildings ORDER BY building_name')->fetchAll();

$activePage = 'settings';
$pageTitle = 'System Settings';
require __DIR__ . '/../includes/header.php';
?>
<div class="requester-dashboard admin-dashboard">
  <aside class="requester-rail">
    <nav class="requester-rail-nav" aria-label="Administrator dashboard">
      <a href="<?= BASE_URL ?>/admin/dashboard.php">Overview</a>
      <a href="<?= BASE_URL ?>/admin/manage_users.php">Manage Users</a>
      <a href="<?= BASE_URL ?>/admin/manage_categories.php">Manage Categories</a>
      <a class="active" href="<?= BASE_URL ?>/admin/system_settings.php">System Settings</a>
      <a href="<?= BASE_URL ?>/admin/audit_logs.php">Audit Logs</a>
    </nav>
  </aside>
  <main class="requester-main">
    <div class="page-header"><div><h1>System Settings</h1><div class="subtitle">General configuration and campus locations.</div></div></div>
      <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

      <div class="card">
        <h2 class="mt-0">General</h2>
        <form method="post" class="form-grid">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="save_settings">
          <div class="form-group"><label>Site Name</label><input type="text" name="site_name" value="<?= e($settings['site_name'] ?? '') ?>"></div>
          <div class="form-group"><label>Support Email</label><input type="email" name="support_email" value="<?= e($settings['support_email'] ?? '') ?>"></div>
          <div class="form-group"><label>Max Upload Size (MB)</label><input type="text" name="max_upload_mb" value="<?= e($settings['max_upload_mb'] ?? '') ?>"></div>
          <div class="form-group"><label>Database Backup Frequency</label>
            <select name="request_backup_frequency">
              <?php foreach (['Daily', 'Weekly'] as $f): ?>
                <option value="<?= $f ?>" <?= ($settings['request_backup_frequency'] ?? '') === $f ? 'selected' : '' ?>><?= $f ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div style="grid-column:1/-1;"><button type="submit" class="btn btn-primary">Save Settings</button></div>
        </form>
      </div>

      <div class="card">
        <h2 class="mt-0">Buildings &amp; Rooms</h2>
        <p class="text-muted">Locations used on the Submit Request form (Sections 6.2-6.4).</p>
        <div class="form-grid">
          <form method="post" class="form-grid" style="grid-column:1/-1;align-items:end;">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_building">
            <div class="form-group"><label>Building Name</label><input type="text" name="building_name" required></div>
            <div class="form-group"><label>Building Code (optional)</label><input type="text" name="building_code"></div>
            <div><button type="submit" class="btn btn-accent">Add Building</button></div>
          </form>
        </div>
        <div class="form-grid">
          <form method="post" class="form-grid" style="grid-column:1/-1;align-items:end;">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_room">
            <div class="form-group">
              <label>Building</label>
              <select name="building_id" required>
                <option value="">— Select —</option>
                <?php foreach ($buildings as $b): ?><option value="<?= $b['building_id'] ?>"><?= e($b['building_name']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label>Room Number</label><input type="text" name="room_number" required></div>
            <div class="form-group"><label>Room Type (optional)</label><input type="text" name="room_type" placeholder="Dormitory, Office, Lab..."></div>
            <div><button type="submit" class="btn btn-accent">Add Room</button></div>
          </form>
        </div>

        <div class="table-wrap" style="margin-top:16px;">
          <table class="data-table">
            <thead><tr><th>Building</th><th>Code</th></tr></thead>
            <tbody>
              <?php foreach ($buildings as $b): ?>
                <tr><td><?= e($b['building_name']) ?></td><td><?= e($b['building_code'] ?? '—') ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
