<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_role([ROLE_ADMIN]);

$user = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
  if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    $errors[] = 'Your session expired. Please try again.';
    $action = '';
  }

    if ($action === 'add') {
        $name = trim($_POST['category_name'] ?? '');
        if ($name === '') {
            $errors[] = 'Category name is required.';
        } else {
            $chk = $pdo->prepare('SELECT category_id FROM categories WHERE category_name = ?');
            $chk->execute([$name]);
            if ($chk->fetch()) {
                $errors[] = 'That category already exists.';
            } else {
                $pdo->prepare('INSERT INTO categories (category_name) VALUES (?)')->execute([$name]);
                audit_log($pdo, $user['user_id'], 'Added category: ' . $name, 'categories', (int) $pdo->lastInsertId());
                flash('success', 'Category added.');
                redirect('admin/manage_categories.php');
            }
        }
    } elseif ($action === 'rename') {
        $categoryId = (int) $_POST['category_id'];
        $name = trim($_POST['category_name'] ?? '');
        if ($name === '') {
            $errors[] = 'Category name is required.';
        } else {
            $pdo->prepare('UPDATE categories SET category_name = ? WHERE category_id = ?')->execute([$name, $categoryId]);
            audit_log($pdo, $user['user_id'], 'Renamed category to: ' . $name, 'categories', $categoryId);
            flash('success', 'Category updated.');
            redirect('admin/manage_categories.php');
        }
    } elseif ($action === 'delete') {
        $categoryId = (int) $_POST['category_id'];
        $inUse = $pdo->prepare('SELECT COUNT(*) FROM maintenance_requests WHERE category_id = ?');
        $inUse->execute([$categoryId]);
        if ((int) $inUse->fetchColumn() > 0) {
            flash('error', 'This category is used by existing requests and cannot be deleted (Section 7.4). Rename it instead.');
        } else {
            $pdo->prepare('DELETE FROM categories WHERE category_id = ?')->execute([$categoryId]);
            audit_log($pdo, $user['user_id'], 'Deleted category', 'categories', $categoryId);
            flash('success', 'Category deleted.');
        }
        redirect('admin/manage_categories.php');
    }
}

$categories = $pdo->query(
    "SELECT c.*, COUNT(r.request_id) AS usage_count FROM categories c
     LEFT JOIN maintenance_requests r ON r.category_id = c.category_id
     GROUP BY c.category_id ORDER BY c.category_name"
)->fetchAll();

$activePage = 'categories';
$pageTitle = 'Manage Categories';
require __DIR__ . '/../includes/header.php';
?>
<div class="requester-dashboard admin-dashboard">
  <aside class="requester-rail">
    <nav class="requester-rail-nav" aria-label="Administrator dashboard">
      <a href="<?= BASE_URL ?>/admin/dashboard.php">Overview</a>
      <a href="<?= BASE_URL ?>/admin/manage_users.php">Manage Users</a>
      <a class="active" href="<?= BASE_URL ?>/admin/manage_categories.php">Manage Categories</a>
      <a href="<?= BASE_URL ?>/admin/system_settings.php">System Settings</a>
      <a href="<?= BASE_URL ?>/admin/audit_logs.php">Audit Logs</a>
    </nav>
  </aside>
  <main class="requester-main">
    <div class="page-header"><div><h1>Manage Categories</h1><div class="subtitle">Maintenance categories offered on the request form.</div></div></div>
      <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

      <div class="card">
        <h2 class="mt-0">Add Category</h2>
        <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="add">
          <input type="text" name="category_name" placeholder="e.g. Landscaping" required style="max-width:280px;">
          <button type="submit" class="btn btn-primary">Add</button>
        </form>
      </div>

      <div class="card">
        <h2 class="mt-0">Existing Categories</h2>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Category</th><th>Requests Using It</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($categories as $c): ?>
                <tr>
                  <td>
                    <form method="post" style="display:flex;gap:8px;">
                      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="rename">
                      <input type="hidden" name="category_id" value="<?= $c['category_id'] ?>">
                      <input type="text" name="category_name" value="<?= e($c['category_name']) ?>" style="max-width:220px;">
                      <button type="submit" class="btn btn-sm btn-outline-nav">Save</button>
                    </form>
                  </td>
                  <td><?= (int) $c['usage_count'] ?></td>
                  <td>
                    <form method="post" style="display:inline;">
                      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="category_id" value="<?= $c['category_id'] ?>">
                      <button type="submit" class="btn btn-sm btn-danger" data-confirm="Delete category '<?= e($c['category_name']) ?>'?">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
