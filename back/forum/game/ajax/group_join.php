<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
global $db, $mybb;

header('Content-Type: application/json; charset=utf-8');
$prefix = TABLE_PREFIX;
$uid = (int)($mybb->user['uid'] ?? 0);

if ($uid <= 0) {
    echo json_encode(['ok' => false, 'message' => 'No autenticado.']);
    exit;
}

$active_pj_id = (int)($db->fetch_field(
    $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1"),
    "active_pj_id"
) ?? 0);

if ($active_pj_id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'No tienes un personaje activo.']);
    exit;
}

$crew_id = (int)($_POST['crew_id'] ?? 0);
if ($crew_id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Grupo inválido.']);
    exit;
}

$pj = $db->fetch_array($db->query("SELECT id, tripulacion_id FROM {$prefix}game_personajes WHERE id = {$active_pj_id}"));
if (!$pj) {
    echo json_encode(['ok' => false, 'message' => 'PJ inválido.']);
    exit;
}

if (!empty($pj['tripulacion_id'])) {
    echo json_encode(['ok' => false, 'message' => 'Ya perteneces a un grupo.']);
    exit;
}

$existing = $db->fetch_array($db->query("SELECT status_peticion FROM {$prefix}game_tripulacion_miembros WHERE pj_id = {$active_pj_id} AND tripulacion_id = {$crew_id}"));
if ($existing) {
    echo json_encode(['ok' => false, 'message' => 'Ya has enviado una solicitud a este grupo.']);
    exit;
}

$db->query("INSERT INTO {$prefix}game_tripulacion_miembros (tripulacion_id, pj_id, role, role_custom, status_peticion, joined_at) VALUES ({$crew_id}, {$active_pj_id}, 'Aspirante', 'Aspirante', 'pendiente', NOW())");

echo json_encode(['ok' => true, 'message' => 'Solicitud de unión enviada.']);
