<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_role([ROLE_ADMIN]);

$user = current_user();
$errors = [];
$roles = $pdo->query('SELECT role_id, role_name FROM roles ORDER BY role_id')->fetchAll();

// ---- Create user ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['university_email'] ?? '');
    $idNumber = trim($_POST['id_number'] ?? '');
    $roleId = (int) ($_POST['role_id'] ?? 0);
    $password = $_POST['password'] ?? '';

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $errors[] = 'Your session expired. Please try again.';
    if ($fullName === '') $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid university email is required.';
    elseif (!preg_match('/@wpu\.ac\.pg$/i', $email)) $errors[] = 'Please use a WPU university email address.';
    if ($idNumber === '') $errors[] = 'ID number is required.';
    if (!in_array($roleId, array_column($roles, 'role_id'), true)) $errors[] = 'Please select a valid role.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';

    if (!$errors) {
        $chk = $pdo->prepare('SELECT user_id FROM users WHERE university_email = ? OR id_number = ?');
        $chk->execute([$email, $idNumber]);
        if ($chk->fetch()) {
            $errors[] = 'A user with that email or ID number already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare('INSERT INTO users (full_name, university_email, id_number, password_hash, role_id) VALUES (?, ?, ?, ?, ?)')
                ->execute([$fullName, $email, $idNumber, $hash, $roleId]);
            audit_log($pdo, $user['user_id'], 'Created user account', 'users', (int) $pdo->lastInsertId());
            flash('success', 'User account created.');
            redirect('admin/manage_users.php');
        }
    }
}

// ---- Toggle active/inactive (deactivate instead of delete — Section 7.4) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_active') {
  if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    flash('error', 'Your session expired. Please try again.');
    redirect('admin/manage_users.php');
  }
    $targetId = (int) $_POST['user_id'];
    if ($targetId === $user['user_id']) {
        flash('error', 'You cannot deactivate your own account.');
    } else {
        $stmt = $pdo->prepare('SELECT is_active, full_name FROM users WHERE user_id = ?');
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();
        if ($target) {
            $newState = $target['is_active'] ? 0 : 1;
            $pdo->prepare('UPDATE users SET is_active = ? WHERE user_id = ?')->execute([$newState, $targetId]);
            audit_log($pdo, $user['user_id'], ($newState ? 'Activated' : 'Deactivated') . ' user: ' . $target['full_name'], 'users', $targetId);
            flash('success', 'User ' . ($newState ? 'activated' : 'deactivated') . '.');
        }
    }
    redirect('admin/manage_users.php');
}

$roleFilter = $_GET['role_id'] ?? '';
$sql = "SELECT u.*, r.role_name FROM users u JOIN roles r ON r.role_id = u.role_id";
$params = [];
if ($roleFilter) { $sql .= ' WHERE u.role_id = ?'; $params[] = $roleFilter; }
$sql .= ' ORDER BY u.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$activePage = 'users';
$pageTitle = 'Manage Users';
require __DIR__ . '/../includes/header.php';
?>
<div class="requester-dashboard admin-dashboard">
  <aside class="requester-rail">
    <nav class="requester-rail-nav" aria-label="Administrator dashboard">
      <a href="<?= BASE_URL ?>/admin/dashboard.php">Overview</a>
      <a class="active" href="<?= BASE_URL ?>/admin/manage_users.php">Manage Users</a>
      <a href="<?= BASE_URL ?>/admin/manage_categories.php">Manage Categories</a>
      <a href="<?= BASE_URL ?>/admin/system_settings.php">System Settings</a>
      <a href="<?= BASE_URL ?>/admin/audit_logs.php">Audit Logs</a>
    </nav>
  </aside>
  <main class="requester-main">
    <div class="page-header"><div><h1>Manage Users</h1><div class="subtitle">Create accounts and activate/deactivate existing ones.</div></div></div>
      <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

      <div class="card">
        <h2 class="mt-0">Add User</h2>
        <form method="post" class="form-grid" style="align-items:end;">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="create">
          <div class="form-group"><label>Full Name</label><input type="text" name="full_name" required></div>
          <div class="form-group"><label>University Email</label><input type="email" name="university_email" required></div>
          <div class="form-group"><label>ID Number</label><input type="text" name="id_number" required></div>
          <div class="form-group">
            <label>Role</label>
            <select name="role_id" required>
              <option value="">— Select —</option>
              <?php foreach ($roles as $r): ?><option value="<?= $r['role_id'] ?>"><?= e($r['role_name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Temporary Password</label><input type="password" name="password" minlength="8" required></div>
          <div><button type="submit" class="btn btn-primary">Create User</button></div>
        </form>
      </div>

      <div class="card">
        <div class="flex-between">
          <h2 class="mt-0">All Users</h2>
          <form method="get" class="filters">
            <select name="role_id" onchange="this.form.submit()">
              <option value="">All roles</option>
              <?php foreach ($roles as $r): ?><option value="<?= $r['role_id'] ?>" <?= (string) $roleFilter === (string) $r['role_id'] ? 'selected' : '' ?>><?= e($r['role_name']) ?></option><?php endforeach; ?>
            </select>
          </form>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Name</th><th>Email</th><th>ID Number</th><th>Role</th><th>Status</th><th>Joined</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td><?= e($u['full_name']) ?></td>
                  <td><?= e($u['university_email']) ?></td>
                  <td><?= e($u['id_number']) ?></td>
                  <td><?= e($u['role_name']) ?></td>
                  <td><?= $u['is_active'] ? '<span class="badge badge-completed">Active</span>' : '<span class="badge badge-rejected">Inactive</span>' ?></td>
                  <td><?= e(date('d M Y', strtotime($u['created_at']))) ?></td>
                  <td>
                    <?php if ((int) $u['user_id'] !== (int) $user['user_id']): ?>
                      <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="toggle_active">
                        <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                        <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-danger' : 'btn-accent' ?>" data-confirm="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?> <?= e($u['full_name']) ?>?">
                          <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                        </button>
                      </form>
                    <?php else: ?>
                      <span class="text-muted">(you)</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
