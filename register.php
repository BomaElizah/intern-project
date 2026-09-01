<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect(role_dashboard_path($_SESSION['role_id']));
}

$errors = [];
$old = ['full_name' => '', 'university_email' => '', 'id_number' => '', 'role_id' => '', 'phone_number' => ''];

// FR-03 roles selectable at self-registration (Administrator accounts are created by an admin only).
$roles = $pdo->query('SELECT role_id, role_name FROM roles WHERE role_name != "Administrator" ORDER BY role_id')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['university_email'] = trim($_POST['university_email'] ?? '');
    $old['id_number'] = trim($_POST['id_number'] ?? '');
    $old['role_id'] = $_POST['role_id'] ?? '';
    $old['phone_number'] = trim($_POST['phone_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $errors[] = 'Your session expired. Please try again.';
    if ($old['full_name'] === '') $errors[] = 'Full name is required.';
    if (!filter_var($old['university_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid university email is required.';
    } elseif (!preg_match('/@(student\.)?wpu\.ac\.pg$/i', $old['university_email'])) {
      $errors[] = 'Please use your WPU university email address.';
    }
    if ($old['id_number'] === '') $errors[] = 'Your Student ID or Staff ID is required.';
    if (!in_array($old['role_id'], array_column($roles, 'role_id'), false) && !in_array((int) $old['role_id'], array_column($roles, 'role_id'), true)) {
        $errors[] = 'Please select a valid role.';
    }
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters long.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE university_email = ? OR id_number = ?');
        $stmt->execute([$old['university_email'], $old['id_number']]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with that university email or ID number already exists.';
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare(
            'INSERT INTO users (full_name, university_email, id_number, password_hash, role_id, phone_number) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$old['full_name'], $old['university_email'], $old['id_number'], $hash, (int) $old['role_id'], $old['phone_number'] ?: null]);
        audit_log($pdo, (int) $pdo->lastInsertId(), 'Account registered', 'users', (int) $pdo->lastInsertId());
        flash('success', 'Account created successfully. You can now sign in.');
        redirect('login.php');
    }
}

$pageTitle = 'Create Account';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="logo-row"><img src="<?= BASE_URL ?>/assets/img/logo.png" alt="WPU logo"></div>
    <h1>Create Your Account</h1>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="form-group">
        <label for="full_name">Full Name</label>
        <input type="text" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>" required>
      </div>
      <div class="form-group">
        <label for="university_email">University Email</label>
        <input type="email" id="university_email" name="university_email" value="<?= e($old['university_email']) ?>" required>
      </div>
      <div class="form-group">
        <label for="id_number">Student ID / Staff ID</label>
        <input type="text" id="id_number" name="id_number" value="<?= e($old['id_number']) ?>" required>
      </div>
      <div class="form-group">
        <label for="role_id">I am a...</label>
        <select id="role_id" name="role_id" required>
          <option value="">— Select role —</option>
          <?php foreach ($roles as $r): ?>
            <option value="<?= $r['role_id'] ?>" <?= (string) $old['role_id'] === (string) $r['role_id'] ? 'selected' : '' ?>><?= e($r['role_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="phone_number">Phone Number (optional)</label>
        <input type="tel" id="phone_number" name="phone_number" value="<?= e($old['phone_number']) ?>">
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" minlength="8" required>
      </div>
      <div class="form-group">
        <label for="confirm_password">Confirm Password</label>
        <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;">Create Account</button>
    </form>
    <p class="switch-link">Already have an account? <a href="<?= BASE_URL ?>/login.php">Sign in</a></p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
