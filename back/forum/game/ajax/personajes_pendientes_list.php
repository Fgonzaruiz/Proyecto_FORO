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
    if ($pj) {
        $staff_level = (int)$pj['staff_level'];
    }
}

if ($staff_level < 1) {
    echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'Permiso denegado.']]);
    exit;
}

$filter = $mybb->get_input('filter', MyBB::INPUT_STRING);
$allowed_filters = ['pendiente', 'revision', 'aprobada', 'rechazada', ''];
if (!in_array($filter, $allowed_filters, true)) {
    $filter = '';
}

$where = '1=1';
if ($filter !== '') {
    $where = "p.status = '" . $db->escape_string($filter) . "'";
}

$query = "SELECT p.id, p.user_id, p.name, p.status, p.avatar, p.rango, p.faction, p.race_name,
                 p.occupation_name, u.username
          FROM {$prefix}game_personajes p
          LEFT JOIN {$prefix}users u ON u.uid = p.user_id
          WHERE {$where}
          ORDER BY FIELD(p.status, 'pendiente', 'revision', 'rechazada', 'aprobada'), p.id ASC";

$result = $db->query($query);

$chars = [];
while ($row = $db->fetch_array($result)) {
    $chars[] = [
        'id'            => (int)$row['id'],
        'user_id'       => (int)$row['user_id'],
        'name'          => $row['name'],
        'status'        => $row['status'],
        'avatar'        => $row['avatar'],
        'rango'         => $row['rango'],
        'faction'       => $row['faction'],
        'race_name'     => $row['race_name'],
        'occupation_name' => $row['occupation_name'],
        'username'      => $row['username'] ?? 'Desconocido',
    ];
}

echo json_encode([
    'ok'   => true,
    'data' => $chars,
    'meta' => ['total' => count($chars)],
    'error' => null
]);
exit;
