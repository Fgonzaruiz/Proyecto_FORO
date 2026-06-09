<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $db;

$uid = GameAjax::requireLogin();
if (game_get_active_staff_level($uid) < 3) {
    GameAjax::fail(403, 'Sin permiso');
}

GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$fromFid = (int)($input['island_from_fid'] ?? 0);
$toFid = (int)($input['island_to_fid'] ?? 0);
$distance = (int)($input['distance'] ?? 0);
$waypoints = $input['waypoint_fids'] ?? [];
$dangerOverride = isset($input['danger_override']) && $input['danger_override'] !== '' ? (int)$input['danger_override'] : null;
$id = (int)($input['id'] ?? 0);
$delete = !empty($input['delete']);

if ($delete && $id > 0) {
    $db->delete_query('game_navigation_routes', "id = {$id}");
    GameAjax::json(true, ['deleted' => true]);
}

if ($fromFid <= 0 || $toFid <= 0 || $fromFid === $toFid || $distance <= 0) {
    GameAjax::fail(400, 'Ruta inválida');
}

if (!is_array($waypoints)) {
    $waypoints = [];
}
$wpsJson = $db->escape_string(json_encode(array_map('intval', $waypoints)));
$dangerSql = $dangerOverride !== null ? (int)max(1, min(5, $dangerOverride)) : 'NULL';

$prefix = TABLE_PREFIX;

if ($id > 0) {
    $db->write_query("UPDATE {$prefix}game_navigation_routes SET
        island_from_fid = {$fromFid}, island_to_fid = {$toFid}, distance = {$distance},
        waypoint_fids = '{$wpsJson}', danger_override = {$dangerSql}
        WHERE id = {$id}");
    GameAjax::json(true, ['id' => $id]);
}

$db->write_query("INSERT INTO {$prefix}game_navigation_routes (island_from_fid, island_to_fid, distance, waypoint_fids, danger_override)
    VALUES ({$fromFid}, {$toFid}, {$distance}, '{$wpsJson}', {$dangerSql})
    ON DUPLICATE KEY UPDATE distance = {$distance}, waypoint_fids = '{$wpsJson}', danger_override = {$dangerSql}");

GameAjax::json(true, ['id' => (int)$db->insert_id()]);
