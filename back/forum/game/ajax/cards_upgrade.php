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

// Perform update
$db->write_query("
    UPDATE {$prefix}game_character_cards 
    SET current_rank = '{$db->escape_string($target_rank)}' 
    WHERE character_id = {$character_id} AND card_id = {$card_id}
");

echo json_encode(['ok' => true, 'data' => ['new_rank' => $target_rank], 'error' => null]);
