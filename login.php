<?php
session_start();
include 'db_connect.php';
include 'audit_log.php';
include 'rate_limit.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    include 'auth.php';
    requireCsrf();

    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rlKey = 'login_ip_' . $clientIp;
    if (isRateLimited($rlKey, 5, 900)) {
        echo "Too many login attempts. Please try again later.";
        exit;
    }

    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT user_id, full_name, role_id, password_hash FROM users WHERE university_email=? AND is_active=TRUE");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (!empty($password) && password_verify($password, $row['password_hash'])) {
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['role_id'] = $row['role_id'];
            $_SESSION['full_name'] = $row['full_name'];

            $roleStmt = $conn->prepare("SELECT role_name FROM roles WHERE role_id = ?");
            $roleStmt->bind_param('i', $row['role_id']);
            $roleStmt->execute();
            $roleResult = $roleStmt->get_result();
            $roleRow = $roleResult->fetch_assoc();
            $_SESSION['role_name'] = $roleRow['role_name'] ?? '';

            // Audit log
            writeAuditLog($row['user_id'], "User logged in", "users", $row['user_id'], $_SERVER['REMOTE_ADDR']);

            // Redirect by role
            switch ($row['role_id']) {
                case 6: // Administrator
                    $destination = 'dashboard_admin.php';
                    break;
                case 5: // Supervisor
                    $destination = 'dashboard_supervisor.php';
                    break;
                case 4: // Maintenance Officer / Technician
                    $destination = 'dashboard_technician.php';
                    break;
                default:
                    $destination = 'dashboard_student.php';
                    break;
            }

            // successful login: reset rate limit
            resetRateLimit($rlKey);
            header("Location: $destination");
            exit();
        } else {
            // register failed attempt
            registerAttempt($rlKey);
            echo "Invalid password.";
        }
    } else {
        // register failed attempt
        registerAttempt($rlKey);
        echo "User not found.";
    }
}
?>
