<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

global $mybb, $db;

$prefix = TABLE_PREFIX;

// Accept either ?thread_id=N or ?url=https://...&uid=N (uid optional)
$tid = (int)($_GET['thread_id'] ?? 0);
$url = $_GET['url'] ?? '';
$uid = (int)($mybb->user['uid'] ?? 0);

// Extract thread_id from URL if not directly given
if (!$tid && $url) {
    $parsed = parse_url($url);
    if ($parsed && isset($parsed['query'])) {
        parse_str($parsed['query'], $params);
        $tid = (int)($params['tid'] ?? 0);
    }
    // Also try matching /thread-(\d+)/ or tid=(\d+) as fallback
    if (!$tid && preg_match('/[?&]tid[=](\d+)/', $url, $m)) {
        $tid = (int)$m[1];
    }
}

if (!$tid) {
    echo json_encode(['ok' => false, 'data' => null, 'error' => ['code' => 400, 'message' => 'thread_id o url inválido.'], 'meta' => null]);
    exit;
}

// Fetch thread info
$tq = $db->query("SELECT tid, subject, uid FROM {$prefix}threads WHERE tid = {$tid} LIMIT 1");
$thread = $db->fetch_array($tq);
if (!$thread) {
    echo json_encode(['ok' => false, 'data' => null, 'error' => ['code' => 404, 'message' => 'Hilo no encontrado.'], 'meta' => null]);
    exit;
}

// Fetch thread meta (type + date)
$mq = $db->query("SELECT * FROM {$prefix}game_thread_meta WHERE thread_id = {$tid} LIMIT 1");
$meta = $db->fetch_array($mq);

// Fetch participants (characters involved in this thread)
$participants = [];
$pq = $db->query("
    SELECT DISTINCT gpc.character_id, p.name
    FROM {$prefix}game_post_characters gpc
    JOIN {$prefix}game_personajes p ON gpc.character_id = p.id
    WHERE gpc.thread_id = {$tid}
    ORDER BY gpc.character_id ASC
");
while ($p = $db->fetch_array($pq)) {
    $participants[] = [
        'pj_id' => (int)$p['character_id'],
        'name'  => $p['name']
    ];
}

$data = [
    'thread_id'    => $tid,
    'thread_name'  => $thread['subject'],
    'thread_uid'   => (int)$thread['uid'],
    'category'     => $meta ? $meta['thread_type'] : 'Presente',
    'day'          => $meta ? (int)$meta['day'] : 1,
    'season'       => $meta ? (int)$meta['season'] : 0,
    'year'         => $meta ? (int)$meta['year'] : 1,
    'participants' => $participants
];

echo json_encode(['ok' => true, 'data' => $data, 'error' => null, 'meta' => null]);
