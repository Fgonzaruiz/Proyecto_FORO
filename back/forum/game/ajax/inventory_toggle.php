<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

global $mybb, $db;
$prefix = TABLE_PREFIX;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => ['code' => 405, 'message' => 'Método no permitido.']]);
    exit;
}

$uid = (int)($mybb->user['uid'] ?? 0);
$char_id = $mybb->get_input('character_id', MyBB::INPUT_INT);
$card_id = $mybb->get_input('card_id', MyBB::INPUT_INT);

if ($char_id <= 0 || $card_id <= 0) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'Parámetros inválidos.']]);
    exit;
}

// Fetch character details to check ownership
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
    echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'No tienes permiso para modificar este equipamiento.']]);
    exit;
}

// Check if character actually owns this card
$owns_q = $db->query("SELECT 1 FROM {$prefix}game_character_cards WHERE character_id = {$char_id} AND card_id = {$card_id} LIMIT 1");
if ($db->num_rows($owns_q) === 0) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'No posees esta carta en tu deck.']]);
    exit;
}

// Fetch card details
$card_q = $db->query("SELECT * FROM {$prefix}game_cards WHERE id = {$card_id} LIMIT 1");
$card = $db->fetch_array($card_q);
if (!$card) {
    echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'Carta no encontrada en el catálogo.']]);
    exit;
}

$type = $card['card_type'];
if (!in_array($type, ['equipo', 'npc_menor', 'barco'], true)) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'Este tipo de carta no se puede equipar.']]);
    exit;
}

// Determine slot type and weight
$slot_type = 'carga';
if ($type === 'npc_menor') {
    $slot_type = 'companero';
} elseif ($type === 'barco') {
    $slot_type = 'barco';
}
$peso = (int)($card['peso'] ?? 1);

// Check if currently equipped
$eq_q = $db->query("SELECT 1 FROM {$prefix}game_character_inventory WHERE character_id = {$char_id} AND card_id = {$card_id} LIMIT 1");
$is_equipped = ($db->num_rows($eq_q) > 0);

if ($is_equipped) {
    // Unequip card
    $db->write_query("DELETE FROM {$prefix}game_character_inventory WHERE character_id = {$char_id} AND card_id = {$card_id}");
    echo json_encode(['ok' => true, 'data' => ['equipped' => false, 'card_id' => $card_id], 'error' => null]);
    exit;
} else {
    // Equip card: Validate limits first
    $stats = !empty($pj['stats_json']) ? json_decode($pj['stats_json'], true) : [];
    $fue = (int)($stats['fue'] ?? $stats['str'] ?? $pj['stat_fp'] ?? 5);

    $data = !empty($pj['data_json']) ? json_decode($pj['data_json'], true) : [];
    $linaje = $data['linaje'] ?? [];
    $general_ids = $linaje['elegidos_general'] ?? [];
    $racial_ids = $linaje['elegidos_racial'] ?? [];

    $has_carga_perk = in_array('g_capacidad_carga', $general_ids) || in_array('g_capacidad_carga', $racial_ids) || in_array('g_carga_extra', $general_ids);
    $has_vinculo_companero = in_array('g_vinculo_companero', $general_ids) || in_array('g_vinculo_companero', $racial_ids) || in_array('rsi_vinculo_ext', $general_ids);

    $cc_max = 5 + (int)floor($fue / 4) + ($has_carga_perk ? 3 : 0);
    $companion_max = $has_vinculo_companero ? 2 : 1;

    // Get current equipped slots counts
    $current_q = $db->query("SELECT slot_type, peso FROM {$prefix}game_character_inventory WHERE character_id = {$char_id}");
    $cc_used = 0;
    $companions_count = 0;
    $barcos_count = 0;

    while ($r = $db->fetch_array($current_q)) {
        if ($r['slot_type'] === 'carga') {
            $cc_used += (int)$r['peso'];
        } elseif ($r['slot_type'] === 'companero') {
            $companions_count++;
        } elseif ($r['slot_type'] === 'barco') {
            $barcos_count++;
        }
    }

    // Check limits
    if ($slot_type === 'carga') {
        if ($cc_used + $peso > $cc_max) {
            echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => "Capacidad de Carga insuficiente. Consumo: {$peso} CC (Límite: {$cc_used}/{$cc_max} CC)."]]);
            exit;
        }
    } elseif ($slot_type === 'companero') {
        if ($companions_count >= $companion_max) {
            echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => "Límite de compañeros excedido ({$companions_count}/{$companion_max}). Desequipa uno primero o amplía tu ranura por linaje."]]);
            exit;
        }
    } elseif ($slot_type === 'barco') {
        if ($barcos_count >= 1) {
            echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => "Ya tienes un barco activo. Desactiva el barco actual primero para equipar uno nuevo."]]);
            exit;
        }
    }

    // Equip item: insert record
    $db->write_query("INSERT INTO {$prefix}game_character_inventory (character_id, card_id, slot_type, peso) VALUES ({$char_id}, {$card_id}, '{$slot_type}', {$peso})");
    echo json_encode(['ok' => true, 'data' => ['equipped' => true, 'card_id' => $card_id], 'error' => null]);
    exit;
}
