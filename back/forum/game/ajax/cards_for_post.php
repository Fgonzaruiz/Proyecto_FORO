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
    $row['reposo'] = isset($row['reposo']) ? (int)$row['reposo'] : 0;
    $row['duracion'] = isset($row['duracion']) ? (int)$row['duracion'] : 0;
    unset($row['tags_json'], $row['effects_json'], $row['upgrade_json']);
    $cards[] = $row;
}

$mods = [
    'pv_change' => 0,
    'pe_change' => 0,
    'stat_mods' => []
];

if ($db->table_exists('game_post_characters')) {
    $char_q = $db->query("
        SELECT pv_change, pe_change, modifiers_json 
        FROM {$prefix}game_post_characters 
        WHERE post_id = {$post_id} 
        LIMIT 1
    ");
    if ($char_row = $db->fetch_array($char_q)) {
        $mods['pv_change'] = (int)($char_row['pv_change'] ?? 0);
        $mods['pe_change'] = (int)($char_row['pe_change'] ?? 0);
        $decoded = json_decode($char_row['modifiers_json'] ?? '{}', true);
        if (is_array($decoded)) {
            $mods['stat_mods'] = $decoded;
        }
    }
}

echo json_encode([
    'ok' => true,
    'data' => $cards,
    'modifications' => $mods,
    'error' => null
]);
