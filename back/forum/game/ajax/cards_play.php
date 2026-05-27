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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => ['code' => 405, 'message' => 'Method not allowed']]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$post_id = (int)($input['post_id'] ?? 0);
$card_ids = $input['card_ids'] ?? [];

if ($post_id <= 0 || !is_array($card_ids)) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'Datos inválidos.']]);
    exit;
}

$prefix = TABLE_PREFIX;

// Verify post belongs to user
$post_q = $db->query("SELECT uid FROM {$prefix}posts WHERE pid = {$post_id} LIMIT 1");
$post = $db->fetch_array($post_q);
if (!$post || (int)$post['uid'] !== $uid) {
    echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'El post no te pertenece.']]);
    exit;
}

// Get active character
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$char_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
if ($char_id <= 0) {
    echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'No hay personaje activo.']]);
    exit;
}

// Fetch active character stats first
$stats = [];
$pj_q = $db->query("SELECT stats_json, stat_fp, stat_dp, stat_rp, stat_vp, stat_ip FROM {$prefix}game_personajes WHERE id = {$char_id} LIMIT 1");
$pj = $db->fetch_array($pj_q);
if ($pj) {
    $stats = json_decode($pj['stats_json'] ?? '{}', true);
    if (!isset($stats['fue'])) $stats['fue'] = (int)($stats['str'] ?? $pj['stat_fp'] ?? 5);
    if (!isset($stats['agi'])) $stats['agi'] = (int)($pj['stat_dp'] ?? 5);
    if (!isset($stats['des'])) $stats['des'] = (int)($stats['res'] ?? $pj['stat_rp'] ?? 5);
    if (!isset($stats['inst'])) $stats['inst'] = (int)($stats['vol'] ?? $pj['stat_vp'] ?? 5);
    if (!isset($stats['esp'])) $stats['esp'] = (int)($stats['vol'] ?? $pj['stat_vp'] ?? 5);
    if (!isset($stats['int'])) $stats['int'] = (int)($pj['stat_ip'] ?? 5);
}

foreach ($card_ids as $cid) {
    $cid = (int)$cid;
    if ($cid <= 0) continue;
    
    // Check if character owns this card and get its current rank
    $own_q = $db->query("SELECT current_rank FROM {$prefix}game_character_cards WHERE character_id = {$char_id} AND card_id = {$cid} LIMIT 1");
    $own = $db->fetch_array($own_q);
    if (!$own) continue;
    $rank = $own['current_rank'];
    
    // Check if already played
    $played_q = $db->query("SELECT id FROM {$prefix}game_post_cards WHERE post_id = {$post_id} AND card_id = {$cid} LIMIT 1");
    if ($db->num_rows($played_q) > 0) continue;
    
    // Check for dice in the card definition
    $card_q = $db->query("SELECT dice FROM {$prefix}game_cards WHERE id = {$cid} LIMIT 1");
    $card = $db->fetch_array($card_q);
    $roll_result = null;
    
    if ($card && !empty($card['dice']) && trim($card['dice']) !== '—') {
        $evaluated = game_evaluate_dice_roll($card['dice'], $stats);
        $roll_result = $db->escape_string($evaluated);
    }
    
    $insert = [
        'post_id' => $post_id,
        'character_id' => $char_id,
        'card_id' => $cid,
        'played_rank' => $rank,
        'roll_result' => $roll_result ?: ''
    ];
    $db->hide_errors();
    $db->insert_query('game_post_cards', $insert);
    $db->show_errors();
}

echo json_encode(['ok' => true, 'data' => null, 'error' => null]);
