<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_role([ROLE_SUPERVISOR]);

$user = current_user();
$errors = [];

// ---- Handle assign / reassign submissions (FR-07, FR-08) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign') {
    $requestId = (int) $_POST['request_id'];
    $technicianId = (int) $_POST['technician_id'];
    $dueDate = $_POST['due_date'] ?: null;

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) $errors[] = 'Your session expired. Please try again.';
    $reqStmt = $pdo->prepare('SELECT * FROM maintenance_requests WHERE request_id = ?');
    $reqStmt->execute([$requestId]);
    $reqRow = $reqStmt->fetch();

    if ($errors) {
    } elseif (!$reqRow) {
        $errors[] = 'Request not found.';
    } elseif (!$technicianId) {
        $errors[] = 'Please select a technician.';
    } else {
      $techStmt = $pdo->prepare('SELECT user_id FROM users WHERE user_id = ? AND role_id = ? AND is_active = TRUE');
      $techStmt->execute([$technicianId, ROLE_TECHNICIAN]);
      $validTechnician = (bool) $techStmt->fetch();
      $dueDateValid = $dueDate === null || (bool) DateTime::createFromFormat('Y-m-d', $dueDate);
      if (!$validTechnician || !$dueDateValid) {
        $errors[] = !$validTechnician ? 'Please select an active technician.' : 'Please select a valid due date.';
      }
    }
    if (!$errors && $reqRow && $technicianId) {
        $pdo->beginTransaction();
        try {
            $isReassign = $reqRow['status'] !== 'Submitted';
            if ($isReassign) {
                $pdo->prepare('UPDATE assignments SET is_current = FALSE WHERE request_id = ? AND is_current = TRUE')->execute([$requestId]);
            }
            $pdo->prepare('INSERT INTO assignments (request_id, technician_id, assigned_by, due_date, is_current) VALUES (?, ?, ?, ?, TRUE)')
                ->execute([$requestId, $technicianId, $user['user_id'], $dueDate]);

            change_request_status($pdo, $requestId, $reqRow['status'], 'Assigned', $user['user_id'], $isReassign ? 'Reassigned to another technician.' : 'Assigned to technician.');
            if ($isReassign) {
                audit_log($pdo, $user['user_id'], 'Reassigned request', 'maintenance_requests', $requestId);
            }
            notify_user($pdo, $technicianId, 'You have been assigned request: "' . $reqRow['title'] . '"', $requestId);
            notify_user($pdo, $reqRow['requester_id'], 'Your request "' . $reqRow['title'] . '" has been assigned to a technician.', $requestId);

            $pdo->commit();
            flash('success', $isReassign ? 'Request reassigned successfully.' : 'Request assigned successfully.');
            redirect('supervisor/all_requests.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Could not save the assignment. Please try again.';
        }
    }
}

// ---- Filters (FR-14) ----
$status = $_GET['status'] ?? '';
$buildingId = $_GET['building_id'] ?? '';
$categoryId = $_GET['category_id'] ?? '';
$technicianId = $_GET['technician_id'] ?? '';

$sql = "SELECT v.* FROM vw_request_summary v WHERE 1=1";
$params = [];
if ($status) { $sql .= ' AND v.status = ?'; $params[] = $status; }
if ($buildingId) { $sql .= ' AND v.building_name = (SELECT building_name FROM buildings WHERE building_id = ?)'; $params[] = $buildingId; }
if ($categoryId) { $sql .= ' AND v.category_name = (SELECT category_name FROM categories WHERE category_id = ?)'; $params[] = $categoryId; }
if ($technicianId) { $sql .= ' AND v.technician_name = (SELECT full_name FROM users WHERE user_id = ?)'; $params[] = $technicianId; }
$sql .= ' ORDER BY v.submitted_at DESC LIMIT 300';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$buildings = $pdo->query('SELECT building_id, building_name FROM buildings ORDER BY building_name')->fetchAll();
$categories = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name')->fetchAll();
$technicians = $pdo->query('SELECT user_id, full_name FROM users WHERE role_id = ' . ROLE_TECHNICIAN . ' AND is_active = TRUE ORDER BY full_name')->fetchAll();

$assignTargetId = (int) ($_GET['assign'] ?? 0);
$assignTarget = null;
if ($assignTargetId) {
    $t = $pdo->prepare('SELECT * FROM maintenance_requests WHERE request_id = ?');
    $t->execute([$assignTargetId]);
    $assignTarget = $t->fetch();
}

$activePage = 'all_requests';
$pageTitle = 'All Requests';
require __DIR__ . '/../includes/header.php';
?>
<div class="requester-dashboard supervisor-dashboard">
  <aside class="requester-rail">
    <nav class="requester-rail-nav" aria-label="Supervisor dashboard">
      <a href="<?= BASE_URL ?>/supervisor/dashboard.php">Overview</a>
      <a class="active" href="<?= BASE_URL ?>/supervisor/all_requests.php">View All Requests</a>
      <a href="<?= BASE_URL ?>/supervisor/reports.php">Generate Reports</a>
    </nav>
  </aside>
  <main class="requester-main">
    <div class="page-header"><div><h1>All Maintenance Requests</h1><div class="subtitle">Assign, reassign, and monitor every request campus-wide.</div></div></div>
      <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

      <?php if ($assignTarget): ?>
        <div class="card" style="border-top:5px solid var(--wpu-gold);">
          <h2 class="mt-0"><?= $assignTarget['status'] === 'Submitted' ? 'Assign' : 'Reassign' ?> Request #<?= $assignTarget['request_id'] ?>: <?= e($assignTarget['title']) ?></h2>
          <form method="post" class="form-grid" style="align-items:end;">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="assign">
            <input type="hidden" name="request_id" value="<?= $assignTarget['request_id'] ?>">
            <div class="form-group">
              <label for="technician_id">Technician</label>
              <select id="technician_id" name="technician_id" required>
                <option value="">— Select technician —</option>
                <?php foreach ($technicians as $t): ?>
                  <option value="<?= $t['user_id'] ?>"><?= e($t['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="due_date">Due Date</label>
              <input type="date" id="due_date" name="due_date" min="<?= date('Y-m-d') ?>">
            </div>
            <div><button type="submit" class="btn btn-primary">Save Assignment</button></div>
            <div><a class="btn btn-outline-nav" href="<?= BASE_URL ?>/supervisor/all_requests.php">Cancel</a></div>
          </form>
        </div>
      <?php endif; ?>

      <div class="card">
        <form method="get" class="filters">
          <select name="status" onchange="this.form.submit()">
            <option value="">All statuses</option>
            <?php foreach (['Submitted', 'Assigned', 'Pending', 'Completed', 'Rejected'] as $s): ?>
              <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
          <select name="building_id" onchange="this.form.submit()">
            <option value="">All buildings</option>
            <?php foreach ($buildings as $b): ?>
              <option value="<?= $b['building_id'] ?>" <?= (string) $buildingId === (string) $b['building_id'] ? 'selected' : '' ?>><?= e($b['building_name']) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="category_id" onchange="this.form.submit()">
            <option value="">All categories</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= $c['category_id'] ?>" <?= (string) $categoryId === (string) $c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="technician_id" onchange="this.form.submit()">
            <option value="">All technicians</option>
            <?php foreach ($technicians as $t): ?>
              <option value="<?= $t['user_id'] ?>" <?= (string) $technicianId === (string) $t['user_id'] ? 'selected' : '' ?>><?= e($t['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>

        <?php if (!$requests): ?>
          <div class="empty-state">No requests match these filters.</div>
        <?php else: ?>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>#</th><th>Title</th><th>Requester</th><th>Location</th><th>Category</th><th>Priority</th><th>Status</th><th>Technician</th><th>Submitted</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($requests as $r): ?>
                  <tr>
                    <td>#<?= $r['request_id'] ?></td>
                    <td><?= e($r['title']) ?></td>
                    <td><?= e($r['requester_name']) ?></td>
                    <td><?= e($r['building_name']) ?><?= $r['room_number'] ? ' / ' . e($r['room_number']) : '' ?></td>
                    <td><?= e($r['category_name']) ?></td>
                    <td><span class="badge <?= priority_badge_class($r['priority']) ?>"><?= e($r['priority']) ?></span></td>
                    <td><span class="badge <?= status_badge_class($r['status']) ?>"><?= e($r['status']) ?></span></td>
                    <td><?= e($r['technician_name'] ?? '—') ?></td>
                    <td><?= e(date('d M Y', strtotime($r['submitted_at']))) ?></td>
                    <td>
                      <a href="<?= BASE_URL ?>/requests/view_request.php?id=<?= $r['request_id'] ?>">View</a>
                      <?php if (!in_array($r['status'], ['Completed', 'Rejected'], true)): ?>
                        &middot; <a href="<?= BASE_URL ?>/supervisor/all_requests.php?assign=<?= $r['request_id'] ?>#top"><?= $r['status'] === 'Submitted' ? 'Assign' : 'Reassign' ?></a>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
