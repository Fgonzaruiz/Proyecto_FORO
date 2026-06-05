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
    JsonResponder::fail(400, ['code' => 'no_active_character', 'message' => 'Selecciona un personaje activo.'], ['endpoint' => 'dm_thread']);
    exit;
}

$threadId = (int)($_GET['thread_id'] ?? $_GET['id'] ?? 0);
if ($threadId <= 0) {
    JsonResponder::fail(400, ['code' => 'invalid_id', 'message' => 'Hilo inválido.'], ['endpoint' => 'dm_thread']);
    exit;
}

$thread = DirectMessageService::getThread($threadId, $activePjId);
if (!$thread) {
    JsonResponder::fail(404, ['code' => 'not_found', 'message' => 'Hilo no encontrado.'], ['endpoint' => 'dm_thread']);
    exit;
}

JsonResponder::ok($thread, ['endpoint' => 'dm_thread']);
