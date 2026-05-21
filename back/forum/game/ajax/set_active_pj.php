<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

$uid = (int)($mybb->user['uid'] ?? 0);
if ($uid === 0) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'data' => null, 'error' => ['code' => 'unauthorized', 'message' => 'Login required']]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$pj_id = isset($input['pj_id']) ? (int)$input['pj_id'] : 0;

if ($pj_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'data' => null, 'error' => ['code' => 'invalid_input', 'message' => 'pj_id requerido']]);
    exit;
}

$prefix = TABLE_PREFIX;

// Verify the character belongs to this user
$check = $db->query("SELECT id FROM {$prefix}game_personajes WHERE id = {$pj_id} AND user_id = {$uid} LIMIT 1");
if (!$db->num_rows($check)) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'data' => null, 'error' => ['code' => 'forbidden', 'message' => 'Ese personaje no te pertenece']]);
    exit;
}

// Ensure user_config exists
$db->write_query("INSERT INTO {$prefix}game_user_config (user_id, max_slots, slots_used, active_pj_id) VALUES ({$uid}, 1, 0, {$pj_id})
    ON DUPLICATE KEY UPDATE active_pj_id = {$pj_id}");

// Get character info for response
$pj_q = $db->query("SELECT id, name, race_name, avatar, banner, is_staff FROM {$prefix}game_personajes WHERE id = {$pj_id} LIMIT 1");
$pj = $db->fetch_array($pj_q);

function pj_img_url(string $path, string $bb): string {
    if ($path === '') return '';
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) return $path;
    return rtrim($bb, '/') . '/' . ltrim($path, '/');
}

$bb = $mybb->settings['bburl'];
$img = $pj['avatar'] ?: $pj['banner'];
$avatar = $img ? pj_img_url($img, $bb) : pj_img_url('images/game/personaje_banner.png', $bb);

header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'data' => [
        'pj_id' => $pj_id,
        'name' => $pj['name'],
        'race_name' => $pj['race_name'],
        'avatar' => $avatar,
        'is_staff' => (bool)$pj['is_staff'],
    ],
    'error' => null,
    'meta' => ['endpoint' => 'set_active_pj'],
]);
exit;
