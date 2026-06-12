<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\Services\CharacterProgression;
use Game\Http\GameAjax;
use Game\Infrastructure\Persistence\PersonajeRepository;
use Game\Shared\StatScale;

header('Content-Type: application/json; charset=utf-8');

global $db, $mybb;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$characterId = (int)($input['character_id'] ?? 0);
$type = trim((string)($input['type'] ?? ''));
$catalogId = (int)($input['catalog_id'] ?? 0);

if ($characterId <= 0 || $catalogId <= 0 || !in_array($type, ['oficio', 'disciplina'], true)) {
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

if (($character['status'] ?? '') !== 'aprobada') {
    GameAjax::json(false, null, ['code' => 403, 'message' => 'El personaje debe estar aprobado para adquirir competencias.'], 403);
}

$data = !empty($character['data_json']) ? json_decode($character['data_json'], true) : [];
if (!is_array($data)) {
    $data = [];
}

CharacterProgression::syncLinajeBonusPp($data, (string)($character['race_name'] ?? ''));
CharacterProgression::normalize($data);

$charNivel = game_get_character_nivel($data);
$ppAvailable = (int)($data['pp'] ?? 0);

$stats = !empty($character['stats_json']) ? json_decode($character['stats_json'], true) : [];
if (!is_array($stats)) {
    $stats = [];
}
$statCtx = game_build_stat_context(StatScale::sanitizeRanks($stats), (string)($character['race_name'] ?? ''));
$espEffectiveRank = (int)($statCtx['effective_ranks']['esp'] ?? 1);

$prefix = TABLE_PREFIX;
$name = '';
$cost = 0;

if ($type === 'oficio') {
    $catalog = $db->fetch_array($db->query(
        "SELECT * FROM {$prefix}game_oficios WHERE id = {$catalogId} AND is_active = 1 LIMIT 1"
    ));
    if (!$catalog) {
        GameAjax::json(false, null, ['code' => 404, 'message' => 'Oficio no encontrado en el catálogo.'], 404);
    }
    if (game_oficio_character_owns($characterId, $catalogId)) {
        GameAjax::json(false, null, ['code' => 400, 'message' => 'Ya tienes este oficio.'], 400);
    }
    $alreadyOwned = game_oficio_count_for_character($characterId);
    $cost = game_get_acquisition_cost($alreadyOwned, 'oficio');
    $nivelReq = game_get_acquisition_level_required($alreadyOwned);
    if ($charNivel < $nivelReq) {
        GameAjax::json(false, null, ['code' => 400, 'message' => 'Nivel insuficiente (requiere nivel ' . $nivelReq . ').'], 400);
    }
    if ($ppAvailable < $cost) {
        GameAjax::json(false, null, ['code' => 400, 'message' => 'PP insuficientes (requiere ' . $cost . ' PP).'], 400);
    }
    $name = (string)$catalog['name'];
    game_oficio_set_character_rank($characterId, $catalogId, 1);
} else {
    $catalog = $db->fetch_array($db->query(
        "SELECT * FROM {$prefix}game_disciplinas WHERE id = {$catalogId} AND is_active = 1 LIMIT 1"
    ));
    if (!$catalog) {
        GameAjax::json(false, null, ['code' => 404, 'message' => 'Disciplina no encontrada en el catálogo.'], 404);
    }
    if (game_disciplina_character_owns($characterId, $catalogId)) {
        GameAjax::json(false, null, ['code' => 400, 'message' => 'Ya tienes esta disciplina.'], 400);
    }
    $ruleCheck = game_disciplina_validate_acquire_rules($catalog, $espEffectiveRank);
    if (!$ruleCheck['ok']) {
        GameAjax::json(false, null, ['code' => 403, 'message' => $ruleCheck['reason']], 403);
    }
    $alreadyOwned = game_disciplina_count_for_character($characterId);
    $cost = game_disciplina_acquire_pp_cost($catalog, $alreadyOwned);
    $nivelReq = game_get_acquisition_level_required($alreadyOwned);
    if ($charNivel < $nivelReq) {
        GameAjax::json(false, null, ['code' => 400, 'message' => 'Nivel insuficiente (requiere nivel ' . $nivelReq . ').'], 400);
    }
    if ($ppAvailable < $cost) {
        GameAjax::json(false, null, ['code' => 400, 'message' => 'PP insuficientes (requiere ' . $cost . ' PP).'], 400);
    }
    $name = (string)$catalog['name'];
    game_disciplina_set_character_rank($characterId, $catalogId, 1);
}

$data['pp'] = max(0, $ppAvailable - $cost);
$dataJsonEsc = $db->escape_string(json_encode($data, JSON_UNESCAPED_UNICODE));
$db->write_query("UPDATE {$prefix}game_personajes SET data_json = '{$dataJsonEsc}' WHERE id = {$characterId}");

$bburl = rtrim((string)($mybb->settings['bburl'] ?? ''), '/');
$link = $bburl . '/game/personaje.php?id=' . $characterId;
if (function_exists('game_create_notification')) {
    $body = $cost > 0
        ? "Has adquirido «{$name}» (grado I) por {$cost} PP."
        : "Has adquirido «{$name}» (grado I).";
    game_create_notification($uid, 'competencia_acquired', 'Nueva competencia', $body, $link, $characterId);
}

GameAjax::json(true, [
    'character_id' => $characterId,
    'type' => $type,
    'catalog_id' => $catalogId,
    'name' => $name,
    'rank' => 1,
    'rank_label' => game_grado_label(1),
    'pp_spent' => $cost,
    'new_pp' => (int)$data['pp'],
    'nivel' => $charNivel,
], null);
