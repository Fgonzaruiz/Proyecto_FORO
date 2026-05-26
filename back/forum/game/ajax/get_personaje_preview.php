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

// Parse data_json (full wizard data)
$data = [];
if (!empty($row['data_json'])) {
    $parsed = json_decode($row['data_json'], true);
    if (is_array($parsed)) {
        $data = $parsed;
    }
}

// Parse stats_json
$stats_raw = [];
if (!empty($row['stats_json'])) {
    $parsed = json_decode($row['stats_json'], true);
    if (is_array($parsed)) {
        $stats_raw = $parsed;
    }
}

// Stats with legacy fallback
$stats = [
    'fue' => (int)($stats_raw['fue'] ?? $stats_raw['str'] ?? (isset($row['stat_fp']) ? $row['stat_fp'] : 5)),
    'agi' => (int)($stats_raw['agi'] ?? (isset($row['stat_dp']) ? $row['stat_dp'] : 5)),
    'des' => (int)($stats_raw['des'] ?? $stats_raw['res'] ?? (isset($row['stat_rp']) ? $row['stat_rp'] : 5)),
    'inst' => (int)($stats_raw['inst'] ?? $stats_raw['vol'] ?? (isset($row['stat_vp']) ? $row['stat_vp'] : 5)),
    'esp' => (int)($stats_raw['esp'] ?? $stats_raw['vol'] ?? (isset($row['stat_vp']) ? $row['stat_vp'] : 5)),
    'int' => (int)($stats_raw['int'] ?? (isset($row['stat_ip']) ? $row['stat_ip'] : 5)),
];

// Linaje (gene tree) from data_json
$linaje = [];
if (!empty($data['linaje'])) {
    $linaje = $data['linaje'];
}

// All bio fields
$bio_fields = [
    'age'        => $data['age'] ?? $data['edad'] ?? 'Desconocida',
    'origin'     => $data['origin'] ?? $data['origen'] ?? 'Desconocido',
    'race'       => $row['race_name'] ?: ($data['race'] ?? 'Desconocida'),
    'pb'         => $data['pb'] ?? 'Desconocido',
    'arquetipo'  => $data['arquetipo'] ?? $data['arquetipo_belico'] ?? 'Desconocido',
    'physique'   => $data['physique'] ?? $data['apariencia_fisica'] ?? '',
    'psychology' => $data['psychology'] ?? $data['perfil_psicologico'] ?? '',
    'extras'     => $data['extras'] ?? '',
    'desc'       => $row['desc'] ?? '',
    'details'    => $row['details'] ?? '',
];

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
        'is_staff'        => (bool)$row['is_staff'],
        'staff_level'     => (int)$row['staff_level'],
        'stats'           => $stats,
        'linaje'          => $linaje,
        'bio'             => $bio_fields,
    ],
    'error' => null
]);
exit;
