<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $db;

$uid = GameAjax::requireLogin();
if (game_get_active_staff_level($uid) < 3) {
    GameAjax::fail(403, 'Sin permiso');
}

if (!$db->table_exists('game_navigation_routes')) {
    GameAjax::json(true, ['routes' => []]);
}

$prefix = TABLE_PREFIX;
$q = $db->query("SELECT r.*, f1.name AS from_name, f2.name AS to_name
    FROM {$prefix}game_navigation_routes r
    JOIN {$prefix}forums f1 ON f1.fid = r.island_from_fid
    JOIN {$prefix}forums f2 ON f2.fid = r.island_to_fid
    ORDER BY r.id DESC");

$routes = [];
while ($row = $db->fetch_array($q)) {
    $row['waypoint_fids'] = json_decode($row['waypoint_fids'] ?? '[]', true);
    $routes[] = $row;
}

GameAjax::json(true, ['routes' => $routes]);
