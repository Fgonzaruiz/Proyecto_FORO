<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

$uid = GameAjax::requireLogin();

// Verify staff level >= 3
$staffLevel = game_get_active_staff_level($uid);
if ($staffLevel < 3) {
    GameAjax::fail(403, 'No tienes permiso para editar islas');
}

GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$fid = (int)($input['fid'] ?? 0);
if ($fid <= 0) {
    GameAjax::fail(400, 'ID de foro inválido');
}

$prefix = TABLE_PREFIX;

// Verify forum exists
$fq = $db->query("SELECT fid, name FROM {$prefix}forums WHERE fid = {$fid} LIMIT 1");
$forum = $db->fetch_array($fq);
if (!$forum) {
    GameAjax::fail(404, 'El foro no existe');
}

$image = $db->escape_string($input['island_image'] ?? '');
$leader = $db->escape_string($input['leader_name'] ?? '');
$desc = $db->escape_string($input['description'] ?? '');
$terrain = $db->escape_string($input['terrain'] ?? '');
$climate = $db->escape_string($input['climate'] ?? '');
$ctemp = $db->escape_string($input['climate_temp'] ?? '');
$cwind = $db->escape_string($input['climate_wind'] ?? '');
$cprecip = $db->escape_string($input['climate_precip'] ?? '');
$buildings = $db->escape_string($input['buildings'] ?? '');
$defenses = $db->escape_string($input['defenses'] ?? '');
$resources = $db->escape_string($input['resources'] ?? '');
$coordX = (int)($input['coord_x'] ?? 0);
$coordY = (int)($input['coord_y'] ?? 0);
$seaZone = $db->escape_string(preg_replace('/[^a-z_]/', '', (string)($input['sea_zone'] ?? 'east_blue')) ?: 'east_blue');
$baseDanger = max(1, min(5, (int)($input['base_danger'] ?? 1)));
$requiresLogPose = !empty($input['requires_log_pose']) ? 1 : 0;
$requiresCompass = !empty($input['requires_compass']) ? 1 : 0;

// Ensure table exists
if (!$db->table_exists('game_forum_islands')) {
    $db->write_query("CREATE TABLE {$prefix}game_forum_islands (
        fid INT UNSIGNED NOT NULL PRIMARY KEY,
        island_image VARCHAR(500) NOT NULL DEFAULT '',
        leader_name VARCHAR(200) NOT NULL DEFAULT '',
        description TEXT NOT NULL,
        terrain VARCHAR(200) NOT NULL DEFAULT '',
        climate VARCHAR(300) NOT NULL DEFAULT '',
        climate_temp VARCHAR(100) NOT NULL DEFAULT '',
        climate_wind VARCHAR(100) NOT NULL DEFAULT '',
        climate_precip VARCHAR(100) NOT NULL DEFAULT '',
        buildings TEXT NOT NULL,
        defenses TEXT NOT NULL,
        resources VARCHAR(300) NOT NULL DEFAULT '',
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$existing = $db->query("SELECT 1 FROM {$prefix}game_forum_islands WHERE fid = {$fid} LIMIT 1");
$navCols = '';
if ($db->field_exists('coord_x', 'game_forum_islands')) {
    $navCols = ", coord_x={$coordX}, coord_y={$coordY}, sea_zone='{$seaZone}', base_danger={$baseDanger}, requires_log_pose={$requiresLogPose}, requires_compass={$requiresCompass}";
}

if ($db->num_rows($existing)) {
    $db->write_query("UPDATE {$prefix}game_forum_islands SET island_image='{$image}', leader_name='{$leader}', description='{$desc}', terrain='{$terrain}', climate='{$climate}', climate_temp='{$ctemp}', climate_wind='{$cwind}', climate_precip='{$cprecip}', buildings='{$buildings}', defenses='{$defenses}', resources='{$resources}'{$navCols} WHERE fid={$fid}");
} else {
    $navInsertCols = $navCols ? ', coord_x, coord_y, sea_zone, base_danger, requires_log_pose, requires_compass' : '';
    $navInsertVals = $navCols ? ", {$coordX}, {$coordY}, '{$seaZone}', {$baseDanger}, {$requiresLogPose}, {$requiresCompass}" : '';
    $db->write_query("INSERT INTO {$prefix}game_forum_islands (fid, island_image, leader_name, description, terrain, climate, climate_temp, climate_wind, climate_precip, buildings, defenses, resources{$navInsertCols}) VALUES ({$fid}, '{$image}', '{$leader}', '{$desc}', '{$terrain}', '{$climate}', '{$ctemp}', '{$cwind}', '{$cprecip}', '{$buildings}', '{$defenses}', '{$resources}'{$navInsertVals})");
}

GameAjax::json(true, ['fid' => $fid]);
