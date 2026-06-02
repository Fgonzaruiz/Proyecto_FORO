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
$message = trim((string)($input['message'] ?? ''));

if ($request_id <= 0 || $message === '') {
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

// Check who is sending: player or staff
$is_player = ((int)$request['player_uid'] === $uid);
$is_staff = false;
$sender_name = '';

// Check staff level from user config
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;
if ($active_pj_id > 0) {
    $pj_q = $db->query("SELECT name, staff_level FROM {$prefix}game_personajes WHERE id = {$active_pj_id} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    if ($pj) {
        $staff_level = (int)$pj['staff_level'];
        if ($staff_level >= 2) {
            $is_staff = true;
            $sender_name = $pj['name'];
        }
    }
}

if (!$is_player && !$is_staff) {
    echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'No autorizado para participar en este hilo.']]);
    exit;
}

$sender_type = $is_player ? 'player' : 'staff';
if ($is_player) {
    $sender_name = $request['character_name'];
}

// Decode existing discussion
$discussion = !empty($request['discussion_json']) ? json_decode($request['discussion_json'], true) : [];
if (!is_array($discussion)) $discussion = [];

// Add new message
$discussion[] = [
    'sender' => $sender_type,
    'sender_name' => $sender_name,
    'message' => $message,
    'timestamp' => date('Y-m-d H:i:s')
];

$discussion_esc = $db->escape_string(json_encode($discussion, JSON_UNESCAPED_UNICODE));

// If player replied, change status back to 'pendiente' so staff sees it
$status_sql = "";
if ($is_player && $request['status'] !== 'aprobada' && $request['status'] !== 'rechazada') {
    $status_sql = ", status = 'pendiente'";
}

$db->write_query("
    UPDATE {$prefix}game_card_requests 
    SET discussion_json = '{$discussion_esc}'
        {$status_sql}
    WHERE id = {$request_id}
");

echo json_encode([
    'ok' => true,
    'data' => [
        'discussion' => $discussion
    ],
    'error' => null
]);
exit;
