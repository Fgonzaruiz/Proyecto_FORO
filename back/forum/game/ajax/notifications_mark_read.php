<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\Services\NotificationService;
use Game\Presentation\Api\JsonResponder;

global $mybb;

$uid = (int)($mybb->user['uid'] ?? 0);
if ($uid === 0) {
    JsonResponder::fail(401, ['code' => 'unauthorized', 'message' => 'Login required'], ['endpoint' => 'notifications_mark_read']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? (int)$input['id'] : 0;

if ($id <= 0) {
    JsonResponder::fail(400, ['code' => 'invalid_input', 'message' => 'id requerido'], ['endpoint' => 'notifications_mark_read']);
    exit;
}

$ok = NotificationService::markRead($id, $uid);

JsonResponder::ok(['marked_read' => $ok], ['endpoint' => 'notifications_mark_read']);
