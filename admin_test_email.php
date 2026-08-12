<?php
include 'auth.php';
requireRole(['Administrator']);
include 'config.php';
include 'mailer.php';
include 'audit_log.php';

$sent = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $to = trim($_POST['to_email'] ?? '');
    $toName = trim($_POST['to_name'] ?? '');
    $subject = trim($_POST['subject'] ?? 'WPU Maintenance Test');
    $message = trim($_POST['message'] ?? 'This is a test message from WPU MRS.');

    if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $sent = sendEmailSMTP($to, $toName, $subject, $message);
        writeAuditLog($_SESSION['user_id'] ?? null, "Sent test email to $to", 'system', null, $_SERVER['REMOTE_ADDR']);
    } else {
        $sent = false;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin - Send Test Email</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="admin-container">
    <h2>Send Test Email</h2>
    <?php if ($sent === true) echo '<p class="success">Email sent successfully.</p>'; ?>
    <?php if ($sent === false) echo '<p class="error">Email failed to send (check SMTP config).</p>'; ?>

    <form method="POST" action="admin_test_email.php">
      <label for="to_email">Recipient email</label>
      <input type="email" id="to_email" name="to_email" required>

      <label for="to_name">Recipient name (optional)</label>
      <input type="text" id="to_name" name="to_name">

      <label for="subject">Subject</label>
      <input type="text" id="subject" name="subject" value="WPU Maintenance Test">

      <label for="message">Message</label>
      <textarea id="message" name="message" rows="6">This is a test message from WPU MRS.</textarea>

      <?php echo '<input type="hidden" name="csrf_token" value="' . e(generateCsrfToken()) . '">'; ?>

      <button type="submit" class="btn-primary">Send Test Email</button>
    </form>
    <p><a href="dashboard_admin.php">Back to admin dashboard</a></p>
  </div>
</body>
</html>
