<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

global $mybb, $db;

$uid = (int)($mybb->user['uid'] ?? 0);
if (!$uid) {
    echo json_encode(['ok' => false, 'error' => ['code' => 401, 'message' => 'No autorizado.']]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['ok' => false, 'error' => ['code' => 405, 'message' => 'Method not allowed']]);
    exit;
}

$character_id = isset($_GET['character_id']) ? (int)$_GET['character_id'] : 0;

if ($character_id <= 0) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'Parámetros inválidos.']]);
    exit;
}

$prefix = TABLE_PREFIX;

// Verify character owner
$char_q = $db->query("SELECT user_id FROM {$prefix}game_personajes WHERE id = {$character_id} LIMIT 1");
$character = $db->fetch_array($char_q);

if (!$character) {
    echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'Personaje no encontrado.']]);
    exit;
}

// Player must own character to fetch private requests
if ((int)$character['user_id'] !== $uid) {
    echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'No autorizado.']]);
    exit;
}

// Fetch all requests
$query = $db->query("
    SELECT r.*, c.name as catalog_card_name, c.card_type as catalog_card_type, c.image_url as catalog_image_url
    FROM {$prefix}game_card_requests r
    LEFT JOIN {$prefix}game_cards c ON r.card_id = c.id
    WHERE r.character_id = {$character_id}
    ORDER BY r.created_at DESC
");

$requests = [];
while ($row = $db->fetch_array($query)) {
    // Decode JSON structures
    $row['card_details'] = !empty($row['card_details_json']) ? json_decode($row['card_details_json'], true) : null;
    $row['discussion'] = !empty($row['discussion_json']) ? json_decode($row['discussion_json'], true) : [];
    unset($row['card_details_json'], $row['discussion_json']);
    
    // Resolve card name and type
    if ($row['request_type'] === 'create' && $row['card_details']) {
        $row['resolved_card_name'] = $row['card_details']['name'] ?? 'Carta Personalizada';
        $row['resolved_card_type'] = $row['card_details']['card_type'] ?? 'tecnica';
    } else {
        $row['resolved_card_name'] = $row['catalog_card_name'] ?? 'Carta Desconocida';
        $row['resolved_card_type'] = $row['catalog_card_type'] ?? 'tecnica';
    }
    
    $requests[] = $row;
}

echo json_encode(['ok' => true, 'data' => $requests, 'error' => null]);
exit;
