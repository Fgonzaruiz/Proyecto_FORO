<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

$uid = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
$post_id = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
$thread_id = isset($_GET['thread_id']) ? (int)$_GET['thread_id'] : 0;
$last_post_for_thread_id = isset($_GET['last_post_for_thread_id']) ? (int)$_GET['last_post_for_thread_id'] : 0;
$top_poster = isset($_GET['top_poster']) ? (int)$_GET['top_poster'] : 0;

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
        'postnum' => (int)($row['postnum'] ?? 0),
        'threadnum' => (int)($row['threadnum'] ?? 0),
    ];
}

// If post_id provided, try to get character stored at post creation time
if ($post_id > 0) {
    $pc_q = $db->query("SELECT character_id FROM {$prefix}game_post_characters WHERE post_id = {$post_id} LIMIT 1");
    $pc = $db->fetch_array($pc_q);
    if ($pc) {
        $pj_q = $db->query("SELECT id, name, race_name, occupation_name, rango, tripulacion, avatar, banner, is_staff, postnum, threadnum FROM {$prefix}game_personajes WHERE id = " . (int)$pc['character_id'] . " LIMIT 1");
        $pj = $db->fetch_array($pj_q);
        if ($pj) $result = _pj_result($pj, $bb);
    }
} elseif ($thread_id > 0) {
    // thread_id provided: look up character stored when thread was created
    $pc_q = $db->query("SELECT character_id FROM {$prefix}game_post_characters WHERE thread_id = {$thread_id} LIMIT 1");
    $pc = $db->fetch_array($pc_q);
    if ($pc) {
        $pj_q = $db->query("SELECT id, name, race_name, occupation_name, rango, tripulacion, avatar, banner, is_staff, postnum, threadnum FROM {$prefix}game_personajes WHERE id = " . (int)$pc['character_id'] . " LIMIT 1");
        $pj = $db->fetch_array($pj_q);
        if ($pj) $result = _pj_result($pj, $bb);
    }
} elseif ($last_post_for_thread_id > 0) {
    // Look up the character of the latest post in this thread
    $pc_q = $db->query("
        SELECT gpc.character_id 
        FROM {$prefix}posts p 
        JOIN {$prefix}game_post_characters gpc ON p.pid = gpc.post_id 
        WHERE p.tid = {$last_post_for_thread_id} 
        ORDER BY p.dateline DESC 
        LIMIT 1
    ");
    $pc = $db->fetch_array($pc_q);
    if ($pc) {
        $pj_q = $db->query("SELECT id, name, race_name, occupation_name, rango, tripulacion, avatar, banner, is_staff, postnum, threadnum FROM {$prefix}game_personajes WHERE id = " . (int)$pc['character_id'] . " LIMIT 1");
        $pj = $db->fetch_array($pj_q);
        if ($pj) $result = _pj_result($pj, $bb);
    }
} elseif ($top_poster > 0 && $uid > 0) {
    // For stats: get the character of this user with the highest postnum
    $pj_q = $db->query("SELECT id, name, race_name, occupation_name, rango, tripulacion, avatar, banner, is_staff, postnum, threadnum FROM {$prefix}game_personajes WHERE user_id = {$uid} ORDER BY postnum DESC LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    if ($pj) $result = _pj_result($pj, $bb);
}

// Fallback to current active character if no post/thread record was found (or if neither was provided)
if (!$result) {
    $cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
    $cfg = $db->fetch_array($cfg_q);
    if ($cfg && $cfg['active_pj_id']) {
        $pj_q = $db->query("SELECT id, name, race_name, occupation_name, rango, tripulacion, avatar, banner, is_staff, postnum, threadnum FROM {$prefix}game_personajes WHERE id = " . (int)$cfg['active_pj_id'] . " LIMIT 1");
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
