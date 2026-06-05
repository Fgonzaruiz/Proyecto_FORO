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
    JsonResponder::fail(400, ['code' => 'no_active_character', 'message' => 'Selecciona un personaje activo.'], ['endpoint' => 'dm_send']);
    exit;
}

$toCharacterId = (int)($input['to_character_id'] ?? 0);
$replyToId = (int)($input['reply_to_id'] ?? 0);
$subject = trim((string)($input['subject'] ?? ''));
$body = trim((string)($input['body'] ?? ''));

try {
    if ($replyToId > 0) {
        $dmId = DirectMessageService::send($activePjId, $toCharacterId, $subject, $body, $replyToId);
        $msg = DirectMessageService::getForCharacter($dmId, $activePjId);
        JsonResponder::ok(['id' => $dmId, 'thread_id' => $msg['thread_id'] ?? $dmId], ['endpoint' => 'dm_send']);
    } else {
        $dmId = DirectMessageService::send($activePjId, $toCharacterId, $subject, $body);
        JsonResponder::ok(['id' => $dmId, 'thread_id' => $dmId], ['endpoint' => 'dm_send']);
    }
} catch (\InvalidArgumentException $e) {
    JsonResponder::fail(400, ['code' => 'validation_error', 'message' => $e->getMessage()], ['endpoint' => 'dm_send']);
}
