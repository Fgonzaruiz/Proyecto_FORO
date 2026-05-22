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

$prefix = TABLE_PREFIX;

$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;

if ($active_pj_id > 0) {
    $pj_q = $db->query("SELECT staff_level, is_staff FROM {$prefix}game_personajes WHERE id = {$active_pj_id} AND user_id = {$uid} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    if ($pj && (int)$pj['is_staff']) {
        $staff_level = (int)$pj['staff_level'];
    }
}

if ($staff_level < 1) {
    echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'Permiso denegado.']]);
    exit;
}

$pj_id = (int)($mybb->get_input('pj', MyBB::INPUT_INT) ?? 0);
if (!$pj_id) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'ID de personaje requerido.']]);
    exit;
}

$query = "SELECT p.*, u.username
          FROM {$prefix}game_personajes p
          LEFT JOIN {$prefix}users u ON u.uid = p.user_id
          WHERE p.id = {$pj_id} LIMIT 1";

$result = $db->query($query);
$row = $db->fetch_array($result);

if (!$row) {
    echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'Personaje no encontrado.']]);
    exit;
}

// Parse data_json
$data_json = [];
if (!empty($row['data_json'])) {
    $parsed = json_decode($row['data_json'], true);
    if (is_array($parsed)) {
        $data_json = $parsed;
    }
}

// Parse stats_json
$stats = [];
if (!empty($row['stats_json'])) {
    $parsed = json_decode($row['stats_json'], true);
    if (is_array($parsed)) {
        $stats = $parsed;
    }
}

// Fallback: if stats_json empty but legacy columns exist
if (empty($stats)) {
    $stat_keys = ['FUE', 'AGI', 'RES', 'VOL'];
    $legacy_map = ['FUE' => 'stat_fp', 'AGI' => 'stat_dp', 'RES' => 'stat_rp', 'VOL' => 'stat_vp'];
    foreach ($stat_keys as $k) {
        $legacy_col = $legacy_map[$k] ?? '';
        if ($legacy_col && isset($row[$legacy_col])) {
            $stats[$k] = (int)$row[$legacy_col];
        }
    }
}

// Build info from data_json
$info = [];
if (!empty($data_json)) {
    $info['desc'] = $data_json['desc'] ?? ($row['desc'] ?? '');
    $info['details'] = $data_json['details'] ?? ($row['details'] ?? '');
    $info['edad'] = $data_json['edad'] ?? $data_json['age'] ?? '';
    $info['origen'] = $data_json['origen'] ?? $data_json['origin'] ?? '';
    $info['arquetipo'] = $data_json['arquetipo'] ?? $data_json['arquetipo_belico'] ?? '';
    $info['race'] = $data_json['race_name'] ?? $row['race_name'] ?? '';
    $info['pb'] = $data_json['pb'] ?? '';
    $info['physique'] = $data_json['physique'] ?? $data_json['apariencia_fisica'] ?? '';
    $info['psychology'] = $data_json['psychology'] ?? $data_json['perfil_psicologico'] ?? '';
    $info['extras'] = $data_json['extras'] ?? '';
} else {
    $info['desc'] = $row['desc'] ?? '';
    $info['details'] = $row['details'] ?? '';
}

echo json_encode([
    'ok' => true,
    'data' => [
        'id'              => (int)$row['id'],
        'user_id'         => (int)$row['user_id'],
        'name'            => $row['name'],
        'username'        => $row['username'] ?? 'Desconocido',
        'status'          => $row['status'] ?? 'pendiente',
        'avatar'          => $row['avatar'],
        'rango'           => $row['rango'],
        'faction'         => $row['faction'],
        'race_name'       => $row['race_name'],
        'occupation_name' => $row['occupation_name'],
        'stats'           => $stats,
        'info'            => $info,
    ],
    'error' => null
]);
exit;
