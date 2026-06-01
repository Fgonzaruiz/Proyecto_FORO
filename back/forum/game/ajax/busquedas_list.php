<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

global $db, $mybb;
$prefix = TABLE_PREFIX;

$q = $db->query("
    SELECT b.id, b.titulo, b.descripcion, b.imagen_url, b.created_at,
           pj.name as pj_name, pj.avatar as pj_avatar, pj.id as pj_id
    FROM {$prefix}game_busquedas b
    LEFT JOIN {$prefix}game_personajes pj ON b.character_id = pj.id
    WHERE b.status = 'aprobada'
    ORDER BY b.updated_at DESC
    LIMIT 12
");

$list = [];
$bburl = $mybb->settings['bburl'];

while ($row = $db->fetch_array($q)) {
    $avatar = $row['pj_avatar'];
    if ($avatar && strpos($avatar, 'http') !== 0) {
        $avatar = rtrim($bburl, '/') . '/' . ltrim($avatar, '/');
    }
    if (!$avatar) $avatar = $bburl . '/images/default_avatar.png';

    $list[] = [
        'id'          => (int)$row['id'],
        'titulo'      => htmlspecialchars($row['titulo']),
        'descripcion' => htmlspecialchars($row['descripcion']),
        'imagen_url'  => htmlspecialchars($row['imagen_url'] ?? ''),
        'pj_name'     => htmlspecialchars($row['pj_name'] ?? 'Desconocido'),
        'pj_avatar'   => $avatar,
        'pj_link'     => $bburl . '/game/public/personaje.php?id=' . (int)$row['pj_id'],
        'pj_id'       => (int)$row['pj_id'],
        'date'        => date('d/m/Y', strtotime($row['created_at'])),
    ];
}

echo json_encode(['ok' => true, 'data' => $list, 'error' => null]);
exit;
