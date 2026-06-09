<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $mybb;

$uid = GameAjax::requireLogin();
if (game_get_active_staff_level($uid) < 2) {
    GameAjax::fail(403, 'Sin permiso');
}

GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$id = (int)($input['id'] ?? 0);
$decision = (string)($input['decision'] ?? '');

if ($id <= 0) {
    GameAjax::fail(400, 'ID inválido');
}

$result = game_navigation_review_voyage($id, $uid, (string)($mybb->user['username'] ?? 'Staff'), $decision);
if (!$result['ok']) {
    GameAjax::fail(400, $result['message'] ?? 'Error al revisar el viaje');
}

GameAjax::json(true, [
    'id' => $id,
    'decision' => $decision,
    'post_id' => $result['post_id'] ?? null,
]);
