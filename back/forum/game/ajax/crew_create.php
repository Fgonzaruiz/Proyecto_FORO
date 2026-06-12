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

$pj = $db->fetch_array($db->query("SELECT id, name, tripulacion_id FROM {$prefix}game_personajes WHERE id = {$active_pj_id}"));
if (!$pj) {
    echo json_encode(['ok' => false, 'message' => 'PJ inválido.']);
    exit;
}

if (!empty($pj['tripulacion_id'])) {
    echo json_encode(['ok' => false, 'message' => 'Ya perteneces a una tripulación.']);
    exit;
}

$name = $db->escape_string(mb_substr(trim($_POST['name'] ?? ''), 0, 150));
if (empty($name)) {
    echo json_encode(['ok' => false, 'message' => 'El nombre es obligatorio.']);
    exit;
}

$motto = $db->escape_string(mb_substr(trim($_POST['motto'] ?? ''), 0, 255));
$image_url = $db->escape_string(mb_substr(trim($_POST['image_url'] ?? ''), 0, 255));

$db->query("INSERT INTO {$prefix}game_tripulaciones 
            (name, motto, image_url, leader_pj_id, created_at, status) 
            VALUES ('{$name}', '{$motto}', '{$image_url}', {$active_pj_id}, NOW(), 'aprobada')");

$crew_id = $db->insert_id();

if ($crew_id > 0) {
    // Insert leader into members table
    $db->query("INSERT INTO {$prefix}game_tripulacion_miembros 
                (tripulacion_id, pj_id, role, role_custom, status_peticion, joined_at) 
                VALUES ({$crew_id}, {$active_pj_id}, 'Capitán', 'Capitán', 'aprobada', NOW())");
                
    // Update PJ
    $db->query("UPDATE {$prefix}game_personajes SET tripulacion_id = {$crew_id} WHERE id = {$active_pj_id}");
    
    echo json_encode(['ok' => true, 'crew_id' => $crew_id, 'message' => 'Tripulación creada.']);
} else {
    echo json_encode(['ok' => false, 'message' => 'Error al crear la tripulación.']);
}
