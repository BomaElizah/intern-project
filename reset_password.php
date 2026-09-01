<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$errors = [];
$done = false;

$stmt = $pdo->prepare('SELECT * FROM password_resets WHERE token = ? AND used = FALSE AND expires_at > NOW()');
$stmt->execute([$token]);
$reset = $stmt->fetch();

if (!$reset) {
    $errors[] = 'This password reset link is invalid or has expired. Please request a new one.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $errors[] = 'Your session expired. Please try again.';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters long.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')->execute([$hash, $reset['user_id']]);
        $pdo->prepare('UPDATE password_resets SET used = TRUE WHERE reset_id = ?')->execute([$reset['reset_id']]);
        audit_log($pdo, $reset['user_id'], 'Password reset', 'users', $reset['user_id']);
        $done = true;
    }
}

$pageTitle = 'Reset Password';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="logo-row"><img src="<?= BASE_URL ?>/assets/img/logo.png" alt="WPU logo"></div>
    <h1>Reset Password</h1>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <?php if ($done): ?>
      <div class="alert alert-success">Your password has been reset. You can now sign in.</div>
      <p class="switch-link"><a class="btn btn-primary" href="<?= BASE_URL ?>/login.php">Go to Sign In</a></p>
    <?php elseif ($reset): ?>
      <form method="post" novalidate>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="form-group">
          <label for="password">New Password</label>
          <input type="password" id="password" name="password" minlength="8" required>
        </div>
        <div class="form-group">
          <label for="confirm_password">Confirm New Password</label>
          <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;">Reset Password</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
