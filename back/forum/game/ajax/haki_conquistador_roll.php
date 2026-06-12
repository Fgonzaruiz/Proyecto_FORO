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

$prefix = TABLE_PREFIX;

// Cargar nivel de staff del usuario activo
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;

if ($active_pj_id > 0) {
    $pj_q = $db->query("SELECT staff_level, is_staff FROM {$prefix}game_personajes WHERE id = {$active_pj_id} AND user_id = {$uid} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    if ($pj && (int)$pj['is_staff']) {
        $staff_level = (int)$pj['staff_level'];
    }
}

$characterId = (int)($input['character_id'] ?? 0);

if ($characterId <= 0) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'ID de personaje inválido.'], 400);
}

// Cargar personaje objetivo
$personajes = new PersonajeRepository();
$targetCharacter = $personajes->findById($characterId);

if ($targetCharacter === null) {
    GameAjax::json(false, null, ['code' => 404, 'message' => 'Personaje objetivo no encontrado.'], 404);
}

// Validar que el usuario sea el dueño o sea staff
$isOwner = ((int)($targetCharacter['user_id'] ?? 0) === $uid);
$isStaff = ($staff_level >= 2);

if (!$isOwner && !$isStaff) {
    GameAjax::json(false, null, ['code' => 403, 'message' => 'Permiso denegado. Solo puedes despertar tu propio Haki o requiere ser moderador.'], 403);
}

if (($targetCharacter['status'] ?? '') !== 'aprobada') {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'El personaje objetivo debe estar aprobado.'], 400);
}

// Validar que no posea ya Haoshoku (nivel > 0)
$haki_q = $db->query("SELECT nivel FROM {$prefix}game_haki_progress WHERE character_id = {$characterId} AND haki_type = 'haoshoku' LIMIT 1");
$haki = $db->fetch_array($haki_q);
$currentLevel = $haki ? (int)$haki['nivel'] : 0;

if ($currentLevel > 0) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'El personaje ya ha despertado el Haki de Conquistador.'], 400);
}

// Cargar stats y calcular ESP efectivo del personaje objetivo
$stats = !empty($targetCharacter['stats_json']) ? json_decode($targetCharacter['stats_json'], true) : [];
if (!is_array($stats)) {
    $stats = [];
}
$statCtx = game_build_stat_context(StatScale::sanitizeRanks($stats), (string)($targetCharacter['race_name'] ?? ''));
$espEffectiveRank = (int)($statCtx['effective_ranks']['esp'] ?? 1);

// Cargar data_json y calcular nivel / PP del personaje objetivo
$data = !empty($targetCharacter['data_json']) ? json_decode($targetCharacter['data_json'], true) : [];
if (!is_array($data)) {
    $data = [];
}
CharacterProgression::syncLinajeBonusPp($data, (string)($targetCharacter['race_name'] ?? ''));
CharacterProgression::normalize($data);

$charNivel = game_get_character_nivel($data);
$ppAvailable = (int)($data['pp'] ?? 0);

// Validar requisitos para el despertar de Haoshoku
if ($espEffectiveRank < 4) { // Rango A mínimo
    $reqLabel = StatScale::rankDisplayLabel(4);
    $currLabel = StatScale::rankDisplayLabel($espEffectiveRank);
    GameAjax::json(false, null, ['code' => 400, 'message' => "El personaje requiere un rango de Espíritu efectivo de al menos {$reqLabel} (A). Actual: {$currLabel}."], 400);
}

if ($charNivel < 4) {
    GameAjax::json(false, null, ['code' => 400, 'message' => "El personaje requiere un Nivel Global de al menos 4. Actual: Nivel {$charNivel}."], 400);
}

$cost = 500;
if ($ppAvailable < $cost) {
    GameAjax::json(false, null, ['code' => 400, 'message' => "El personaje requiere al menos {$cost} PP. Actual: {$ppAvailable} PP."], 400);
}

// Ejecutar la tirada
$roll = rand(1, 100);
$bonus = ($espEffectiveRank - 4) * 5;
$total = $roll + $bonus;

$unlockedLevel = 0;
$resultLabel = 'Fallo';

if ($total >= 91) {
    $unlockedLevel = 3; // Medio
    $resultLabel = 'Despertar Poderoso (Grado III)';
} elseif ($total >= 71) {
    $unlockedLevel = 2; // Básico
    $resultLabel = 'Despertar Básico (Grado II)';
} elseif ($total >= 41) {
    $unlockedLevel = 1; // Latente
    $resultLabel = 'Despertar Latente (Grado I)';
}

// Restar los PP independientemente del resultado
$data['pp'] = max(0, $ppAvailable - $cost);
$dataJsonEsc = $db->escape_string(json_encode($data, JSON_UNESCAPED_UNICODE));
$db->write_query("UPDATE {$prefix}game_personajes SET data_json = '{$dataJsonEsc}' WHERE id = {$characterId}");

// Registrar en game_haki_progress
$unlocked_at_sql = ($unlockedLevel > 0) ? "NOW()" : "NULL";
$db->write_query("
    INSERT INTO {$prefix}game_haki_progress (character_id, haki_type, nivel, status, pp_reservados, unlocked_at)
    VALUES ({$characterId}, 'haoshoku', {$unlockedLevel}, 'activo', 0, {$unlocked_at_sql})
    ON DUPLICATE KEY UPDATE
        nivel = {$unlockedLevel},
        status = 'activo',
        pp_reservados = 0,
        unlocked_at = {$unlocked_at_sql}
");

// Notificación para el usuario
$targetUid = (int)$targetCharacter['user_id'];
if (function_exists('game_create_notification') && $targetUid > 0) {
    $bburl = rtrim((string)($mybb->settings['bburl'] ?? ''), '/');
    $link = $bburl . '/game/personaje.php?id=' . $characterId;
    
    if ($unlockedLevel > 0) {
        $title = "¡Haki del Rey despertado!";
        $body = "¡Tu Espíritu se ha manifestado! Has despertado el Haki de Conquistador a nivel {$unlockedLevel} ({$resultLabel}) tras una tirada de {$roll} + {$bonus} = {$total}.";
    } else {
        $title = "Intento de despertar fallido";
        $body = "El intento de despertar tu Haki de Conquistador ha fallado. La tirada final fue de {$roll} + {$bonus} = {$total} (requerido >= 41). Se han consumido {$cost} PP.";
    }

    game_create_notification(
        $targetUid,
        'haki_conquistador_roll',
        $title,
        $body,
        $link,
        $characterId
    );
}

game_log_action('haki_conquistador_roll', [
    'staff_uid' => $uid,
    'character_id' => $characterId,
    'roll' => $roll,
    'bonus' => $bonus,
    'total' => $total,
    'unlocked_level' => $unlockedLevel
]);

GameAjax::json(true, [
    'character_id' => $characterId,
    'roll' => $roll,
    'bonus' => $bonus,
    'total' => $total,
    'unlocked_level' => $unlockedLevel,
    'result_label' => $resultLabel,
    'new_pp' => $data['pp']
], null);
