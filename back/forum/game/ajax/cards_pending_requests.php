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

$prefix = TABLE_PREFIX;

// Check staff level (Moderator level 2 or Administrator level 3)
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;
if ($active_pj_id > 0) {
    $pj_q = $db->query("SELECT staff_level FROM {$prefix}game_personajes WHERE id = {$active_pj_id} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    $staff_level = $pj ? (int)$pj['staff_level'] : 0;
}

if ($staff_level < 2) {
    echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'No tienes permisos de staff.']]);
    exit;
}

// Fetch pending requests
$query = $db->query("
    SELECT r.*, p.name as character_name, c.name as card_name, c.card_type, p.avatar as character_avatar
    FROM {$prefix}game_card_requests r
    JOIN {$prefix}game_personajes p ON r.character_id = p.id
    LEFT JOIN {$prefix}game_cards c ON r.card_id = c.id
    WHERE r.status IN ('pendiente', 'conforme')
    ORDER BY r.created_at ASC
");

$requests = [];
while ($row = $db->fetch_array($query)) {
    $requests[] = $row;
}

echo json_encode(['ok' => true, 'data' => $requests, 'error' => null]);
