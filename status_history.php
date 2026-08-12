<?php
include 'db_connect.php';

function recordStatusHistory($request_id, $old_status, $new_status, $changed_by, $remarks = '') {
    global $conn;

    if ($old_status === null || $old_status === '') {
        $old_status = 'N/A';
    }

    $stmt = $conn->prepare("INSERT INTO request_status_history (request_id, old_status, new_status, changed_by, remarks) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issis", $request_id, $old_status, $new_status, $changed_by, $remarks);

    if (!$stmt->execute()) {
        error_log("Status History Error: " . $stmt->error);
        return false;
    }

    return true;
}

function getRequestStatusHistory($request_id) {
    global $conn;

    $stmt = $conn->prepare("SELECT history_id, old_status, new_status, changed_by, changed_at, remarks FROM request_status_history WHERE request_id=? ORDER BY changed_at DESC");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    return $stmt->get_result();
}

function getUserFullName($user_id) {
    global $conn;

    $stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        return $row['full_name'];
    }

    return 'Unknown';
}
