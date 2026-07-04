<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $db;

$uid = GameAjax::requireLogin();
$prefix = TABLE_PREFIX;

$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;
if ($active_pj_id > 0) {
    $pj_q = $db->query("SELECT staff_level FROM {$prefix}game_personajes WHERE id = {$active_pj_id} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    $staff_level = (int)($pj['staff_level'] ?? 0);
}

if ($staff_level < 2) {
    GameAjax::fail(403, 'Sin permisos');
}

if (!$db->table_exists('game_admin_requests')) {
    GameAjax::json(true, ['requests' => []]);
}

$q = $db->query("
    SELECT r.*, p.name AS character_name, p.avatar AS character_avatar
    FROM {$prefix}game_admin_requests r
    JOIN {$prefix}game_personajes p ON r.character_id = p.id
    WHERE r.status = 'pendiente'
    ORDER BY r.created_at ASC
");

$requests = [];
while ($row = $db->fetch_array($q)) {
    $payload = !empty($row['payload_json']) ? json_decode($row['payload_json'], true) : null;
    $requests[] = [
        'id' => (int)$row['id'],
        'source' => $row['source'],
        'request_kind' => $row['request_kind'],
        'title' => $row['title'],
        'description' => $row['description'],
        'link' => $row['link'],
        'character_id' => (int)$row['character_id'],
        'character_name' => $row['character_name'],
        'character_avatar' => $row['character_avatar'] ?? '',
        'payload' => is_array($payload) ? $payload : null,
        'created_at' => $row['created_at'],
    ];
}

GameAjax::json(true, ['requests' => $requests, 'count' => count($requests)]);
