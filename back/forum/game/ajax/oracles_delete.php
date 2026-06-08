<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $db;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();

$prefix = TABLE_PREFIX;
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
    GameAjax::fail(403, 'Permisos insuficientes.');
}

$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$oracle_id = isset($input['id']) ? (int)$input['id'] : 0;
if ($oracle_id <= 0) {
    GameAjax::fail(400, 'ID de oráculo inválido.');
}

$check = $db->query("SELECT id, is_system FROM {$prefix}game_oracles WHERE id = {$oracle_id} LIMIT 1");
$row = $db->fetch_array($check);
if (!$row) {
    GameAjax::fail(404, 'Oráculo no encontrado.');
}
if ((int)$row['is_system'] === 1) {
    GameAjax::fail(403, 'No se puede eliminar un oráculo del sistema.');
}

$db->delete_query('game_oracles', "id = {$oracle_id}");

GameAjax::json(true, null);
