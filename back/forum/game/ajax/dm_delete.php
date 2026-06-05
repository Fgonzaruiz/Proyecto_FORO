<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\Services\DirectMessageService;
use Game\Http\GameAjax;
use Game\Presentation\Api\JsonResponder;

global $mybb;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$activePjId = game_get_active_pj_id($uid);
if ($activePjId <= 0) {
    JsonResponder::fail(400, ['code' => 'no_active_character', 'message' => 'Selecciona un personaje activo.'], ['endpoint' => 'dm_delete']);
    exit;
}

$id = (int)($input['id'] ?? 0);
if ($id <= 0) {
    JsonResponder::fail(400, ['code' => 'invalid_id', 'message' => 'ID inválido.'], ['endpoint' => 'dm_delete']);
    exit;
}

$ok = DirectMessageService::delete($id, $activePjId);
if (!$ok) {
    JsonResponder::fail(404, ['code' => 'not_found', 'message' => 'Mensaje no encontrado.'], ['endpoint' => 'dm_delete']);
    exit;
}

JsonResponder::ok(['deleted' => true], ['endpoint' => 'dm_delete']);
