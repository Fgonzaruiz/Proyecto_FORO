<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $db, $mybb;

$uid = GameAjax::requireLogin();
$prefix = TABLE_PREFIX;

$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$cid = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;
if ($cid > 0) {
    $pj_q = $db->query("SELECT staff_level FROM {$prefix}game_personajes WHERE id = {$cid} AND user_id = {$uid} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    $staff_level = (int)($pj['staff_level'] ?? 0);
}

if ($staff_level < 2) {
    GameAjax::fail(403, 'Sin permisos');
}

$bburl = $mybb->settings['bburl'];

$q = $db->query("
    SELECT hp.id, hp.character_id, hp.haki_type, hp.nivel, hp.status, hp.pp_reservados, hp.updated_at,
           p.name AS character_name, p.avatar AS character_avatar
    FROM {$prefix}game_haki_progress hp
    JOIN {$prefix}game_personajes p ON hp.character_id = p.id
    WHERE hp.status = 'pendiente_subida'
    ORDER BY hp.updated_at ASC
");

$list = [];
while ($row = $db->fetch_array($q)) {
    $avatar = $row['character_avatar'];
    if ($avatar && strpos($avatar, 'http') !== 0) {
        $avatar = rtrim($bburl, '/') . '/' . ltrim($avatar, '/');
    }
    if (!$avatar) {
        $avatar = $bburl . '/images/default_avatar.png';
    }

    $list[] = [
        'id'               => (int)$row['id'],
        'character_id'     => (int)$row['character_id'],
        'character_name'   => htmlspecialchars($row['character_name']),
        'character_avatar' => $avatar,
        'haki_type'        => $row['haki_type'],
        'nivel_actual'     => (int)$row['nivel'],
        'nivel_siguiente'  => (int)$row['nivel'] + 1,
        'pp_reservados'    => (int)$row['pp_reservados'],
        'date'             => date('d/m/Y H:i', strtotime($row['updated_at'])),
    ];
}

GameAjax::json(true, $list);
