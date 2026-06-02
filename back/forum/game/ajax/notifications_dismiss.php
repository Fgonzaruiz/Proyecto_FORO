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
$dismissed = isset($input['dismissed']) ? (bool)$input['dismissed'] : true;

if ($id <= 0) {
    JsonResponder::fail(400, ['code' => 'invalid_input', 'message' => 'id requerido'], ['endpoint' => 'notifications_dismiss']);
    exit;
}

$ok = NotificationService::toggleDismiss($id, $uid, $dismissed);

JsonResponder::ok(['toggled' => $ok, 'is_dismissed' => $dismissed], ['endpoint' => 'notifications_dismiss']);
