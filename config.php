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
