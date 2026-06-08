<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

global $db, $mybb;

$post_id = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
$debug = function_exists('game_post_rpg_debug_enabled') && game_post_rpg_debug_enabled();
$debug_info = [];

try {
    if ($post_id <= 0) {
        echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'post_id inválido']]);
        exit;
    }

    $prefix = TABLE_PREFIX;
    $debug_info['post_id'] = $post_id;
    $debug_info['tables'] = [
        'game_post_characters' => $db->table_exists('game_post_characters'),
        'game_post_cards' => $db->table_exists('game_post_cards'),
        'game_post_oracles' => $db->table_exists('game_post_oracles'),
        'game_oracles' => $db->table_exists('game_oracles'),
    ];
    $debug_info['columns'] = [
        'hidden_actions_json' => $db->table_exists('game_post_characters')
            && $db->field_exists('hidden_actions_json', 'game_post_characters'),
        'hidden_action_index' => $db->table_exists('game_post_cards')
            && $db->field_exists('hidden_action_index', 'game_post_cards'),
        'modifiers_json' => game_post_rpg_modifiers_ready(),
    ];

    $post_character_id = 0;
    $hidden_actions = [];
    if ($db->table_exists('game_post_characters')) {
        $hidden_col = $debug_info['columns']['hidden_actions_json'] ? ', hidden_actions_json' : '';
        $char_q = $db->query("SELECT character_id{$hidden_col} FROM {$prefix}game_post_characters WHERE post_id = {$post_id} LIMIT 1");
        if ($char_row = $db->fetch_array($char_q)) {
            $post_character_id = (int)$char_row['character_id'];
            if ($hidden_col !== '') {
                $decoded = json_decode($char_row['hidden_actions_json'] ?? '[]', true);
                if (is_array($decoded)) {
                    $hidden_actions = $decoded;
                }
            }
        }
    }

    $current_uid = (int)($mybb->user['uid'] ?? 0);
    $viewer_char_id = 0;
    if ($current_uid > 0 && $db->table_exists('game_user_config')) {
        $cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$current_uid} LIMIT 1");
        $cfg = $db->fetch_array($cfg_q);
        $viewer_char_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
    }

    $is_post_owner_character = ($viewer_char_id > 0 && $viewer_char_id === $post_character_id);
    $debug_info['post_character_id'] = $post_character_id;
    $debug_info['viewer_char_id'] = $viewer_char_id;
    $debug_info['is_owner'] = $is_post_owner_character;

    $processed_hidden_actions = [];
    $visible_hidden_indexes = [];

    foreach ($hidden_actions as $act) {
        $idx = (int)($act['index'] ?? 0);
        if ($idx <= 0) {
            continue;
        }
        $revealed = (bool)($act['is_revealed'] ?? false);
        $can_see = ($revealed || $is_post_owner_character);

        if ($can_see) {
            $processed_hidden_actions[] = [
                'index' => $idx,
                'description' => $act['description'] ?? '',
                'is_revealed' => $revealed,
                'can_reveal' => ($is_post_owner_character && !$revealed),
                'cards' => [],
            ];
            $visible_hidden_indexes[$idx] = true;
        }
    }

    $normal_cards = [];
    $hidden_cards_by_action = [];

    if ($db->table_exists('game_post_cards') && $db->table_exists('game_cards')) {
        $hidden_idx_sql = $debug_info['columns']['hidden_action_index']
            ? 'pc.hidden_action_index'
            : '0 AS hidden_action_index';
        $query = $db->query("
            SELECT pc.played_rank, pc.roll_result, pc.played_at, {$hidden_idx_sql}, c.*
            FROM {$prefix}game_post_cards pc
            JOIN {$prefix}game_cards c ON pc.card_id = c.id
            WHERE pc.post_id = {$post_id}
            ORDER BY pc.id ASC
        ");

        while ($row = $db->fetch_array($query)) {
            $row['rank'] = $row['played_rank'];
            unset($row['played_rank']);

            $row['tags'] = json_decode($row['tags_json'] ?? '[]', true);
            $row['effects'] = json_decode($row['effects_json'] ?? '{}', true);
            $row['upgrade'] = json_decode($row['upgrade_json'] ?? '{}', true);
            $row['reposo'] = isset($row['reposo']) ? (int)$row['reposo'] : 0;
            $row['duracion'] = isset($row['duracion']) ? (int)$row['duracion'] : 0;
            $row['execution_cost'] = isset($row['execution_cost']) ? (int)$row['execution_cost'] : 0;
            unset($row['tags_json'], $row['effects_json'], $row['upgrade_json']);

            $h_idx = isset($row['hidden_action_index']) ? (int)$row['hidden_action_index'] : 0;

            if ($h_idx === 0) {
                $normal_cards[] = $row;
            } elseif (!empty($visible_hidden_indexes[$h_idx])) {
                $hidden_cards_by_action[$h_idx][] = $row;
            }
        }
    }

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
        'stat_mods' => [],
    ];

    if ($db->table_exists('game_post_characters') && game_post_rpg_modifiers_ready()) {
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

    $oracles = [];
    if ($db->table_exists('game_post_oracles') && $db->table_exists('game_oracles')) {
        $oq = $db->query("
            SELECT po.id, po.roll_value, po.result_range, po.result_text, po.result_description,
                   po.auto_invoked, po.invoked_by_post_oracle_id,
                   o.id AS oracle_id, o.name, o.description AS oracle_description,
                   o.oracle_type, o.subtype, o.dice_type
            FROM {$prefix}game_post_oracles po
            JOIN {$prefix}game_oracles o ON po.oracle_id = o.id
            WHERE po.post_id = {$post_id}
            ORDER BY po.auto_invoked ASC, po.id ASC
        ");
        while ($row = $db->fetch_array($oq)) {
            $oracles[] = [
                'id' => (int)$row['id'],
                'oracle_id' => (int)$row['oracle_id'],
                'name' => $row['name'],
                'description' => $row['oracle_description'],
                'oracle_type' => $row['oracle_type'],
                'subtype' => $row['subtype'],
                'dice_type' => $row['dice_type'],
                'roll_value' => $row['roll_value'],
                'result_range' => $row['result_range'],
                'result_text' => $row['result_text'],
                'result_description' => $row['result_description'],
                'auto_invoked' => (int)$row['auto_invoked'],
            ];
        }
    }

    $debug_info['counts'] = [
        'cards' => count($normal_cards),
        'hidden_actions' => count($processed_hidden_actions),
        'oracles' => count($oracles),
        'pv_change' => $mods['pv_change'],
        'pe_change' => $mods['pe_change'],
        'stat_mods' => count($mods['stat_mods'] ?? []),
    ];

    if (function_exists('game_log_post_rpg')) {
        game_log_post_rpg('cards_for_post', $debug_info);
    }

    $response = [
        'ok' => true,
        'data' => $normal_cards,
        'modifications' => $mods,
        'hidden_actions' => $processed_hidden_actions,
        'oracles' => $oracles,
        'error' => null,
    ];
    if ($debug) {
        $response['_debug'] = $debug_info;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (function_exists('game_log_post_rpg')) {
        game_log_post_rpg('cards_for_post_error', [
            'post_id' => $post_id,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => ['code' => 500, 'message' => 'Error al cargar datos del post.'],
        '_debug' => $debug ? ['exception' => $e->getMessage()] : null,
    ], JSON_UNESCAPED_UNICODE);
}
