<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\Services\DirectMessageService;
use Game\Http\GameAjax;
use Game\Presentation\Api\JsonResponder;

global $mybb;

$uid = GameAjax::requireLogin();
$activePjId = game_get_active_pj_id($uid);
if ($activePjId <= 0) {
    JsonResponder::fail(400, ['code' => 'no_active_character', 'message' => 'Selecciona un personaje activo.'], ['endpoint' => 'dm_read']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    JsonResponder::fail(400, ['code' => 'invalid_id', 'message' => 'ID inválido.'], ['endpoint' => 'dm_read']);
    exit;
}

$message = DirectMessageService::getForCharacter($id, $activePjId);
if (!$message) {
    JsonResponder::fail(404, ['code' => 'not_found', 'message' => 'Mensaje no encontrado.'], ['endpoint' => 'dm_read']);
    exit;
}

if ($message['is_inbox'] && !$message['is_read']) {
    DirectMessageService::markRead($id, $activePjId);
    $message['is_read'] = true;
}

JsonResponder::ok($message, ['endpoint' => 'dm_read']);
