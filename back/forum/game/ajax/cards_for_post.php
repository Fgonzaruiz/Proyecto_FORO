<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

global $db;

$post_id = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
if ($post_id <= 0) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'post_id inválido']]);
    exit;
}

$prefix = TABLE_PREFIX;
$query = $db->query("
    SELECT pc.played_rank, pc.roll_result, pc.played_at, c.* 
    FROM {$prefix}game_post_cards pc
    JOIN {$prefix}game_cards c ON pc.card_id = c.id
    WHERE pc.post_id = {$post_id}
    ORDER BY pc.id ASC
");

$cards = [];
while ($row = $db->fetch_array($query)) {
    // Override definition rank with the rank it had when played
    $row['rank'] = $row['played_rank'];
    unset($row['played_rank']);
    
    $row['tags'] = json_decode($row['tags_json'] ?? '[]', true);
    $row['effects'] = json_decode($row['effects_json'] ?? '{}', true);
    $row['upgrade'] = json_decode($row['upgrade_json'] ?? '{}', true);
    unset($row['tags_json'], $row['effects_json'], $row['upgrade_json']);
    $cards[] = $row;
}

echo json_encode(['ok' => true, 'data' => $cards, 'error' => null]);
