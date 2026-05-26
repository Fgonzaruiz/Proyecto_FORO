<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
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

$input = json_decode(file_get_contents('php://input'), true);
$request_id = (int)($input['request_id'] ?? 0);
$action = trim((string)($input['action'] ?? '')); // 'approve' or 'reject'
$staff_message = trim((string)($input['staff_message'] ?? ''));

if ($request_id <= 0 || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'Parámetros inválidos.']]);
    exit;
}

$prefix = TABLE_PREFIX;

// Check staff level (Moderator level 2 or Administrator level 3)
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;
if ($active_pj_id > 0) {
    $pj_q = $db->query("SELECT staff_level FROM {$prefix}game_personajes WHERE id = {$active_pj_id} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    $staff_level = $pj ? (int)$pj['staff_level'] : 0;
}

if ($staff_level < 2) {
    echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'No tienes permisos de moderación/administración.']]);
    exit;
}

// Fetch request details
$req_q = $db->query("
    SELECT r.*, p.user_id as player_uid, p.name as character_name, c.name as card_name 
    FROM {$prefix}game_card_requests r
    JOIN {$prefix}game_personajes p ON r.character_id = p.id
    JOIN {$prefix}game_cards c ON r.card_id = c.id
    WHERE r.id = {$request_id} LIMIT 1
");
$request = $db->fetch_array($req_q);

if (!$request) {
    echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'Solicitud no encontrada.']]);
    exit;
}

if ($request['status'] !== 'pendiente') {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'La solicitud ya ha sido resuelta.']]);
    exit;
}

$player_uid = (int)$request['player_uid'];
$character_id = (int)$request['character_id'];
$card_id = (int)$request['card_id'];
$request_type = $request['request_type'];
$card_name = $request['card_name'];

if ($action === 'approve') {
    if ($request_type === 'upgrade') {
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
        
        $notif_title = "Tu solicitud de mejora para «{$card_name}» fue aprobada (Rango {$target_rank})";
    } else { // delete
        // Remove card assignment
        $db->delete_query('game_character_cards', "character_id = {$character_id} AND card_id = {$card_id}");
        
        $notif_title = "Tu solicitud de borrado para «{$card_name}» fue aprobada";
    }
    
    // Update request status
    $db->write_query("
        UPDATE {$prefix}game_card_requests 
        SET status = 'aprobada', 
            resolved_by = {$uid}, 
            resolved_at = NOW(), 
            staff_message = '{$db->escape_string($staff_message)}' 
        WHERE id = {$request_id}
    ");
    
} else { // reject
    // Update request status to rejected
    $db->write_query("
        UPDATE {$prefix}game_card_requests 
        SET status = 'rechazada', 
            resolved_by = {$uid}, 
            resolved_at = NOW(), 
            staff_message = '{$db->escape_string($staff_message)}' 
        WHERE id = {$request_id}
    ");
    
    $action_text = $request_type === 'upgrade' ? 'mejora' : 'borrado';
    $notif_title = "Tu solicitud de {$action_text} para «{$card_name}» fue rechazada";
}

// Send Notification to user (if function is defined in game_postcharacter.php)
if (function_exists('game_create_notification')) {
    $notif_body = $staff_message !== '' ? "Comentario del Staff: {$staff_message}" : '';
    $bb = $mybb->settings['bburl'];
    $link = rtrim($bb, '/') . "/game/public/personaje.php?pj={$character_id}";
    game_create_notification($player_uid, 'card_request_resolved', $notif_title, $notif_body, $link, $character_id);
}

echo json_encode(['ok' => true, 'data' => null, 'error' => null]);
