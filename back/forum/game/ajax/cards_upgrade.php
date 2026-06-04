<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\Services\CharacterProgression;
use Game\Http\GameAjax;

global $db;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);
$character_id = (int)($input['character_id'] ?? 0);
$card_id = (int)($input['card_id'] ?? 0);
$new_rank = isset($input['new_rank']) ? strtoupper(trim((string)$input['new_rank'])) : null;

if ($character_id <= 0 || $card_id <= 0) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'IDs inválidos.']]);
    exit;
}

$prefix = TABLE_PREFIX;

// Fetch character owner
$char_q = $db->query("SELECT user_id FROM {$prefix}game_personajes WHERE id = {$character_id} LIMIT 1");
$character = $db->fetch_array($char_q);

if (!$character) {
    echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'Personaje no encontrado.']]);
    exit;
}

$is_owner = ((int)$character['user_id'] === $uid);

// Check if staff
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;
if ($active_pj_id > 0) {
    $pj_q = $db->query("SELECT staff_level FROM {$prefix}game_personajes WHERE id = {$active_pj_id} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    $staff_level = $pj ? (int)$pj['staff_level'] : 0;
}

$is_staff = ($staff_level >= 3);

if (!$is_owner && !$is_staff) {
    echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'No tienes permiso para modificar este personaje.']]);
    exit;
}

// Check if the card is assigned to the character
$own_q = $db->query("SELECT current_rank FROM {$prefix}game_character_cards WHERE character_id = {$character_id} AND card_id = {$card_id} LIMIT 1");
$own = $db->fetch_array($own_q);

if (!$own) {
    echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'La carta no está asignada al personaje.']]);
    exit;
}

$current_rank = strtoupper(trim($own['current_rank']));
$target_rank = $new_rank;

if ($target_rank === null) {
    // Auto promote rank: C -> B -> A -> S
    $ranks = ['C', 'B', 'A', 'S'];
    $idx = array_search($current_rank, $ranks);
    if ($idx === false) {
        $target_rank = 'C';
    } elseif ($idx >= count($ranks) - 1) {
        echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'La carta ya está en el rango máximo (S).']]);
        exit;
    } else {
        $target_rank = $ranks[$idx + 1];
    }
} else {
    // Validate rank
    $allowed_ranks = ['C', 'B', 'A', 'S'];
    if (!in_array($target_rank, $allowed_ranks)) {
        echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'Rango inválido. Rangos permitidos: C, B, A, S.']]);
        exit;
    }
}

$upgradeCostPp = 5;

if (!$is_staff) {
    $data_q = $db->query("SELECT data_json FROM {$prefix}game_personajes WHERE id = {$character_id} LIMIT 1");
    $data_row = $db->fetch_array($data_q);
    $data = !empty($data_row['data_json']) ? json_decode($data_row['data_json'], true) : [];
    if (!is_array($data)) {
        $data = [];
    }
    CharacterProgression::normalize($data);

    if ((int)$data['pp'] < $upgradeCostPp) {
        GameAjax::json(false, null, [
            'code' => 400,
            'message' => "Necesitas {$upgradeCostPp} PP para mejorar la carta (tienes {$data['pp']}).",
        ], 400);
    }

    $alloc = CharacterProgression::allocatePpSpend(
        $upgradeCostPp,
        (int)$data['pp'],
        (int)$data['pp_linaje']
    );
    $data['pp'] = $alloc['new_pp'];
    $data['pp_linaje'] = $alloc['new_pp_linaje'];
    $dataEsc = $db->escape_string(json_encode($data, JSON_UNESCAPED_UNICODE));
    $db->write_query("UPDATE {$prefix}game_personajes SET data_json = '{$dataEsc}' WHERE id = {$character_id}");
}

$db->write_query("
    UPDATE {$prefix}game_character_cards 
    SET current_rank = '{$db->escape_string($target_rank)}' 
    WHERE character_id = {$character_id} AND card_id = {$card_id}
");

GameAjax::json(true, [
    'new_rank' => $target_rank,
    'pp_spent' => $is_staff ? 0 : $upgradeCostPp,
], null);
