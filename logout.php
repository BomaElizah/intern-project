<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    audit_log($pdo, $_SESSION['user_id'], 'User logged out', 'users', $_SESSION['user_id']);
}
$_SESSION = [];
session_destroy();
session_start();
flash('info', 'You have been signed out.');
redirect('login.php');
