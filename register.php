<?php
include 'db_connect.php';
include 'send_notification.php';
include 'audit_log.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name       = $_POST['full_name'];
    $email      = $_POST['email'];
    $id_number  = $_POST['id_number'];
    $password   = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role_id    = $_POST['role_id'];

    $stmt = $conn->prepare("INSERT INTO users (full_name, university_email, id_number, password_hash, role_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $name, $email, $id_number, $password, $role_id);

    if ($stmt->execute()) {
        $user_id = $conn->insert_id;

        // 🔔 Send notification to admin (optional)
        sendNotification($user_id, null, "New account created: $name ($email)", "Dashboard");

        // 📝 Write audit log
        writeAuditLog($user_id, "Created new account", "users", $user_id, $_SERVER['REMOTE_ADDR']);

        echo "Registration successful! You can now login.";
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>
