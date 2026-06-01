<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

global $mybb, $db;

$uid = (int)($mybb->user['uid'] ?? 0);
if (!$uid) {
    echo json_encode(['ok' => false, 'error' => ['code' => 401, 'message' => 'No autorizado.']]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['ok' => false, 'error' => ['code' => 405, 'message' => 'Method not allowed']]);
    exit;
}

$query = $db->simple_select('game_cards', '*', '', ['order_by' => 'name', 'order_dir' => 'ASC']);
$cards = [];
while ($row = $db->fetch_array($query)) {
    $row['tags'] = json_decode($row['tags_json'] ?? '[]', true);
    $row['effects'] = json_decode($row['effects_json'] ?? '{}', true);
    $row['upgrade'] = json_decode($row['upgrade_json'] ?? '{}', true);
    $row['reposo'] = isset($row['reposo']) ? (int)$row['reposo'] : 0;
    $row['duracion'] = isset($row['duracion']) ? (int)$row['duracion'] : 0;
    unset($row['tags_json'], $row['effects_json'], $row['upgrade_json']);
    $cards[] = $row;
}

echo json_encode(['ok' => true, 'data' => $cards, 'error' => null]);
