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

$busqueda_id = isset($_POST['busqueda_id']) ? (int)$_POST['busqueda_id'] : 0;
if ($busqueda_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'ID de búsqueda inválido.']);
    exit;
}

// 1. Obtener personaje activo del solicitante
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$requester_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;

if ($requester_pj_id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Debes tener un personaje activo seleccionado para contactar por una trama.']);
    exit;
}

// Obtener datos del personaje del solicitante
$req_pj_q = $db->query("SELECT name FROM {$prefix}game_personajes WHERE id = {$requester_pj_id} AND user_id = {$uid} LIMIT 1");
$req_pj = $db->fetch_array($req_pj_q);
if (!$req_pj) {
    echo json_encode(['ok' => false, 'error' => 'Personaje activo no encontrado o no te pertenece.']);
    exit;
}
$requester_name = $req_pj['name'];

// 2. Obtener datos de la búsqueda y su creador
$b_q = $db->query("SELECT id, user_id, character_id, titulo FROM {$prefix}game_busquedas WHERE id = {$busqueda_id} LIMIT 1");
$busqueda = $db->fetch_array($b_q);
if (!$busqueda) {
    echo json_encode(['ok' => false, 'error' => 'La búsqueda seleccionada no existe o ya no está disponible.']);
    exit;
}

$creator_pj_id = (int)$busqueda['character_id'];
$creator_user_id = (int)$busqueda['user_id'];
$titulo_trama = $busqueda['titulo'];

if ($requester_pj_id === $creator_pj_id) {
    echo json_encode(['ok' => false, 'error' => 'No puedes contactarte a ti mismo por tu propia búsqueda.']);
    exit;
}

// 3. Verificar si ya existe una solicitud de contacto pendiente
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
    echo json_encode(['ok' => false, 'error' => 'Ya has enviado una solicitud de contacto para esta búsqueda y está pendiente de respuesta.']);
    exit;
}

// 4. Crear notificación (tipo "MD") para el creador
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

echo json_encode(['ok' => true, 'error' => null]);
exit;
