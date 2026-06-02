<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\Services\NotificationService;
use Game\Http\GameAjax;
use Game\Presentation\Api\JsonResponder;

global $mybb;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postInput();
GameAjax::requireCsrf($input);
$id = isset($input['id']) ? (int)$input['id'] : 0;

if ($id <= 0) {
    JsonResponder::fail(400, ['code' => 'invalid_input', 'message' => 'id requerido'], ['endpoint' => 'notifications_delete']);
    exit;
}

$ok = NotificationService::delete($id, $uid);

JsonResponder::ok(['deleted' => $ok], ['endpoint' => 'notifications_delete']);
