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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => ['code' => 405, 'message' => 'Method not allowed']]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$character_id = (int)($input['character_id'] ?? 0);
$stat = trim((string)($input['stat'] ?? ''));
$amount = (int)($input['amount'] ?? 1);

$valid_stats = ['fue', 'agi', 'des', 'inst', 'esp', 'int'];

if ($character_id <= 0 || !in_array($stat, $valid_stats, true) || $amount <= 0) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'Parámetros inválidos.']]);
    exit;
}

$prefix = TABLE_PREFIX;

// Fetch character details
$char_q = $db->query("SELECT * FROM {$prefix}game_personajes WHERE id = {$character_id} LIMIT 1");
$character = $db->fetch_array($char_q);

if (!$character) {
    echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'Personaje no encontrado.']]);
    exit;
}

if ((int)$character['user_id'] !== $uid) {
    echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'No eres el propietario de este personaje.']]);
    exit;
}

if ($character['status'] !== 'aprobada') {
    echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'El personaje debe estar aprobado para realizar compras.']]);
    exit;
}

// Decode JSON fields
$data = !empty($character['data_json']) ? json_decode($character['data_json'], true) : [];
if (!is_array($data)) $data = [];

$stats = !empty($character['stats_json']) ? json_decode($character['stats_json'], true) : [];
if (!is_array($stats)) $stats = [];

// Determine current PP
$current_pp = 0;
if (isset($data['pp'])) {
    $current_pp = (int)$data['pp'];
} elseif (isset($data['linaje']['bonusPP'])) {
    $current_pp = (int)$data['linaje']['bonusPP'];
}

// 5 PP per stat point
$cost = $amount * 5;

if ($current_pp < $cost) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => "Puntos de Progresión (PP) insuficientes. Necesitas {$cost} PP, tienes {$current_pp} PP."]]);
    exit;
}

// Deduct PP
$new_pp = $current_pp - $cost;
$data['pp'] = $new_pp;
if (isset($data['linaje']) && is_array($data['linaje'])) {
    $data['linaje']['bonusPP'] = $new_pp;
}

// Ensure base stats structure is initialized
$stats['fue'] = (int)($stats['fue'] ?? $stats['str'] ?? $character['stat_fp'] ?? 5);
$stats['agi'] = (int)($stats['agi'] ?? $character['stat_dp'] ?? 5);
$stats['des'] = (int)($stats['des'] ?? $stats['res'] ?? $character['stat_rp'] ?? 5);
$stats['inst'] = (int)($stats['inst'] ?? $stats['vol'] ?? $character['stat_vp'] ?? 5);
$stats['esp'] = (int)($stats['esp'] ?? $stats['vol'] ?? $character['stat_vp'] ?? 5);
$stats['int'] = (int)($stats['int'] ?? $character['stat_ip'] ?? 5);

// Increment selected stat
$stats[$stat] += $amount;

// Update json fields
$data_json_esc = $db->escape_string(json_encode($data, JSON_UNESCAPED_UNICODE));
$stats_json_esc = $db->escape_string(json_encode($stats, JSON_UNESCAPED_UNICODE));

// Map stat keys to physical columns
$col_map = [
    'fue' => 'stat_fp',
    'agi' => 'stat_dp',
    'des' => 'stat_rp',
    'inst' => 'stat_vp',
    'esp' => 'stat_vp', // Note: Instinto and Espíritu historically share the same stat_vp or distinct fallback, let's keep stat_vp updated for both
    'int' => 'stat_ip',
];

$col_to_update = $col_map[$stat];
$new_val = $stats[$stat];

$db->write_query("
    UPDATE {$prefix}game_personajes 
    SET data_json = '{$data_json_esc}', 
        stats_json = '{$stats_json_esc}',
        {$col_to_update} = {$new_val}
    WHERE id = {$character_id}
");

echo json_encode([
    'ok' => true,
    'data' => [
        'new_pp' => $new_pp,
        'new_stats' => $stats
    ],
    'error' => null
]);
exit;
