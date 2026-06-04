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

$thread_id = $mybb->get_input('thread_id', MyBB::INPUT_INT);
$char_id = $mybb->get_input('character_id', MyBB::INPUT_INT);
$prefix = TABLE_PREFIX;

if ($char_id <= 0) {
    $cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
    $cfg = $db->fetch_array($cfg_q);
    $char_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
}

if ($char_id <= 0) {
    echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'Sin personaje activo.']]);
    exit;
}

$pj_q = $db->query("SELECT stats_json, data_json FROM {$prefix}game_personajes WHERE id = {$char_id} LIMIT 1");
$pj = $db->fetch_array($pj_q);
if (!$pj) {
    echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'Personaje no encontrado.']]);
    exit;
}

$stats = json_decode($pj['stats_json'] ?? '{}', true);
if (!is_array($stats)) {
    $stats = [];
}
$data = json_decode($pj['data_json'] ?? '{}', true);
if (!is_array($data)) {
    $data = [];
}

$fue = (int)($stats['fue'] ?? $stats['str'] ?? 5);
$agi = (int)($stats['agi'] ?? 5);
$des = (int)($stats['des'] ?? $stats['res'] ?? 5);
$inst = (int)($stats['inst'] ?? $stats['vol'] ?? 5);
$esp = (int)($stats['esp'] ?? $stats['vol'] ?? 5);
$int = (int)($stats['int'] ?? 5);

$max_pv = ($fue * 4) + ($agi * 2) + ($esp * 3) + ($int * 1);
$max_pe = ($esp * 4) + ($des * 3) + ($agi * 2) + ($int * 1);

$mod_raza = (int)($data['modificadores_pa_raza'] ?? 0);
$mod_linaje = (int)($data['linaje']['modificadores_pa'] ?? 0);
$max_pa = 10 + intdiv($agi, 2) + $mod_raza + $mod_linaje;

$current_pv = $max_pv;
$current_pe = $max_pe;
$stat_mods = [];

if ($thread_id > 0 && $db->table_exists('game_thread_pj_state')) {
    $state_q = $db->query("
        SELECT current_pv, current_pe, stat_mods_json
        FROM {$prefix}game_thread_pj_state
        WHERE thread_id = {$thread_id} AND character_id = {$char_id}
        LIMIT 1
    ");
    $state = $db->fetch_array($state_q);
    if ($state) {
        $current_pv = (int)$state['current_pv'];
        $current_pe = (int)$state['current_pe'];
        $decoded = json_decode($state['stat_mods_json'] ?? '{}', true);
        if (is_array($decoded)) {
            $stat_mods = $decoded;
        }
    }
}

echo json_encode([
    'ok' => true,
    'data' => [
        'thread_id' => $thread_id,
        'character_id' => $char_id,
        'current_pv' => $current_pv,
        'current_pe' => $current_pe,
        'max_pv' => $max_pv,
        'max_pe' => $max_pe,
        'max_pa' => $max_pa,
        'stat_mods' => $stat_mods,
    ],
    'error' => null,
]);
