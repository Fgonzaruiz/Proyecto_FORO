<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

global $db, $mybb;
$prefix = TABLE_PREFIX;

try {
    $data = [
        'latest_posts' => [],
        'active_missions' => [],
        'staff' => [],
    ];

    $resolve_local_img = static function ($path, $bburl) {
        if (!$path) {
            return $bburl . '/images/default_avatar.png';
        }
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            return $path;
        }
        return rtrim($bburl, '/') . '/' . ltrim($path, '/');
    };

    $q_latest = $db->query("
        SELECT t.tid, t.subject, t.lastpost, 
               COALESCE(pj.name, pj_fallback.name) as character_name, 
               COALESCE(pj.avatar, pj_fallback.avatar) as character_avatar
        FROM {$prefix}threads t
        LEFT JOIN {$prefix}posts post ON post.tid = t.tid AND post.dateline = t.lastpost
        LEFT JOIN {$prefix}game_post_characters gpc ON gpc.post_id = post.pid
        LEFT JOIN {$prefix}game_personajes pj ON gpc.character_id = pj.id
        LEFT JOIN {$prefix}game_user_config guc ON guc.user_id = t.lastposteruid
        LEFT JOIN {$prefix}game_personajes pj_fallback ON guc.active_pj_id = pj_fallback.id
        WHERE t.visible = 1 AND t.closed != 1 AND (COALESCE(pj.name, pj_fallback.name) IS NULL OR COALESCE(pj.name, pj_fallback.name) NOT IN ('Narrador', 'STAFF'))
        ORDER BY t.lastpost DESC
        LIMIT 10
    ");

    while ($row = $db->fetch_array($q_latest)) {
        $data['latest_posts'][] = [
            'tid' => (int)$row['tid'],
            'subject' => htmlspecialchars($row['subject']),
            'character_name' => $row['character_name'] ? htmlspecialchars($row['character_name']) : 'Anónimo',
            'character_avatar' => $resolve_local_img($row['character_avatar'], $mybb->settings['bburl']),
            'time' => date('d/m/Y H:i', (int)$row['lastpost']),
            'link' => $mybb->settings['bburl'] . '/showthread.php?tid=' . (int)$row['tid'] . '&action=lastpost',
        ];
    }

    $q_missions = $db->query("
        SELECT t.tid, t.subject, tm.thread_type, tp.prefix as mybb_prefix
        FROM {$prefix}threads t
        LEFT JOIN {$prefix}game_thread_meta tm ON t.tid = tm.thread_id
        LEFT JOIN {$prefix}threadprefixes tp ON t.prefix = tp.pid
        WHERE t.visible = 1 AND t.closed != 1 
        ORDER BY t.lastpost DESC
        LIMIT 10
    ");

    while ($row = $db->fetch_array($q_missions)) {
        $typeStr = 'tp: ' . ($row['mybb_prefix'] ?: 'null') . ' | tm: ' . ($row['thread_type'] ?: 'null');
        $data['active_missions'][] = [
            'tid' => (int)$row['tid'],
            'subject' => htmlspecialchars($row['subject']),
            'type' => htmlspecialchars($typeStr),
            'link' => $mybb->settings['bburl'] . '/showthread.php?tid=' . (int)$row['tid'],
        ];
    }

    $q_staff = $db->query("
        SELECT id, name, avatar
        FROM {$prefix}game_personajes
        WHERE is_staff = 1 AND name NOT IN ('Narrador', 'STAFF')
        ORDER BY id ASC
        LIMIT 8
    ");

    while ($row = $db->fetch_array($q_staff)) {
        $data['staff'][] = [
            'id' => (int)$row['id'],
            'name' => htmlspecialchars($row['name']),
            'avatar' => $resolve_local_img($row['avatar'], $mybb->settings['bburl']),
            'link' => $mybb->settings['bburl'] . '/game/public/personaje.php?pj=' . (int)$row['id'],
        ];
    }

    echo json_encode(['ok' => true, 'data' => $data, 'error' => null]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'data' => null, 'error' => ['code' => 500, 'message' => 'Error al cargar actividad.']]);
}
exit;
