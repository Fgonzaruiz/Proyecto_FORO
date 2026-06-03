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
    SELECT b.id, b.titulo, b.descripcion, b.imagen_url, b.status, b.staff_nota, b.created_at,
           pj.name as pj_name, pj.avatar as pj_avatar, pj.id as pj_id
    FROM {$prefix}game_busquedas b
    LEFT JOIN {$prefix}game_personajes pj ON b.character_id = pj.id
    WHERE b.status = 'pendiente'
    ORDER BY b.created_at ASC
");

$list = [];
while ($row = $db->fetch_array($q)) {
    $avatar = $row['pj_avatar'];
    if ($avatar && strpos($avatar, 'http') !== 0) {
        $avatar = rtrim($bburl, '/') . '/' . ltrim($avatar, '/');
    }
    if (!$avatar) {
        $avatar = $bburl . '/images/default_avatar.png';
    }

    $list[] = [
        'id'          => (int)$row['id'],
        'titulo'      => htmlspecialchars($row['titulo']),
        'descripcion' => htmlspecialchars($row['descripcion']),
        'imagen_url'  => htmlspecialchars($row['imagen_url'] ?? ''),
        'pj_name'     => htmlspecialchars($row['pj_name'] ?? 'Desconocido'),
        'pj_avatar'   => $avatar,
        'pj_link'     => $bburl . '/game/public/personaje.php?id=' . (int)$row['pj_id'],
        'date'        => date('d/m/Y H:i', strtotime($row['created_at'])),
    ];
}

GameAjax::json(true, $list);
