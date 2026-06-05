<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\Services\DirectMessageService;
use Game\Application\Services\NotificationService;
use Game\Http\GameAjax;

global $db;

$uid = GameAjax::requireLogin();
$prefix = TABLE_PREFIX;

GameAjax::requirePost();
GameAjax::requireCsrf($_POST);

$notification_id = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

if ($notification_id <= 0 || !in_array($action, ['aceptar', 'rechazar'], true)) {
    GameAjax::fail(400, 'Parámetros inválidos.');
}

$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;

if ($active_pj_id <= 0) {
    GameAjax::fail(400, 'Debes tener un personaje activo seleccionado.');
}

$pj_q = $db->query("SELECT name FROM {$prefix}game_personajes WHERE id = {$active_pj_id} AND user_id = {$uid} LIMIT 1");
$pj = $db->fetch_array($pj_q);
if (!$pj) {
    GameAjax::fail(403, 'Personaje activo no encontrado o no te pertenece.');
}
$creator_name = $pj['name'];

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
    GameAjax::fail(404, 'La notificación no existe o no tienes permisos para gestionarla.');
}

$link = $notification['link'];
if (strpos($link, 'busqueda_contact:') !== 0) {
    GameAjax::fail(400, 'Esta notificación no corresponde a un contacto de búsqueda de rol.');
}

$parts = explode(':', $link);
$busqueda_id = isset($parts[1]) ? (int)$parts[1] : 0;
$requester_pj_id = isset($parts[2]) ? (int)$parts[2] : 0;

if ($busqueda_id <= 0 || $requester_pj_id <= 0) {
    GameAjax::fail(400, 'Enlace de notificación dañado.');
}

$req_q = $db->query("SELECT user_id, name FROM {$prefix}game_personajes WHERE id = {$requester_pj_id} LIMIT 1");
$requester = $db->fetch_array($req_q);
if (!$requester) {
    GameAjax::fail(404, 'El personaje que solicitó la trama ya no existe.');
}
$requester_user_id = (int)$requester['user_id'];
$requester_name = $requester['name'];

$b_q = $db->query("SELECT id, titulo FROM {$prefix}game_busquedas WHERE id = {$busqueda_id} LIMIT 1");
$busqueda = $db->fetch_array($b_q);
$titulo_trama = $busqueda ? $busqueda['titulo'] : 'la trama';

if ($action === 'aceptar') {
    if ($busqueda) {
        $db->write_query("DELETE FROM {$prefix}game_busquedas WHERE id = {$busqueda_id}");
    }

    $title_notif = "{$creator_name} ha aceptado tu propuesta de trama";
    $body_notif = "¡Felicidades! {$creator_name} ha aceptado tu propuesta de trama para '{$titulo_trama}'. Ya podéis coordinaros por el buzón.";
    try {
        DirectMessageService::send(
            $active_pj_id,
            $requester_pj_id,
            "Trama aceptada: {$titulo_trama}",
            $body_notif
        );
    } catch (\Throwable $e) {
        NotificationService::create($requester_user_id, 'system', $title_notif, $body_notif, 'game/public/buzon.php', $requester_pj_id);
    }

    $db->write_query("UPDATE {$prefix}game_notifications SET is_read = 1, is_dismissed = 1 WHERE id = {$notification_id}");
} else {
    $title_notif = "{$creator_name} ha declinado tu propuesta de trama";
    $body_notif = "{$creator_name} ha decidido seguir buscando compañeros para la trama '{$titulo_trama}'.";
    try {
        DirectMessageService::send(
            $active_pj_id,
            $requester_pj_id,
            "Trama declinada: {$titulo_trama}",
            $body_notif
        );
    } catch (\Throwable $e) {
        NotificationService::create($requester_user_id, 'system', $title_notif, $body_notif, 'game/public/buzon.php', $requester_pj_id);
    }

    $db->write_query("UPDATE {$prefix}game_notifications SET is_read = 1, is_dismissed = 1 WHERE id = {$notification_id}");
}

GameAjax::json(true, null);
