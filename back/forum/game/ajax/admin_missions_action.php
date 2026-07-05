<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

header('Content-Type: application/json; charset=utf-8');

global $db;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$prefix = TABLE_PREFIX;

// Check staff level
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;
if ($active_pj_id > 0) {
    $pj_q = $db->query("SELECT staff_level FROM {$prefix}game_personajes WHERE id = {$active_pj_id} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    $staff_level = $pj ? (int)$pj['staff_level'] : 0;
}

if ($staff_level < 3) {
    GameAjax::json(false, null, ['code' => 403, 'message' => 'Solo administradores pueden realizar esta acción.'], 403);
}

$action = trim((string)($input['action'] ?? ''));

if ($action === 'create' || $action === 'edit') {
    $id = (int)($input['id'] ?? 0);
    $title = trim((string)($input['title'] ?? ''));
    $description = trim((string)($input['description'] ?? ''));
    $rank = trim((string)($input['rank'] ?? 'D'));
    $min_level = (int)($input['min_level'] ?? 1);
    $max_level = (int)($input['max_level'] ?? 99);
    $points_reward = (int)($input['points_reward'] ?? 0);
    $jenny_reward = (int)($input['jenny_reward'] ?? $input['berry_reward'] ?? 0);
    $isla = trim((string)($input['isla'] ?? ''));
    $categoria = trim((string)($input['categoria'] ?? 'mision'));
    $faction = trim((string)($input['faction'] ?? 'Global'));
    $max_posts = (int)($input['max_posts'] ?? 15);

    if ($title === '' || $description === '' || $isla === '') {
        GameAjax::json(false, null, ['code' => 400, 'message' => 'Título, descripción e isla son obligatorios.'], 400);
    }

    $titleEsc = $db->escape_string($title);
    $descEsc = $db->escape_string($description);
    $rankEsc = $db->escape_string($rank);
    $islaEsc = $db->escape_string($isla);
    $catEsc = $db->escape_string($categoria);
    $factionEsc = $db->escape_string($faction);

    if ($action === 'create') {
        $db->write_query("INSERT INTO {$prefix}game_missions 
            (title, description, `rank`, min_level, max_level, points_reward, jenny_reward, isla, categoria, faction, max_posts, is_active)
            VALUES 
            ('{$titleEsc}', '{$descEsc}', '{$rankEsc}', {$min_level}, {$max_level}, {$points_reward}, {$jenny_reward}, '{$islaEsc}', '{$catEsc}', '{$factionEsc}', {$max_posts}, 1)");
        $newId = $db->insert_id();
        GameAjax::json(true, ['id' => $newId, 'message' => 'Misión creada exitosamente.'], null);
    } else {
        if ($id <= 0) {
            GameAjax::json(false, null, ['code' => 400, 'message' => 'ID de misión inválido para edición.'], 400);
        }
        $db->write_query("UPDATE {$prefix}game_missions SET 
            title = '{$titleEsc}',
            description = '{$descEsc}',
            `rank` = '{$rankEsc}',
            min_level = {$min_level},
            max_level = {$max_level},
            points_reward = {$points_reward},
            jenny_reward = {$jenny_reward},
            isla = '{$islaEsc}',
            categoria = '{$catEsc}',
            faction = '{$factionEsc}',
            max_posts = {$max_posts}
            WHERE id = {$id}");
        GameAjax::json(true, ['id' => $id, 'message' => 'Misión actualizada exitosamente.'], null);
    }
} elseif ($action === 'delete') {
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) {
        GameAjax::json(false, null, ['code' => 400, 'message' => 'ID de misión inválido.'], 400);
    }
    // Deactivate instead of physical delete to preserve history/active missions referencing it
    $db->write_query("UPDATE {$prefix}game_missions SET is_active = 0 WHERE id = {$id}");
    GameAjax::json(true, ['id' => $id, 'message' => 'Misión desactivada del catálogo.'], null);
} else {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Acción inválida.'], 400);
}
