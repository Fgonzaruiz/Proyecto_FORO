<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

$uid = (int)($mybb->user['uid'] ?? 0);
if ($uid === 0) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'data' => null, 'error' => ['code' => 'unauthorized', 'message' => 'Login required']]);
    exit;
}

$prefix = TABLE_PREFIX;

// Get user config
$cfg_q = $db->query("SELECT * FROM {$prefix}game_user_config WHERE user_id = {$uid}");
$cfg = $db->fetch_array($cfg_q);
if (!$cfg) {
    $db->write_query("INSERT INTO {$prefix}game_user_config (user_id, max_slots, slots_used) VALUES ({$uid}, 1, 0)");
    $cfg = ['max_slots' => 1, 'slots_used' => 0, 'active_pj_id' => null];
}

// Get user's characters
$chars_q = $db->query("SELECT id, name, race_name, occupation_name, avatar, banner, rango, tripulacion, is_staff FROM {$prefix}game_personajes WHERE user_id = {$uid} ORDER BY id ASC");
$chars = [];
$active_id = $cfg['active_pj_id'] ? (int)$cfg['active_pj_id'] : null;
while ($row = $db->fetch_array($chars_q)) {
    $chars[] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'race_name' => $row['race_name'],
        'occupation_name' => $row['occupation_name'],
        'avatar' => $row['avatar'] ?: $row['banner'],
        'rango' => $row['rango'],
        'tripulacion' => $row['tripulacion'],
        'is_staff' => (bool)$row['is_staff'],
        'is_active' => (int)$row['id'] === $active_id,
    ];
}

header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'data' => [
        'chars' => $chars,
        'max_slots' => (int)$cfg['max_slots'],
        'slots_used' => (int)$cfg['slots_used'],
        'active_pj_id' => $active_id,
    ],
    'error' => null,
    'meta' => ['endpoint' => 'my_personajes'],
]);
