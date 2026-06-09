<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $db;

$uid = GameAjax::requireLogin();
if (game_get_active_staff_level($uid) < 2) {
    GameAjax::fail(403, 'Sin permiso');
}

GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$id = (int)($input['id'] ?? 0);
$status = (string)($input['status'] ?? '');
$allowed = ['active', 'arrived', 'cancelled'];

if ($id <= 0 || !in_array($status, $allowed, true)) {
    GameAjax::fail(400, 'Datos inválidos');
}

$prefix = TABLE_PREFIX;
$esc = $db->escape_string($status);
$db->write_query("UPDATE {$prefix}game_navigation_voyages SET status = '{$esc}' WHERE id = {$id}");

GameAjax::json(true, ['id' => $id, 'status' => $status]);
