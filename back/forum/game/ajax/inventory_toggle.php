<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $db;
$prefix = TABLE_PREFIX;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();

$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$char_id = isset($input['character_id']) ? (int)$input['character_id'] : 0;
$card_id = isset($input['card_id']) ? (int)$input['card_id'] : 0;

if ($char_id <= 0 || $card_id <= 0) {
    GameAjax::fail(400, 'Parámetros inválidos.');
}

// Fetch character details to check ownership
$pj_q = $db->query("SELECT * FROM {$prefix}game_personajes WHERE id = {$char_id} LIMIT 1");
$pj = $db->fetch_array($pj_q);
if (!$pj) {
    GameAjax::fail(404, 'Personaje no encontrado.');
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
    GameAjax::fail(403, 'No tienes permiso para modificar este equipamiento.');
}

// Check if character actually owns this card
$owns_q = $db->query("SELECT 1 FROM {$prefix}game_character_cards WHERE character_id = {$char_id} AND card_id = {$card_id} LIMIT 1");
if ($db->num_rows($owns_q) === 0) {
    GameAjax::fail(400, 'No posees esta carta en tu deck.');
}

// Fetch card details
$card_q = $db->query("SELECT * FROM {$prefix}game_cards WHERE id = {$card_id} LIMIT 1");
$card = $db->fetch_array($card_q);
if (!$card) {
    GameAjax::fail(404, 'Carta no encontrada en el catálogo.');
}

$type = $card['card_type'];
if (!in_array($type, ['equipo', 'npc_menor', 'barco'], true)) {
    GameAjax::fail(400, 'Este tipo de carta no se puede equipar.');
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
    GameAjax::json(true, ['equipped' => false, 'card_id' => $card_id]);
} else {
    // Equip card: Validate limits first
    $stats = !empty($pj['stats_json']) ? json_decode($pj['stats_json'], true) : [];
    $data = !empty($pj['data_json']) ? json_decode($pj['data_json'], true) : [];
    require_once __DIR__ . '/../inc/stat_helpers.php';
    $linajeToggle = is_array($data['linaje'] ?? null) ? $data['linaje'] : [];
    $raceNameToggle = (string)($linajeToggle['raza'] ?? $linajeToggle['race'] ?? '');
    $fue = game_build_stat_context(is_array($stats) ? $stats : [], $raceNameToggle)['values']['fuerza'] ?? 4;
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
            GameAjax::fail(400, "Capacidad de Carga insuficiente. Consumo: {$peso} CC (Límite: {$cc_used}/{$cc_max} CC).");
        }
    } elseif ($slot_type === 'companero') {
        if ($companions_count >= $companion_max) {
            GameAjax::fail(400, "Límite de compañeros excedido ({$companions_count}/{$companion_max}). Desequipa uno primero o amplía tu ranura por linaje.");
        }
    } elseif ($slot_type === 'barco') {
        if ($barcos_count >= 1) {
            GameAjax::fail(400, "Ya tienes un barco activo. Desactiva el barco actual primero para equipar uno nuevo.");
        }
    }

    // Equip item: insert record
    $db->write_query("INSERT INTO {$prefix}game_character_inventory (character_id, card_id, slot_type, peso) VALUES ({$char_id}, {$card_id}, '{$slot_type}', {$peso})");
    GameAjax::json(true, ['equipped' => true, 'card_id' => $card_id]);
}
