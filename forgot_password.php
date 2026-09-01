<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$errors = [];
$resetLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['university_email'] ?? '');
    $idNumber = trim($_POST['id_number'] ?? '');

    $user = null;
    $now = time();
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
      $errors[] = 'Your session expired. Please try again.';
    } elseif (!empty($_SESSION['reset_attempts']) && $_SESSION['reset_attempts']['until'] > $now && $_SESSION['reset_attempts']['count'] >= 5) {
      $errors[] = 'Too many reset attempts. Please try again later.';
    } else {
      if (empty($_SESSION['reset_attempts']) || $_SESSION['reset_attempts']['until'] <= $now) $_SESSION['reset_attempts'] = ['count' => 0, 'until' => $now + 900];
      $_SESSION['reset_attempts']['count']++;
      $stmt = $pdo->prepare('SELECT user_id FROM users WHERE university_email = ? AND id_number = ?');
      $stmt->execute([$email, $idNumber]);
      $user = $stmt->fetch();
    }

    if (!$user) {
        $errors[] = 'No account matches that university email and ID number.';
    } else {
        $token = bin2hex(random_bytes(24));
        $pdo->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))')
            ->execute([$user['user_id'], $token]);
        // Section 3.2 excludes email delivery from v1.0 scope, so the reset
        // link is surfaced directly on screen rather than emailed.
        $resetLink = BASE_URL . '/reset_password.php?token=' . $token;
    }
}

$pageTitle = 'Forgot Password';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="logo-row"><img src="<?= BASE_URL ?>/assets/img/logo.png" alt="WPU logo"></div>
    <h1>Forgot Password</h1>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <?php if ($resetLink): ?>
      <div class="alert alert-success">
        Identity verified. Since this v1.0 system does not send email (Section 3.2),
        use this one-time link now — it expires in 30 minutes:<br>
        <a href="<?= e($resetLink) ?>"><?= e($resetLink) ?></a>
      </div>
    <?php else: ?>
      <p class="text-muted">Enter your university email and ID number to verify your identity and reset your password.</p>
      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="form-group">
          <label for="university_email">University Email</label>
          <input type="email" id="university_email" name="university_email" required>
        </div>
        <div class="form-group">
          <label for="id_number">Student ID / Staff ID</label>
          <input type="text" id="id_number" name="id_number" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;">Verify &amp; Continue</button>
      </form>
    <?php endif; ?>
    <p class="switch-link"><a href="<?= BASE_URL ?>/login.php">Back to Sign In</a></p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
