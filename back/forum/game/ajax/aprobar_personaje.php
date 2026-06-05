<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/autoload.php';

use Game\Application\Services\CharacterSaveService;
use Game\Application\Services\DirectMessageService;
use Game\Http\GameAjax;

global $db;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$prefix = TABLE_PREFIX;

$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;

if ($active_pj_id > 0) {
    $pj_q = $db->query("SELECT name, staff_level, is_staff FROM {$prefix}game_personajes WHERE id = {$active_pj_id} AND user_id = {$uid} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    if ($pj && (int)$pj['is_staff']) {
        $staff_level = (int)$pj['staff_level'];
    }
}

if ($staff_level < 1) {
    GameAjax::json(false, null, ['code' => 403, 'message' => 'Permiso denegado.'], 403);
}

$personaje_id = (int)($input['personaje_id'] ?? 0);
$action = $input['action'] ?? '';
$mensaje = trim((string)($input['mensaje'] ?? ''));

if (!$personaje_id || !in_array($action, ['aprobar', 'rechazar', 'revision', 'pendiente'], true)) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Parámetros inválidos.'], 400);
}

$status_map = [
    'aprobar'  => 'aprobada',
    'rechazar' => 'rechazada',
    'revision' => 'revision',
    'pendiente' => 'pendiente',
];
$nuevo_status = $status_map[$action];

$char_q = $db->query("SELECT user_id, name, status, race_name, data_json, stats_json FROM {$prefix}game_personajes WHERE id = {$personaje_id} LIMIT 1");
$char = $db->fetch_array($char_q);
if (!$char) {
    GameAjax::json(false, null, ['code' => 404, 'message' => 'Personaje no encontrado.'], 404);
}

$status_anterior = $char['status'];

if ($nuevo_status === 'aprobada') {
    $data = !empty($char['data_json']) ? json_decode($char['data_json'], true) : [];
    $stats = !empty($char['stats_json']) ? json_decode($char['stats_json'], true) : [];
    if (!is_array($data)) {
        $data = [];
    }
    if (!is_array($stats)) {
        $stats = [];
    }

    $race = trim((string)($char['race_name'] ?: ($data['race'] ?? '')));
    $saveService = new CharacterSaveService();
    $recalc = $saveService->recalculateOnApprove($race, $data, $stats);
    if (!$recalc['ok']) {
        GameAjax::json(false, null, ['code' => 400, 'message' => $recalc['message'] ?? 'Linaje inválido.'], 400);
    }

    $dataEsc = $db->escape_string(json_encode($recalc['data_json'], JSON_UNESCAPED_UNICODE));
    $statsEsc = $db->escape_string(json_encode($recalc['stats_json'], JSON_UNESCAPED_UNICODE));
    $db->write_query("UPDATE {$prefix}game_personajes SET data_json = '{$dataEsc}', stats_json = '{$statsEsc}' WHERE id = {$personaje_id}");
}

if ($nuevo_status === 'rechazada') {
    $db->write_query("DELETE FROM {$prefix}game_personajes WHERE id = {$personaje_id}");
    $cnt_q = $db->query("SELECT COUNT(*) AS cnt FROM {$prefix}game_personajes WHERE user_id = " . (int)$char['user_id'] . " AND is_npc = 0");
    $actual = (int)$db->fetch_field($cnt_q, 'cnt');
    $db->write_query("UPDATE {$prefix}game_user_config SET slots_used = {$actual} WHERE user_id = " . (int)$char['user_id']);
} else {
    $db->write_query("UPDATE {$prefix}game_personajes SET status = '{$nuevo_status}' WHERE id = {$personaje_id}");
    $approved_val = ($nuevo_status === 'aprobada') ? 1 : 0;
    $db->write_query("UPDATE {$prefix}game_personajes SET approved = {$approved_val} WHERE id = {$personaje_id}");
}

$mensaje_es = $db->escape_string($mensaje);
$db->write_query("INSERT INTO {$prefix}game_personajes_revisiones (personaje_id, staff_user_id, staff_char_id, status_anterior, status_nuevo, mensaje) VALUES (
    {$personaje_id},
    {$uid},
    {$active_pj_id},
    '{$db->escape_string($status_anterior)}',
    '{$nuevo_status}',
    '{$mensaje_es}'
)");

if ((int)$char['user_id'] > 0) {
    $status_labels = [
        'aprobada'  => 'Aprobada',
        'rechazada'  => 'Rechazada',
        'revision' => 'En Revisión',
        'pendiente' => 'Pendiente',
    ];
    $label = $status_labels[$nuevo_status] ?? $nuevo_status;
    $notif_title = "Ficha de {$char['name']}: {$label}";
    $notif_body = "Tu personaje {$char['name']} ha sido actualizado a estado: {$label}.";
    $notif_link = "game/public/personaje.php?pj={$personaje_id}";

    $target_character_id = $personaje_id;

    if ($mensaje !== '' && $active_pj_id > 0) {
        try {
            $dmId = DirectMessageService::send(
                $active_pj_id,
                $personaje_id,
                "Moderación Ficha: {$char['name']}",
                "Tu ficha ha sido moderada al estado: {$label}.\n\nAnotaciones del Staff:\n{$mensaje}"
            );
            $notif_link = 'game/public/buzon.php?read=' . $dmId;
        } catch (\Throwable $e) {
            $notif_link = 'game/public/buzon.php';
        }
    } elseif ($nuevo_status === 'rechazada') {
        $notif_link = 'game/public/mis_personajes.php';
    }

    if ($mensaje === '') {
        try {
            $notifService = new \Game\Application\Services\NotificationService();
            $notifService->create(
                (int)$char['user_id'],
                'system',
                $notif_title,
                $notif_body,
                $notif_link,
                $target_character_id
            );
        } catch (\Throwable $e) {
            // Notification is non-critical
        }
    }
}

game_log_action('aprobar_personaje', [
    'uid' => $uid,
    'personaje_id' => $personaje_id,
    'status_nuevo' => $nuevo_status,
]);
GameAjax::json(true, [
    'personaje_id'    => $personaje_id,
    'status_anterior' => $status_anterior,
    'status_nuevo'    => $nuevo_status,
    'mensaje_enviado' => $mensaje !== '',
], null);
