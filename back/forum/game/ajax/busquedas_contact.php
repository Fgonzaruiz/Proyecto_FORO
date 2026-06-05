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

$busqueda_id = isset($_POST['busqueda_id']) ? (int)$_POST['busqueda_id'] : 0;
if ($busqueda_id <= 0) {
    GameAjax::fail(400, 'ID de búsqueda inválido.');
}

$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$requester_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;

if ($requester_pj_id <= 0) {
    GameAjax::fail(400, 'Debes tener un personaje activo seleccionado para contactar por una trama.');
}

$req_pj_q = $db->query("SELECT name FROM {$prefix}game_personajes WHERE id = {$requester_pj_id} AND user_id = {$uid} LIMIT 1");
$req_pj = $db->fetch_array($req_pj_q);
if (!$req_pj) {
    GameAjax::fail(403, 'Personaje activo no encontrado o no te pertenece.');
}
$requester_name = $req_pj['name'];

$b_q = $db->query("SELECT id, user_id, character_id, titulo FROM {$prefix}game_busquedas WHERE id = {$busqueda_id} LIMIT 1");
$busqueda = $db->fetch_array($b_q);
if (!$busqueda) {
    GameAjax::fail(404, 'La búsqueda seleccionada no existe o ya no está disponible.');
}

$creator_pj_id = (int)$busqueda['character_id'];
$creator_user_id = (int)$busqueda['user_id'];
$titulo_trama = $busqueda['titulo'];

if ($requester_pj_id === $creator_pj_id) {
    GameAjax::fail(400, 'No puedes contactarte a ti mismo por tu propia búsqueda.');
}

$dup_q = $db->query("
    SELECT COUNT(*) as cnt
    FROM {$prefix}game_notifications
    WHERE user_id = {$creator_user_id}
      AND character_id = {$creator_pj_id}
      AND type = 'busqueda_contact'
      AND link = 'busqueda_contact:{$busqueda_id}:{$requester_pj_id}'
      AND is_read = 0
      AND is_dismissed = 0
");
$dup = $db->fetch_array($dup_q);
if ((int)($dup['cnt'] ?? 0) > 0) {
    GameAjax::fail(400, 'Ya has enviado una solicitud de contacto para esta búsqueda y está pendiente de respuesta.');
}

$title = "{$requester_name} quiere tu trama '{$titulo_trama}'";
$body = "¡Hola! Me gustaría coordinar esta trama contigo. ¿Aceptas mi propuesta? (Al aceptar, la búsqueda se marcará como resuelta y se quitará del tablón)";
$link = "busqueda_contact:{$busqueda_id}:{$requester_pj_id}";

NotificationService::create(
    $creator_user_id,
    'busqueda_contact',
    $title,
    $body,
    $link,
    $creator_pj_id
);

try {
    DirectMessageService::send(
        $requester_pj_id,
        $creator_pj_id,
        "Interés en tu trama: {$titulo_trama}",
        $body,
        null,
        false
    );
} catch (\Throwable $e) {
    // El contacto por notificación sigue disponible aunque falle el buzón.
}

GameAjax::json(true, null);
