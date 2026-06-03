<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

$uid = (int)($mybb->user['uid'] ?? 0);
if ($uid === 0) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'data' => null, 'error' => ['code' => 'unauthorized', 'message' => 'Login required']]);
    exit;
}

$prefix = TABLE_PREFIX;

// Get user config
$cfg_q = $db->query("SELECT * FROM {$prefix}game_user_config WHERE user_id = {$uid}");
$cfg = $db->fetch_array($cfg_q);
if (!$cfg) {
    $db->write_query("INSERT INTO {$prefix}game_user_config (user_id, max_slots, slots_used) VALUES ({$uid}, 1, 0)");
    $cfg = ['max_slots' => 1, 'slots_used' => 0, 'active_pj_id' => null];
}

// Recalculate slots_used from actual non-deleted characters
$cnt_q = $db->query("SELECT COUNT(*) AS cnt FROM {$prefix}game_personajes WHERE user_id = {$uid} AND is_npc = 0");
$actual_used = (int)$db->fetch_field($cnt_q, 'cnt');
if ((int)($cfg['slots_used'] ?? 0) !== $actual_used) {
    $db->write_query("UPDATE {$prefix}game_user_config SET slots_used = {$actual_used} WHERE user_id = {$uid}");
    $cfg['slots_used'] = $actual_used;
}

// Check if user has an admin character (staff_level = 3)
$is_admin = false;
$check_admin_q = $db->query("SELECT COUNT(*) as cnt FROM {$prefix}game_personajes WHERE user_id = {$uid} AND staff_level = 3");
if ($db->fetch_field($check_admin_q, 'cnt') > 0) {
    $is_admin = true;
}

// Check if user has narrator characters
$narrator_pjs = [];
$narrator_pjs_q = $db->query("SELECT id FROM {$prefix}game_personajes WHERE user_id = {$uid} AND is_narrator = 1");
while ($row = $db->fetch_array($narrator_pjs_q)) {
    $narrator_pjs[] = (int)$row['id'];
}

// Get user's own standard characters
$chars_q = $db->query("SELECT id, name, race_name, occupation_name, avatar, banner, rango, tripulacion, is_staff, staff_level, is_npc, is_narrator FROM {$prefix}game_personajes WHERE user_id = {$uid} AND is_npc = 0 ORDER BY id ASC");
$chars_ids = [];
$chars_list = [];
while ($row = $db->fetch_array($chars_q)) {
    $chars_ids[] = (int)$row['id'];
    $chars_list[] = $row;
}

// Append the active character if it is not already in the list (e.g., if it is an NPC)
$active_id = $cfg['active_pj_id'] ? (int)$cfg['active_pj_id'] : null;
if ($active_id && !in_array($active_id, $chars_ids, true)) {
    $act_q = $db->query("SELECT id, name, race_name, occupation_name, avatar, banner, rango, tripulacion, is_staff, staff_level, is_npc, is_narrator FROM {$prefix}game_personajes WHERE id = {$active_id} LIMIT 1");
    $row = $db->fetch_array($act_q);
    if ($row) {
        $chars_list[] = $row;
    }
}

function pj_img_url(string $path, string $bb): string {
    if ($path === '') return '';
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) return $path;
    return rtrim($bb, '/') . '/' . ltrim($path, '/');
}

$bb = $mybb->settings['bburl'];
$chars = [];
$active_id = $cfg['active_pj_id'] ? (int)$cfg['active_pj_id'] : null;
foreach ($chars_list as $row) {
    $img = $row['avatar'] ?: $row['banner'];
    $chars[] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'race_name' => $row['race_name'],
        'occupation_name' => $row['occupation_name'],
        'avatar' => $img ? pj_img_url($img, $bb) : pj_img_url('images/game/personaje_banner.png', $bb),
        'rango' => $row['rango'],
        'tripulacion' => $row['tripulacion'],
        'is_staff' => (bool)$row['is_staff'],
        'staff_level' => (int)$row['staff_level'],
        'is_npc' => (bool)($row['is_npc'] ?? false),
        'is_narrator' => (bool)($row['is_narrator'] ?? false),
        'is_active' => (int)$row['id'] === $active_id,
    ];
}

header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'data' => [
        'chars' => $chars,
        'max_slots' => (int)$cfg['max_slots'],
        'slots_used' => (int)$cfg['slots_used'],
        'active_pj_id' => $active_id,
    ],
    'error' => null,
    'meta' => ['endpoint' => 'my_personajes'],
]);
exit;
