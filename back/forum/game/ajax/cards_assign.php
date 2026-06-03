<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $db;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();

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

$input = GameAjax::postJson();
GameAjax::requireCsrf($input);
$character_id = (int)($input['character_id'] ?? 0);
$card_id = (int)($input['card_id'] ?? 0);

if ($character_id <= 0 || $card_id <= 0) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'IDs inválidos.']]);
    exit;
}

// Buscar el rango original de la carta en la base de datos
$card_q = $db->query("SELECT rank FROM {$prefix}game_cards WHERE id = {$card_id} LIMIT 1");
$card = $db->fetch_array($card_q);
if (!$card) {
    echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'La carta seleccionada no existe.']]);
    exit;
}
$rank = $db->escape_string($card['rank']);

// Insert or update on duplicate key
$db->write_query("
    INSERT INTO {$prefix}game_character_cards (character_id, card_id, current_rank, assigned_by) 
    VALUES ({$character_id}, {$card_id}, '{$rank}', {$uid}) 
    ON DUPLICATE KEY UPDATE current_rank = '{$rank}', assigned_by = {$uid}
");

echo json_encode(['ok' => true, 'data' => null, 'error' => null]);
