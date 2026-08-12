<?php
// Simple file-based rate limiter.
// Uses a per-key JSON file storing array of epoch timestamps for attempts.

function get_rate_dir() {
    $dir = __DIR__ . '/tmp/ratelimit/';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    return $dir;
}

function rate_limit_file($key) {
    return get_rate_dir() . 'rl_' . preg_replace('/[^a-z0-9_\-]/i','_', $key) . '.json';
}

function isRateLimited($key, $maxAttempts = 5, $windowSeconds = 900) {
    $file = rate_limit_file($key);
    $now = time();
    $attempts = [];
    if (file_exists($file)) {
        $data = @file_get_contents($file);
        if ($data !== false) {
            $arr = json_decode($data, true);
            if (is_array($arr)) $attempts = $arr;
        }
    }
    // prune old
    $attempts = array_filter($attempts, function($t) use ($now, $windowSeconds) { return ($t + $windowSeconds) >= $now; });
    if (count($attempts) >= $maxAttempts) return true;
    return false;
}

function registerAttempt($key) {
    $file = rate_limit_file($key);
    $now = time();
    $attempts = [];
    if (file_exists($file)) {
        $data = @file_get_contents($file);
        if ($data !== false) {
            $arr = json_decode($data, true);
            if (is_array($arr)) $attempts = $arr;
        }
    }
    $attempts[] = $now;
    // keep only recent 100 attempts
    $attempts = array_slice($attempts, -100);
    @file_put_contents($file, json_encode($attempts), LOCK_EX);
}

function resetRateLimit($key) {
    $file = rate_limit_file($key);
    if (file_exists($file)) { @unlink($file); }
}

?>