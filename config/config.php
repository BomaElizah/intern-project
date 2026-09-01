<?php
/**
 * WPU Maintenance Request System — Core Configuration
 * Western Pacific University
 */

// ---- Database connection settings (XAMPP defaults — Section 3.5) ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'wpu_maintenance');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---- Application settings ----
define('APP_NAME', 'WPU Maintenance Request System');
define('APP_SHORT_NAME', 'WPU MRS');
// BASE_URL is derived automatically so the app works regardless of the
// folder it is served from under XAMPP's htdocs.
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$root = preg_replace('#/(requests|technician|supervisor|admin|notifications)$#', '', $scriptDir);
define('BASE_URL', rtrim($root, '/'));
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5 MB per file (Section 5.2 performance)

// Role IDs — must match database/seed.sql insert order into `roles`.
define('ROLE_STUDENT', 1);
define('ROLE_STAFF', 2);
define('ROLE_ACADEMIC', 3);
define('ROLE_TECHNICIAN', 4);
define('ROLE_SUPERVISOR', 5);
define('ROLE_ADMIN', 6);

if (session_status() === PHP_SESSION_NONE) {
    // Harden the session cookie (Section 5.1 security requirements).
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

date_default_timezone_set('Pacific/Port_Moresby');

// ---- Database connection (PDO, prepared statements only — Section 5.1) ----
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;max-width:640px;margin:60px auto;padding:24px;
        border:1px solid #e2c200;background:#fffbe6;border-radius:8px;">
        <h2 style="color:#0B1D6B;margin-top:0;">Database connection failed</h2>
        <p>The system could not connect to MySQL. Make sure XAMPP\'s MySQL service is
        running and that the <code>wpu_maintenance</code> database has been created
        from <code>database/schema.sql</code> and <code>database/seed.sql</code>.</p>
        <p style="color:#888;font-size:0.9em;">Please check the local MySQL service and database configuration.</p></div>');
}
