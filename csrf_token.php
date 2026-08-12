<?php
// Returns a JSON object with a CSRF token and ensures a session is active.
include 'auth.php';
header('Content-Type: application/json');
$token = generateCsrfToken();
echo json_encode(['csrf_token' => $token]);
?>