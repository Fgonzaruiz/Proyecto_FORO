<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

global $db, $mybb;
$prefix = TABLE_PREFIX;

$data = [
    'latest_posts' => [],
    'active_missions' => [],
    'staff' => []
];

// 1. Latest 10 threads with activity (visible to guests/registered)
// We join with game_post_characters to get the character info for the last post.
$q_latest = $db->query("
    SELECT t.tid, t.subject, t.lastpost, p.character_id, pj.nombre as character_name, pj.avatar as character_avatar
    FROM {$prefix}threads t
    LEFT JOIN {$prefix}game_post_characters p ON t.lastpostpid = p.post_id
    LEFT JOIN {$prefix}game_personajes pj ON p.character_id = pj.id
    WHERE t.visible = 1 AND t.closed != 1
    ORDER BY t.lastpost DESC
    LIMIT 10
");

while ($row = $db->fetch_array($q_latest)) {
    $data['latest_posts'][] = [
        'tid' => (int)$row['tid'],
        'subject' => htmlspecialchars($row['subject']),
        'character_name' => $row['character_name'] ? htmlspecialchars($row['character_name']) : 'Anónimo',
        'character_avatar' => $row['character_avatar'] ? pj_img_url($row['character_avatar']) : $mybb->settings['bburl'].'/images/default_avatar.png',
        'time' => date('d/m/Y H:i', (int)$row['lastpost']),
        'link' => $mybb->settings['bburl'] . "/showthread.php?tid=" . (int)$row['tid'] . "&action=lastpost"
    ];
}

// 2. Active Presente/Mision threads
$q_missions = $db->query("
    SELECT t.tid, t.subject, tm.thread_type
    FROM {$prefix}threads t
    INNER JOIN {$prefix}game_thread_meta tm ON t.tid = tm.thread_id
    WHERE t.visible = 1 AND t.closed != 1 AND tm.thread_type IN ('Presente', 'Mision')
    ORDER BY t.lastpost DESC
    LIMIT 10
");

while ($row = $db->fetch_array($q_missions)) {
    $data['active_missions'][] = [
        'tid' => (int)$row['tid'],
        'subject' => htmlspecialchars($row['subject']),
        'type' => htmlspecialchars($row['thread_type']),
        'link' => $mybb->settings['bburl'] . "/showthread.php?tid=" . (int)$row['tid']
    ];
}

// 3. Staff members
$q_staff = $db->query("
    SELECT id, nombre, avatar
    FROM {$prefix}game_personajes
    WHERE is_staff = 1
    ORDER BY nombre ASC
");

while ($row = $db->fetch_array($q_staff)) {
    $data['staff'][] = [
        'id' => (int)$row['id'],
        'name' => htmlspecialchars($row['nombre']),
        'avatar' => $row['avatar'] ? pj_img_url($row['avatar']) : $mybb->settings['bburl'].'/images/default_avatar.png',
        'link' => $mybb->settings['bburl'] . "/game/public/personaje.php?id=" . (int)$row['id']
    ];
}

echo json_encode(['ok' => true, 'data' => $data, 'error' => null]);
exit;
