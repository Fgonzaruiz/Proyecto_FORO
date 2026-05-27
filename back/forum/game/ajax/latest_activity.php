<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

global $db, $mybb;
$prefix = TABLE_PREFIX;

$log_file = __DIR__ . '/debug_latest.log';
file_put_contents($log_file, "[" . date('H:i:s') . "] Iniciando latest_activity.php\n", FILE_APPEND);

try {
    $data = [
        'latest_posts' => [],
        'active_missions' => [],
        'staff' => []
    ];

    function resolve_local_img($path, $bburl) {
        if (!$path) return $bburl . '/images/default_avatar.png';
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            return $path;
        }
        return rtrim($bburl, '/') . '/' . ltrim($path, '/');
    }

    file_put_contents($log_file, "[" . date('H:i:s') . "] Ejecutando q_latest\n", FILE_APPEND);
    // 1. Latest 10 threads with activity (visible to guests/registered)
    // We join with game_post_characters to get the character info for the thread author.
    $q_latest = $db->query("
        SELECT t.tid, t.subject, t.lastpost, p.character_id, pj.name as character_name, pj.avatar as character_avatar
        FROM {$prefix}threads t
        LEFT JOIN {$prefix}game_post_characters p ON t.tid = p.thread_id
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
            'character_avatar' => resolve_local_img($row['character_avatar'], $mybb->settings['bburl']),
            'time' => date('d/m/Y H:i', (int)$row['lastpost']),
            'link' => $mybb->settings['bburl'] . "/showthread.php?tid=" . (int)$row['tid'] . "&action=lastpost"
        ];
    }

    file_put_contents($log_file, "[" . date('H:i:s') . "] Ejecutando q_missions\n", FILE_APPEND);
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

    file_put_contents($log_file, "[" . date('H:i:s') . "] Ejecutando q_staff\n", FILE_APPEND);
    // 3. Staff members
    $q_staff = $db->query("
        SELECT id, name, avatar
        FROM {$prefix}game_personajes
        WHERE staff_level >= 1
        ORDER BY name ASC
    ");

    while ($row = $db->fetch_array($q_staff)) {
        $data['staff'][] = [
            'id' => (int)$row['id'],
            'name' => htmlspecialchars($row['name']),
            'avatar' => resolve_local_img($row['avatar'], $mybb->settings['bburl']),
            'link' => $mybb->settings['bburl'] . "/game/public/personaje.php?id=" . (int)$row['id']
        ];
    }

    file_put_contents($log_file, "[" . date('H:i:s') . "] Fin exitoso\n", FILE_APPEND);
    echo json_encode(['ok' => true, 'data' => $data, 'error' => null]);

} catch (Exception $e) {
    file_put_contents($log_file, "[" . date('H:i:s') . "] ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['ok' => false, 'data' => null, 'error' => $e->getMessage()]);
}
exit;
