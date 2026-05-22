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
