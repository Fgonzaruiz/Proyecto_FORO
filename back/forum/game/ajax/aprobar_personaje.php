<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/autoload.php';

header('Content-Type: application/json; charset=utf-8');

global $mybb, $db;

$uid = (int)($mybb->user['uid'] ?? 0);
if (!$uid) {
    echo json_encode(['ok' => false, 'error' => ['code' => 401, 'message' => 'No autorizado.']]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => ['code' => 405, 'message' => 'Method not allowed']]);
    exit;
}

$prefix = TABLE_PREFIX;

$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;
$staff_char_name = '';

if ($active_pj_id > 0) {
    $pj_q = $db->query("SELECT name, staff_level, is_staff FROM {$prefix}game_personajes WHERE id = {$active_pj_id} AND user_id = {$uid} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    if ($pj && (int)$pj['is_staff']) {
        $staff_level = (int)$pj['staff_level'];
        $staff_char_name = $pj['name'];
    }
}

if ($staff_level < 1) {
    echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'Permiso denegado.']]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'Payload inválido.']]);
    exit;
}

$personaje_id = (int)($input['personaje_id'] ?? 0);
$action = $input['action'] ?? '';
$mensaje = trim($input['mensaje'] ?? '');

if (!$personaje_id || !in_array($action, ['aprobar', 'rechazar', 'revision', 'pendiente'], true)) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'Parámetros inválidos.']]);
    exit;
}

// Map action to status
$status_map = [
    'aprobar'  => 'aprobada',
    'rechazar' => 'rechazada',
    'revision' => 'revision',
    'pendiente' => 'pendiente',
];
$nuevo_status = $status_map[$action];

// Get current character data
$char_q = $db->query("SELECT user_id, name, status FROM {$prefix}game_personajes WHERE id = {$personaje_id} LIMIT 1");
$char = $db->fetch_array($char_q);
if (!$char) {
    echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'Personaje no encontrado.']]);
    exit;
}

$status_anterior = $char['status'];

// Update character status or delete if rejected
if ($nuevo_status === 'rechazada') {
    $db->write_query("DELETE FROM {$prefix}game_personajes WHERE id = {$personaje_id}");
    $db->write_query("UPDATE {$prefix}game_user_config SET slots_used = GREATEST(0, slots_used - 1) WHERE user_id = {$char['user_id']}");
} else {
    $db->write_query("UPDATE {$prefix}game_personajes SET status = '{$nuevo_status}' WHERE id = {$personaje_id}");
    $approved_val = ($nuevo_status === 'aprobada') ? 1 : 0;
    $db->write_query("UPDATE {$prefix}game_personajes SET approved = {$approved_val} WHERE id = {$personaje_id}");
}

// Insert revision record
$mensaje_es = $db->escape_string($mensaje);
$db->write_query("INSERT INTO {$prefix}game_personajes_revisiones (personaje_id, staff_user_id, staff_char_id, status_anterior, status_nuevo, mensaje) VALUES (
    {$personaje_id},
    {$uid},
    {$active_pj_id},
    '{$db->escape_string($status_anterior)}',
    '{$nuevo_status}',
    '{$mensaje_es}'
)");

// Send notification to character owner
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
    
    // Send PM with annotations
    if ($mensaje !== '') {
        require_once MYBB_ROOT . "inc/datahandlers/pm.php";
        $pm = [
            'subject' => "Moderación Ficha: {$char['name']}",
            'message' => "Tu ficha ha sido moderada al estado: **{$label}**.\n\nAnotaciones del Staff:\n{$mensaje}",
            'touid' => $char['user_id'],
            'receivepms' => 1
        ];
        
        if (send_pm($pm, $uid, true)) {
            $pm_q = $db->query("SELECT pmid FROM {$prefix}privatemessages WHERE fromid = {$uid} AND toid = {$char['user_id']} ORDER BY pmid DESC LIMIT 1");
            $pmid = $db->fetch_field($pm_q, 'pmid');
            if ($pmid) {
                $notif_link = "private.php?action=read&pmid={$pmid}";
            }
        }
    } elseif ($nuevo_status === 'rechazada') {
        $notif_link = "game/public/mis_personajes.php";
    }

    try {
        $notifService = new \Game\Application\Services\NotificationService();
        $target_character_id = ($nuevo_status === 'rechazada') ? null : $personaje_id;
        $notifService->create(
            (int)$char['user_id'],
            'message',
            $notif_title,
            $notif_body,
            $notif_link,
            $target_character_id
        );
    } catch (\Throwable $e) {
        // Notification is non-critical
    }
}

echo json_encode([
    'ok'   => true,
    'data' => [
        'personaje_id'    => $personaje_id,
        'status_anterior' => $status_anterior,
        'status_nuevo'    => $nuevo_status,
        'mensaje_enviado' => $mensaje !== '',
    ],
    'error' => null
]);
exit;
