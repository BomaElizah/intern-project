<?php
// Central configuration for uploads and other app-wide constants
define('UPLOAD_REL_PATH', 'assets/uploads');
define('ALLOWED_UPLOAD_EXT', ['jpg', 'jpeg', 'png', 'pdf']);
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5 MB

function get_upload_dir() {
    $dir = __DIR__ . '/' . UPLOAD_REL_PATH . '/';
    return $dir;
}

function get_upload_url_base() {
    // Web-accessible base path for stored uploads (relative to project root)
    return UPLOAD_REL_PATH . '/';
}

// Email configuration - set EMAIL_ENABLED to true and adjust MAIL_FROM_ADDRESS
// to enable outgoing emails via PHP's mail() function. For production, prefer
// a proper SMTP relay or a library like PHPMailer with authenticated SMTP.
define('EMAIL_ENABLED', false);
define('MAIL_FROM_ADDRESS', 'no-reply@wpu.edu');
define('MAIL_FROM_NAME', 'WPU Maintenance');

// SMTP configuration for PHPMailer
define('SMTP_ENABLED', false);
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'smtp-user@example.com');
define('SMTP_PASS', 'change-me');
define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl' or ''
define('SMTP_AUTH', true);
