<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect(role_dashboard_path($_SESSION['role_id']));
}

$errors = [];
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
      $errors[] = 'Your session expired. Please try again.';
    } elseif ($identifier === '' || $password === '') {
        $errors[] = 'Please enter your email/ID and password.';
    } else {
        // FR-02: log in with username/Student ID/email and password.
        $stmt = $pdo->prepare(
            'SELECT u.*, r.role_name FROM users u JOIN roles r ON r.role_id = u.role_id
             WHERE u.university_email = ? OR u.id_number = ? LIMIT 1'
        );
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $errors[] = 'Incorrect email/ID or password.';
        } elseif (!$user['is_active']) {
            $errors[] = 'This account has been deactivated. Please contact an administrator.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role_id'] = (int) $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['university_email'] = $user['university_email'];
            audit_log($pdo, $user['user_id'], 'User logged in', 'users', $user['user_id']);
            flash('success', 'Welcome back, ' . $user['full_name'] . '!');
            redirect(role_dashboard_path($user['role_id']));
        }
    }
}

$pageTitle = 'Sign In';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="logo-row"><img src="<?= BASE_URL ?>/assets/img/logo.png" alt="WPU logo"></div>
    <h1>Sign In</h1>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="form-group">
        <label for="identifier">University Email or ID Number</label>
        <input type="text" id="identifier" name="identifier" value="<?= e($identifier) ?>" required autofocus>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;">Sign In</button>
    </form>
    <p class="switch-link"><a href="<?= BASE_URL ?>/forgot_password.php">Forgot your password?</a></p>
    <p class="switch-link">New here? <a href="<?= BASE_URL ?>/register.php">Create an account</a></p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
