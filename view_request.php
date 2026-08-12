<?php
session_start();
include 'db_connect.php';

function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

if (!isset($_GET['request_id']) || !is_numeric($_GET['request_id'])) {
    echo "Invalid request id.";
    exit;
}

$request_id = (int)$_GET['request_id'];

$stmt = $conn->prepare("SELECT mr.*, u.full_name AS requester_name FROM maintenance_requests mr LEFT JOIN users u ON mr.requester_id = u.user_id WHERE mr.request_id = ?");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$res = $stmt->get_result();
if (!$request = $res->fetch_assoc()) {
    echo "Request not found.";
    exit;
}

$attStmt = $conn->prepare("SELECT * FROM attachments WHERE request_id = ? ORDER BY uploaded_at ASC");
$attStmt->bind_param("i", $request_id);
$attStmt->execute();
$atts = $attStmt->get_result();

$comStmt = $conn->prepare("SELECT wc.*, u.full_name FROM work_comments wc LEFT JOIN users u ON wc.technician_id = u.user_id WHERE wc.request_id = ? ORDER BY created_at ASC");
$comStmt->bind_param("i", $request_id);
$comStmt->execute();
$comments = $comStmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Request Details</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .attachment { margin:8px 0; }
    .attachment img { max-width:300px; display:block; margin-bottom:4px; }
    .comment { border-left:3px solid #ddd; padding-left:8px; margin:8px 0; }
  </style>
</head>
<body>
  <main class="container">
    <h1>Request #<?php echo h($request['request_id']); ?> - <?php echo h($request['title']); ?></h1>
    <p><strong>Requested by:</strong> <?php echo h($request['requester_name']); ?> | <strong>Status:</strong> <?php echo h($request['status']); ?></p>
    <section>
      <h2>Attachments</h2>
      <?php if ($atts->num_rows === 0): ?>
        <p>No attachments uploaded yet.</p>
      <?php else: ?>
        <?php while ($a = $atts->fetch_assoc()):
            $path = $a['file_path'];
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        ?>
          <div class="attachment">
            <div><strong><?php echo h($a['attachment_stage']); ?></strong> — uploaded at <?php echo h($a['uploaded_at']); ?></div>
            <?php if (in_array($ext, ['jpg','jpeg','png'])): ?>
              <a href="<?php echo h($path); ?>" target="_blank"><img src="<?php echo h($path); ?>" alt="attachment"></a>
            <?php else: ?>
              <a href="<?php echo h($path); ?>" target="_blank">Download <?php echo h(basename($path)); ?></a>
            <?php endif; ?>
          </div>
        <?php endwhile; ?>
      <?php endif; ?>
    </section>

    <section>
      <h2>Technician Comments</h2>
      <?php if ($comments->num_rows === 0): ?>
        <p>No comments yet.</p>
      <?php else: ?>
        <?php while ($c = $comments->fetch_assoc()): ?>
          <div class="comment">
            <div><strong><?php echo h($c['full_name']); ?></strong> — <?php echo h($c['created_at']); ?></div>
            <div><?php echo nl2br(h($c['comment'])); ?></div>
          </div>
        <?php endwhile; ?>
      <?php endif; ?>
    </section>

    <p><a href="index.html">Back</a></p>
  </main>
</body>
</html>
