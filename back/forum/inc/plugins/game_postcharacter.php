<?php
if (!defined('IN_MYBB')) die('Direct access denined.');

function game_postcharacter_info() {
    return [
        'name'          => 'Game Post Character Linker + Notifications',
        'description'   => 'Vincula posts con personajes, gestiona fecha onrol y envía notificaciones.',
        'website'       => '',
        'author'        => 'Game Module',
        'authorsite'    => '',
        'version'       => '1.1',
        'guid'          => '',
        'compatibility' => '18*',
    ];
}

$plugins->add_hook('datahandler_post_insert_post_end', 'game_postcharacter_save_post');
$plugins->add_hook('datahandler_post_insert_thread_end', 'game_postcharacter_save_thread');
$plugins->add_hook('class_moderation_delete_post_start', 'game_postcharacter_delete_post');
$plugins->add_hook('class_moderation_delete_thread_start', 'game_postcharacter_delete_thread');
$plugins->add_hook('global_start', 'game_postcharacter_global_date');
$plugins->add_hook('editpost_start', 'game_postcharacter_block_edit');
$plugins->add_hook('xmlhttp_edit_post_start', 'game_postcharacter_block_ajax_edit');

function game_postcharacter_is_consumible_card(array $card): bool
{
    if (($card['card_type'] ?? '') !== 'equipo') {
        return false;
    }
    $ef = json_decode($card['effects_json'] ?? '{}', true);
    if (strtolower((string)($ef['equipo_type'] ?? '')) === 'util') {
        return true;
    }
    $tags = json_decode($card['tags_json'] ?? '[]', true);
    if (!is_array($tags)) {
        return false;
    }
    foreach ($tags as $t) {
        $u = strtoupper((string)$t);
        if (in_array($u, ['CONSUMIBLE', 'MUNICION', 'AMMO'], true)) {
            return true;
        }
    }
    return false;
}

function game_postcharacter_decrement_consumible(int $cid, int $card_id): void
{
    global $db;
    $prefix = TABLE_PREFIX;
    if (!$db->field_exists('cantidad', 'game_character_cards')) {
        return;
    }
    $db->write_query(
        "UPDATE {$prefix}game_character_cards SET cantidad = GREATEST(0, cantidad - 1)
         WHERE character_id = {$cid} AND card_id = {$card_id}",
        1
    );
    $db->write_query(
        "DELETE FROM {$prefix}game_character_cards
         WHERE character_id = {$cid} AND card_id = {$card_id} AND cantidad <= 0",
        1
    );
}

function game_postcharacter_save_thread_state(int $tid, int $cid, int $pid): void
{
    global $db;
    if ($tid <= 0 || $cid <= 0 || $pid <= 0) {
        return;
    }
    if (!isset($_POST['rpg_thread_pv']) && !isset($_POST['rpg_thread_pe'])) {
        return;
    }
    $prefix = TABLE_PREFIX;
    if (!$db->table_exists('game_thread_pj_state')) {
        return;
    }

    $current_pv = isset($_POST['rpg_thread_pv']) ? (int)$_POST['rpg_thread_pv'] : 0;
    $current_pe = isset($_POST['rpg_thread_pe']) ? (int)$_POST['rpg_thread_pe'] : 0;
    $stat_mods = '{}';
    if (!empty($_POST['rpg_modifiers'])) {
        $raw = json_decode($_POST['rpg_modifiers'], true);
        if (is_array($raw)) {
            $stat_mods = json_encode($raw, JSON_UNESCAPED_UNICODE);
        }
    }
    $mods_esc = $db->escape_string($stat_mods);

    $db->write_query("
        INSERT INTO {$prefix}game_thread_pj_state (thread_id, character_id, current_pv, current_pe, stat_mods_json, last_post_id)
        VALUES ({$tid}, {$cid}, {$current_pv}, {$current_pe}, '{$mods_esc}', {$pid})
        ON DUPLICATE KEY UPDATE
            current_pv = {$current_pv},
            current_pe = {$current_pe},
            stat_mods_json = '{$mods_esc}',
            last_post_id = {$pid}
    ");
}

function game_postcharacter_process_cards($pid, $cid) {
    if (empty($_POST['rpg_played_cards'])) {
        return;
    }
    $card_ids = json_decode($_POST['rpg_played_cards'], true);
    if (!is_array($card_ids)) {
        return;
    }
    if (empty($card_ids)) {
        return;
    }
    
    global $db;
    $prefix = TABLE_PREFIX;
    $pid = (int)$pid;
    $cid = (int)$cid;
    
    // Fetch character stats first
    $stats = [];
    $pj_q = $db->query("SELECT name, stats_json FROM {$prefix}game_personajes WHERE id = {$cid} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    if ($pj) {
        $stats_decoded = json_decode($pj['stats_json'] ?? '{}', true);
        $stats = is_array($stats_decoded) ? $stats_decoded : [];
        if (!isset($stats['fue'])) $stats['fue'] = (int)($stats['str'] ?? 5);
        if (!isset($stats['agi'])) $stats['agi'] = 5;
        if (!isset($stats['des'])) $stats['des'] = (int)($stats['res'] ?? 5);
        if (!isset($stats['inst'])) $stats['inst'] = (int)($stats['vol'] ?? 5);
        if (!isset($stats['esp'])) $stats['esp'] = (int)($stats['vol'] ?? 5);
        if (!isset($stats['int'])) $stats['int'] = 5;
    }

    // Aplicar modificadores de buff/debuff del turno (enviados desde el panel JS)
    if (!empty($_POST['rpg_modifiers'])) {
        $raw_mods = json_decode($_POST['rpg_modifiers'], true);
        if (is_array($raw_mods)) {
            $valid_stats = ['fue', 'agi', 'des', 'int', 'esp', 'inst'];
            foreach ($raw_mods as $mod_stat => $mod_val) {
                $mod_stat = strtolower(trim((string)$mod_stat));
                $mod_val  = (int)$mod_val;
                if ($mod_val !== 0 && in_array($mod_stat, $valid_stats)) {
                    $stats[$mod_stat] = ($stats[$mod_stat] ?? 0) + $mod_val;
                }
            }
        }
    }

    foreach ($card_ids as $c_entry) {
        $c = 0;
        $selected_weapons = [];
        $selected_ammo = [];
        $selected_action = '';
        
        if (is_numeric($c_entry)) {
            $c = (int)$c_entry;
        } elseif (is_array($c_entry)) {
            $c = (int)($c_entry['card_id'] ?? 0);
            if (isset($c_entry['selected_action'])) {
                $selected_action = trim((string)$c_entry['selected_action']);
            }
            if (isset($c_entry['weapons']) && is_array($c_entry['weapons'])) {
                foreach ($c_entry['weapons'] as $w_id) {
                    $selected_weapons[] = (int)$w_id;
                }
            }
            if (isset($c_entry['ammo'])) {
                if (is_array($c_entry['ammo'])) {
                    foreach ($c_entry['ammo'] as $a_id) {
                        $selected_ammo[] = (int)$a_id;
                    }
                } else {
                    $selected_ammo[] = (int)$c_entry['ammo'];
                }
            }
        }
        
        if ($c <= 0) {
            continue;
        }
        
        $own_q = $db->query("SELECT current_rank FROM {$prefix}game_character_cards WHERE character_id = {$cid} AND card_id = {$c} LIMIT 1");
        $own = $db->fetch_array($own_q);
        if (!$own) {
            continue;
        }
        
        $rank = $own['current_rank'];
        
        $card_q = $db->query("SELECT name, card_type, dice, execution_stat, effects_json, tags_json FROM {$prefix}game_cards WHERE id = {$c} LIMIT 1");
        $card = $db->fetch_array($card_q);
        if (!$card) {
            continue;
        }

        // Para armas de equipo: añadir el stat de escalado al dado si no está ya incluido
        if ($card['card_type'] === 'equipo' && !empty($card['dice']) && trim($card['dice']) !== '—') {
            $card_ef = json_decode($card['effects_json'] ?? '{}', true);
            if (($card_ef['equipo_type'] ?? '') === 'arma' && !empty($card['execution_stat'])) {
                $scale_stat = strtolower(trim($card['execution_stat']));
                if ($scale_stat !== '' && stripos($card['dice'], $scale_stat) === false) {
                    $card['dice'] = $card['dice'] . '+' . $scale_stat;
                }
            }
        }

        $roll_result = null;
        if ($card['card_type'] === 'npc_menor') {
            $effects = json_decode($card['effects_json'] ?? '{}', true);
            $npc_mascota_type = $effects['npc_mascota_type'] ?? 'npc';
            $acciones = $effects['acciones'] ?? [];
            if (is_string($acciones)) {
                $acciones = array_filter(array_map('trim', explode("\n", $acciones)));
            }
            if ($npc_mascota_type === 'npc') {
                if (is_array($acciones) && count($acciones) > 0) {
                    $picked = $acciones[array_rand($acciones)];
                    $roll_result = game_postcharacter_format_npc_action($picked, $stats);
                } else {
                    $roll_result = 'Acción básica de NPC';
                }
            } elseif ($npc_mascota_type === 'mascota') {
                if ($selected_action !== '') {
                    $picked = null;
                    if (is_array($acciones)) {
                        foreach ($acciones as $act) {
                            $act_name = is_array($act) ? ($act['name'] ?? '') : (string)$act;
                            if (strcasecmp(trim($act_name), $selected_action) === 0) {
                                $picked = $act;
                                break;
                            }
                        }
                    }
                    $roll_result = $picked !== null
                        ? game_postcharacter_format_npc_action($picked, $stats)
                        : game_postcharacter_format_npc_action($selected_action, $stats);
                } else {
                    $roll_result = 'Acción básica de Mascota';
                }
            }
        } elseif (!empty($card['dice']) && trim($card['dice']) !== '—') {
            $formula = $card['dice'];
            
            // Reemplazar [ARMA] con las fórmulas de armas seleccionadas
            if (strpos($formula, '[ARMA]') !== false) {
                $weapon_formulas = [];
                foreach ($selected_weapons as $w_id) {
                    if ($w_id <= 0) continue;
                    // Verificar que el personaje tiene el arma
                    $w_own_q = $db->query("SELECT 1 FROM {$prefix}game_character_cards WHERE character_id = {$cid} AND card_id = {$w_id} LIMIT 1");
                    if ($db->num_rows($w_own_q) > 0) {
                        $w_card_q = $db->query("SELECT dice FROM {$prefix}game_cards WHERE id = {$w_id} LIMIT 1");
                        if ($w_card = $db->fetch_array($w_card_q)) {
                            $w_dice = trim($w_card['dice']);
                            if ($w_dice !== '' && $w_dice !== '—') {
                                $weapon_formulas[] = preg_replace('/\[.*?\]$/', '', $w_dice); // Limpiar tags
                            }
                        }
                    }
                }
                
                $w_idx = 0;
                while (strpos($formula, '[ARMA]') !== false) {
                    $replacement = isset($weapon_formulas[$w_idx]) ? $weapon_formulas[$w_idx] : '0';
                    $pos = strpos($formula, '[ARMA]');
                    $formula = substr_replace($formula, $replacement, $pos, strlen('[ARMA]'));
                    $w_idx++;
                }
            }
            
            // Reemplazar [MUNICION] con las fórmulas de munición seleccionadas
            if (strpos($formula, '[MUNICION]') !== false) {
                $ammo_formulas = [];
                foreach ($selected_ammo as $a_id) {
                    if ($a_id <= 0) continue;
                    // Verificar que el personaje tiene la munición
                    $a_own_q = $db->query("SELECT 1 FROM {$prefix}game_character_cards WHERE character_id = {$cid} AND card_id = {$a_id} LIMIT 1");
                    if ($db->num_rows($a_own_q) > 0) {
                        $a_card_q = $db->query("SELECT dice FROM {$prefix}game_cards WHERE id = {$a_id} LIMIT 1");
                        if ($a_card = $db->fetch_array($a_card_q)) {
                            $a_dice = trim($a_card['dice']);
                            if ($a_dice !== '' && $a_dice !== '—') {
                                $ammo_formulas[] = preg_replace('/\[.*?\]$/', '', $a_dice); // Limpiar tags
                            }
                        }
                    }
                }
                
                $a_idx = 0;
                while (strpos($formula, '[MUNICION]') !== false) {
                    $replacement = isset($ammo_formulas[$a_idx]) ? $ammo_formulas[$a_idx] : '0';
                    $pos = strpos($formula, '[MUNICION]');
                    $formula = substr_replace($formula, $replacement, $pos, strlen('[MUNICION]'));
                    $a_idx++;
                }
            }
            
            try {
                $evaluated = game_evaluate_dice_roll($formula, $stats);
                $roll_result = $db->escape_string($evaluated);
            } catch (Throwable $t) {
            }
        }
        
        $insert = [
            'post_id' => $pid,
            'character_id' => $cid,
            'card_id' => $c,
            'played_rank' => $rank,
            'roll_result' => $roll_result ?: ''
        ];
        
        // Construir la consulta manualmente para pasar hide_errors = 1 a write_query
        $fields = [];
        $values = [];
        foreach ($insert as $key => $val) {
            $fields[] = "`" . $db->escape_string($key) . "`";
            $values[] = "'" . $db->escape_string((string)$val) . "'";
        }
        $fields_str = implode(',', $fields);
        $values_str = implode(',', $values);
        $sql = "INSERT INTO {$prefix}game_post_cards ({$fields_str}) VALUES ({$values_str})";
        
        try {
            $db->write_query($sql, 1);
        } catch (Throwable $t) {
        }

        // Decrementar cantidad para consumibles jugados como carta principal
        if (game_postcharacter_is_consumible_card($card)) {
            game_postcharacter_decrement_consumible($cid, $c);
        }

        // Decrementar munición/consumibles usados como adjunto [MUNICION]
        $ammo_used = array_unique(array_filter(array_map('intval', $selected_ammo)));
        foreach ($ammo_used as $a_id) {
            if ($a_id <= 0 || $a_id === $c) {
                continue;
            }
            $a_q = $db->query("SELECT card_type, effects_json, tags_json FROM {$prefix}game_cards WHERE id = {$a_id} LIMIT 1");
            $a_card = $db->fetch_array($a_q);
            if (!$a_card) {
                continue;
            }
            $a_own = $db->query("SELECT 1 FROM {$prefix}game_character_cards WHERE character_id = {$cid} AND card_id = {$a_id} LIMIT 1");
            if (!$db->num_rows($a_own)) {
                continue;
            }
            if (game_postcharacter_is_consumible_card($a_card)) {
                game_postcharacter_decrement_consumible($cid, $a_id);
            }
        }

    }
}

function game_postcharacter_block_edit() {
    global $mybb, $db;
    $pid = (int)($mybb->get_input('pid', MyBB::INPUT_INT));
    if ($pid > 0) {
        $prefix = TABLE_PREFIX;
        // Check if this post has cards with dice rolls
        $q = $db->query("SELECT id FROM {$prefix}game_post_cards WHERE post_id = {$pid} AND roll_result != '' LIMIT 1");
        if ($db->num_rows($q) > 0) {
            error("Este mensaje contiene tiradas de dados y no puede ser editado.");
        }
    }
}

function game_postcharacter_block_ajax_edit() {
    global $mybb, $db;
    $pid = (int)($mybb->get_input('pid', MyBB::INPUT_INT));
    if ($pid > 0) {
        $prefix = TABLE_PREFIX;
        // Check if this post has cards with dice rolls
        $q = $db->query("SELECT id FROM {$prefix}game_post_cards WHERE post_id = {$pid} AND roll_result != '' LIMIT 1");
        if ($db->num_rows($q) > 0) {
            xmlhttp_error("Este mensaje contiene tiradas de dados y no puede ser editado.");
        }
    }
}

function game_postcharacter_save_post($dh) {
    if (!isset($dh->pid) || !isset($dh->data['uid'])) return;
    global $db;
    $prefix = TABLE_PREFIX;
    $uid = (int)$dh->data['uid'];
    if ($uid <= 0) return;
    $cfg = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
    $row = $db->fetch_array($cfg);
    if (!$row || !$row['active_pj_id']) return;
    $pid = (int)$dh->pid;
    $cid = (int)$row['active_pj_id'];
    $db->write_query("INSERT IGNORE INTO {$prefix}game_post_characters (post_id, user_id, character_id) VALUES ({$pid}, {$uid}, {$cid})");
    
    // Increment character post count
    $db->write_query("UPDATE {$prefix}game_personajes SET postnum = postnum + 1 WHERE id = {$cid}");

    // Notify thread author (if replying to someone else's thread)
    if (isset($dh->data['tid']) && (int)$dh->data['tid'] > 0) {
        $tid = (int)$dh->data['tid'];
        $thread_q = $db->query("SELECT uid, subject FROM {$prefix}threads WHERE tid = {$tid} LIMIT 1");
        $thread = $db->fetch_array($thread_q);
        if ($thread && (int)$thread['uid'] !== $uid) {
            $char_name_q = $db->query("SELECT name FROM {$prefix}game_personajes WHERE id = {$cid} LIMIT 1");
            $char_name_row = $db->fetch_array($char_name_q);
            $char_name = $char_name_row ? $char_name_row['name'] : 'Alguien';
            $subject = $thread['subject'];
            $bb = '';
            global $mybb;
            if (isset($mybb) && isset($mybb->settings['bburl'])) $bb = $mybb->settings['bburl'];
            $link = rtrim($bb, '/') . "/showthread.php?tid={$tid}&pid={$pid}#pid{$pid}";
            game_create_notification(
                (int)$thread['uid'],
                'role_reply',
                "{$char_name} respondió en «{$subject}»",
                '',
                $link
            );
        }
    }
    
    // Process cards if any
    game_postcharacter_process_cards($pid, $cid);

    if (isset($dh->data['tid']) && (int)$dh->data['tid'] > 0) {
        game_postcharacter_save_thread_state((int)$dh->data['tid'], $cid, $pid);
    }
}

function game_postcharacter_save_thread($dh) {
    if (!isset($dh->pid) || !isset($dh->data['uid']) || !isset($dh->tid)) return;
    global $db;
    $prefix = TABLE_PREFIX;
    $uid = (int)$dh->data['uid'];
    if ($uid <= 0) return;
    $cfg = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
    $row = $db->fetch_array($cfg);
    if (!$row || !$row['active_pj_id']) return;
    $pid = (int)$dh->pid;
    $tid = (int)$dh->tid;
    $cid = (int)$row['active_pj_id'];
    $db->write_query("INSERT IGNORE INTO {$prefix}game_post_characters (post_id, thread_id, user_id, character_id) VALUES ({$pid}, {$tid}, {$uid}, {$cid})");
    
    // Increment character post count and thread count
    $db->write_query("UPDATE {$prefix}game_personajes SET postnum = postnum + 1, threadnum = threadnum + 1 WHERE id = {$cid}");

    // Save thread type and in-game date from POST (set when creating a thread)
    if (isset($_POST['game_thread_type'])) {
        $allowed_types = ['Pasado','Presente','Mision','Evento','Trama','Fic','Off_Rol'];
        $type = in_array($_POST['game_thread_type'], $allowed_types) ? $_POST['game_thread_type'] : 'Presente';
        
        if ($type === 'Presente') {
            $epoch = strtotime('2026-05-01');
            $now = time();
            $diff_seconds = max(0, $now - $epoch);
            $diff_days_float = $diff_seconds / 86400;
            $rol_days = floor($diff_days_float * 1.5) + 1;
            
            $days_per_season = 65;
            $days_per_year = $days_per_season * 4;
            
            $year = floor(($rol_days - 1) / $days_per_year) + 1;
            $day_of_year = (($rol_days - 1) % $days_per_year) + 1;
            $season = floor(($day_of_year - 1) / $days_per_season);
            $day = (($day_of_year - 1) % $days_per_season) + 1;
        } else {
            $day = max(1, min(100, (int)($_POST['game_day'] ?? 1)));
            $season = max(0, min(3, (int)($_POST['game_season'] ?? 0)));
            $year = max(1, (int)($_POST['game_year'] ?? 1));
        }
        
        $db->write_query("INSERT INTO {$prefix}game_thread_meta (thread_id, thread_type, day, season, year)
            VALUES ({$tid}, '{$db->escape_string($type)}', {$day}, {$season}, {$year})
            ON DUPLICATE KEY UPDATE thread_type='{$db->escape_string($type)}', day={$day}, season={$season}, year={$year}");
    }
    
    // Process cards if any
    game_postcharacter_process_cards($pid, $cid);

    game_postcharacter_save_thread_state($tid, $cid, $pid);
}

function game_postcharacter_delete_post($pid) {
    global $db;
    $prefix = TABLE_PREFIX;
    $pid = (int)$pid;
    if ($pid <= 0) return $pid;
    
    $query = $db->query("SELECT character_id FROM {$prefix}game_post_characters WHERE post_id = {$pid} LIMIT 1");
    $row = $db->fetch_array($query);
    if ($row && $row['character_id']) {
        $cid = (int)$row['character_id'];
        $db->write_query("UPDATE {$prefix}game_personajes SET postnum = GREATEST(0, postnum - 1) WHERE id = {$cid}");
    }
    return $pid;
}

function game_postcharacter_delete_thread($tid) {
    global $db;
    $prefix = TABLE_PREFIX;
    $tid = (int)$tid;
    if ($tid <= 0) return $tid;
    
    // Decrement threadnum for the author (the one who created thread_id)
    $q_thread = $db->query("SELECT character_id FROM {$prefix}game_post_characters WHERE thread_id = {$tid} LIMIT 1");
    $author = $db->fetch_array($q_thread);
    if ($author && $author['character_id']) {
        $cid = (int)$author['character_id'];
        $db->write_query("UPDATE {$prefix}game_personajes SET threadnum = GREATEST(0, threadnum - 1) WHERE id = {$cid}");
    }
    
    // Decrement postnum for everyone who posted in this thread
    // Note: MyBB's delete_thread doesn't call delete_post for each post individually for performance,
    // so we need to find all posts in this thread and decrement the respective character's postnum.
    // game_post_characters maps post_id -> character_id.
    // We must join with MyBB's posts table to get the posts in this thread before they are deleted.
    $q_posts = $db->query("
        SELECT gpc.character_id, COUNT(*) as post_count
        FROM {$prefix}posts p
        JOIN {$prefix}game_post_characters gpc ON p.pid = gpc.post_id
        WHERE p.tid = {$tid}
        GROUP BY gpc.character_id
    ");
    
    while ($r = $db->fetch_array($q_posts)) {
        $cid = (int)$r['character_id'];
        $count = (int)$r['post_count'];
        $db->write_query("UPDATE {$prefix}game_personajes SET postnum = GREATEST(0, postnum - {$count}) WHERE id = {$cid}");
    }
    
    return $tid;
}

function game_postcharacter_global_date() {
    global $mybb;
    $mybb->settings['game_rol_header_html'] = '';
    if (!defined('THIS_SCRIPT') || THIS_SCRIPT !== 'index') return;
    $epoch = strtotime('2026-05-01');
    $now = time();
    $diff_days = max(0, floor(($now - $epoch) / 86400));
    $rol_days = ($diff_days * 2) + 1;
    $rol_year = floor(($rol_days - 1) / 400) + 1;
    $day_of_year = (($rol_days - 1) % 400) + 1;
    $season_idx = floor(($day_of_year - 1) / 100);
    $rol_day = (($day_of_year - 1) % 100) + 1;
    $seasons_names = ['Primavera', 'Verano', 'Otoño', 'Invierno'];
    $current_season = $seasons_names[$season_idx] ?? 'Desconocida';
    $date_full = "Día {$rol_day} de {$current_season}, Año {$rol_year}";
    $mybb->settings['game_rol_header_html'] = '
    <div class="game-hero-date">
        <div class="game-hero-date-inner">
            <i class="fas fa-sun" style="color: #f59e0b; font-size: 18px;"></i>
            <span class="game-hero-date-text">' . $date_full . '</span>
            <span class="game-hero-date-label">CRONOLOGÍA MUNDIAL</span>
        </div>
    </div>';
}

/**
 * Crea una notificación en la base de datos.
 * Llamable desde cualquier hook o script admin.
 */
function game_create_notification(int $userId, string $type, string $title, string $body = '', string $link = '', ?int $characterId = null): void {
    global $db;
    $prefix = TABLE_PREFIX;
    $cid = $characterId ? (int)$characterId : 'NULL';
    $db->write_query(
        "INSERT INTO {$prefix}game_notifications (user_id, character_id, type, title, body, link)
         VALUES ({$userId}, {$cid}, '{$db->escape_string($type)}', '{$db->escape_string($title)}', '{$db->escape_string($body)}', '{$db->escape_string($link)}')"
    );
}

function game_evaluate_dice_roll(string $formula, array $stats): string {
    $original_formula = trim($formula);
    if ($original_formula === '' || $original_formula === '—') {
        return '';
    }
    
    // 1. Extract bracketed tags at the end (e.g. [FUEGO], [AGUA], etc.)
    $tag = '';
    if (preg_match('/\[(.*?)\]$/', $original_formula, $tag_matches)) {
        $tag = trim($tag_matches[1]);
        $formula_no_tag = trim(substr($original_formula, 0, -strlen($tag_matches[0])));
    } else {
        $formula_no_tag = $original_formula;
    }
    
    // Clean spaces and make lowercase for parsing
    $clean_formula = str_replace(' ', '', strtolower($formula_no_tag));
    
    // 2. Tokenize the formula by splitting on + or - signs, keeping delimiters
    $tokens = preg_split('/([+-])/', $clean_formula, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    
    $total = 0;
    $sign = 1;
    $details = [];
    
    foreach ($tokens as $token) {
        if ($token === '+') {
            $sign = 1;
        } elseif ($token === '-') {
            $sign = -1;
        } else {
            // Is it a dice notation? (e.g. "2d8", "1d6")
            if (preg_match('/^(\d+)d(\d+)$/', $token, $dice_matches)) {
                $num = (int)$dice_matches[1];
                $faces = (int)$dice_matches[2];
                if ($num > 100) $num = 100; // safety cap
                if ($faces > 1000) $faces = 1000;
                
                $rolls = [];
                $sum = 0;
                for ($i = 0; $i < $num; $i++) {
                    $r = mt_rand(1, $faces);
                    $rolls[] = $r;
                    $sum += $r;
                }
                
                $total += $sum * $sign;
                $prefix = ($sign < 0) ? '- ' : '';
                if ($sign > 0 && empty($details)) {
                    $prefix = '';
                } elseif ($sign > 0) {
                    $prefix = '+ ';
                }
                $details[] = $prefix . "{$num}d{$faces} (" . implode(' + ', $rolls) . ")";
                
            } elseif (is_numeric($token)) {
                // Is it a constant number?
                $val = (int)$token;
                $total += $val * $sign;
                
                $prefix = ($sign < 0) ? '- ' : '';
                if ($sign > 0 && empty($details)) {
                    $prefix = '';
                } elseif ($sign > 0) {
                    $prefix = '+ ';
                }
                $details[] = $prefix . $val;
                
            } else {
                // Check if it has a multiplier or divisor
                $stat_name = '';
                $multiplier = 1.0;
                $divisor = 1.0;
                $val = 0;
                $label = '';
                
                if (preg_match('/^([\d.]+)\*([a-z_]+)$/', $token, $m)) {
                    $multiplier = (float)$m[1];
                    $stat_name = $m[2];
                    $label = $multiplier . "*" . strtoupper($stat_name);
                } elseif (preg_match('/^([a-z_]+)\*([\d.]+)$/', $token, $m)) {
                    $stat_name = $m[1];
                    $multiplier = (float)$m[2];
                    $label = strtoupper($stat_name) . "*" . $multiplier;
                } elseif (preg_match('/^([a-z_]+)\/([\d.]+)$/', $token, $m)) {
                    $stat_name = $m[1];
                    $divisor = (float)$m[2];
                    $label = strtoupper($stat_name) . "/" . $divisor;
                } else {
                    $stat_name = $token;
                    $label = strtoupper($stat_name);
                }
                
                // Map legacy stats to new stats
                $mapped_name = $stat_name;
                if ($stat_name === 'str') $mapped_name = 'fue';
                elseif ($stat_name === 'res') $mapped_name = 'des';
                elseif ($stat_name === 'vol') $mapped_name = 'esp';
                
                $stat_val = (int)($stats[$mapped_name] ?? $stats[$stat_name] ?? 0);
                
                if ($divisor != 0) {
                    $val = (int)floor(($stat_val * $multiplier) / $divisor);
                } else {
                    $val = 0;
                }
                
                $total += $val * $sign;
                
                $prefix = ($sign < 0) ? '- ' : '';
                if ($sign > 0 && empty($details)) {
                    $prefix = '';
                } elseif ($sign > 0) {
                    $prefix = '+ ';
                }
                $details[] = $prefix . $val . " (" . $label . ")";
            }
            
            // Reset sign
            $sign = 1;
        }
    }
    
    $detail_str = implode(' ', $details);
    $tag_suffix = ($tag !== '') ? " [" . $tag . "]" : '';

    return $detail_str . " = " . $total . $tag_suffix;
}

/**
 * Formatea y evalúa una acción de NPC/mascota (string legacy u objeto {name,dice,stat}).
 */
function game_postcharacter_format_npc_action($action, array $stats): string
{
    if (is_array($action)) {
        $name = trim((string)($action['name'] ?? 'Acción'));
        $dice = trim((string)($action['dice'] ?? ''));
        $stat = trim((string)($action['stat'] ?? ''));
        if ($dice !== '') {
            $formula = $dice . ($stat !== '' ? '+' . $stat : '');
            try {
                $evaluated = game_evaluate_dice_roll($formula, $stats);
                return $name . ': ' . $evaluated;
            } catch (Throwable $t) {
                return $name;
            }
        }
        return $name;
    }
    $text = trim((string)$action);
    if ($text === '') {
        return 'Acción básica';
    }
    if (preg_match('/\d+d\d+/i', $text)) {
        return game_evaluate_dice_in_action($text, $stats);
    }
    return $text;
}

/**
 * Detecta notación de dados dentro del texto de una acción de NPC/mascota,
 * evalúa la tirada y devuelve el texto con el resultado appended.
 * Formato esperado: "Texto descriptivo: 1d6 + DES" o "1d6+fue"
 */
function game_evaluate_dice_in_action(string $action_text, array $stats): string {
    if (!preg_match('/\d+d\d+/i', $action_text)) {
        return $action_text;
    }

    // Intentar extraer la fórmula: parte después del último ":" o "–" / "—"
    $formula = '';
    if (preg_match('/[:\-–—]\s*(\d.+)$/u', $action_text, $m)) {
        $formula = trim($m[1]);
    } elseif (preg_match('/(\d+d\d+(?:\s*[+\-]\s*(?:\d+d\d+|\d+|[a-z_]+))*)\s*$/i', $action_text, $m)) {
        $formula = trim($m[1]);
    }

    if ($formula === '') {
        return $action_text;
    }

    // Limpiar puntuación al final
    $formula = rtrim($formula, '.,!;:)');

    try {
        $evaluated = game_evaluate_dice_roll($formula, $stats);
        return $action_text . "\n→ " . $evaluated;
    } catch (Throwable $t) {
        return $action_text;
    }
}
