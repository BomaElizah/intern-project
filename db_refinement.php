<?php
include 'db_connect.php';

$statements = [
    "ALTER TABLE maintenance_requests ADD INDEX idx_requests_status_submitted (status, submitted_at)",
    "ALTER TABLE maintenance_requests ADD INDEX idx_requests_requester_status (requester_id, status)",
    "ALTER TABLE assignments ADD INDEX idx_assignments_technician_current (technician_id, is_current)",
    "ALTER TABLE notifications ADD INDEX idx_notifications_user_read (user_id, is_read, sent_at)",
    "ALTER TABLE audit_logs ADD INDEX idx_audit_logs_user_time (user_id, created_at)"
];

$viewSql = "CREATE OR REPLACE VIEW request_dashboard_summary AS
SELECT
    mr.request_id,
    mr.requester_id,
    mr.title,
    mr.status,
    mr.priority,
    mr.submitted_at,
    mr.completed_at,
    u.full_name AS requester_name,
    c.category_name,
    b.building_name,
    a.technician_id,
    t.full_name AS technician_name
FROM maintenance_requests mr
LEFT JOIN users u ON mr.requester_id = u.user_id
LEFT JOIN categories c ON mr.category_id = c.category_id
LEFT JOIN buildings b ON mr.building_id = b.building_id
LEFT JOIN assignments a ON mr.request_id = a.request_id AND a.is_current = TRUE
LEFT JOIN users t ON a.technician_id = t.user_id";

foreach ($statements as $sql) {
    if (!$conn->query($sql)) {
        $code = $conn->errno;
        if ($code !== 1061) {
            die("Failed to apply refinement: {$conn->error}");
        }
    }
}

if (!$conn->query($viewSql)) {
    die("Failed to create reporting view: {$conn->error}");
}

echo "Database refinement applied successfully.";
?>
