<?php
include 'auth.php';
requireLogin();
include 'send_notification.php';
include 'audit_log.php';
include 'status_history.php';
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    requireCsrf();

    $title = $_POST['title'];
    $description = $_POST['description'];
    $building = $_POST['building'];
    $room = isset($_POST['room']) && $_POST['room'] !== '' ? intval($_POST['room']) : null;
    $category = $_POST['category'];
    $priority = $_POST['priority'];
    $requester_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("INSERT INTO maintenance_requests (requester_id, title, description, building_id, room_id, category_id, priority, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Submitted')");
    $stmt->bind_param("issiiis", $requester_id, $title, $description, $building, $room, $category, $priority);

    if ($stmt->execute()) {
        $request_id = $conn->insert_id;

        recordStatusHistory($request_id, 'N/A', 'Submitted', $requester_id, 'Request created');

        // Attachment handling at submission: support single `attachment` or multiple `attachments[]`
        $process_single = function($file) use ($conn, $request_id, $requester_id) {
            if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return;
            if ($file['size'] > MAX_UPLOAD_BYTES) return;
            $pathinfo = pathinfo($file['name']);
            $ext = strtolower($pathinfo['extension'] ?? '');
            if (!in_array($ext, ALLOWED_UPLOAD_EXT)) return;
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $validImageMimes = ['image/jpeg','image/png'];
            $validPdf = 'application/pdf';
            if (!($mime === $validPdf || in_array($mime, $validImageMimes))) return;
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
        };

        if (isset($_FILES['attachment'])) {
            $process_single($_FILES['attachment']);
        }

        if (isset($_FILES['attachments'])) {
            $files = $_FILES['attachments'];
            for ($i = 0; $i < count($files['name']); $i++) {
                $file = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];
                $process_single($file);
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
