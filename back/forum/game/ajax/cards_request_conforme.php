<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $db;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);
$request_id = (int)($input['request_id'] ?? 0);

if ($request_id <= 0) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'Parámetros inválidos.']]);
    exit;
}

$prefix = TABLE_PREFIX;

// Fetch request and character details
$req_q = $db->query("
    SELECT r.*, p.user_id as player_uid, p.name as character_name 
    FROM {$prefix}game_card_requests r
    JOIN {$prefix}game_personajes p ON r.character_id = p.id
    WHERE r.id = {$request_id} LIMIT 1
");
$request = $db->fetch_array($req_q);

if (!$request) {
    echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'Solicitud no encontrada.']]);
    exit;
}

// Only the character owner can click "conforme"
if ((int)$request['player_uid'] !== $uid) {
    echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'No tienes permiso para aprobar esta solicitud.']]);
    exit;
}

if ($request['status'] !== 'pendiente') {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'La solicitud no se encuentra pendiente.']]);
    exit;
}

// Decode existing discussion and append system message
$discussion = !empty($request['discussion_json']) ? json_decode($request['discussion_json'], true) : [];
if (!is_array($discussion)) $discussion = [];

$discussion[] = [
    'sender' => 'player',
    'sender_name' => $request['character_name'],
    'message' => "El jugador ha marcado la solicitud como CONFORME. Esperando aprobación final del Administrador.",
    'timestamp' => date('Y-m-d H:i:s')
];

$discussion_esc = $db->escape_string(json_encode($discussion, JSON_UNESCAPED_UNICODE));

$db->write_query("
    UPDATE {$prefix}game_card_requests 
    SET status = 'conforme',
        discussion_json = '{$discussion_esc}'
    WHERE id = {$request_id}
");

echo json_encode(['ok' => true, 'data' => null, 'error' => null]);
exit;
