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
    JsonResponder::fail(400, ['code' => 'no_active_character', 'message' => 'Selecciona un personaje activo.'], ['endpoint' => 'dm_search_characters']);
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
$chars = DirectMessageService::searchCharacters($q, $activePjId, 20);

JsonResponder::ok(['characters' => $chars], ['endpoint' => 'dm_search_characters']);
