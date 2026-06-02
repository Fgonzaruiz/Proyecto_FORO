<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $db;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);
$request_id = (int)($input['request_id'] ?? 0);
$action = trim((string)($input['action'] ?? '')); // 'approve', 'reject', 'reply', 'moderate'
$staff_message = trim((string)($input['staff_message'] ?? ''));

if ($request_id <= 0 || !in_array($action, ['approve', 'reject', 'reply', 'moderate'])) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'Parámetros inválidos.']]);
    exit;
}

$prefix = TABLE_PREFIX;

// Check staff level (Moderator level 2 or Administrator level 3)
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;
$staff_name = 'Staff';
if ($active_pj_id > 0) {
    $pj_q = $db->query("SELECT name, staff_level FROM {$prefix}game_personajes WHERE id = {$active_pj_id} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    if ($pj) {
        $staff_level = (int)$pj['staff_level'];
        $staff_name = $pj['name'];
    }
}

if ($staff_level < 2) {
    echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'No tienes permisos de moderación/administración.']]);
    exit;
}

// Fetch request details - LEFT JOIN on game_cards because 'create' request doesn't have an existing card_id
$req_q = $db->query("
    SELECT r.*, p.user_id as player_uid, p.name as character_name, c.name as card_name 
    FROM {$prefix}game_card_requests r
    JOIN {$prefix}game_personajes p ON r.character_id = p.id
    LEFT JOIN {$prefix}game_cards c ON r.card_id = c.id
    WHERE r.id = {$request_id} LIMIT 1
");
$request = $db->fetch_array($req_q);

if (!$request) {
    echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'Solicitud no encontrada.']]);
    exit;
}

if (in_array($request['status'], ['aprobada', 'rechazada'])) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'La solicitud ya ha sido resuelta.']]);
    exit;
}

$player_uid = (int)$request['player_uid'];
$character_id = (int)$request['character_id'];
$card_id = (int)$request['card_id'];
$request_type = $request['request_type'];
$card_name = $request['card_name'];

// --- 1. ACTION: MODERATE ---
if ($action === 'moderate') {
    // Get card details from payload
    $card_details = $input['card_details'] ?? null;
    if (!$card_details || !is_array($card_details)) {
        echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'Datos de carta inválidos para moderación.']]);
        exit;
    }
    
    $card_details_json_str = json_encode($card_details, JSON_UNESCAPED_UNICODE);
    $card_details_esc = $db->escape_string($card_details_json_str);
    
    // Append staff message to discussion
    $discussion = !empty($request['discussion_json']) ? json_decode($request['discussion_json'], true) : [];
    if (!is_array($discussion)) {
        $discussion = [];
    }
    
    $msg = $staff_message !== '' ? $staff_message : 'El Staff ha modificado las especificaciones de la carta.';
    $discussion[] = [
        'sender' => 'staff',
        'sender_name' => $staff_name,
        'message' => $msg,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    $discussion_esc = $db->escape_string(json_encode($discussion, JSON_UNESCAPED_UNICODE));
    
    // Update request (re-verify status stays or becomes 'pendiente')
    $db->write_query("
        UPDATE {$prefix}game_card_requests 
        SET card_details_json = '{$card_details_esc}', 
            discussion_json = '{$discussion_esc}',
            status = 'pendiente'
        WHERE id = {$request_id}
    ");
    
    // Send Notification to player
    if (function_exists('game_create_notification')) {
        $notif_title = "Tu propuesta de carta fue moderada por el Staff";
        $notif_body = $staff_message !== '' ? "Comentario del Staff: {$staff_message}" : "El Staff ha modificado las especificaciones de tu propuesta.";
        $bb = $mybb->settings['bburl'];
        $link = rtrim($bb, '/') . "/game/public/personaje.php?pj={$character_id}";
        game_create_notification($player_uid, 'card_request_moderated', $notif_title, $notif_body, $link, $character_id);
    }
    
    echo json_encode(['ok' => true, 'data' => null, 'error' => null]);
    exit;
}

// --- 2. ACTION: REPLY ---
if ($action === 'reply') {
    if ($staff_message === '') {
        echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'El mensaje no puede estar vacío.']]);
        exit;
    }
    
    // Append staff message to discussion
    $discussion = !empty($request['discussion_json']) ? json_decode($request['discussion_json'], true) : [];
    if (!is_array($discussion)) {
        $discussion = [];
    }
    
    $discussion[] = [
        'sender' => 'staff',
        'sender_name' => $staff_name,
        'message' => $staff_message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    $discussion_esc = $db->escape_string(json_encode($discussion, JSON_UNESCAPED_UNICODE));
    
    // Update request
    $db->write_query("
        UPDATE {$prefix}game_card_requests 
        SET discussion_json = '{$discussion_esc}',
            status = 'pendiente'
        WHERE id = {$request_id}
    ");
    
    // Send Notification to player
    if (function_exists('game_create_notification')) {
        $notif_title = "Nuevo mensaje del Staff en tu solicitud de carta";
        $notif_body = $staff_message;
        $bb = $mybb->settings['bburl'];
        $link = rtrim($bb, '/') . "/game/public/personaje.php?pj={$character_id}";
        game_create_notification($player_uid, 'card_request_reply', $notif_title, $notif_body, $link, $character_id);
    }
    
    echo json_encode(['ok' => true, 'data' => null, 'error' => null]);
    exit;
}

// --- 3. ACTION: REJECT ---
if ($action === 'reject') {
    // Update request status to rejected
    $db->write_query("
        UPDATE {$prefix}game_card_requests 
        SET status = 'rechazada', 
            resolved_by = {$uid}, 
            resolved_at = NOW(), 
            staff_message = '{$db->escape_string($staff_message)}' 
        WHERE id = {$request_id}
    ");
    
    // Append message to discussion history for interactive create/catalog requests
    if ($request_type === 'create' || $request_type === 'add_existing') {
        $discussion = !empty($request['discussion_json']) ? json_decode($request['discussion_json'], true) : [];
        if (!is_array($discussion)) {
            $discussion = [];
        }
        $msg = $staff_message !== '' ? "SOLICITUD RECHAZADA: " . $staff_message : 'La solicitud ha sido rechazada por el Staff.';
        $discussion[] = [
            'sender' => 'staff',
            'sender_name' => $staff_name,
            'message' => $msg,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        $discussion_esc = $db->escape_string(json_encode($discussion, JSON_UNESCAPED_UNICODE));
        $db->write_query("
            UPDATE {$prefix}game_card_requests 
            SET discussion_json = '{$discussion_esc}' 
            WHERE id = {$request_id}
        ");
    }
    
    $action_text = 'de carta';
    if ($request_type === 'upgrade') $action_text = 'de mejora';
    elseif ($request_type === 'delete') $action_text = 'de borrado';
    elseif ($request_type === 'create') $action_text = 'de creación de carta';
    elseif ($request_type === 'add_existing') $action_text = 'de adición de carta';
    
    $resolved_card_name = $card_name;
    if ($request_type === 'create') {
        $details = json_decode($request['card_details_json'] ?? '{}', true);
        $resolved_card_name = $details['name'] ?? 'Carta Personalizada';
    }
    
    $notif_title = "Tu solicitud {$action_text} «{$resolved_card_name}» fue rechazada";
    
    // Send Notification to user
    if (function_exists('game_create_notification')) {
        $notif_body = $staff_message !== '' ? "Comentario del Staff: {$staff_message}" : '';
        $bb = $mybb->settings['bburl'];
        $link = rtrim($bb, '/') . "/game/public/personaje.php?pj={$character_id}";
        game_create_notification($player_uid, 'card_request_resolved', $notif_title, $notif_body, $link, $character_id);
    }
    
    echo json_encode(['ok' => true, 'data' => null, 'error' => null]);
    exit;
}

// --- 4. ACTION: APPROVE ---
if ($action === 'approve') {
    // Check level permissions for approval
    if ($request_type === 'create' || $request_type === 'add_existing') {
        if ($staff_level < 3) {
            echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'Solo los Administradores (Nivel 3) pueden aprobar y crear o asignar cartas.']]);
            exit;
        }
    } else {
        if ($staff_level < 2) {
            echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'No tienes permisos de moderación.']]);
            exit;
        }
    }
    
    if ($request_type === 'create') {
        // Read specs from card_details_json
        $details = json_decode($request['card_details_json'] ?? '', true);
        if (!$details || empty($details['name'])) {
            echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'Las especificaciones de la carta moderada no son válidas o están vacías.']]);
            exit;
        }
        
        $name_esc = $db->escape_string($details['name']);
        $card_type_esc = $db->escape_string($details['card_type'] ?? 'tecnica');
        $rank_esc = $db->escape_string($details['rank'] ?? 'C');
        $activation_esc = $db->escape_string($details['activation'] ?? 'activa');
        $tags_json_esc = $db->escape_string(json_encode($details['tags'] ?? [], JSON_UNESCAPED_UNICODE));
        $description_esc = $db->escape_string($details['description'] ?? '');
        $cost_pe_esc = $db->escape_string($details['cost_pe'] ?? '—');
        $execution_stat_esc = $db->escape_string($details['execution_stat'] ?? '');
        $dice_esc = $db->escape_string($details['dice'] ?? '');
        $effects_json_esc = $db->escape_string(json_encode($details['effects'] ?? [], JSON_UNESCAPED_UNICODE));
        $upgrade_json_esc = $db->escape_string(json_encode($details['upgrade'] ?? [], JSON_UNESCAPED_UNICODE));
        $notes_esc = $db->escape_string($details['notes'] ?? '');
        $image_url_esc = $db->escape_string($details['image_url'] ?? '');
        $reposo_esc = isset($details['reposo']) ? (int)$details['reposo'] : 0;
        $duracion_esc = isset($details['duracion']) ? (int)$details['duracion'] : 0;
        
        $insert_card = [
            'name' => $name_esc,
            'card_type' => $card_type_esc,
            'rank' => $rank_esc,
            'activation' => $activation_esc,
            'tags_json' => $tags_json_esc,
            'description' => $description_esc,
            'cost_pe' => $cost_pe_esc,
            'execution_stat' => $execution_stat_esc,
            'dice' => $dice_esc,
            'effects_json' => $effects_json_esc,
            'upgrade_json' => $upgrade_json_esc,
            'notes' => $notes_esc,
            'image_url' => $image_url_esc,
            'reposo' => $reposo_esc,
            'duracion' => $duracion_esc,
            'created_by' => $uid
        ];
        
        $db->insert_query('game_cards', $insert_card);
        $new_card_id = (int)$db->insert_id();
        
        if ($new_card_id <= 0) {
            echo json_encode(['ok' => false, 'error' => ['code' => 500, 'message' => 'Error al registrar la carta en el catálogo.']]);
            exit;
        }
        
        // Assign card to character deck
        $db->write_query("
            INSERT INTO {$prefix}game_character_cards (character_id, card_id, current_rank, assigned_by) 
            VALUES ({$character_id}, {$new_card_id}, '{$rank_esc}', {$uid})
            ON DUPLICATE KEY UPDATE current_rank = '{$rank_esc}', assigned_by = {$uid}
        ");
        
        // Update request details and status
        $db->write_query("
            UPDATE {$prefix}game_card_requests 
            SET status = 'aprobada', 
                resolved_by = {$uid}, 
                resolved_at = NOW(), 
                staff_message = '{$db->escape_string($staff_message)}',
                card_id = {$new_card_id}
            WHERE id = {$request_id}
        ");
        
        $notif_title = "Tu propuesta «{$details['name']}» fue aprobada, creada y añadida a tu deck";
        
    } elseif ($request_type === 'add_existing') {
        if ($card_id <= 0) {
            echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'ID de carta inválido para adición.']]);
            exit;
        }
        
        // Assign card to character deck (default C rank)
        $db->write_query("
            INSERT INTO {$prefix}game_character_cards (character_id, card_id, current_rank, assigned_by) 
            VALUES ({$character_id}, {$card_id}, 'C', {$uid})
            ON DUPLICATE KEY UPDATE current_rank = 'C', assigned_by = {$uid}
        ");
        
        // Update request status
        $db->write_query("
            UPDATE {$prefix}game_card_requests 
            SET status = 'aprobada', 
                resolved_by = {$uid}, 
                resolved_at = NOW(), 
                staff_message = '{$db->escape_string($staff_message)}' 
            WHERE id = {$request_id}
        ");
        
        $resolved_card_name = $card_name ? $card_name : "Carta existente ID: {$card_id}";
        $notif_title = "Tu petición de la carta «{$resolved_card_name}» fue aprobada y añadida a tu deck";
        
    } elseif ($request_type === 'upgrade') {
        // Find current rank assigned to character
        $own_q = $db->query("SELECT current_rank FROM {$prefix}game_character_cards WHERE character_id = {$character_id} AND card_id = {$card_id} LIMIT 1");
        $own = $db->fetch_array($own_q);
        if (!$own) {
            echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'La carta ya no está asignada al personaje.']]);
            exit;
        }
        $current_rank = strtoupper(trim($own['current_rank']));
        
        // C -> B -> A -> S
        $ranks = ['C', 'B', 'A', 'S'];
        $idx = array_search($current_rank, $ranks);
        if ($idx === false) {
            $target_rank = 'B';
        } elseif ($idx >= count($ranks) - 1) {
            $target_rank = 'S'; // already max
        } else {
            $target_rank = $ranks[$idx + 1];
        }
        
        // Update character card rank
        $db->write_query("
            UPDATE {$prefix}game_character_cards 
            SET current_rank = '{$db->escape_string($target_rank)}' 
            WHERE character_id = {$character_id} AND card_id = {$card_id}
        ");
        
        // Update request status
        $db->write_query("
            UPDATE {$prefix}game_card_requests 
            SET status = 'aprobada', 
                resolved_by = {$uid}, 
                resolved_at = NOW(), 
                staff_message = '{$db->escape_string($staff_message)}' 
            WHERE id = {$request_id}
        ");
        
        $resolved_card_name = $card_name;
        $notif_title = "Tu solicitud de mejora para «{$resolved_card_name}» fue aprobada (Rango {$target_rank})";
        
    } else { // delete
        // Remove card assignment
        $db->delete_query('game_character_cards', "character_id = {$character_id} AND card_id = {$card_id}");
        
        // Update request status
        $db->write_query("
            UPDATE {$prefix}game_card_requests 
            SET status = 'aprobada', 
                resolved_by = {$uid}, 
                resolved_at = NOW(), 
                staff_message = '{$db->escape_string($staff_message)}' 
            WHERE id = {$request_id}
        ");
        
        $resolved_card_name = $card_name;
        $notif_title = "Tu solicitud de borrado para «{$resolved_card_name}» fue aprobada";
    }
    
    // Also append message to discussion if it's a creation/catalog request
    if ($request_type === 'create' || $request_type === 'add_existing') {
        $discussion = !empty($request['discussion_json']) ? json_decode($request['discussion_json'], true) : [];
        if (!is_array($discussion)) {
            $discussion = [];
        }
        $msg = $staff_message !== '' ? "SOLICITUD APROBADA: " . $staff_message : 'La solicitud ha sido aprobada por el Staff.';
        $discussion[] = [
            'sender' => 'staff',
            'sender_name' => $staff_name,
            'message' => $msg,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        $discussion_esc = $db->escape_string(json_encode($discussion, JSON_UNESCAPED_UNICODE));
        $db->write_query("
            UPDATE {$prefix}game_card_requests 
            SET discussion_json = '{$discussion_esc}' 
            WHERE id = {$request_id}
        ");
    }
    
    // Send Notification to user (if function is defined in game_postcharacter.php)
    if (function_exists('game_create_notification')) {
        $notif_body = $staff_message !== '' ? "Comentario del Staff: {$staff_message}" : '';
        $bb = $mybb->settings['bburl'];
        $link = rtrim($bb, '/') . "/game/public/personaje.php?pj={$character_id}";
        game_create_notification($player_uid, 'card_request_resolved', $notif_title, $notif_body, $link, $character_id);
    }
    
    echo json_encode(['ok' => true, 'data' => null, 'error' => null]);
    exit;
}
