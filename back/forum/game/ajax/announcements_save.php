<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $db;
$prefix = TABLE_PREFIX;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();

// Verificar staff level 3
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
if (!$cfg || !$cfg['active_pj_id']) {
    echo json_encode(['ok' => false, 'error' => ['message' => 'Sin personaje activo']]);
    exit;
}

$pj_q = $db->query("SELECT is_staff, staff_level FROM {$prefix}game_personajes WHERE id = " . (int)$cfg['active_pj_id'] . " AND user_id = {$uid} LIMIT 1");
$pj = $db->fetch_array($pj_q);
if (!$pj || !(int)$pj['is_staff'] || (int)$pj['staff_level'] < 3) {
    echo json_encode(['ok' => false, 'error' => ['message' => 'Nivel de staff insuficiente']]);
    exit;
}

$data = GameAjax::postJson();
GameAjax::requireCsrf($data);

$action = $data['action'] ?? '';

if ($action === 'create' || $action === 'edit') {
    $title = $db->escape_string(trim($data['title'] ?? ''));
    $content = $db->escape_string(trim($data['content'] ?? ''));
    
    if (empty($title) || empty($content)) {
        echo json_encode(['ok' => false, 'error' => ['message' => 'Título y contenido son obligatorios']]);
        exit;
    }
    
    if ($action === 'create') {
        $db->write_query("
            INSERT INTO {$prefix}game_announcements (title, content, created_by, created_at, is_active)
            VALUES ('{$title}', '{$content}', {$uid}, CURRENT_TIMESTAMP, 1)
        ");
        echo json_encode(['ok' => true]);
        exit;
    } else {
        $id = (int)$data['id'];
        $db->write_query("
            UPDATE {$prefix}game_announcements
            SET title = '{$title}', content = '{$content}'
            WHERE id = {$id}
        ");
        echo json_encode(['ok' => true]);
        exit;
    }
} elseif ($action === 'delete') {
    $id = (int)$data['id'];
    $db->write_query("DELETE FROM {$prefix}game_announcements WHERE id = {$id}");
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'error' => ['message' => 'Acción no válida']]);
exit;
