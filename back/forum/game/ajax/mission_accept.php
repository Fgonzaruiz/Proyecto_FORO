<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;
use Game\Infrastructure\Persistence\PersonajeRepository;

header('Content-Type: application/json; charset=utf-8');

global $db;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$characterId = (int)($input['character_id'] ?? 0);
$missionId = (int)($input['mission_id'] ?? 0);
$companions = is_array($input['companions'] ?? null) ? $input['companions'] : [];

if ($characterId <= 0 || $missionId <= 0) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Parámetros inválidos.'], 400);
}

if (game_get_active_pj_id($uid) !== $characterId) {
    GameAjax::json(false, null, ['code' => 403, 'message' => 'Debes usar tu personaje activo.'], 403);
}

$personajes = new PersonajeRepository();
$character = $personajes->findByIdForUser($characterId, $uid);

if ($character === null) {
    GameAjax::json(false, null, ['code' => 404, 'message' => 'Personaje no encontrado.'], 404);
}

$error = '';
$activeId = game_accept_mission($characterId, $missionId, $companions, $error);

if ($activeId === null) {
    GameAjax::json(false, null, ['code' => 400, 'message' => $error], 400);
}

// Fetch thread details to return thread URL
$prefix = TABLE_PREFIX;
$tq = $db->query("SELECT tid FROM {$prefix}game_missions_active WHERE id = {$activeId} LIMIT 1");
$tid = (int)$db->fetch_field($tq, 'tid');
$bburl = rtrim((string)($mybb->settings['bburl'] ?? ''), '/');
$threadUrl = $bburl . '/showthread.php?tid=' . $tid;

GameAjax::json(true, [
    'active_mission_id' => $activeId,
    'thread_id' => $tid,
    'thread_url' => $threadUrl,
], null);
