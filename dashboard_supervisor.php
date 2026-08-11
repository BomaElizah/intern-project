<?php
require_once __DIR__ . '/auth.php';
require_auth();
require_role([ROLE_SUPERVISOR]);

$dashboard_path = __DIR__ . '/dashboard_supervisor.html';
$dashboard_markup = file_get_contents($dashboard_path);
$dashboard_markup = inject_csrf_token_to_forms($dashboard_markup);
$nav_html = render_nav_for_role((int)$_SESSION['role_id']);

$nav_start = strpos($dashboard_markup, '<nav>');
$nav_end = strpos($dashboard_markup, '</nav>');
if ($nav_start !== false && $nav_end !== false) {
    $nav_end += strlen('</nav>');
    $dashboard_markup = substr($dashboard_markup, 0, $nav_start) . $nav_html . substr($dashboard_markup, $nav_end);
}

echo $dashboard_markup;
