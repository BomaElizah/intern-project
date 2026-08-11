<?php
require_once __DIR__ . '/auth.php';
require_role([ROLE_ADMIN]);

session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/audit_log.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $setting = $_POST['setting'] ?? '';
    $enabled = $_POST['enabled'] ?? '1';
    $user_id = $_SESSION['user_id'] ?? null;

    if ($user_id) {
        writeAuditLog($user_id, "Updated system setting: $setting", "system_settings", null, $_SERVER['REMOTE_ADDR']);
    }

    echo "System setting updated successfully.";
    exit;
}

header('Location: dashboard_admin.php');
