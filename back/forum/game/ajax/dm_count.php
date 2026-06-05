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
    JsonResponder::ok(['unread' => 0], ['endpoint' => 'dm_count']);
    exit;
}

$unread = DirectMessageService::unreadCount($activePjId);
JsonResponder::ok(['unread' => $unread], ['endpoint' => 'dm_count']);
