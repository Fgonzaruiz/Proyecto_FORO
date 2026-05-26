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

$input = json_decode(file_get_contents('php://input'), true);
$character_id = (int)($input['character_id'] ?? 0);
$card_id = (int)($input['card_id'] ?? 0);
$rank = $db->escape_string($input['rank'] ?? 'C');

if ($character_id <= 0 || $card_id <= 0) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'IDs inválidos.']]);
    exit;
}

// Insert or update on duplicate key
$db->write_query("
    INSERT INTO {$prefix}game_character_cards (character_id, card_id, current_rank, assigned_by) 
    VALUES ({$character_id}, {$card_id}, '{$rank}', {$uid}) 
    ON DUPLICATE KEY UPDATE current_rank = '{$rank}', assigned_by = {$uid}
");

echo json_encode(['ok' => true, 'data' => null, 'error' => null]);
