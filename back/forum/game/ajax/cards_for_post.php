<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

global $db;

$post_id = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
if ($post_id <= 0) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'post_id inválido']]);
    exit;
}

$prefix = TABLE_PREFIX;

// Obtener autor del post
$post_q = $db->query("SELECT uid FROM {$prefix}posts WHERE pid = {$post_id} LIMIT 1");
$post_row = $db->fetch_array($post_q);
// Obtener el personaje que posee el post y las acciones ocultas
$post_character_id = 0;
$hidden_actions = [];
if ($db->table_exists('game_post_characters')) {
    $char_q = $db->query("SELECT character_id, hidden_actions_json FROM {$prefix}game_post_characters WHERE post_id = {$post_id} LIMIT 1");
    if ($char_row = $db->fetch_array($char_q)) {
        $post_character_id = (int)$char_row['character_id'];
        $decoded = json_decode($char_row['hidden_actions_json'] ?? '[]', true);
        if (is_array($decoded)) {
            $hidden_actions = $decoded;
        }
    }
}

$current_uid = (int)($mybb->user['uid'] ?? 0);

// Obtener el personaje activo del usuario actual
$viewer_char_id = 0;
if ($current_uid > 0 && $db->table_exists('game_user_config')) {
    $cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$current_uid} LIMIT 1");
    $cfg = $db->fetch_array($cfg_q);
    $viewer_char_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
}

$is_post_owner_character = ($viewer_char_id > 0 && $viewer_char_id === $post_character_id);

// Filtrar las acciones ocultas visibles
$processed_hidden_actions = [];
$visible_hidden_indexes = []; // index -> bool

foreach ($hidden_actions as $act) {
    $idx = (int)($act['index'] ?? 0);
    if ($idx <= 0) continue;
    $revealed = (bool)($act['is_revealed'] ?? false);
    
    // Solo es visible si ya se reveló, o si el personaje activo del visor es el dueño del post
    $can_see = ($revealed || $is_post_owner_character);
    
    if ($can_see) {
        $processed_hidden_actions[] = [
            'index' => $idx,
            'description' => $act['description'] ?? '',
            'is_revealed' => $revealed,
            'can_reveal' => ($is_post_owner_character && !$revealed),
            'cards' => []
        ];
        $visible_hidden_indexes[$idx] = true;
    }
}

$query = $db->query("
    SELECT pc.played_rank, pc.roll_result, pc.played_at, pc.hidden_action_index, c.* 
    FROM {$prefix}game_post_cards pc
    JOIN {$prefix}game_cards c ON pc.card_id = c.id
    WHERE pc.post_id = {$post_id}
    ORDER BY pc.id ASC
");

$normal_cards = [];
$hidden_cards_by_action = [];

while ($row = $db->fetch_array($query)) {
    // Override definition rank with the rank it had when played
    $row['rank'] = $row['played_rank'];
    unset($row['played_rank']);
    
    $row['tags'] = json_decode($row['tags_json'] ?? '[]', true);
    $row['effects'] = json_decode($row['effects_json'] ?? '{}', true);
    $row['upgrade'] = json_decode($row['upgrade_json'] ?? '{}', true);
    $row['reposo'] = isset($row['reposo']) ? (int)$row['reposo'] : 0;
    $row['duracion'] = isset($row['duracion']) ? (int)$row['duracion'] : 0;
    unset($row['tags_json'], $row['effects_json'], $row['upgrade_json']);
    
    $h_idx = isset($row['hidden_action_index']) ? (int)$row['hidden_action_index'] : 0;
    
    if ($h_idx === 0) {
        $normal_cards[] = $row;
    } else {
        if (!empty($visible_hidden_indexes[$h_idx])) {
            $hidden_cards_by_action[$h_idx][] = $row;
        }
    }
}

// Cruzar cartas con sus acciones ocultas correspondientes
foreach ($processed_hidden_actions as &$act) {
    $idx = $act['index'];
    if (isset($hidden_cards_by_action[$idx])) {
        $act['cards'] = $hidden_cards_by_action[$idx];
    }
}
unset($act);

$mods = [
    'pv_change' => 0,
    'pe_change' => 0,
    'stat_mods' => []
];

if ($db->table_exists('game_post_characters')) {
    $char_q = $db->query("
        SELECT pv_change, pe_change, modifiers_json 
        FROM {$prefix}game_post_characters 
        WHERE post_id = {$post_id} 
        LIMIT 1
    ");
    if ($char_row = $db->fetch_array($char_q)) {
        $mods['pv_change'] = (int)($char_row['pv_change'] ?? 0);
        $mods['pe_change'] = (int)($char_row['pe_change'] ?? 0);
        $decoded = json_decode($char_row['modifiers_json'] ?? '{}', true);
        if (is_array($decoded)) {
            $mods['stat_mods'] = $decoded;
        }
    }
}

echo json_encode([
    'ok' => true,
    'data' => $normal_cards,
    'modifications' => $mods,
    'hidden_actions' => $processed_hidden_actions,
    'error' => null
]);

