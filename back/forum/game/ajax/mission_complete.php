<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;
use Game\Infrastructure\Persistence\PersonajeRepository;
use Game\Application\Services\AdminRequestService;

header('Content-Type: application/json; charset=utf-8');

global $db;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$characterId = (int)($input['character_id'] ?? 0);
$activeMissionId = (int)($input['active_mission_id'] ?? 0);

if ($characterId <= 0 || $activeMissionId <= 0) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Parámetros inválidos.'], 400);
}

if (game_get_active_pj_id($uid) !== $characterId) {
    GameAjax::json(false, null, ['code' => 403, 'message' => 'Debes usar tu personaje activo.'], 403);
}

$prefix = TABLE_PREFIX;

// Fetch active mission
$amQ = $db->query("
    SELECT ma.*, m.title, m.points_reward, m.berry_reward, m.max_posts
    FROM {$prefix}game_missions_active ma
    JOIN {$prefix}game_missions m ON ma.mission_id = m.id
    WHERE ma.id = {$activeMissionId} AND ma.leader_character_id = {$characterId} LIMIT 1
");
$activeMission = $db->fetch_array($amQ);

if (!$activeMission) {
    GameAjax::json(false, null, ['code' => 404, 'message' => 'No se encontró una misión activa de la cual seas líder.'], 404);
}

if ($activeMission['status'] !== 'active') {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'La misión no se encuentra activa (estado actual: ' . $activeMission['status'] . ').'], 400);
}

// Check how many posts have been made in the thread (or use the post_count in game_missions_active)
$tid = (int)$activeMission['tid'];
$postCount = (int)$activeMission['post_count'];

// If post_count is stored, let's verify if there is an actual post count in MyBB threads table
$threadQ = $db->query("SELECT replies FROM {$prefix}threads WHERE tid = {$tid} LIMIT 1");
if ($threadRow = $db->fetch_array($threadQ)) {
    // MyBB replies column doesn't include the first post, so total posts in thread is replies + 1
    $postCount = (int)$threadRow['replies'] + 1;
}

// Update active mission status to review
$db->write_query("
    UPDATE {$prefix}game_missions_active 
    SET status = 'review', post_count = {$postCount} 
    WHERE id = {$activeMissionId}
");

// Close the thread in MyBB
$db->write_query("UPDATE {$prefix}threads SET closed = '1' WHERE tid = {$tid}");

// Set cooldown for all confirmed participants (14 days)
$db->write_query("
    UPDATE {$prefix}game_mission_participants 
    SET cooldown_until = DATE_ADD(NOW(), INTERVAL 14 DAY) 
    WHERE active_mission_id = {$activeMissionId} AND confirmed = 1
");

// Create request for staff review
$leaderNameQ = $db->query("SELECT name FROM {$prefix}game_personajes WHERE id = {$characterId} LIMIT 1");
$leaderName = $db->fetch_field($leaderNameQ, 'name') ?: 'Líder';

$title = "Revisión Misión: {$activeMission['title']}";
$description = "El grupo liderado por {$leaderName} ha completado la misión '{$activeMission['title']}'. Hilo cerrado. Total de posts redactados: {$postCount} de {$activeMission['max_posts']}. Recompensa: {$activeMission['points_reward']} PD, {$activeMission['berry_reward']} Berries.";
$bburl = rtrim((string)($mybb->settings['bburl'] ?? ''), '/');
$link = "showthread.php?tid=" . $tid;

$requestId = AdminRequestService::create(
    $uid,
    $characterId,
    'mision',
    'mision_review',
    $title,
    $description,
    $link,
    ['active_mission_id' => $activeMissionId]
);

// Notify staff
AdminRequestService::notifyStaffPending("Nueva Misión Completada: " . $activeMission['title'], "/game/public/zona_staff_peticiones.php");

GameAjax::json(true, [
    'active_mission_id' => $activeMissionId,
    'status' => 'review',
    'request_id' => $requestId,
], null);
