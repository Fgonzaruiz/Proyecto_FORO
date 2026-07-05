<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

header('Content-Type: application/json; charset=utf-8');

global $mybb, $db;

$userId = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$pjId = (int)($input['pj_id'] ?? 0);
$avatar = isset($input['avatar']) ? trim((string)$input['avatar']) : null;
$banner = isset($input['banner']) ? trim((string)$input['banner']) : null;
$firma = isset($input['firma']) ? trim((string)$input['firma']) : null;

if ($pjId <= 0) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'ID de personaje inválido.'], 400);
}

$prefix = TABLE_PREFIX;

// Check if user is admin (staff_level = 3 on ANY character of this user)
$is_admin = false;
$check_admin_q = $db->query("SELECT COUNT(*) as cnt FROM {$prefix}game_personajes WHERE user_id = {$userId} AND staff_level = 3");
if ($db->fetch_field($check_admin_q, 'cnt') > 0) {
    $is_admin = true;
}

// Verify that the user owns this character, or is admin
if ($is_admin) {
    $q = $db->query("SELECT id FROM {$prefix}game_personajes WHERE id = {$pjId} LIMIT 1");
} else {
    $q = $db->query("SELECT id FROM {$prefix}game_personajes WHERE id = {$pjId} AND user_id = {$userId} LIMIT 1");
}

$pj = $db->fetch_array($q);
if (!$pj) {
    GameAjax::json(false, null, ['code' => 403, 'message' => 'No tienes permisos para editar este personaje.'], 403);
}

// Update DB
$update = [];
if ($avatar !== null) {
    $update['avatar'] = $db->escape_string($avatar);
}
if ($banner !== null) {
    $update['banner'] = $db->escape_string($banner);
}
if ($firma !== null) {
    $update['firma'] = $db->escape_string($firma);
}

if (!empty($update)) {
    $db->update_query('game_personajes', $update, "id = {$pjId}");
}

game_log_action('save_avatar_sig', ['uid' => $userId, 'pj_id' => $pjId]);
GameAjax::json(true, null, null);
