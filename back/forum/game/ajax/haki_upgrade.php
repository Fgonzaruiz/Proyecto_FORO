<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;
use Game\Infrastructure\Persistence\PersonajeRepository;
use Game\Shared\StatScale;
use Game\Application\Services\CharacterProgression;

header('Content-Type: application/json; charset=utf-8');

global $db, $mybb;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$characterId = (int)($input['character_id'] ?? 0);
$hakiType = trim((string)($input['haki_type'] ?? ''));

if ($characterId <= 0 || !in_array($hakiType, ['kenbunshoku', 'busoshoku', 'haoshoku'], true)) {
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
    GameAjax::json(false, null, ['code' => 403, 'message' => 'El personaje debe estar aprobado para progresar Haki.'], 403);
}

$prefix = TABLE_PREFIX;

// Cargar estado de Haki actual
$haki_q = $db->query("SELECT * FROM {$prefix}game_haki_progress WHERE character_id = {$characterId} AND haki_type = '{$db->escape_string($hakiType)}' LIMIT 1");
$haki = $db->fetch_array($haki_q);

$currentLevel = $haki ? (int)$haki['nivel'] : 0;
$currentStatus = $haki ? $haki['status'] : 'activo';
$currentUsos = $haki ? (int)$haki['usos_total'] : 0;

if ($currentStatus === 'pendiente_subida') {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Ya tienes una solicitud de subida pendiente para este Haki.'], 400);
}

if ($currentLevel >= 5) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Este Haki ya está en el nivel máximo (5).'], 400);
}

$targetLevel = $currentLevel + 1;

// Requisitos y costes por tipo de Haki y nivel objetivo
$reqs = [
    'kenbunshoku' => [
        1 => ['esp' => 2, 'nivel_pj' => 1, 'usos' => 0, 'coste' => 100],
        2 => ['esp' => 3, 'nivel_pj' => 2, 'usos' => 5, 'coste' => 300],
        3 => ['esp' => 4, 'nivel_pj' => 3, 'usos' => 15, 'coste' => 700],
        4 => ['esp' => 5, 'nivel_pj' => 4, 'usos' => 35, 'coste' => 1500],
        5 => ['esp' => 6, 'nivel_pj' => 5, 'usos' => 60, 'coste' => 3000],
    ],
    'busoshoku' => [
        1 => ['esp' => 2, 'nivel_pj' => 1, 'usos' => 0, 'coste' => 100],
        2 => ['esp' => 3, 'nivel_pj' => 2, 'usos' => 5, 'coste' => 300],
        3 => ['esp' => 4, 'nivel_pj' => 3, 'usos' => 15, 'coste' => 700],
        4 => ['esp' => 5, 'nivel_pj' => 4, 'usos' => 35, 'coste' => 1500],
        5 => ['esp' => 6, 'nivel_pj' => 5, 'usos' => 60, 'coste' => 3000],
    ],
    'haoshoku' => [
        1 => null, // Despertado solo por staff
        2 => ['esp' => 4, 'nivel_pj' => 4, 'usos' => 10, 'coste' => 500],
        3 => ['esp' => 5, 'nivel_pj' => 5, 'usos' => 25, 'coste' => 1200],
        4 => ['esp' => 6, 'nivel_pj' => 5, 'usos' => 45, 'coste' => 2500],
        5 => ['esp' => 6, 'nivel_pj' => 6, 'usos' => 70, 'coste' => 5000],
    ]
];

$targetReq = $reqs[$hakiType][$targetLevel] ?? null;

if ($targetReq === null) {
    if ($hakiType === 'haoshoku' && $currentLevel === 0) {
        GameAjax::json(false, null, ['code' => 403, 'message' => 'El Haki de Conquistador debe ser despertado primero por el staff.'], 403);
    } else {
        GameAjax::json(false, null, ['code' => 403, 'message' => 'No puedes solicitar este nivel de Haki directamente.'], 403);
    }
}

// Cargar stats y calcular ESP efectivo
$stats = !empty($character['stats_json']) ? json_decode($character['stats_json'], true) : [];
if (!is_array($stats)) {
    $stats = [];
}
$statCtx = game_build_stat_context(StatScale::sanitizeRanks($stats), (string)($character['race_name'] ?? ''));
$espEffectiveRank = (int)($statCtx['effective_ranks']['esp'] ?? 1);

// Cargar data_json y calcular nivel / PP
$data = !empty($character['data_json']) ? json_decode($character['data_json'], true) : [];
if (!is_array($data)) {
    $data = [];
}
CharacterProgression::syncLinajeBonusPp($data, (string)($character['race_name'] ?? ''));
CharacterProgression::normalize($data);

$charNivel = game_get_character_nivel($data);
$ppAvailable = (int)($data['pp'] ?? 0);

// Validar requisitos
if ($espEffectiveRank < $targetReq['esp']) {
    $reqLabel = StatScale::rankDisplayLabel($targetReq['esp']);
    $currLabel = StatScale::rankDisplayLabel($espEffectiveRank);
    GameAjax::json(false, null, ['code' => 400, 'message' => "Espíritu efectivo insuficiente. Requerido: {$reqLabel}, Tienes: {$currLabel}."], 400);
}

if ($charNivel < $targetReq['nivel_pj']) {
    GameAjax::json(false, null, ['code' => 400, 'message' => "Nivel de personaje insuficiente. Requerido: Nivel {$targetReq['nivel_pj']}, Tienes: Nivel {$charNivel}."], 400);
}

if ($currentUsos < $targetReq['usos']) {
    GameAjax::json(false, null, ['code' => 400, 'message' => "Usos acumulados insuficientes. Requerido: {$targetReq['usos']} usos, Tienes: {$currentUsos}."], 400);
}

$cost = (int)$targetReq['coste'];
if ($ppAvailable < $cost) {
    GameAjax::json(false, null, ['code' => 400, 'message' => "PP insuficientes. Coste: {$cost} PP, Tienes: {$ppAvailable} PP."], 400);
}

// Realizar la transacción
$data['pp'] = max(0, $ppAvailable - $cost);
$dataJsonEsc = $db->escape_string(json_encode($data, JSON_UNESCAPED_UNICODE));
$db->write_query("UPDATE {$prefix}game_personajes SET data_json = '{$dataJsonEsc}' WHERE id = {$characterId}");

// Crear o actualizar fila en game_haki_progress
$db->write_query("
    INSERT INTO {$prefix}game_haki_progress (character_id, haki_type, status, pp_reservados)
    VALUES ({$characterId}, '{$db->escape_string($hakiType)}', 'pendiente_subida', {$cost})
    ON DUPLICATE KEY UPDATE
        status = 'pendiente_subida',
        pp_reservados = {$cost}
");

// Crear notificación
$bburl = rtrim((string)($mybb->settings['bburl'] ?? ''), '/');
$link = $bburl . '/game/personaje.php?id=' . $characterId;
$hakiLabel = $hakiType === 'kenbunshoku' ? 'Observación' : ($hakiType === 'busoshoku' ? 'Armamento' : 'Conquistador');

if (function_exists('game_create_notification')) {
    game_create_notification(
        $uid,
        'haki_upgrade_pending',
        'Solicitud de Haki enviada',
        "Has solicitado subir tu Haki de {$hakiLabel} al nivel {$targetLevel}. Se han reservado {$cost} PP.",
        $link,
        $characterId
    );
}

GameAjax::json(true, [
    'character_id' => $characterId,
    'haki_type' => $hakiType,
    'target_level' => $targetLevel,
    'pp_spent' => $cost,
    'new_pp' => $data['pp'],
    'status' => 'pendiente_subida'
], null);
