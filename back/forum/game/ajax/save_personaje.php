<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

global $mybb, $db;

$user_id = (int)($mybb->user['uid'] ?? 0);
if (!$user_id) {
    echo json_encode(['ok' => false, 'error' => ['code' => 401, 'message' => 'No autorizado.']]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => ['code' => 405, 'message' => 'Method not allowed']]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'Payload inválido.']]);
    exit;
}

$edit_pj_id = (int)($input['pj_id'] ?? 0);

$name = $db->escape_string($input['name'] ?? 'Sin Nombre');
$faction = $db->escape_string($input['faction'] ?? '');
$rank = $db->escape_string($input['rank'] ?? '');
$race = $db->escape_string($input['race'] ?? '');
$job = $db->escape_string($input['job'] ?? '');
$avatar = $db->escape_string($input['avatar'] ?? '');
$arquetipo = $db->escape_string($input['arquetipo'] ?? '');

$data_json = $db->escape_string(json_encode($input, JSON_UNESCAPED_UNICODE));
$stats_json = $db->escape_string(json_encode($input['stats'] ?? [], JSON_UNESCAPED_UNICODE));

$prefix = TABLE_PREFIX;

$insert_array = [
    'user_id' => $user_id,
    'name' => $name,
    'race_name' => $race,
    'faction' => $faction,
    'rango' => $rank,
    'occupation_name' => $job,
    'avatar' => $avatar,
    'data_json' => $data_json,
    'stats_json' => $stats_json,
    'approved' => 0,
    'status' => 'pendiente'
];

if ($edit_pj_id > 0) {
    // Check if character belongs to user and is editable
    $q = $db->query("SELECT id, status FROM {$prefix}game_personajes WHERE id = {$edit_pj_id} AND user_id = {$user_id} LIMIT 1");
    $pj = $db->fetch_array($q);
    if (!$pj) {
        echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'Personaje no encontrado o sin permisos.']]);
        exit;
    }
    if ($pj['status'] !== 'pendiente' && $pj['status'] !== 'revision') {
        echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'El personaje no puede ser editado en su estado actual.']]);
        exit;
    }
    
    // Update existing
    unset($insert_array['user_id']); // Don't change owner
    $db->update_query('game_personajes', $insert_array, "id = {$edit_pj_id}");
    $new_pj_id = $edit_pj_id;
} else {
    // Validate slot limit server-side before creating
    $slot_q = $db->query("SELECT max_slots, slots_used FROM {$prefix}game_user_config WHERE user_id = {$user_id} LIMIT 1");
    $slot_row = $db->fetch_array($slot_q);
    $max_slots = (int)($slot_row['max_slots'] ?? 1);
    
    // Count actual non-deleted characters for accuracy
    $actual_q = $db->query("SELECT COUNT(*) AS cnt FROM {$prefix}game_personajes WHERE user_id = {$user_id}");
    $actual_used = (int)$db->fetch_field($actual_q, 'cnt');
    
    if ($actual_used >= $max_slots) {
        echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'Has alcanzado el límite de personajes.']]);
        exit;
    }

    // Insert new
    $db->insert_query('game_personajes', $insert_array);
    $new_pj_id = $db->insert_id();

    // Update active character config
    if ($db->num_rows($slot_q) > 0) {
        $db->write_query("UPDATE {$prefix}game_user_config SET slots_used = {$actual_used} + 1 WHERE user_id = {$user_id}");
    } else {
        $db->write_query("INSERT INTO {$prefix}game_user_config (user_id, max_slots, slots_used, active_pj_id) VALUES ({$user_id}, 1, 1, {$new_pj_id})");
    }
}

echo json_encode([
    'ok' => true,
    'data' => ['pj_id' => $new_pj_id],
    'error' => null
]);
exit;
