<?php
/**
 * Renders the left-hand dashboard navigation for the current user's role.
 * Expects $user (from current_user()) and $activePage (string key) to be set
 * by the including page, e.g. $activePage = 'my_requests';
 */
$roleId = $user['role_id'];
$items = [];

if (in_array($roleId, [ROLE_STUDENT, ROLE_STAFF, ROLE_ACADEMIC], true)) {
    $items = [
        'my_requests' => ['requests/my_requests.php', 'My Request'],
        'dashboard' => ['requests/dashboard.php', 'Overview'],
        'submit' => ['requests/submit_request.php', 'Submit Request'],
    ];
} elseif ($roleId == ROLE_TECHNICIAN) {
    $items = [
        'dashboard' => ['technician/dashboard.php', 'Assigned Jobs'],
    ];
} elseif ($roleId == ROLE_SUPERVISOR) {
    $items = [
        'dashboard' => ['supervisor/dashboard.php', 'Overview'],
        'all_requests' => ['supervisor/all_requests.php', 'View All Requests'],
        'reports' => ['supervisor/reports.php', 'Generate Reports'],
    ];
} elseif ($roleId == ROLE_ADMIN) {
    $items = [
        'dashboard' => ['admin/dashboard.php', 'Overview'],
        'users' => ['admin/manage_users.php', 'Manage Users'],
        'categories' => ['admin/manage_categories.php', 'Manage Categories'],
        'settings' => ['admin/system_settings.php', 'System Settings'],
        'audit' => ['admin/audit_logs.php', 'Audit Logs'],
    ];
}
?>
<div class="dash-sidebar">
  <?php foreach ($items as $key => [$path, $label]): ?>
    <a href="<?= BASE_URL . '/' . $path ?>" class="<?= $activePage === $key ? 'active' : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>
