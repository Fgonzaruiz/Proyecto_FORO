<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\Services\NotificationService;
use Game\Presentation\Api\JsonResponder;

global $mybb;

$uid = (int)($mybb->user['uid'] ?? 0);
if ($uid === 0) {
    JsonResponder::fail(401, ['code' => 'unauthorized', 'message' => 'Login required'], ['endpoint' => 'notifications_count']);
    exit;
}

$unread = NotificationService::unreadCount($uid);

JsonResponder::ok(['unread' => $unread], ['endpoint' => 'notifications_count']);
