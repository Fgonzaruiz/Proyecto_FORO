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
$action = trim((string)($input['action'] ?? ''));

if ($character_id <= 0 || $card_id <= 0 || !in_array($action, ['upgrade', 'delete'])) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'Parámetros inválidos.']]);
    exit;
}

$prefix = TABLE_PREFIX;

// Fetch character and verify owner
$char_q = $db->query("SELECT user_id FROM {$prefix}game_personajes WHERE id = {$character_id} LIMIT 1");
$character = $db->fetch_array($char_q);

if (!$character) {
    echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'Personaje no encontrado.']]);
    exit;
}

if ((int)$character['user_id'] !== $uid) {
    echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'No eres el propietario de este personaje.']]);
    exit;
}

// Fetch card assignment and current rank
$own_q = $db->query("SELECT current_rank FROM {$prefix}game_character_cards WHERE character_id = {$character_id} AND card_id = {$card_id} LIMIT 1");
$own = $db->fetch_array($own_q);

if (!$own) {
    echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'La carta no está asignada al personaje.']]);
    exit;
}

$current_rank = $own['current_rank'];

// Check if a pending request of the same type already exists
$pending_q = $db->query("
    SELECT id 
    FROM {$prefix}game_card_requests 
    WHERE character_id = {$character_id} 
      AND card_id = {$card_id} 
      AND request_type = '{$db->escape_string($action)}' 
      AND status = 'pendiente' 
    LIMIT 1
");

if ($db->num_rows($pending_q) > 0) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'Ya existe una solicitud pendiente para esta carta.']]);
    exit;
}

// Check if upgrade is requested but card is already max rank (S)
if ($action === 'upgrade' && strtoupper(trim($current_rank)) === 'S') {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'La carta ya está en el rango máximo (S).']]);
    exit;
}

// Insert request
$insert = [
    'character_id' => $character_id,
    'card_id' => $card_id,
    'request_type' => $action,
    'status' => 'pendiente',
    'current_rank' => $current_rank
];
$db->insert_query('game_card_requests', $insert);

echo json_encode(['ok' => true, 'data' => null, 'error' => null]);
