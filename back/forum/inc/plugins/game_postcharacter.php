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

function game_cards_debug_log($message) {
    // Depuración desactivada por solicitud del usuario
}

function game_postcharacter_process_cards($pid, $cid) {
    game_cards_debug_log("Iniciando game_postcharacter_process_cards para post=$pid, pj=$cid. POST[rpg_played_cards] = " . ($_POST['rpg_played_cards'] ?? 'NO ENVIADO'));
    
    if (empty($_POST['rpg_played_cards'])) {
        game_cards_debug_log("rpg_played_cards está vacío. Retornando.");
        return;
    }
    $card_ids = json_decode($_POST['rpg_played_cards'], true);
    if (!is_array($card_ids)) {
        game_cards_debug_log("rpg_played_cards no pudo ser decodificado como array. Valor: " . $_POST['rpg_played_cards']);
        return;
    }
    if (empty($card_ids)) {
        game_cards_debug_log("El array card_ids está vacío. Retornando.");
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
        game_cards_debug_log("Personaje encontrado: " . $pj['name']);
        $stats_decoded = json_decode($pj['stats_json'] ?? '{}', true);
        $stats = is_array($stats_decoded) ? $stats_decoded : [];
        if (!isset($stats['fue'])) $stats['fue'] = (int)($stats['str'] ?? 5);
        if (!isset($stats['agi'])) $stats['agi'] = 5;
        if (!isset($stats['des'])) $stats['des'] = (int)($stats['res'] ?? 5);
        if (!isset($stats['inst'])) $stats['inst'] = (int)($stats['vol'] ?? 5);
        if (!isset($stats['esp'])) $stats['esp'] = (int)($stats['vol'] ?? 5);
        if (!isset($stats['int'])) $stats['int'] = 5;
        game_cards_debug_log("Stats cargados: " . json_encode($stats));
    } else {
        game_cards_debug_log("ERROR: Personaje id=$cid no encontrado en la base de datos.");
    }
    
    foreach ($card_ids as $c) {
        $c = (int)$c;
        if ($c <= 0) {
            game_cards_debug_log("Ignorando card_id inválido ($c).");
            continue;
        }
        
        game_cards_debug_log("Procesando carta id=$c");
        
        $own_q = $db->query("SELECT current_rank FROM {$prefix}game_character_cards WHERE character_id = {$cid} AND card_id = {$c} LIMIT 1");
        $own = $db->fetch_array($own_q);
        if (!$own) {
            game_cards_debug_log("ERROR: El personaje pj=$cid no posee la carta card=$c.");
            continue;
        }
        
        $rank = $own['current_rank'];
        game_cards_debug_log("Rango de la carta para el personaje: $rank");
        
        $card_q = $db->query("SELECT name, dice FROM {$prefix}game_cards WHERE id = {$c} LIMIT 1");
        $card = $db->fetch_array($card_q);
        if (!$card) {
            game_cards_debug_log("ERROR: No existe definición de carta para id=$c.");
            continue;
        }
        
        $roll_result = null;
        if (!empty($card['dice']) && trim($card['dice']) !== '—') {
            game_cards_debug_log("Fórmula de dados detectada: " . $card['dice']);
            try {
                $evaluated = game_evaluate_dice_roll($card['dice'], $stats);
                $roll_result = $db->escape_string($evaluated);
                game_cards_debug_log("Tirada evaluada: " . $roll_result);
            } catch (Throwable $t) {
                game_cards_debug_log("ERROR evaluando dados para carta $c: " . $t->getMessage());
            }
        } else {
            game_cards_debug_log("La carta no requiere tirada de dados.");
        }
        
        $insert = [
            'post_id' => $pid,
            'character_id' => $cid,
            'card_id' => $c,
            'played_rank' => $rank,
            'roll_result' => $roll_result ?: ''
        ];
        
        game_cards_debug_log("Intentando insertar en game_post_cards: " . json_encode($insert));
        
        // Construir la consulta manualmente para pasar hide_errors = 1 a write_query,
        // ya que insert_query fuerza la finalización del script ante cualquier error de base de datos en MyBB.
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
            $inserted = $db->write_query($sql, 1);
            if ($inserted) {
                game_cards_debug_log("Inserción exitosa para carta=$c.");
            } else {
                $db_err = $db->error_string() ?: 'Desconocido (error_string vacío)';
                game_cards_debug_log("La inserción falló. MySQL Error: " . $db_err);
            }
        } catch (Throwable $t) {
            game_cards_debug_log("EXCEPCIÓN al insertar carta $c: " . $t->getMessage() . " en " . $t->getFile() . ":" . $t->getLine());
        }
    }
    game_cards_debug_log("Fin de game_postcharacter_process_cards.");
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
        $day = max(1, min(100, (int)($_POST['game_day'] ?? 1)));
        $season = max(0, min(3, (int)($_POST['game_season'] ?? 0)));
        $year = max(1, (int)($_POST['game_year'] ?? 1));
        $db->write_query("INSERT INTO {$prefix}game_thread_meta (thread_id, thread_type, day, season, year)
            VALUES ({$tid}, '{$db->escape_string($type)}', {$day}, {$season}, {$year})
            ON DUPLICATE KEY UPDATE thread_type='{$db->escape_string($type)}', day={$day}, season={$season}, year={$year}");
    }
    
    // Process cards if any
    game_postcharacter_process_cards($pid, $cid);
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
                // It must be a stat name! E.g. "agi", "fue", "des", etc.
                // Map legacy stats to new stats
                $stat_name = $token;
                $mapped_name = $stat_name;
                if ($stat_name === 'str') $mapped_name = 'fue';
                elseif ($stat_name === 'res') $mapped_name = 'des';
                elseif ($stat_name === 'vol') $mapped_name = 'esp';
                
                $stat_val = (int)($stats[$mapped_name] ?? $stats[$stat_name] ?? 0);
                $total += $stat_val * $sign;
                
                $prefix = ($sign < 0) ? '- ' : '';
                if ($sign > 0 && empty($details)) {
                    $prefix = '';
                } elseif ($sign > 0) {
                    $prefix = '+ ';
                }
                $details[] = $prefix . $stat_val . " (" . strtoupper($stat_name) . ")";
            }
            
            // Reset sign
            $sign = 1;
        }
    }
    
    $detail_str = implode(' ', $details);
    $tag_suffix = ($tag !== '') ? " [" . $tag . "]" : '';
    
    return $detail_str . " = " . $total . $tag_suffix;
}
