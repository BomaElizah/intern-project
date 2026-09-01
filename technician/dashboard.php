<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_role([ROLE_TECHNICIAN]);

$user = current_user();

$stmt = $pdo->prepare(
    "SELECT a.assignment_id, a.due_date, r.request_id, r.title, r.priority, r.status, r.submitted_at,
            b.building_name, rm.room_number, c.category_name
     FROM assignments a
     JOIN maintenance_requests r ON r.request_id = a.request_id
     JOIN buildings b ON b.building_id = r.building_id
     LEFT JOIN rooms rm ON rm.room_id = r.room_id
     JOIN categories c ON c.category_id = r.category_id
     WHERE a.technician_id = ? AND a.is_current = TRUE AND r.status != 'Completed' AND r.status != 'Rejected'
     ORDER BY FIELD(r.priority,'Urgent','High','Medium','Low'), a.due_date IS NULL, a.due_date"
);
$stmt->execute([$user['user_id']]);
$jobs = $stmt->fetchAll();

$completedCount = $pdo->prepare(
    "SELECT COUNT(*) FROM assignments a JOIN maintenance_requests r ON r.request_id = a.request_id
     WHERE a.technician_id = ? AND r.status = 'Completed'"
);
$completedCount->execute([$user['user_id']]);
$completedTotal = (int) $completedCount->fetchColumn();

$activePage = 'dashboard';
$pageTitle = 'Assigned Jobs';
require __DIR__ . '/../includes/header.php';
?>
<div class="requester-dashboard technician-dashboard">
  <aside class="requester-rail">
    <nav class="requester-rail-nav" aria-label="Technician dashboard">
      <a class="active" href="<?= BASE_URL ?>/technician/dashboard.php">Assigned Jobs</a>
    </nav>
    <?php if ($jobs): ?>
      <div class="technician-rail-jobs">
        <span class="technician-rail-heading">Current Jobs</span>
        <?php foreach ($jobs as $job): ?>
          <a href="<?= BASE_URL ?>/technician/job.php?id=<?= (int) $job['request_id'] ?>" title="Open <?= e($job['title']) ?>">
            <strong>#<?= (int) $job['request_id'] ?></strong>
            <span><?= e($job['title']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </aside>
  <main class="requester-main">
    <div class="page-header"><div><h1>Technician Dashboard</h1><div class="subtitle">Your assigned work queue, ordered by priority and due date.</div></div></div>
      <div class="stat-grid">
        <div class="stat-card"><div class="stat-value"><?= count($jobs) ?></div><div class="stat-label">Active Jobs</div></div>
        <div class="stat-card gold"><div class="stat-value"><?= $completedTotal ?></div><div class="stat-label">Completed (All Time)</div></div>
      </div>

      <div class="card">
        <h2 class="mt-0">Assigned Jobs</h2>
        <?php if (!$jobs): ?>
          <div class="empty-state">You have no active assigned jobs right now.</div>
        <?php else: ?>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Title</th><th>Location</th><th>Category</th><th>Priority</th><th>Due Date</th><th>Status</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($jobs as $j): ?>
                  <tr>
                    <td><?= e($j['title']) ?></td>
                    <td><?= e($j['building_name']) ?><?= $j['room_number'] ? ' / ' . e($j['room_number']) : '' ?></td>
                    <td><?= e($j['category_name']) ?></td>
                    <td><span class="badge <?= priority_badge_class($j['priority']) ?>"><?= e($j['priority']) ?></span></td>
                    <td><?= $j['due_date'] ? e(date('d M Y', strtotime($j['due_date']))) : '—' ?></td>
                    <td><span class="badge <?= status_badge_class($j['status']) ?>"><?= e($j['status']) ?></span></td>
                    <td><a href="<?= BASE_URL ?>/technician/job.php?id=<?= $j['request_id'] ?>">Open Job &rarr;</a></td>
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
