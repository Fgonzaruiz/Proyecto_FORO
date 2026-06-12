<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;
use Game\Infrastructure\Persistence\PersonajeRepository;
use Game\Application\Services\CharacterProgression;

header('Content-Type: application/json; charset=utf-8');

global $db, $mybb;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$prefix = TABLE_PREFIX;

// Validar que el usuario activo sea staff de nivel >= 2
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

if ($staff_level < 2) {
    GameAjax::json(false, null, ['code' => 403, 'message' => 'Permiso denegado. Se requiere nivel de Staff 2 (Moderador) o superior.'], 403);
}

$characterId = (int)($input['character_id'] ?? 0);
$hakiType = trim((string)($input['haki_type'] ?? ''));
$action = trim((string)($input['action'] ?? ''));
$motivo = trim((string)($input['motivo'] ?? ''));

if ($characterId <= 0 || !in_array($hakiType, ['kenbunshoku', 'busoshoku', 'haoshoku'], true) || !in_array($action, ['aprobar', 'rechazar'], true)) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Parámetros inválidos.'], 400);
}

// Cargar estado de Haki
$haki_q = $db->query("SELECT * FROM {$prefix}game_haki_progress WHERE character_id = {$characterId} AND haki_type = '{$db->escape_string($hakiType)}' LIMIT 1");
$haki = $db->fetch_array($haki_q);

if (!$haki || $haki['status'] !== 'pendiente_subida') {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'No hay una solicitud de subida pendiente para este Haki.'], 400);
}

// Cargar personaje para poder notificar y/o modificar PP
$personajes = new PersonajeRepository();
$targetCharacter = $personajes->findById($characterId);

if ($targetCharacter === null) {
    GameAjax::json(false, null, ['code' => 404, 'message' => 'Personaje objetivo no encontrado.'], 404);
}

$hakiLabel = $hakiType === 'kenbunshoku' ? 'Observación' : ($hakiType === 'busoshoku' ? 'Armamento' : 'Conquistador');
$targetUid = (int)$targetCharacter['user_id'];
$targetLevel = (int)$haki['nivel'] + 1;
$reservedPp = (int)$haki['pp_reservados'];

$bburl = rtrim((string)($mybb->settings['bburl'] ?? ''), '/');
$link = $bburl . '/game/personaje.php?id=' . $characterId;

if ($action === 'aprobar') {
    $unlocked_sql = ($haki['nivel'] == 0) ? ", unlocked_at = NOW()" : "";
    $db->write_query("
        UPDATE {$prefix}game_haki_progress
        SET nivel = nivel + 1,
            pp_reservados = 0,
            status = 'activo'
            {$unlocked_sql}
        WHERE character_id = {$characterId} AND haki_type = '{$db->escape_string($hakiType)}'
    ");

    if (function_exists('game_create_notification') && $targetUid > 0) {
        game_create_notification(
            $targetUid,
            'haki_upgrade_approved',
            'Solicitud de Haki aprobada',
            "Tu solicitud para subir tu Haki de {$hakiLabel} al nivel {$targetLevel} ha sido aprobada.",
            $link,
            $characterId
        );
    }

    game_log_action('haki_resolve_approve', [
        'staff_uid' => $uid,
        'character_id' => $characterId,
        'haki_type' => $hakiType,
        'new_level' => $targetLevel
    ]);

    GameAjax::json(true, [
        'character_id' => $characterId,
        'haki_type' => $hakiType,
        'action' => 'aprobar',
        'new_level' => $targetLevel
    ], null);

} else {
    // Rechazar: Devolver PP
    $data = !empty($targetCharacter['data_json']) ? json_decode($targetCharacter['data_json'], true) : [];
    if (!is_array($data)) {
        $data = [];
    }
    CharacterProgression::syncLinajeBonusPp($data, (string)($targetCharacter['race_name'] ?? ''));
    CharacterProgression::normalize($data);

    $currentPp = (int)($data['pp'] ?? 0);
    $data['pp'] = $currentPp + $reservedPp;

    $dataJsonEsc = $db->escape_string(json_encode($data, JSON_UNESCAPED_UNICODE));
    $db->write_query("UPDATE {$prefix}game_personajes SET data_json = '{$dataJsonEsc}' WHERE id = {$characterId}");

    // Resetear fila en game_haki_progress
    $db->write_query("
        UPDATE {$prefix}game_haki_progress
        SET status = 'activo',
            pp_reservados = 0
        WHERE character_id = {$characterId} AND haki_type = '{$db->escape_string($hakiType)}'
    ");

    if (function_exists('game_create_notification') && $targetUid > 0) {
        $notifBody = "Tu solicitud para subir tu Haki de {$hakiLabel} al nivel {$targetLevel} ha sido rechazada.";
        if ($motivo !== '') {
            $notifBody .= " Motivo: " . $motivo;
        }
        $notifBody .= " Se han devuelto {$reservedPp} PP a tu ficha.";

        game_create_notification(
            $targetUid,
            'haki_upgrade_rejected',
            'Solicitud de Haki rechazada',
            $notifBody,
            $link,
            $characterId
        );
    }

    game_log_action('haki_resolve_reject', [
        'staff_uid' => $uid,
        'character_id' => $characterId,
        'haki_type' => $hakiType,
        'refunded_pp' => $reservedPp,
        'reason' => $motivo
    ]);

    GameAjax::json(true, [
        'character_id' => $characterId,
        'haki_type' => $hakiType,
        'action' => 'rechazar',
        'refunded_pp' => $reservedPp
    ], null);
}
