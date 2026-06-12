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

$prefix = TABLE_PREFIX;
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;
if ($active_pj_id > 0) {
    $pj_q = $db->query("SELECT staff_level FROM {$prefix}game_personajes WHERE id = {$active_pj_id} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    $staff_level = $pj ? (int)$pj['staff_level'] : 0;
}

if ($staff_level < 3) {
    echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'Permisos insuficientes.']]);
    exit;
}

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    $query = $db->query("SELECT id, name FROM {$prefix}game_personajes WHERE name != 'Narrador' ORDER BY name ASC LIMIT 50");
} else {
    $escaped = $db->escape_string($q);
    $query = $db->query("SELECT id, name FROM {$prefix}game_personajes WHERE name != 'Narrador' AND name LIKE '%{$escaped}%' ORDER BY name ASC LIMIT 20");
}

$chars = [];
while ($row = $db->fetch_array($query)) {
    $chars[] = ['id' => (int)$row['id'], 'name' => $row['name']];
}

echo json_encode(['ok' => true, 'data' => $chars, 'error' => null]);
