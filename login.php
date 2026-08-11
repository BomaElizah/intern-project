<?php
session_start();
include 'db_connect.php';
include 'audit_log.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT user_id, full_name, role_id, password_hash FROM users WHERE university_email=? AND is_active=TRUE");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password_hash'])) {
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['role_id'] = $row['role_id'];

            // Audit log
            writeAuditLog($row['user_id'], "User logged in", "users", $row['user_id'], $_SERVER['REMOTE_ADDR']);

            header("Location: ../pages/dashboard_student.html"); // adjust per role
            exit();
        } else {
            echo "Invalid password.";
        }
    } else {
        echo "User not found.";
    }
}
?>
