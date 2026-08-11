<?php
require_once __DIR__ . '/auth.php';
include 'db_connect.php';
include 'audit_log.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT user_id, full_name, role_id, password_hash FROM users WHERE university_email=? AND is_active=TRUE");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password_hash'])) {
            $_SESSION['user_id'] = (int)$row['user_id'];
            $_SESSION['role_id'] = (int)$row['role_id'];
            $_SESSION['role_name'] = role_id_to_name((int)$row['role_id']);

            // Audit log
            writeAuditLog($row['user_id'], "User logged in", "users", $row['user_id'], $_SERVER['REMOTE_ADDR']);

            $dashboard = dashboard_for_role((int)$row['role_id']);
            header('Location: ' . $dashboard);
            exit();
        } else {
            echo "Invalid password.";
        }
    } else {
        echo "User not found.";
    }
}
?>
