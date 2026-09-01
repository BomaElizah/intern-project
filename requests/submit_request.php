<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_role([ROLE_STUDENT, ROLE_STAFF, ROLE_ACADEMIC]);

$user = current_user();
$labels = location_labels($user['role_id']);
$buildings = $pdo->query('SELECT building_id, building_name FROM buildings ORDER BY building_name')->fetchAll();
$categories = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name')->fetchAll();

$errors = [];
$old = ['title' => '', 'description' => '', 'building_id' => '', 'room_number' => '', 'category_id' => '', 'priority' => 'Medium'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['title'] = trim($_POST['title'] ?? '');
    $old['description'] = trim($_POST['description'] ?? '');
    $old['building_id'] = $_POST['building_id'] ?? '';
    $old['room_number'] = trim($_POST['room_number'] ?? '');
    $old['category_id'] = $_POST['category_id'] ?? '';
    $old['priority'] = $_POST['priority'] ?? '';

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $errors[] = 'Your session expired. Please try again.';
    if ($old['title'] === '') $errors[] = 'Please enter a title for the request.';
    if ($old['description'] === '') $errors[] = 'Please describe the issue.';
    if (!$old['building_id']) $errors[] = $labels['building'] . ' is required.';
    if (strlen($old['room_number']) > 20) $errors[] = $labels['room'] . ' must be 20 characters or fewer.';
    if (!$old['category_id']) $errors[] = 'Please select a category.';
    if (!in_array($old['priority'], ['Low', 'Medium', 'High', 'Urgent'], true)) $errors[] = 'Please select a valid priority.';

    // Photo/attachment validation (optional, multiple).
    $uploadedFiles = [];
    if (!empty($_FILES['attachments']['name'][0])) {
      if (count($_FILES['attachments']['name']) > 5) $errors[] = 'You can upload up to 5 photos at a time.';
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
      foreach (array_slice($_FILES['attachments']['name'], 0, 5, true) as $i => $name) {
            if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $tmp = $_FILES['attachments']['tmp_name'][$i];
            $size = $_FILES['attachments']['size'][$i];
            $type = mime_content_type($tmp);
            if ($size > MAX_UPLOAD_BYTES) { $errors[] = "$name is larger than 5MB."; continue; }
            if (!in_array($type, $allowed, true)) { $errors[] = "$name is not a supported image type."; continue; }
            $mimeExtensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            $uploadedFiles[] = ['tmp' => $tmp, 'name' => $name, 'extension' => $mimeExtensions[$type]];
        }
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
        $roomId = null;
        if ($old['room_number'] !== '') {
          $roomStmt = $pdo->prepare('SELECT room_id FROM rooms WHERE building_id = ? AND room_number = ? LIMIT 1');
          $roomStmt->execute([(int) $old['building_id'], $old['room_number']]);
          $roomId = $roomStmt->fetchColumn();
          if (!$roomId) {
            $pdo->prepare('INSERT INTO rooms (building_id, room_number) VALUES (?, ?)')
              ->execute([(int) $old['building_id'], $old['room_number']]);
            $roomId = $pdo->lastInsertId();
          }
        }
            $stmt = $pdo->prepare(
                'INSERT INTO maintenance_requests (requester_id, title, description, building_id, room_id, category_id, priority)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $user['user_id'], $old['title'], $old['description'],
                (int) $old['building_id'], $roomId ?: null, (int) $old['category_id'], $old['priority'],
            ]);
            $requestId = (int) $pdo->lastInsertId();

            $pdo->prepare('INSERT INTO request_status_history (request_id, old_status, new_status, changed_by, remarks) VALUES (?, NULL, "Submitted", ?, "Request submitted")')
                ->execute([$requestId, $user['user_id']]);

            if ($uploadedFiles) {
                if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
                $attStmt = $pdo->prepare('INSERT INTO attachments (request_id, uploaded_by, file_path, attachment_stage) VALUES (?, ?, ?, "Request")');
                foreach ($uploadedFiles as $f) {
                    $safeName = 'req' . $requestId . '_' . bin2hex(random_bytes(6)) . '.' . $f['extension'];
                    if (!move_uploaded_file($f['tmp'], UPLOAD_DIR . $safeName)) throw new RuntimeException('Attachment could not be saved.');
                    $attStmt->execute([$requestId, $user['user_id'], 'uploads/' . $safeName]);
                }
            }

            // Notify all active supervisors that a new request needs assignment (FR-07).
            $supervisors = $pdo->query('SELECT user_id FROM users WHERE role_id = ' . ROLE_SUPERVISOR . ' AND is_active = TRUE')->fetchAll();
            foreach ($supervisors as $s) {
                notify_user($pdo, $s['user_id'], 'New maintenance request submitted: "' . $old['title'] . '"', $requestId);
            }

            $pdo->commit();
            flash('success', 'Your maintenance request has been submitted.');
            redirect('requests/view_request.php?id=' . $requestId);
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Something went wrong while saving your request. Please try again.';
        }
    }
}

$activePage = 'submit';
$pageTitle = 'Submit Request';
require __DIR__ . '/../includes/header.php';
?>
<div class="requester-dashboard">
  <aside class="requester-rail">
    <nav class="requester-rail-nav" aria-label="Requester dashboard">
      <a href="<?= BASE_URL ?>/requests/dashboard.php">Overview</a>
      <a href="<?= BASE_URL ?>/requests/my_requests.php">My Request</a>
      <a class="active" href="<?= BASE_URL ?>/requests/submit_request.php">Submit Request</a>
    </nav>
  </aside>
  <main class="requester-main">
    <div class="page-header"><div><h1>Submit a Maintenance Request</h1><div class="subtitle">Describe the issue and we'll route it to the right team.</div></div></div>
      <div class="card">
        <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <div class="form-group">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?= e($old['title']) ?>" required>
          </div>
          <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" required><?= e($old['description']) ?></textarea>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label for="building_id"><?= e($labels['building']) ?></label>
              <select id="building_id" name="building_id" required>
                <option value="">— Select —</option>
                <?php foreach ($buildings as $b): ?>
                  <option value="<?= $b['building_id'] ?>" <?= (string) $old['building_id'] === (string) $b['building_id'] ? 'selected' : '' ?>><?= e($b['building_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="room_number"><?= e($labels['room']) ?></label>
              <input type="text" id="room_number" name="room_number" value="<?= e($old['room_number']) ?>" maxlength="20" placeholder="Enter room number">
            </div>
          </div>
          <div class="form-grid">
            <div class="form-group">
              <label for="category_id">Category</label>
              <select id="category_id" name="category_id" required>
                <option value="">— Select —</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= $c['category_id'] ?>" <?= (string) $old['category_id'] === (string) $c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="priority">Priority Level</label>
              <select id="priority" name="priority" required>
                <?php foreach (['Low', 'Medium', 'High', 'Urgent'] as $p): ?>
                  <option value="<?= $p ?>" <?= $old['priority'] === $p ? 'selected' : '' ?>><?= $p ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label for="attachments">Photo/Attachments (optional, up to 5MB each)</label>
            <input type="file" id="attachments" name="attachments[]" multiple accept="image/*" data-preview-label="fileCount">
            <div class="hint" id="fileCount"></div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn btn-primary">Submit Request</button>
            <a class="btn btn-outline-nav" href="<?= BASE_URL ?>/requests/my_requests.php">Cancel</a>
          </div>
        </form>
      </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
