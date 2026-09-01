<?php
/**
 * Shared site header / navigation.
 * Expects config/config.php and includes/functions.php to already be loaded,
 * and an optional $pageTitle variable set by the including page.
 */
$user = current_user();
$unread = $user ? unread_notification_count($pdo, $user['user_id']) : 0;
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' — ' : '' ?><?= APP_NAME ?></title>
<link rel="icon" href="<?= BASE_URL ?>/assets/img/logo.png">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<script>window.APP_BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
</head>
<body>
<header class="site-header">
  <div class="navbar">
    <a class="brand" href="<?= BASE_URL ?>/index.php">
      <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="Western Pacific University logo">
      <span class="brand-text"><strong>Western Pacific University</strong><span>Maintenance Request System</span></span>
    </a>
    <button class="menu-toggle" type="button" aria-label="Main Menu" title="Main Menu" aria-expanded="false" aria-controls="main-menu">&#9776;</button>
    <nav class="nav-links" id="main-menu">
      <?php if ($user): ?>
        <a href="<?= BASE_URL ?>/index.php">Home</a>
        <a href="<?= BASE_URL . '/' . role_dashboard_path($user['role_id']) ?>">Dashboard</a>
        <?php if (in_array($user['role_id'], [ROLE_STUDENT, ROLE_STAFF, ROLE_ACADEMIC], true)): ?>
          <a href="<?= BASE_URL ?>/requests/submit_request.php">Submit Request</a>
        <?php endif; ?>
        <span class="nav-account">
          <a class="notif-bell" href="<?= BASE_URL ?>/notifications/index.php" title="Notifications" aria-label="Notifications">&#128276;<?php if ($unread): ?><span class="notif-count"><?= $unread ?></span><?php endif; ?></a>
          <span class="user-menu">
            <span class="user-avatar"><?= e(strtoupper(substr($user['full_name'], 0, 1) . substr(strrchr($user['full_name'], ' ') ?: $user['full_name'], 1, 1))) ?></span>
            <span class="user-menu-copy"><strong><?= e($user['full_name']) ?></strong><small><?= e($user['role_name']) ?></small></span>
          </span>
          <a class="btn-gold" href="<?= BASE_URL ?>/logout.php">Logout</a>
        </span>
      <?php else: ?>
        <a href="<?= BASE_URL ?>/index.php">Home</a>
        <a href="<?= BASE_URL ?>/login.php">Sign In</a>
        <a class="btn-gold" href="<?= BASE_URL ?>/register.php">Create Account</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<?php $flashes = get_flashes(); if ($flashes): ?>
  <div class="container" style="padding-top:16px;">
    <?php foreach ($flashes as $f): ?>
      <div class="alert alert-<?= e($f['type'] === 'error' ? 'error' : $f['type']) ?>" data-autohide><?= e($f['message']) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
