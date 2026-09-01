<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

header('Content-Type: application/json');

$buildingId = (int) ($_GET['building_id'] ?? 0);
if (!$buildingId) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare('SELECT room_id, room_number, room_type FROM rooms WHERE building_id = ? ORDER BY room_number');
$stmt->execute([$buildingId]);
echo json_encode($stmt->fetchAll());
