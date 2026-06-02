<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

use Game\Application\Services\NotificationService;
use Game\Http\GameAjax;

global $db, $mybb;
$prefix = TABLE_PREFIX;

$uid = (int)($mybb->user['uid'] ?? 0);
if ($uid === 0) {
    echo json_encode(['ok' => false, 'error' => 'No autenticado. Por favor, inicia sesión.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    exit;
}

GameAjax::requireCsrf($_POST);

$notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

if ($notification_id <= 0 || !in_array($action, ['aceptar', 'rechazar'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos.']);
    exit;
}

// 1. Obtener personaje activo del creador (dueño de la notificación)
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;

if ($active_pj_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Debes tener un personaje activo seleccionado.']);
    exit;
}

// Obtener datos del personaje activo
$pj_q = $db->query("SELECT name FROM {$prefix}game_personajes WHERE id = {$active_pj_id} AND user_id = {$uid} LIMIT 1");
$pj = $db->fetch_array($pj_q);
if (!$pj) {
    echo json_encode(['ok' => false, 'error' => 'Personaje activo no encontrado o no te pertenece.']);
    exit;
}
$creator_name = $pj['name'];

// 2. Cargar la notificación y validar pertenencia
$notif_q = $db->query("
    SELECT id, user_id, character_id, link, title, is_read, is_dismissed 
    FROM {$prefix}game_notifications 
    WHERE id = {$notification_id} 
      AND user_id = {$uid} 
      AND character_id = {$active_pj_id} 
    LIMIT 1
");
$notification = $db->fetch_array($notif_q);
if (!$notification) {
    echo json_encode(['ok' => false, 'error' => 'La notificación no existe o no tienes permisos para gestionarla.']);
    exit;
}

// Validar tipo de link (busqueda_contact:BUSQUEDA_ID:REQUESTER_PJ_ID)
$link = $notification['link'];
if (strpos($link, 'busqueda_contact:') !== 0) {
    echo json_encode(['ok' => false, 'error' => 'Esta notificación no corresponde a un contacto de búsqueda de rol.']);
    exit;
}

$parts = explode(':', $link);
$busqueda_id = isset($parts[1]) ? (int)$parts[1] : 0;
$requester_pj_id = isset($parts[2]) ? (int)$parts[2] : 0;

if ($busqueda_id <= 0 || $requester_pj_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Enlace de notificación dañado.']);
    exit;
}

// 3. Cargar datos del solicitante
$req_q = $db->query("SELECT user_id, name FROM {$prefix}game_personajes WHERE id = {$requester_pj_id} LIMIT 1");
$requester = $db->fetch_array($req_q);
if (!$requester) {
    echo json_encode(['ok' => false, 'error' => 'El personaje que solicitó la trama ya no existe.']);
    exit;
}
$requester_user_id = (int)$requester['user_id'];
$requester_name = $requester['name'];

// Cargar título de la búsqueda (si aún existe)
$b_q = $db->query("SELECT id, titulo FROM {$prefix}game_busquedas WHERE id = {$busqueda_id} LIMIT 1");
$busqueda = $db->fetch_array($b_q);
$titulo_trama = $busqueda ? $busqueda['titulo'] : 'la trama';

// 4. Ejecutar la acción
if ($action === 'aceptar') {
    // Si la búsqueda existe, la borramos del tablón
    if ($busqueda) {
        $db->write_query("DELETE FROM {$prefix}game_busquedas WHERE id = {$busqueda_id}");
    }
    
    // Notificamos al solicitante que ha sido aceptado
    $title_notif = "{$creator_name} ha aceptado tu propuesta de trama";
    $body_notif = "¡Felicidades! {$creator_name} ha aceptado tu propuesta de trama para '{$titulo_trama}'. Ya podéis coordinaros por privado.";
    NotificationService::create($requester_user_id, 'system', $title_notif, $body_notif, '', $requester_pj_id);

    // Marcamos la notificación actual como leída y archivada
    $db->write_query("UPDATE {$prefix}game_notifications SET is_read = 1, is_dismissed = 1 WHERE id = {$notification_id}");

} else {
    // Seguir buscando / rechazar la propuesta
    // Notificamos al solicitante
    $title_notif = "{$creator_name} ha declinado tu propuesta de trama";
    $body_notif = "{$creator_name} ha decidido seguir buscando compañeros para la trama '{$titulo_trama}'.";
    NotificationService::create($requester_user_id, 'system', $title_notif, $body_notif, '', $requester_pj_id);

    // Marcamos la notificación actual como leída y archivada
    $db->write_query("UPDATE {$prefix}game_notifications SET is_read = 1, is_dismissed = 1 WHERE id = {$notification_id}");
}

echo json_encode(['ok' => true, 'error' => null]);
exit;
