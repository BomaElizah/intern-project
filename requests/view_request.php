<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$user = current_user();
$requestId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT r.*, b.building_name, rm.room_number, c.category_name, u.full_name AS requester_name, u.university_email AS requester_email
     FROM maintenance_requests r
     JOIN buildings b ON b.building_id = r.building_id
     LEFT JOIN rooms rm ON rm.room_id = r.room_id
     JOIN categories c ON c.category_id = r.category_id
     JOIN users u ON u.user_id = r.requester_id
     WHERE r.request_id = ?'
);
$stmt->execute([$requestId]);
$request = $stmt->fetch();

if (!$request) {
    flash('error', 'Request not found.');
    redirect(role_dashboard_path($user['role_id']));
}

// Access control: requester sees own; technician sees if currently/previously assigned; supervisor/admin see all.
$allowed = false;
if (in_array($user['role_id'], [ROLE_SUPERVISOR, ROLE_ADMIN], true)) {
    $allowed = true;
} elseif ($request['requester_id'] == $user['user_id']) {
    $allowed = true;
} elseif ($user['role_id'] == ROLE_TECHNICIAN) {
    $chk = $pdo->prepare('SELECT 1 FROM assignments WHERE request_id = ? AND technician_id = ? LIMIT 1');
    $chk->execute([$requestId, $user['user_id']]);
    $allowed = (bool) $chk->fetch();
}
if (!$allowed) {
    flash('error', 'You do not have permission to view that request.');
    redirect(role_dashboard_path($user['role_id']));
}

$history = $pdo->prepare(
    'SELECT h.*, u.full_name AS changed_by_name FROM request_status_history h
     JOIN users u ON u.user_id = h.changed_by WHERE h.request_id = ? ORDER BY h.changed_at ASC'
);
$history->execute([$requestId]);
$historyRows = $history->fetchAll();

$assignment = $pdo->prepare(
    'SELECT a.*, t.full_name AS technician_name, s.full_name AS assigned_by_name
     FROM assignments a JOIN users t ON t.user_id = a.technician_id JOIN users s ON s.user_id = a.assigned_by
     WHERE a.request_id = ? ORDER BY a.assigned_at DESC'
);
$assignment->execute([$requestId]);
$assignments = $assignment->fetchAll();

$attachments = $pdo->prepare('SELECT a.*, u.full_name AS uploaded_by_name FROM attachments a JOIN users u ON u.user_id = a.uploaded_by WHERE a.request_id = ? ORDER BY a.uploaded_at');
$attachments->execute([$requestId]);
$attachmentRows = $attachments->fetchAll();

$comments = $pdo->prepare('SELECT c.*, u.full_name AS technician_name FROM work_comments c JOIN users u ON u.user_id = c.technician_id WHERE c.request_id = ? ORDER BY c.created_at');
$comments->execute([$requestId]);
$commentRows = $comments->fetchAll();

$activePage = 'my_requests';
$pageTitle = 'Request #' . $requestId;
require __DIR__ . '/../includes/header.php';
?>
<div class="page-shell container">
  <div class="page-header">
    <div>
      <h1><?= e($request['title']) ?></h1>
      <div class="subtitle">Request #<?= $requestId ?> &middot; Submitted <?= e(date('d M Y, g:i a', strtotime($request['submitted_at']))) ?></div>
    </div>
    <span class="badge <?= status_badge_class($request['status']) ?>" style="font-size:0.95rem;"><?= e($request['status']) ?></span>
  </div>

  <div class="card">
    <h2 class="mt-0">Details</h2>
    <p><?= nl2br(e($request['description'])) ?></p>
    <div class="form-grid">
      <div><strong>Requester:</strong><br><?= e($request['requester_name']) ?></div>
      <div><strong>Location:</strong><br><?= e($request['building_name']) ?><?= $request['room_number'] ? ' / ' . e($request['room_number']) : '' ?></div>
      <div><strong>Category:</strong><br><?= e($request['category_name']) ?></div>
      <div><strong>Priority:</strong><br><span class="badge <?= priority_badge_class($request['priority']) ?>"><?= e($request['priority']) ?></span></div>
      <div><strong>Completed:</strong><br><?= $request['completed_at'] ? e(date('d M Y, g:i a', strtotime($request['completed_at']))) : '—' ?></div>
    </div>
    <?php if ($attachmentRows): ?>
      <h3>Attachments</h3>
      <div class="thumb-row">
        <?php foreach ($attachmentRows as $a): ?>
          <a href="<?= BASE_URL . '/' . e($a['file_path']) ?>" target="_blank" title="<?= e($a['attachment_stage']) ?> — <?= e($a['uploaded_by_name']) ?>">
            <img class="thumb" src="<?= BASE_URL . '/' . e($a['file_path']) ?>" alt="<?= e($a['attachment_stage']) ?> photo">
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($assignments): ?>
    <div class="card">
      <h2 class="mt-0">Assignment</h2>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Technician</th><th>Assigned By</th><th>Assigned</th><th>Due Date</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($assignments as $a): ?>
              <tr>
                <td><?= e($a['technician_name']) ?></td>
                <td><?= e($a['assigned_by_name']) ?></td>
                <td><?= e(date('d M Y', strtotime($a['assigned_at']))) ?></td>
                <td><?= $a['due_date'] ? e(date('d M Y', strtotime($a['due_date']))) : '—' ?></td>
                <td><?= $a['is_current'] ? '<span class="badge badge-assigned">Current</span>' : '<span class="badge badge-rejected">Reassigned</span>' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <div class="card">
    <h2 class="mt-0">Status History</h2>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>When</th><th>Change</th><th>By</th><th>Remarks</th></tr></thead>
        <tbody>
          <?php foreach ($historyRows as $h): ?>
            <tr>
              <td><?= e(date('d M Y, g:i a', strtotime($h['changed_at']))) ?></td>
              <td><?= $h['old_status'] ? e($h['old_status']) . ' &rarr; ' : '' ?><span class="badge <?= status_badge_class($h['new_status']) ?>"><?= e($h['new_status']) ?></span></td>
              <td><?= e($h['changed_by_name']) ?></td>
              <td><?= e($h['remarks'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($commentRows): ?>
    <div class="card">
      <h2 class="mt-0">Technician Comments</h2>
      <?php foreach ($commentRows as $c): ?>
        <p style="border-bottom:1px solid var(--border);padding-bottom:10px;">
          <strong><?= e($c['technician_name']) ?></strong>
          <span class="text-muted" style="font-size:0.8rem;"> — <?= e(date('d M Y, g:i a', strtotime($c['created_at']))) ?></span><br>
          <?= nl2br(e($c['comment'])) ?>
        </p>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <a href="<?= BASE_URL . '/' . role_dashboard_path($user['role_id']) ?>">&larr; Back to Dashboard</a>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
