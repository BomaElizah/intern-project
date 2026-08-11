<?php
require_once __DIR__ . '/auth.php';
http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Access Denied</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="login-container">
    <h2>Access Denied</h2>
    <p>You do not have permission to view this page.</p>
    <p><a href="<?= dashboard_for_role((int)($_SESSION['role_id'] ?? 0)); ?>">Return to your dashboard</a></p>
    <p><a href="login.html">Login as another user</a></p>
  </div>
</body>
</html>
