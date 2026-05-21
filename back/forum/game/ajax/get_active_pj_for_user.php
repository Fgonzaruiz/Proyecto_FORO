<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

$uid = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;

if ($uid <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'data' => null, 'error' => ['code' => 'invalid_input', 'message' => 'uid required']]);
    exit;
}

$prefix = TABLE_PREFIX;

$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);

$result = null;

if ($cfg && $cfg['active_pj_id']) {
    $pj_q = $db->query("SELECT id, name, race_name, occupation_name, rango, tripulacion, avatar, banner, is_staff FROM {$prefix}game_personajes WHERE id = " . (int)$cfg['active_pj_id'] . " LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    if ($pj) {
        $result = [
            'id' => (int)$pj['id'],
            'name' => $pj['name'],
            'race_name' => $pj['race_name'],
            'occupation_name' => $pj['occupation_name'],
            'rango' => $pj['rango'],
            'tripulacion' => $pj['tripulacion'],
            'avatar' => $pj['avatar'] ?: $pj['banner'],
            'is_staff' => (bool)$pj['is_staff'],
        ];
    }
}

header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'data' => $result,
    'error' => null,
    'meta' => ['endpoint' => 'get_active_pj_for_user'],
]);
