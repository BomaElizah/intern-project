<?php
session_start();
include 'db_connect.php';
include 'send_notification.php';
include 'audit_log.php';
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $building = $_POST['building'];
    $room = $_POST['room'];
    $category = $_POST['category'];
    $priority = $_POST['priority'];
    $requester_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("INSERT INTO maintenance_requests (requester_id, title, description, building_id, room_id, category_id, priority, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Submitted')");
    $stmt->bind_param("issiiis", $requester_id, $title, $description, $building, $room, $category, $priority);

    if ($stmt->execute()) {
        $request_id = $conn->insert_id;

        // Optional attachment handling at submission
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['attachment'];
            if ($file['size'] <= MAX_UPLOAD_BYTES) {
                $pathinfo = pathinfo($file['name']);
                $ext = strtolower($pathinfo['extension'] ?? '');
                if (in_array($ext, ALLOWED_UPLOAD_EXT)) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                    $validImageMimes = ['image/jpeg','image/png'];
                    $validPdf = 'application/pdf';
                    if ($mime === $validPdf || in_array($mime, $validImageMimes)) {
                        try { $random = bin2hex(random_bytes(6)); } catch (Exception $e) { $random = uniqid(); }
                        $safeName = time() . '_' . $random . '.' . $ext;
                        $uploadDir = get_upload_dir();
                        if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
                        $target = $uploadDir . $safeName;
                        if (move_uploaded_file($file['tmp_name'], $target)) {
                            $dbPath = get_upload_url_base() . $safeName;
                            $stmtAtt = $conn->prepare("INSERT INTO attachments (request_id, uploaded_by, file_path, attachment_stage) VALUES (?, ?, ?, 'Request')");
                            $stmtAtt->bind_param("iis", $request_id, $requester_id, $dbPath);
                            $stmtAtt->execute();
                        }
                    }
                }
            }
        }

        // Notify requester
        sendNotification($requester_id, $request_id, "Your request has been submitted.", "Dashboard");

        // Audit log
        writeAuditLog($requester_id, "Submitted maintenance request", "maintenance_requests", $request_id, $_SERVER['REMOTE_ADDR']);

        echo "Request submitted successfully!";
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>
