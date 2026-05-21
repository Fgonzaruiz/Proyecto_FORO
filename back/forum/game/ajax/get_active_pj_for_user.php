<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

$uid = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
$post_id = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
$thread_id = isset($_GET['thread_id']) ? (int)$_GET['thread_id'] : 0;

if ($uid <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'data' => null, 'error' => ['code' => 'invalid_input', 'message' => 'uid required']]);
    exit;
}

$prefix = TABLE_PREFIX;
$bb = $mybb->settings['bburl'];
$result = null;

function _pj_result(array $row, string $bb): array {
    $img = $row['avatar'] ?: $row['banner'];
    if ($img && strpos($img, 'http://') !== 0 && strpos($img, 'https://') !== 0) {
        $img = rtrim($bb, '/') . '/' . ltrim($img, '/');
    }
    return [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'race_name' => $row['race_name'],
        'occupation_name' => $row['occupation_name'],
        'rango' => $row['rango'],
        'tripulacion' => $row['tripulacion'],
        'avatar' => $img ?: '',
        'is_staff' => (bool)$row['is_staff'],
    ];
}

// If post_id provided, try to get character stored at post creation time
if ($post_id > 0) {
    $pc_q = $db->query("SELECT character_id FROM {$prefix}game_post_characters WHERE post_id = {$post_id} LIMIT 1");
    $pc = $db->fetch_array($pc_q);
    if ($pc) {
        $pj_q = $db->query("SELECT id, name, race_name, occupation_name, rango, tripulacion, avatar, banner, is_staff FROM {$prefix}game_personajes WHERE id = " . (int)$pc['character_id'] . " LIMIT 1");
        $pj = $db->fetch_array($pj_q);
        if ($pj) $result = _pj_result($pj, $bb);
    }
    // If post_id was given but no record found, return null (don't fallback)
} elseif ($thread_id > 0) {
    // thread_id provided: look up character stored when thread was created
    $pc_q = $db->query("SELECT character_id FROM {$prefix}game_post_characters WHERE thread_id = {$thread_id} LIMIT 1");
    $pc = $db->fetch_array($pc_q);
    if ($pc) {
        $pj_q = $db->query("SELECT id, name, race_name, occupation_name, rango, tripulacion, avatar, banner, is_staff FROM {$prefix}game_personajes WHERE id = " . (int)$pc['character_id'] . " LIMIT 1");
        $pj = $db->fetch_array($pj_q);
        if ($pj) $result = _pj_result($pj, $bb);
    }
} else {
    // No post_id: fallback to current active character
    $cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
    $cfg = $db->fetch_array($cfg_q);
    if ($cfg && $cfg['active_pj_id']) {
        $pj_q = $db->query("SELECT id, name, race_name, occupation_name, rango, tripulacion, avatar, banner, is_staff FROM {$prefix}game_personajes WHERE id = " . (int)$cfg['active_pj_id'] . " LIMIT 1");
        $pj = $db->fetch_array($pj_q);
        if ($pj) $result = _pj_result($pj, $bb);
    }
}

header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'data' => $result,
    'error' => null,
    'meta' => ['endpoint' => 'get_active_pj_for_user'],
]);
