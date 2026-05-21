<?php
if (!defined('IN_MYBB')) die('Direct access denined.');

function game_postcharacter_info() {
    return [
        'name'          => 'Game Post Character Linker',
        'description'   => 'Vincula cada post con el personaje activo en el momento de crearlo.',
        'website'       => '',
        'author'        => 'Game Module',
        'authorsite'    => '',
        'version'       => '1.0',
        'guid'          => '',
        'compatibility' => '18*',
    ];
}

$plugins->add_hook('datahandler_post_insert_post_end', 'game_postcharacter_save_post');
$plugins->add_hook('datahandler_post_insert_thread_end', 'game_postcharacter_save_thread');
$plugins->add_hook('class_moderation_delete_post_start', 'game_postcharacter_delete_post');
$plugins->add_hook('class_moderation_delete_thread_start', 'game_postcharacter_delete_thread');

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
