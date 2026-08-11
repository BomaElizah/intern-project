<?php
session_start();

const ROLE_STUDENT = 1;
const ROLE_TECHNICIAN = 4;
const ROLE_SUPERVISOR = 5;
const ROLE_ADMIN = 6;

function role_id_to_name(int $role_id): string
{
    $role_map = [
        ROLE_STUDENT => 'student',
        ROLE_TECHNICIAN => 'technician',
        ROLE_SUPERVISOR => 'supervisor',
        ROLE_ADMIN => 'admin',
    ];

    return $role_map[$role_id] ?? 'unknown';
}

function role_name_to_id(string $role_name): ?int
{
    $role_name = strtolower(trim($role_name));
    $role_map = [
        'student' => ROLE_STUDENT,
        'technician' => ROLE_TECHNICIAN,
        'supervisor' => ROLE_SUPERVISOR,
        'admin' => ROLE_ADMIN,
    ];

    return $role_map[$role_name] ?? null;
}

function dashboard_for_role(int $role_id): string
{
    $role_dashboard_map = [
        ROLE_STUDENT => 'dashboard_student.php',
        ROLE_TECHNICIAN => 'dashboard_technician.php',
        ROLE_SUPERVISOR => 'dashboard_supervisor.php',
        ROLE_ADMIN => 'dashboard_admin.php',
    ];

    return $role_dashboard_map[$role_id] ?? 'index.html';
}

function generate_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['csrf_token'];
}

function require_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $script_name = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
    $is_static_login_or_register = in_array($script_name, ['login.php', 'register.php', 'forgot_password.php', 'reset_password.php'], true);
    $submitted_token = $_POST['csrf_token'] ?? '';

    if ($is_static_login_or_register && empty($submitted_token)) {
        return;
    }

    if (!isset($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)$submitted_token)) {
        http_response_code(419);
        echo 'Invalid or missing CSRF token.';
        exit;
    }
}

function inject_csrf_token_to_forms(string $markup): string
{
    $token = generate_csrf_token();
    $hidden = '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';

    return preg_replace(
        '/<form\s+([^>]*)>/i',
        '<form $1>' . PHP_EOL . $hidden,
        $markup
    ) ?? $markup;
}

function sanitize_text(string $value): string
{
    return trim(strip_tags($value));
}

function validate_int_like($value): ?int
{
    if (!isset($value) || $value === '') {
        return null;
    }

    $value = trim((string)$value);
    if (!ctype_digit($value)) {
        return null;
    }

    return (int)$value;
}

function require_auth(): void
{
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        header('Location: login.html');
        exit;
    }
}

function require_role(array $allowed_roles): void
{
    require_auth();

    $role_id = isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : 0;
    $allowed_ids = [];

    foreach ($allowed_roles as $role) {
        if (is_int($role)) {
            $allowed_ids[] = $role;
        } else {
            $resolved = role_name_to_id($role);
            if ($resolved !== null) {
                $allowed_ids[] = $resolved;
            }
        }
    }

    if (!in_array($role_id, $allowed_ids, true)) {
        header('Location: unauthorized.php');
        exit;
    }
}

function render_nav_for_role(int $role_id): string
{
    $pages = [
        ROLE_STUDENT => [
            ['Dashboard', 'dashboard_student.php'],
            ['Reports', 'generate_report.php'],
            ['Logout', 'index.html'],
        ],
        ROLE_TECHNICIAN => [
            ['Technician Dashboard', 'dashboard_technician.php'],
            ['Reports', 'generate_report.php'],
            ['Logout', 'index.html'],
        ],
        ROLE_SUPERVISOR => [
            ['Supervisor Dashboard', 'dashboard_supervisor.php'],
            ['Reports', 'generate_report.php'],
            ['Logout', 'index.html'],
        ],
        ROLE_ADMIN => [
            ['Admin Dashboard', 'dashboard_admin.php'],
            ['Manage Categories', 'manage_categories.php'],
            ['Reports', 'generate_report.php'],
            ['Logout', 'index.html'],
        ],
    ];

    $links = $pages[$role_id] ?? $pages[ROLE_STUDENT];
    $html = '<nav>';
    foreach ($links as $link) {
        $label = $link[0];
        $href = $link[1];
        $html .= '<a href="' . $href . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
    }
    $html .= '</nav>';

    return $html;
}
