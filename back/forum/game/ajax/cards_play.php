<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $db;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);
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
$pj_q = $db->query("SELECT stats_json FROM {$prefix}game_personajes WHERE id = {$char_id} LIMIT 1");
$pj = $db->fetch_array($pj_q);
if ($pj) {
    $stats_decoded = json_decode($pj['stats_json'] ?? '{}', true);
    $stats = is_array($stats_decoded) ? $stats_decoded : [];
    if (!isset($stats['fue'])) $stats['fue'] = (int)($stats['str'] ?? 5);
    if (!isset($stats['agi'])) $stats['agi'] = 5;
    if (!isset($stats['des'])) $stats['des'] = (int)($stats['res'] ?? 5);
    if (!isset($stats['inst'])) $stats['inst'] = (int)($stats['vol'] ?? 5);
    if (!isset($stats['esp'])) $stats['esp'] = (int)($stats['vol'] ?? 5);
    if (!isset($stats['int'])) $stats['int'] = 5;
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
    
    $fields = [];
    $values = [];
    foreach ($insert as $key => $val) {
        $fields[] = "`" . $db->escape_string($key) . "`";
        $values[] = "'" . $db->escape_string((string)$val) . "'";
    }
    $fields_str = implode(',', $fields);
    $values_str = implode(',', $values);
    $sql = "INSERT INTO {$prefix}game_post_cards ({$fields_str}) VALUES ({$values_str})";
    
    $db->write_query($sql, 1);
}

echo json_encode(['ok' => true, 'data' => null, 'error' => null]);
