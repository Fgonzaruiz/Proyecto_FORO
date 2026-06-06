<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

global $mybb, $db;
$prefix = TABLE_PREFIX;

$uid = (int)($mybb->user['uid'] ?? 0);
$char_id = $mybb->get_input('character_id', MyBB::INPUT_INT);

if ($char_id <= 0) {
    if (!$uid) {
        echo json_encode(['ok' => false, 'error' => ['code' => 401, 'message' => 'No autorizado.']]);
        exit;
    }
    $cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
    $cfg = $db->fetch_array($cfg_q);
    $char_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
}

if ($char_id <= 0) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'Personaje no encontrado.']]);
    exit;
}

// Fetch character details
$pj_q = $db->query("SELECT * FROM {$prefix}game_personajes WHERE id = {$char_id} LIMIT 1");
$pj = $db->fetch_array($pj_q);
if (!$pj) {
    echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'Personaje no encontrado.']]);
    exit;
}

$is_owner = ($uid > 0 && (int)$pj['user_id'] === $uid);
$is_staff = false;
if ($uid > 0) {
    $staff_q = $db->query("SELECT is_staff FROM {$prefix}game_personajes WHERE user_id = {$uid} AND is_staff = 1 LIMIT 1");
    if ($db->num_rows($staff_q) > 0) {
        $is_staff = true;
    }
}

if (!$is_owner && !$is_staff) {
    echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'No tienes permiso para ver este inventario.']]);
    exit;
}

// Parse stats and linaje
$stats = !empty($pj['stats_json']) ? json_decode($pj['stats_json'], true) : [];
$fue = (int)($stats['fue'] ?? $stats['str'] ?? $pj['stat_fp'] ?? 5);

$data = !empty($pj['data_json']) ? json_decode($pj['data_json'], true) : [];
$linaje = $data['linaje'] ?? [];
$general_ids = $linaje['elegidos_general'] ?? [];
$racial_ids = $linaje['elegidos_racial'] ?? [];

$has_carga_perk = in_array('g_capacidad_carga', $general_ids) || in_array('g_capacidad_carga', $racial_ids) || in_array('g_carga_extra', $general_ids);
$has_vinculo_companero = in_array('g_vinculo_companero', $general_ids) || in_array('g_vinculo_companero', $racial_ids) || in_array('rsi_vinculo_ext', $general_ids);

// Calculate Limits
$cc_max = 5 + (int)floor($fue / 4) + ($has_carga_perk ? 3 : 0);
$companion_max = $has_vinculo_companero ? 2 : 1;
$barco_max = 1;

// Fetch Equipped items
$equipped_q = $db->query("
    SELECT i.*, c.name, c.card_type, c.`rank`, c.description, c.image_url
    FROM {$prefix}game_character_inventory i
    JOIN {$prefix}game_cards c ON i.card_id = c.id
    WHERE i.character_id = {$char_id}
    ORDER BY i.slot_type ASC, i.equipped_at ASC
");

$equipped = [];
$equipped_ids = [];
$cc_used = 0;
$companions_count = 0;
$barcos_count = 0;

while ($row = $db->fetch_array($equipped_q)) {
    $row['card_id'] = (int)$row['card_id'];
    $row['peso'] = (int)$row['peso'];
    $equipped[] = $row;
    $equipped_ids[] = $row['card_id'];
    
    if ($row['slot_type'] === 'carga') {
        $cc_used += $row['peso'];
    } elseif ($row['slot_type'] === 'companero') {
        $companions_count++;
    } elseif ($row['slot_type'] === 'barco') {
        $barcos_count++;
    }
}

// Fetch Owned equippable cards
$cantidad_col = "1 AS cantidad";
if ($db->field_exists('cantidad', 'game_character_cards')) {
    $cantidad_col = "cc.cantidad";
}

$owned_q = $db->query("
    SELECT c.id, c.name, c.card_type, cc.current_rank as `rank`, c.description, c.image_url, c.peso, {$cantidad_col}
    FROM {$prefix}game_character_cards cc
    JOIN {$prefix}game_cards c ON cc.card_id = c.id
    WHERE cc.character_id = {$char_id} AND c.card_type IN ('equipo', 'npc_menor', 'barco')
    ORDER BY c.card_type ASC, c.name ASC
");

$owned = [];
while ($row = $db->fetch_array($owned_q)) {
    $row['id'] = (int)$row['id'];
    $row['peso'] = (int)$row['peso'];
    $row['is_equipped'] = in_array($row['id'], $equipped_ids, true);
    
    // Determine subtype for companions
    if ($row['card_type'] === 'npc_menor') {
        // Fetch original card to inspect subtype if needed, or default
        $row['subtipo'] = 'Compañero';
    }
    
    $owned[] = $row;
}

echo json_encode([
    'ok' => true,
    'data' => [
        'character' => [
            'id' => $char_id,
            'name' => $pj['name'],
            'fue' => $fue,
            'cc_max' => $cc_max,
            'cc_used' => $cc_used,
            'companion_max' => $companion_max,
            'companion_used' => $companions_count,
            'barco_max' => $barco_max,
            'barco_used' => $barcos_count
        ],
        'equipped' => $equipped,
        'owned' => $owned
    ],
    'error' => null
]);
