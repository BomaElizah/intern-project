<?php
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: login.html');
    exit();
}

include 'db_connect.php';
include 'audit_log.php';

$user_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_read') {
    $notification_id = (int) $_POST['notification_id'];
    $markStmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE notification_id=? AND user_id=?");
    $markStmt->bind_param("ii", $notification_id, $user_id);
    $markStmt->execute();

    writeAuditLog($user_id, "Marked notification as read", "notifications", $notification_id, $_SERVER['REMOTE_ADDR']);

    header('Location: notifications.php');
    exit();
}

$stmt = $conn->prepare("SELECT notification_id, request_id, message, notification_type, is_read, sent_at FROM notifications WHERE user_id=? ORDER BY sent_at DESC LIMIT 25");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$unreadCountStmt = $conn->prepare("SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id=? AND is_read=FALSE");
$unreadCountStmt->bind_param("i", $user_id);
$unreadCountStmt->execute();
$unreadCountResult = $unreadCountStmt->get_result();
$unreadCountRow = $unreadCountResult->fetch_assoc();
$unreadCount = (int) ($unreadCountRow['unread_count'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications - WPU MRS</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header class="header">
    <h1>WPU Maintenance Request System</h1>
    <nav>
      <a href="dashboard_student.html">Dashboard</a>
      <a href="notifications.php" class="active">Notifications<?php echo $unreadCount > 0 ? ' (' . $unreadCount . ')' : ''; ?></a>
      <a href="index.html">Logout</a>
    </nav>
  </header>

  <main class="dashboard-container">
    <section class="submit-request">
      <h2>Your Notifications</h2>
      <?php if ($result->num_rows === 0): ?>
        <p>No notifications yet.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Message</th>
              <th>Type</th>
              <th>Status</th>
              <th>Received</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?php echo htmlspecialchars($row['message'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($row['notification_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo $row['is_read'] ? 'Read' : 'Unread'; ?></td>
                <td><?php echo htmlspecialchars($row['sent_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                  <?php if (!$row['is_read']): ?>
                    <form method="POST" style="display:inline;">
                      <input type="hidden" name="action" value="mark_read">
                      <input type="hidden" name="notification_id" value="<?php echo (int) $row['notification_id']; ?>">
                      <button type="submit" class="btn-secondary">Mark Read</button>
                    </form>
                  <?php else: ?>
                    <span>Seen</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
