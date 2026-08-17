<?php
// Central configuration for uploads and other app-wide constants
define('UPLOAD_REL_PATH', 'assets/uploads');
define('ALLOWED_UPLOAD_EXT', ['jpg', 'jpeg', 'png', 'pdf']);
define('ALLOWED_UPLOAD_MIMES', [
    'image/jpeg' => 'jpg',
    'image/png' => 'png', 
    'application/pdf' => 'pdf'
]);
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5 MB
define('MIN_FILENAME_LENGTH', 3);
define('MAX_FILENAME_LENGTH', 255);

function get_upload_dir() {
    $dir = __DIR__ . '/' . UPLOAD_REL_PATH . '/';
    // Ensure directory exists
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function get_upload_url_base() {
    // Web-accessible base path for stored uploads (relative to project root)
    return UPLOAD_REL_PATH . '/';
}

function validate_upload_file($file) {
    // Check for upload errors
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['valid' => false, 'error' => 'Invalid file upload'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds PHP upload limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary upload directory',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by extension'
        ];
        $message = $errorMessages[$file['error']] ?? 'Unknown upload error';
        return ['valid' => false, 'error' => $message];
    }
    
    // Check file size
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        return ['valid' => false, 'error' => 'File exceeds maximum size of ' . (MAX_UPLOAD_BYTES / 1024 / 1024) . 'MB'];
    }
    
    // Check file size is not zero
    if ($file['size'] === 0) {
        return ['valid' => false, 'error' => 'File is empty'];
    }
    
    // Validate extension
    $pathinfo = pathinfo($file['name']);
    $ext = strtolower($pathinfo['extension'] ?? '');
    
    if (!in_array($ext, ALLOWED_UPLOAD_EXT)) {
        return ['valid' => false, 'error' => 'File type not allowed. Allowed types: ' . implode(', ', ALLOWED_UPLOAD_EXT)];
    }
    
    // Validate MIME type
    if (!function_exists('mime_content_type')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } else {
        $mime = mime_content_type($file['tmp_name']);
    }
    
    if (!isset(ALLOWED_UPLOAD_MIMES[$mime])) {
        return ['valid' => false, 'error' => 'File MIME type not allowed (detected: ' . $mime . ')'];
    }
    
    return ['valid' => true];
}

function sanitize_filename($filename) {
    // Remove any path components
    $filename = basename($filename);
    
    // Remove special characters but keep dots and common punctuation
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    
    // Limit length
    if (strlen($filename) > MAX_FILENAME_LENGTH) {
        $pathinfo = pathinfo($filename);
        $name = substr($pathinfo['filename'], 0, MAX_FILENAME_LENGTH - strlen($pathinfo['extension']) - 1);
        $filename = $name . '.' . $pathinfo['extension'];
    }
    
    // Ensure minimum length
    if (strlen($filename) < MIN_FILENAME_LENGTH) {
        $filename = 'file_' . time();
    }
    
    return $filename;
}

function generate_safe_filename($original_filename, $extension = null) {
    // Use timestamp and random bytes for uniqueness
    try {
        $random = bin2hex(random_bytes(6));
    } catch (Exception $e) {
        $random = uniqid();
    }
    
    $ext = $extension ? $extension : strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
    return time() . '_' . $random . '.' . $ext;
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

