<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_role([ROLE_TECHNICIAN]);

$user = current_user();
$requestId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT r.*, b.building_name, rm.room_number, c.category_name, u.full_name AS requester_name
     FROM maintenance_requests r
     JOIN buildings b ON b.building_id = r.building_id
     LEFT JOIN rooms rm ON rm.room_id = r.room_id
     JOIN categories c ON c.category_id = r.category_id
     JOIN users u ON u.user_id = r.requester_id
     WHERE r.request_id = ?'
);
$stmt->execute([$requestId]);
$request = $stmt->fetch();

if (!$request) { flash('error', 'Job not found.'); redirect('technician/dashboard.php'); }

$assignChk = $pdo->prepare('SELECT * FROM assignments WHERE request_id = ? AND technician_id = ? AND is_current = TRUE');
$assignChk->execute([$requestId, $user['user_id']]);
$assignment = $assignChk->fetch();
if (!$assignment) { flash('error', 'This job is not currently assigned to you.'); redirect('technician/dashboard.php'); }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
  if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    $errors[] = 'Your session expired. Please try again.';
    $action = '';
  } elseif (in_array($request['status'], ['Completed', 'Rejected'], true)) {
    $errors[] = 'This job is closed and no longer accepts updates.';
    $action = '';
  }

    if ($action === 'status') {
        $newStage = $_POST['stage'] ?? '';
        $remarks = trim($_POST['remarks'] ?? '');
        $map = [
            'accept' => ['status' => 'Assigned', 'note' => 'Technician accepted the assignment.'],
            'start' => ['status' => 'Assigned', 'note' => 'Work started.'],
            'pause' => ['status' => 'Pending', 'note' => 'Work paused.'],
            'resume' => ['status' => 'Assigned', 'note' => 'Work resumed.'],
            'complete' => ['status' => 'Completed', 'note' => 'Work marked completed.'],
        ];
        $allowedTransitions = [
          'accept' => ['Assigned'],
          'start' => ['Assigned'],
          'pause' => ['Assigned'],
          'resume' => ['Pending'],
          'complete' => ['Assigned'],
        ];
        if (isset($map[$newStage]) && in_array($request['status'], $allowedTransitions[$newStage], true)) {
            $note = $remarks !== '' ? $map[$newStage]['note'] . ' ' . $remarks : $map[$newStage]['note'];
            change_request_status($pdo, $requestId, $request['status'], $map[$newStage]['status'], $user['user_id'], $note);
            notify_user($pdo, $request['requester_id'], 'Your request "' . $request['title'] . '" is now ' . $map[$newStage]['status'] . '.', $requestId);
            flash('success', 'Job status updated.');
            redirect('technician/job.php?id=' . $requestId);
        } else {
          $errors[] = 'That job action is not valid for the current status.';
        }
    } elseif ($action === 'comment') {
        $comment = trim($_POST['comment'] ?? '');
        if ($comment === '') {
            $errors[] = 'Comment cannot be empty.';
        } else {
            $pdo->prepare('INSERT INTO work_comments (request_id, technician_id, comment) VALUES (?, ?, ?)')
                ->execute([$requestId, $user['user_id'], $comment]);
            flash('success', 'Comment added.');
            redirect('technician/job.php?id=' . $requestId);
        }
    } elseif ($action === 'evidence') {
        $stage = $_POST['evidence_stage'] ?? '';
        if (!in_array($stage, ['Before-Work', 'After-Work'], true)) {
            $errors[] = 'Invalid evidence stage.';
        } elseif (empty($_FILES['evidence']['name'][0])) {
            $errors[] = 'Please choose at least one photo to upload.';
        } else {
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $saved = 0;
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
            if (count($_FILES['evidence']['name']) > 5) $errors[] = 'You can upload up to 5 photos at a time.';
            foreach (array_slice($_FILES['evidence']['name'], 0, 5, true) as $i => $name) {
                if ($_FILES['evidence']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $tmp = $_FILES['evidence']['tmp_name'][$i];
                if ($_FILES['evidence']['size'][$i] > MAX_UPLOAD_BYTES) { $errors[] = "$name exceeds 5MB."; continue; }
                if (!in_array(mime_content_type($tmp), $allowed, true)) { $errors[] = "$name is not a supported image."; continue; }
                $mimeExtensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
                $mime = mime_content_type($tmp);
                $ext = $mimeExtensions[$mime];
                $safeName = 'req' . $requestId . '_' . strtolower(str_replace('-', '', $stage)) . '_' . bin2hex(random_bytes(6)) . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext);
                if (!move_uploaded_file($tmp, UPLOAD_DIR . $safeName)) { $errors[] = "$name could not be saved."; continue; }
                $pdo->prepare('INSERT INTO attachments (request_id, uploaded_by, file_path, attachment_stage) VALUES (?, ?, ?, ?)')
                    ->execute([$requestId, $user['user_id'], 'uploads/' . $safeName, $stage]);
                $saved++;
            }
            if ($saved) {
                flash('success', "$saved photo(s) uploaded as $stage evidence.");
                redirect('technician/job.php?id=' . $requestId);
            }
        }
    }
}

$attachments = $pdo->prepare('SELECT * FROM attachments WHERE request_id = ? ORDER BY uploaded_at');
$attachments->execute([$requestId]);
$attachmentRows = $attachments->fetchAll();

$comments = $pdo->prepare('SELECT * FROM work_comments WHERE request_id = ? ORDER BY created_at DESC');
$comments->execute([$requestId]);
$commentRows = $comments->fetchAll();

$activePage = 'dashboard';
$pageTitle = 'Job #' . $requestId;
require __DIR__ . '/../includes/header.php';
?>
<div class="page-shell container">
  <div class="page-header">
    <div><h1><?= e($request['title']) ?></h1><div class="subtitle">Job #<?= $requestId ?> for <?= e($request['requester_name']) ?></div></div>
    <span class="badge <?= status_badge_class($request['status']) ?>" style="font-size:0.95rem;"><?= e($request['status']) ?></span>
  </div>

  <?php foreach ($errors as $err): ?><div class="alert alert-error container"><?= e($err) ?></div><?php endforeach; ?>

  <div class="card">
    <h2 class="mt-0">Job Details</h2>
    <p><?= nl2br(e($request['description'])) ?></p>
    <div class="form-grid">
      <div><strong>Location:</strong><br><?= e($request['building_name']) ?><?= $request['room_number'] ? ' / ' . e($request['room_number']) : '' ?></div>
      <div><strong>Category:</strong><br><?= e($request['category_name']) ?></div>
      <div><strong>Priority:</strong><br><span class="badge <?= priority_badge_class($request['priority']) ?>"><?= e($request['priority']) ?></span></div>
      <div><strong>Due Date:</strong><br><?= $assignment['due_date'] ? e(date('d M Y', strtotime($assignment['due_date']))) : '—' ?></div>
    </div>
  </div>

  <div class="card">
    <h2 class="mt-0">Update Job Progress</h2>
    <?php if (in_array($request['status'], ['Completed', 'Rejected'], true)): ?>
      <p class="text-muted">This job is closed and no longer accepts progress updates.</p>
    <?php else: ?>
      <form method="post" class="form-grid" style="align-items:end;">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="status">
        <div class="form-group" style="grid-column:1/-1;">
          <label for="remarks">Remarks (optional)</label>
          <textarea id="remarks" name="remarks" placeholder="Add a note about this update..."></textarea>
        </div>
        <div>
          <button type="submit" name="stage" value="accept" class="btn btn-outline-nav">Accept Assignment</button>
        </div>
        <div>
          <button type="submit" name="stage" value="start" class="btn btn-primary">Start Work</button>
        </div>
        <div>
          <button type="submit" name="stage" value="pause" class="btn btn-accent">Pause Work</button>
        </div>
        <div>
          <button type="submit" name="stage" value="resume" class="btn btn-outline-nav">Resume Work</button>
        </div>
        <div>
          <button type="submit" name="stage" value="complete" class="btn btn-primary" data-confirm="Mark this job as completed?">Mark Completed</button>
        </div>
      </form>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2 class="mt-0">Upload Work Photos</h2>
    <form method="post" enctype="multipart/form-data" class="form-grid" style="align-items:end;">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="evidence">
      <div class="form-group">
        <label for="evidence_stage">Stage</label>
        <select id="evidence_stage" name="evidence_stage" required>
          <option value="Before-Work">Before Work</option>
          <option value="After-Work">After Work</option>
        </select>
      </div>
      <div class="form-group">
        <label for="evidence">Photos</label>
        <input type="file" id="evidence" name="evidence[]" multiple accept="image/*">
      </div>
      <div><button type="submit" class="btn btn-primary">Upload</button></div>
    </form>
    <?php if ($attachmentRows): ?>
      <div class="thumb-row">
        <?php foreach ($attachmentRows as $a): ?>
          <a href="<?= BASE_URL . '/' . e($a['file_path']) ?>" target="_blank" title="<?= e($a['attachment_stage']) ?>">
            <img class="thumb" src="<?= BASE_URL . '/' . e($a['file_path']) ?>" alt="<?= e($a['attachment_stage']) ?>">
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2 class="mt-0">Comments</h2>
    <form method="post" style="margin-bottom:16px;">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="comment">
      <div class="form-group">
        <textarea name="comment" placeholder="Add a comment for this job..." required></textarea>
      </div>
      <button type="submit" class="btn btn-primary btn-sm">Add Comment</button>
    </form>
    <?php foreach ($commentRows as $c): ?>
      <p style="border-bottom:1px solid var(--border);padding-bottom:10px;">
        <span class="text-muted" style="font-size:0.8rem;"><?= e(date('d M Y, g:i a', strtotime($c['created_at']))) ?></span><br>
        <?= nl2br(e($c['comment'])) ?>
      </p>
    <?php endforeach; ?>
  </div>

  <a href="<?= BASE_URL ?>/technician/dashboard.php">&larr; Back to Assigned Jobs</a>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
