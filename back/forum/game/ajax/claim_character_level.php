<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\Services\CharacterProgression;
use Game\Http\GameAjax;

header('Content-Type: application/json; charset=utf-8');

global $db;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$character_id = (int)($input['character_id'] ?? 0);

if ($character_id <= 0) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Parámetros inválidos.'], 400);
}

$prefix = TABLE_PREFIX;

$char_q = $db->query("SELECT * FROM {$prefix}game_personajes WHERE id = {$character_id} LIMIT 1");
$character = $db->fetch_array($char_q);

if (!$character) {
    GameAjax::json(false, null, ['code' => 404, 'message' => 'Personaje no encontrado.'], 404);
}

if ((int)$character['user_id'] !== $uid) {
    GameAjax::json(false, null, ['code' => 403, 'message' => 'No eres el propietario de este personaje.'], 403);
}

if ($character['status'] !== 'aprobada') {
    GameAjax::json(false, null, ['code' => 403, 'message' => 'El personaje debe estar aprobado.'], 403);
}

$data = !empty($character['data_json']) ? json_decode($character['data_json'], true) : [];
if (!is_array($data)) {
    $data = [];
}

CharacterProgression::normalize($data);

if (CharacterProgression::getPendingLevels($data) < 1) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'No tienes subidas de nivel pendientes.'], 400);
}

if (!CharacterProgression::canLevelUpThisWeek($data)) {
    $next = CharacterProgression::getNextLevelAvailableAt($data);
    $when = $next ? date('d/m/Y H:i', $next) : 'próximamente';
    GameAjax::json(false, null, ['code' => 429, 'message' => "Solo puedes subir un nivel por semana. Próxima disponible: {$when}."], 429);
}

CharacterProgression::tryApplyPendingLevels($data);

$data_json_esc = $db->escape_string(json_encode($data, JSON_UNESCAPED_UNICODE));
$db->write_query("UPDATE {$prefix}game_personajes SET data_json = '{$data_json_esc}' WHERE id = {$character_id}");

GameAjax::json(true, CharacterProgression::snapshot($data), null);
