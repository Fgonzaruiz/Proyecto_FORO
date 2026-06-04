<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $db, $mybb;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$post_id = isset($input['post_id']) ? (int)$input['post_id'] : 0;
$action_index = isset($input['index']) ? (int)$input['index'] : 0;

if ($post_id <= 0 || $action_index <= 0) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'Parámetros inválidos.']]);
    exit;
}

$prefix = TABLE_PREFIX;

// Fetch post author to verify the current user is the owner
$post_q = $db->query("SELECT uid FROM {$prefix}posts WHERE pid = {$post_id} LIMIT 1");
$post_row = $db->fetch_array($post_q);

if (!$post_row) {
    echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'Mensaje no encontrado.']]);
    exit;
}

if ((int)$post_row['uid'] !== $uid) {
    echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'No tienes permiso para revelar acciones de este mensaje.']]);
    exit;
}

// Fetch hidden_actions_json
$gpc_q = $db->query("SELECT hidden_actions_json FROM {$prefix}game_post_characters WHERE post_id = {$post_id} LIMIT 1");
$gpc_row = $db->fetch_array($gpc_q);

if (!$gpc_row) {
    echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'No hay acciones ocultas en este mensaje.']]);
    exit;
}

$hidden_actions = json_decode($gpc_row['hidden_actions_json'] ?? '[]', true);
if (!is_array($hidden_actions)) {
    $hidden_actions = [];
}

$found = false;
foreach ($hidden_actions as &$act) {
    if ((int)($act['index'] ?? 0) === $action_index) {
        $act['is_revealed'] = 1;
        $found = true;
        break;
    }
}
unset($act);

if (!$found) {
    echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'Acción oculta no encontrada en el mensaje.']]);
    exit;
}

$updated_json = json_encode($hidden_actions, JSON_UNESCAPED_UNICODE);
$esc_json = "'" . $db->escape_string($updated_json) . "'";
$db->write_query("UPDATE {$prefix}game_post_characters SET hidden_actions_json = {$esc_json} WHERE post_id = {$post_id}");

echo json_encode(['ok' => true, 'data' => null, 'error' => null]);
