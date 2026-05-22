<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\Services\NotificationService;
use Game\Presentation\Api\JsonResponder;

global $mybb;

$uid = (int)($mybb->user['uid'] ?? 0);
if ($uid === 0) {
    JsonResponder::fail(401, ['code' => 'unauthorized', 'message' => 'Login required'], ['endpoint' => 'notifications_list']);
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(50, max(1, (int)($_GET['per_page'] ?? 20)));

$data = NotificationService::list($uid, $page, $perPage);

JsonResponder::ok($data, ['endpoint' => 'notifications_list']);
