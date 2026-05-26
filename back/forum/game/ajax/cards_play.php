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

// We don't overwrite if it already exists, just insert the new ones or re-insert?
// Let's delete existing for this post and re-insert to be safe, BUT what about dice rolls?
// Actually, dice rolls should be immutable. If we re-play, we'd lose the roll result.
// To keep it simple, we just insert cards that aren't already played on this post.

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
        // Simple automatic dice roll simulation: "3d6", "1d20+FUE", etc.
        // We'll just generate a visual string for now.
        // Real logic would parse it and roll random numbers.
        $formula = trim($card['dice']);
        $match = [];
        if (preg_match('/^(\d+)d(\d+)/i', $formula, $match)) {
            $num = (int)$match[1];
            $faces = (int)$match[2];
            $rolls = [];
            $total = 0;
            for ($i = 0; $i < $num; $i++) {
                $r = mt_rand(1, $faces);
                $rolls[] = $r;
                $total += $r;
            }
            $roll_result = $db->escape_string("[" . implode(", ", $rolls) . "] = " . $total . " (Base: " . $formula . ")");
        } else {
            // Not parseable as pure dice, just record it was rolled
            $roll_result = $db->escape_string("Tirada automática: " . $formula);
        }
    }
    
    $insert = [
        'post_id' => $post_id,
        'character_id' => $char_id,
        'card_id' => $cid,
        'played_rank' => $rank,
        'roll_result' => $roll_result ?: ''
    ];
    $db->insert_query('game_post_cards', $insert);
}

echo json_encode(['ok' => true, 'data' => null, 'error' => null]);
