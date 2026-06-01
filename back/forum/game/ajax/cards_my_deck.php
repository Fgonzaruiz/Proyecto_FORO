<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

global $mybb, $db;

$uid = (int)($mybb->user['uid'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['ok' => false, 'error' => ['code' => 405, 'message' => 'Method not allowed']]);
    exit;
}

// Accept an optional character_id. If not provided, use the active character.
$char_id = $mybb->get_input('character_id', MyBB::INPUT_INT);
$prefix = TABLE_PREFIX;

if ($char_id <= 0) {
    if (!$uid) {
        echo json_encode(['ok' => false, 'error' => ['code' => 401, 'message' => 'No autorizado.']]);
        exit;
    }
    $cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
    $cfg = $db->fetch_array($cfg_q);
    $char_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
}

if ($char_id <= 0) {
    echo json_encode(['ok' => true, 'data' => [], 'error' => null]);
    exit;
}

$thread_id = $mybb->get_input('thread_id', MyBB::INPUT_INT);
$meta = null;

if ($thread_id > 0) {
    // Get all posts by this character in this thread to establish turn counts
    $posts_q = $db->query("
        SELECT p.pid
        FROM {$prefix}posts p
        JOIN {$prefix}game_post_characters gpc ON p.pid = gpc.post_id
        WHERE p.tid = {$thread_id} AND gpc.character_id = {$char_id}
        ORDER BY p.pid ASC
    ");
    
    $char_posts = [];
    $post_indices = [];
    $idx = 1;
    while ($p_row = $db->fetch_array($posts_q)) {
        $pid = (int)$p_row['pid'];
        $char_posts[] = $pid;
        $post_indices[$pid] = $idx++;
    }
    
    $total_posts = count($char_posts);
    $last_played_turns = [];
    
    if ($total_posts > 0) {
        $pids_str = implode(',', $char_posts);
        $played_q = $db->query("
            SELECT post_id, card_id
            FROM {$prefix}game_post_cards
            WHERE character_id = {$char_id} AND post_id IN ({$pids_str})
        ");
        
        while ($pl_row = $db->fetch_array($played_q)) {
            $pid = (int)$pl_row['post_id'];
            $cid = (int)$pl_row['card_id'];
            $turn = $post_indices[$pid] ?? 0;
            if ($turn > ($last_played_turns[$cid] ?? 0)) {
                $last_played_turns[$cid] = $turn;
            }
        }
    }
    
    $meta = [
        'total_posts' => $total_posts,
        'last_played_turns' => $last_played_turns
    ];
}

$query = $db->query("
    SELECT c.*, cc.current_rank 
    FROM {$prefix}game_character_cards cc
    JOIN {$prefix}game_cards c ON cc.card_id = c.id
    WHERE cc.character_id = {$char_id}
    ORDER BY c.card_type ASC, c.name ASC
");

$cards = [];
while ($row = $db->fetch_array($query)) {
    // Override the base rank with the character's current rank for this card
    $row['rank'] = $row['current_rank'];
    unset($row['current_rank']);
    
    $row['tags'] = json_decode($row['tags_json'] ?? '[]', true);
    $row['effects'] = json_decode($row['effects_json'] ?? '{}', true);
    $row['upgrade'] = json_decode($row['upgrade_json'] ?? '{}', true);
    $row['reposo'] = isset($row['reposo']) ? (int)$row['reposo'] : 0;
    $row['duracion'] = isset($row['duracion']) ? (int)$row['duracion'] : 0;
    unset($row['tags_json'], $row['effects_json'], $row['upgrade_json']);
    $cards[] = $row;
}

echo json_encode(['ok' => true, 'data' => $cards, 'error' => null, 'meta' => $meta]);
