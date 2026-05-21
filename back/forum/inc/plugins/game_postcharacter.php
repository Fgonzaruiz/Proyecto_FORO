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
}

function game_postcharacter_save_thread($dh) {
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
}
