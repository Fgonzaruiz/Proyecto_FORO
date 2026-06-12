<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
global $db, $mybb;

header('Content-Type: application/json; charset=utf-8');
$prefix = TABLE_PREFIX;
$uid = (int)($mybb->user['uid'] ?? 0);

// Verificar autenticación
if ($uid === 0) {
    echo json_encode(['ok' => false, 'message' => 'No autenticado.']);
    exit;
}

$action = $_POST['action'] ?? '';
$crew_id = (int)($_POST['crew_id'] ?? 0);
$pj_id_target = (int)($_POST['pj_id'] ?? 0);

if ($crew_id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'ID de tripulación inválido.']);
    exit;
}

// Obtener PJ activo y verificar que es capitán de ESTA crew
$active_pj_id = (int)($db->fetch_field(
    $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1"),
    "active_pj_id"
) ?? 0);

if ($active_pj_id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'No tienes un personaje activo.']);
    exit;
}

$my_role = $db->fetch_field(
    $db->query("SELECT role FROM {$prefix}game_tripulacion_miembros 
                WHERE pj_id = {$active_pj_id} AND tripulacion_id = {$crew_id} AND status_peticion = 'aprobada'"),
    "role"
);

if ($my_role !== 'Capitán') {
    echo json_encode(['ok' => false, 'message' => 'Solo el capitán puede gestionar la tripulación.']);
    exit;
}

switch ($action) {
    case 'accept_member':
        if ($pj_id_target <= 0) {
            echo json_encode(['ok' => false, 'message' => 'ID de personaje inválido.']);
            break;
        }
        $db->query("UPDATE {$prefix}game_tripulacion_miembros 
                     SET status_peticion = 'aprobada', role = 'Miembro', joined_at = NOW() 
                     WHERE pj_id = {$pj_id_target} AND tripulacion_id = {$crew_id}");
        $db->query("UPDATE {$prefix}game_personajes 
                     SET tripulacion_id = {$crew_id} WHERE id = {$pj_id_target}");
        echo json_encode(['ok' => true, 'message' => 'Miembro aceptado.']);
        break;

    case 'reject_member':
        if ($pj_id_target <= 0) {
            echo json_encode(['ok' => false, 'message' => 'ID de personaje inválido.']);
            break;
        }
        $db->query("DELETE FROM {$prefix}game_tripulacion_miembros 
                     WHERE pj_id = {$pj_id_target} AND tripulacion_id = {$crew_id}");
        echo json_encode(['ok' => true, 'message' => 'Solicitud rechazada.']);
        break;

    case 'kick_member':
        if ($pj_id_target <= 0) {
            echo json_encode(['ok' => false, 'message' => 'ID de personaje inválido.']);
            break;
        }
        if ($pj_id_target === $active_pj_id) {
            echo json_encode(['ok' => false, 'message' => 'No puedes expulsarte a ti mismo.']);
            break;
        }
        $db->query("DELETE FROM {$prefix}game_tripulacion_miembros 
                     WHERE pj_id = {$pj_id_target} AND tripulacion_id = {$crew_id}");
        $db->query("UPDATE {$prefix}game_personajes 
                     SET tripulacion_id = NULL WHERE id = {$pj_id_target}");
        echo json_encode(['ok' => true, 'message' => 'Miembro expulsado.']);
        break;

    case 'update_role':
        if ($pj_id_target <= 0) {
            echo json_encode(['ok' => false, 'message' => 'ID de personaje inválido.']);
            break;
        }
        $role_custom = $db->escape_string(mb_substr($_POST['role_custom'] ?? '', 0, 80));
        $db->query("UPDATE {$prefix}game_tripulacion_miembros 
                     SET role_custom = '{$role_custom}' 
                     WHERE pj_id = {$pj_id_target} AND tripulacion_id = {$crew_id}");
        echo json_encode(['ok' => true, 'message' => 'Rol actualizado.']);
        break;

    case 'update_crew':
        $name = $db->escape_string(mb_substr(trim($_POST['name'] ?? ''), 0, 150));
        if (empty($name)) {
            echo json_encode(['ok' => false, 'message' => 'El nombre no puede estar vacío.']);
            break;
        }
        $motto = $db->escape_string(mb_substr(trim($_POST['motto'] ?? ''), 0, 255));
        $factions = $db->escape_string(mb_substr(trim($_POST['factions'] ?? ''), 0, 255));
        $desc = $db->escape_string(trim($_POST['description'] ?? ''));
        $img = $db->escape_string(mb_substr(trim($_POST['image_url'] ?? ''), 0, 255));
        $rels = $db->escape_string(trim($_POST['relations'] ?? ''));
        $ost = $db->escape_string(mb_substr(trim($_POST['ost_url'] ?? ''), 0, 500));
        $ship_name = $db->escape_string(mb_substr(trim($_POST['ship_name'] ?? ''), 0, 150));
        $ship_image_url = $db->escape_string(mb_substr(trim($_POST['ship_image_url'] ?? ''), 0, 255));
        $ship_data = $db->escape_string(trim($_POST['ship_data'] ?? ''));
        
        $db->query("UPDATE {$prefix}game_tripulaciones 
                     SET name = '{$name}', motto = '{$motto}', factions = '{$factions}', description = '{$desc}', 
                         image_url = '{$img}', relations = '{$rels}', ost_url = '{$ost}',
                         ship_name = '{$ship_name}', ship_image_url = '{$ship_image_url}', ship_data = '{$ship_data}'
                     WHERE id = {$crew_id}");
        echo json_encode(['ok' => true, 'message' => 'Tripulación actualizada con éxito.']);
        break;

    case 'update_relations':
        $relations_json = $_POST['relations'] ?? '';
        $test_decode = json_decode($relations_json, true);
        if (!is_array($test_decode)) {
            echo json_encode(['ok' => false, 'message' => 'Formato de relaciones inválido.']);
            break;
        }
        $relations_escaped = $db->escape_string($relations_json);
        $db->query("UPDATE {$prefix}game_tripulaciones 
                     SET relations = '{$relations_escaped}' 
                     WHERE id = {$crew_id}");
        echo json_encode(['ok' => true, 'message' => 'Relaciones diplomáticas actualizadas.']);
        break;

    case 'add_memory':
        $title = mb_substr(trim($_POST['title'] ?? ''), 0, 150);
        $img = mb_substr(trim($_POST['image'] ?? ''), 0, 255);
        $text = trim($_POST['text'] ?? '');
        if (empty($title)) {
            echo json_encode(['ok' => false, 'message' => 'Título requerido.']);
            break;
        }
        
        $crew_row = $db->fetch_array($db->query("SELECT memories FROM {$prefix}game_tripulaciones WHERE id = {$crew_id}"));
        $memories = json_decode($crew_row['memories'] ?? '[]', true);
        if (!is_array($memories)) $memories = [];
        
        $memories[] = [
            'title' => $title,
            'image' => $img,
            'text' => $text,
            'date' => date('Y-m-d H:i:s')
        ];
        
        $db->query("UPDATE {$prefix}game_tripulaciones SET memories = '" . $db->escape_string(json_encode($memories, JSON_UNESCAPED_UNICODE)) . "' WHERE id = {$crew_id}");
        echo json_encode(['ok' => true, 'message' => 'Recuerdo añadido.']);
        break;

    case 'delete_memory':
        $idx = (int)($_POST['index'] ?? -1);
        $crew_row = $db->fetch_array($db->query("SELECT memories FROM {$prefix}game_tripulaciones WHERE id = {$crew_id}"));
        $memories = json_decode($crew_row['memories'] ?? '[]', true);
        if (!is_array($memories)) $memories = [];
        
        if (isset($memories[$idx])) {
            array_splice($memories, $idx, 1);
            $db->query("UPDATE {$prefix}game_tripulaciones SET memories = '" . $db->escape_string(json_encode($memories, JSON_UNESCAPED_UNICODE)) . "' WHERE id = {$crew_id}");
        }
        echo json_encode(['ok' => true, 'message' => 'Recuerdo eliminado.']);
        break;

    default:
        echo json_encode(['ok' => false, 'message' => 'Acción desconocida.']);
}
