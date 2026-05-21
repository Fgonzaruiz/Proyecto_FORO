<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

global $mybb, $db;

$user_id = (int)($mybb->user['uid'] ?? 0);
if (!$user_id) {
    echo json_encode(['ok' => false, 'error' => ['code' => 401, 'message' => 'No autorizado.']]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => ['code' => 405, 'message' => 'Method not allowed']]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['pj_id']) || empty($input['type'])) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'Payload inválido.']]);
    exit;
}

$pj_id = (int)$input['pj_id'];
$type = $input['type']; // 'diario' or 'relacion'

$prefix = TABLE_PREFIX;
$query = $db->query("SELECT id, user_id, cronologia_json FROM {$prefix}game_personajes WHERE id = {$pj_id} LIMIT 1");
$char = $db->fetch_array($query);

if (!$char) {
    echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'Personaje no encontrado.']]);
    exit;
}

if ((int)$char['user_id'] !== $user_id) {
    echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'No es tu personaje.']]);
    exit;
}

$cronologia = !empty($char['cronologia_json']) ? json_decode($char['cronologia_json'], true) : ['diario' => [], 'relaciones' => []];

if ($type === 'diario') {
    $cronologia['diario'][] = [
        'id' => uniqid(),
        'day' => (int)($input['day'] ?? 1),
        'season' => (int)($input['season'] ?? 0),
        'year' => (int)($input['year'] ?? 1),
        'desc' => htmlspecialchars($input['desc'] ?? ''),
        'link' => htmlspecialchars($input['link'] ?? '')
    ];
} elseif ($type === 'relacion') {
    $is_npc = !empty($input['is_npc']);
    $cronologia['relaciones'][] = [
        'id' => uniqid(),
        'pj_id' => $is_npc ? null : (int)($input['pj_id'] ?? 0),
        'name' => htmlspecialchars($is_npc ? ($input['npc_name'] ?? '') : ($input['pj_name'] ?? '')),
        'relation' => htmlspecialchars($input['relation'] ?? ''),
        'desc' => htmlspecialchars($input['desc'] ?? ''),
        'image' => htmlspecialchars($input['image'] ?? ''),
        'is_npc' => $is_npc
    ];
}

$new_json = $db->escape_string(json_encode($cronologia, JSON_UNESCAPED_UNICODE));
$db->write_query("UPDATE {$prefix}game_personajes SET cronologia_json = '{$new_json}' WHERE id = {$pj_id}");

echo json_encode(['ok' => true, 'data' => ['success' => true], 'error' => null]);
