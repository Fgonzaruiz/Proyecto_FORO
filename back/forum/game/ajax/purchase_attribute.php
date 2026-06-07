<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\Services\CharacterProgression;
use Game\Http\GameAjax;
use Game\Infrastructure\Persistence\PersonajeRepository;
use Game\Shared\StatScale;

header('Content-Type: application/json; charset=utf-8');

global $db;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$character_id = (int)($input['character_id'] ?? 0);
$stat = trim((string)($input['stat'] ?? ''));

if ($character_id <= 0 || !in_array($stat, StatScale::STAT_KEYS, true)) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Parámetros inválidos.'], 400);
}

$personajes = new PersonajeRepository();
$character = $personajes->findByIdForUser($character_id, $uid);

if ($character === null) {
    GameAjax::json(false, null, ['code' => 404, 'message' => 'Personaje no encontrado.'], 404);
}

if ($character['status'] !== 'aprobada') {
    GameAjax::json(false, null, ['code' => 403, 'message' => 'El personaje debe estar aprobado para realizar compras.'], 403);
}

$data = !empty($character['data_json']) ? json_decode($character['data_json'], true) : [];
if (!is_array($data)) {
    $data = [];
}

$stats = !empty($character['stats_json']) ? json_decode($character['stats_json'], true) : [];
if (!is_array($stats)) {
    $stats = [];
}
$stats = StatScale::sanitizeRanks($stats);

CharacterProgression::syncLinajeBonusPp($data, (string)($character['race_name'] ?? ''));
CharacterProgression::normalize($data);

$validation = CharacterProgression::validateStatUpgrade($data, $stats, $stat);
if (!($validation['ok'] ?? false)) {
    GameAjax::json(false, null, ['code' => 400, 'message' => $validation['error'] ?? 'Compra no permitida.'], 400);
}

try {
    $result = CharacterProgression::applyStatUpgrade($data, $stats, $stat);
} catch (\InvalidArgumentException $e) {
    GameAjax::json(false, null, ['code' => 400, 'message' => $e->getMessage()], 400);
}

$prefix = TABLE_PREFIX;
$data_json_esc = $db->escape_string(json_encode($data, JSON_UNESCAPED_UNICODE));
$stats_json_esc = $db->escape_string(json_encode($stats, JSON_UNESCAPED_UNICODE));

$db->write_query("
    UPDATE {$prefix}game_personajes
    SET data_json = '{$data_json_esc}',
        stats_json = '{$stats_json_esc}'
    WHERE id = {$character_id}
");

$snapshot = CharacterProgression::snapshot($data, $stats);

GameAjax::json(true, array_merge($snapshot, [
    'new_pp' => $result['new_pp'],
    'new_pp_linaje' => $result['new_pp_linaje'],
    'new_stats' => $stats,
    'upgrade_cost' => $result['upgrade_cost'],
    'stat_upgraded' => $stat,
]), null);
