<?php
include 'db_connect.php';

function writeAuditLog($user_id, $action, $entity_type = null, $entity_id = null, $ip_address = null) {
    global $conn;

    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issis", $user_id, $action, $entity_type, $entity_id, $ip_address);

    if ($stmt->execute()) {
        return true;
    } else {
        error_log("Audit Log Error: " . $stmt->error);
        return false;
    }
}

// Example usage
// writeAuditLog(6, "Deactivated user", "users", 3, $_SERVER['REMOTE_ADDR']);
?>
