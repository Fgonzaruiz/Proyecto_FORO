<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\Services\NotificationService;
use Game\Presentation\Api\JsonResponder;

global $mybb;

$uid = (int)($mybb->user['uid'] ?? 0);
if ($uid === 0) {
    JsonResponder::fail(401, ['code' => 'unauthorized', 'message' => 'Login required'], ['endpoint' => 'notifications_mark_all_read']);
    exit;
}

NotificationService::markAllRead($uid);

JsonResponder::ok(['marked_all_read' => true], ['endpoint' => 'notifications_mark_all_read']);
