<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $db;
$prefix = TABLE_PREFIX;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();

$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
if (!$cfg || !$cfg['active_pj_id']) {
    GameAjax::fail(400, 'Sin personaje activo');
}

$pj_q = $db->query("SELECT is_staff, staff_level FROM {$prefix}game_personajes WHERE id = " . (int)$cfg['active_pj_id'] . " AND user_id = {$uid} LIMIT 1");
$pj = $db->fetch_array($pj_q);
if (!$pj || !(int)$pj['is_staff'] || (int)$pj['staff_level'] < 3) {
    GameAjax::fail(403, 'Nivel de staff insuficiente');
}

$data = GameAjax::postJson();
GameAjax::requireCsrf($data);

$action = $data['action'] ?? '';

if ($action === 'create' || $action === 'edit') {
    $title = $db->escape_string(trim($data['title'] ?? ''));
    $content = $db->escape_string(trim($data['content'] ?? ''));

    if ($title === '' || $content === '') {
        GameAjax::fail(400, 'Título y contenido son obligatorios');
    }

    if ($action === 'create') {
        $db->write_query("
            INSERT INTO {$prefix}game_announcements (title, content, created_by, created_at, is_active)
            VALUES ('{$title}', '{$content}', {$uid}, CURRENT_TIMESTAMP, 1)
        ");
        GameAjax::json(true, null);
    }

    $id = (int)$data['id'];
    $db->write_query("
        UPDATE {$prefix}game_announcements
        SET title = '{$title}', content = '{$content}'
        WHERE id = {$id}
    ");
    GameAjax::json(true, null);
}

if ($action === 'delete') {
    $id = (int)$data['id'];
    $db->write_query("DELETE FROM {$prefix}game_announcements WHERE id = {$id}");
    GameAjax::json(true, null);
}

GameAjax::fail(400, 'Acción no válida');
