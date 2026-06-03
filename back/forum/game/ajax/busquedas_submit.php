<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $mybb, $db;

$uid = GameAjax::requireLogin();
$prefix = TABLE_PREFIX;

$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$cid = $cfg ? (int)$cfg['active_pj_id'] : 0;

if ($cid <= 0) {
    GameAjax::fail(400, 'Debes tener un personaje activo para enviar una búsqueda');
}

GameAjax::requirePost();
GameAjax::requireCsrf($_POST);

$titulo = trim($_POST['titulo'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$imagen_url = trim($_POST['imagen_url'] ?? '');

if (strlen($titulo) < 3) {
    GameAjax::fail(400, 'El título es demasiado corto (mínimo 3 caracteres)');
}
if (strlen($descripcion) < 10) {
    GameAjax::fail(400, 'La descripción es demasiado corta (mínimo 10 caracteres)');
}

$titulo_esc = $db->escape_string($titulo);
$desc_esc = $db->escape_string($descripcion);
$img_esc = $db->escape_string($imagen_url);

$db->write_query("
    INSERT INTO {$prefix}game_busquedas (user_id, character_id, titulo, descripcion, imagen_url, status)
    VALUES ({$uid}, {$cid}, '{$titulo_esc}', '{$desc_esc}', '{$img_esc}', 'pendiente')
");

$staff_q = $db->query("SELECT user_id FROM {$prefix}game_personajes WHERE staff_level >= 2");
while ($staff_row = $db->fetch_array($staff_q)) {
    $staff_uid = (int)$staff_row['user_id'];
    if ($staff_uid !== $uid) {
        $pj_q = $db->query("SELECT name FROM {$prefix}game_personajes WHERE id = {$cid} LIMIT 1");
        $pj = $db->fetch_array($pj_q);
        $pj_name = $pj['name'] ?? 'Alguien';
        game_create_notification(
            $staff_uid,
            'busqueda_pendiente',
            "Nueva búsqueda de rol: «{$titulo}» por {$pj_name}",
            '',
            $mybb->settings['bburl'] . '/game/public/zona_staff_busquedas.php'
        );
    }
}

GameAjax::json(true, null);
