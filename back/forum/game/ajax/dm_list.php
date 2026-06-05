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
    JsonResponder::fail(400, ['code' => 'no_active_character', 'message' => 'Selecciona un personaje activo.'], ['endpoint' => 'dm_list']);
    exit;
}

$folder = ($_GET['folder'] ?? 'inbox') === 'sent' ? 'sent' : 'inbox';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(50, max(1, (int)($_GET['per_page'] ?? 20)));

$data = $folder === 'sent'
    ? DirectMessageService::listSent($activePjId, $page, $perPage)
    : DirectMessageService::listInbox($activePjId, $page, $perPage);

JsonResponder::ok($data, ['endpoint' => 'dm_list']);
