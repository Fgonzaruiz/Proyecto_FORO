<?php
declare(strict_types=1);

/**
 * Solicitud de subida de grado (§3 v2): el jugador pide al staff; no sube el grado solo.
 * La subida efectiva la hace staff vía character_*_save.php (PP + cooldown).
 */

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\Services\CharacterProgression;
use Game\Http\GameAjax;
use Game\Infrastructure\Persistence\PersonajeRepository;

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
    GameAjax::json(false, null, ['code' => 403, 'message' => 'El personaje debe estar aprobado.'], 403);
}

$data = !empty($character['data_json']) ? json_decode($character['data_json'], true) : [];
if (!is_array($data)) {
    $data = [];
}
CharacterProgression::normalize($data);

$charNivel = game_get_character_nivel($data);
$lastUpgrade = game_grado_last_upgrade_at($data);
$lastUpgradeRank = game_grado_last_upgrade_rank($data);
$ppAvailable = (int)($data['pp'] ?? 0);

$prefix = TABLE_PREFIX;
$currentRank = 0;
$name = '';

if ($type === 'oficio') {
    $row = $db->fetch_array($db->query("
        SELECT co.`rank`, o.name
        FROM {$prefix}game_character_oficios co
        JOIN {$prefix}game_oficios o ON o.id = co.oficio_id
        WHERE co.character_id = {$characterId} AND co.oficio_id = {$catalogId}
        LIMIT 1
    "));
    if (!$row) {
        GameAjax::json(false, null, ['code' => 404, 'message' => 'No tienes este oficio.'], 404);
    }
    $currentRank = (int)$row['rank'];
    $name = (string)$row['name'];
} else {
    $row = $db->fetch_array($db->query("
        SELECT cd.`rank`, d.name
        FROM {$prefix}game_character_disciplinas cd
        JOIN {$prefix}game_disciplinas d ON d.id = cd.disciplina_id
        WHERE cd.character_id = {$characterId} AND cd.disciplina_id = {$catalogId}
        LIMIT 1
    "));
    if (!$row) {
        GameAjax::json(false, null, ['code' => 404, 'message' => 'No tienes esta disciplina.'], 404);
    }
    $currentRank = (int)$row['rank'];
    $name = (string)$row['name'];
}

if ($currentRank >= 5) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Grado máximo (V) alcanzado.'], 400);
}

$nextRank = $currentRank + 1;
$reqNivel = game_grado_nivel_required($nextRank);
$price = game_grado_upgrade_price($nextRank);

if ($charNivel < $reqNivel) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Nivel insuficiente (requiere nivel ' . $reqNivel . ').'], 400);
}
if ($ppAvailable < $price) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'PP insuficientes (requiere ' . number_format($price, 0, ',', '.') . ' PP).'], 400);
}
if (!game_grado_cooldown_ok($lastUpgrade, $lastUpgradeRank)) {
    $left = game_grado_cooldown_remaining_days($lastUpgrade, $lastUpgradeRank);
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Debes esperar ' . $left . ' día(s) desde la última subida de grado.'], 400);
}

$typeLabel = $type === 'oficio' ? 'oficio' : 'disciplina';
$nextLabel = game_grado_label($nextRank);
$pjName = (string)($character['name'] ?? 'PJ');
$bburl = rtrim((string)($mybb->settings['bburl'] ?? ''), '/');
$staffLink = $type === 'oficio'
    ? $bburl . '/game/public/zona_staff_oficios.php'
    : $bburl . '/game/public/zona_staff_disciplinas.php';

if (function_exists('game_create_notification')) {
    $staff_q = $db->query("SELECT DISTINCT user_id FROM {$prefix}game_personajes WHERE staff_level >= 2 AND user_id > 0");
    while ($staff_row = $db->fetch_array($staff_q)) {
        $staffUid = (int)$staff_row['user_id'];
        if ($staffUid === $uid) {
            continue;
        }
        game_create_notification(
            $staffUid,
            'competencia_grado_request',
            'Solicitud de subida de grado',
            "{$pjName} solicita subir «{$name}» ({$typeLabel}) a grado {$nextLabel} ("
            . number_format($price, 0, ',', '.') . " PP, nivel {$charNivel}).",
            $staffLink,
            $characterId
        );
    }

    game_create_notification(
        $uid,
        'competencia_grado_request_sent',
        'Solicitud enviada',
        "Tu solicitud para subir «{$name}» a grado {$nextLabel} está pendiente de aprobación del staff.",
        $bburl . '/game/personaje.php?id=' . $characterId,
        $characterId
    );
}

GameAjax::json(true, [
    'character_id' => $characterId,
    'type' => $type,
    'catalog_id' => $catalogId,
    'name' => $name,
    'current_rank' => $currentRank,
    'requested_rank' => $nextRank,
    'rank_label' => $nextLabel,
    'price_pp' => $price,
    'pp' => $ppAvailable,
    'pending_staff' => true,
    'nivel' => $charNivel,
], null);
