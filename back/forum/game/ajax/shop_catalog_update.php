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
    GameAjax::json(false, null, ['code' => 403, 'message' => 'Permisos insuficientes.'], 403);
}

$card_id = (int)($input['card_id'] ?? 0);
if ($card_id <= 0) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'ID de carta inválido.'], 400);
}

$card_q = $db->query("
    SELECT id, card_type, cost_jenny
    FROM {$prefix}game_cards
    WHERE id = {$card_id}
      AND card_type IN ('equipo', 'npc_menor')
      AND cost_jenny > 0
    LIMIT 1
");
$card = $db->fetch_array($card_q);
if (!$card) {
    GameAjax::json(false, null, ['code' => 404, 'message' => 'Carta no encontrada o no comerciable.'], 404);
}

$allowed_cats = ['utiles', 'armeria', 'mascotas'];
$update = [];

if (array_key_exists('in_shop', $input)) {
    $update['in_shop'] = ((int)$input['in_shop'] === 1) ? 1 : 0;
}

if (!empty($input['shop_category'])) {
    $cat = (string)$input['shop_category'];
    if (!in_array($cat, $allowed_cats, true)) {
        GameAjax::json(false, null, ['code' => 400, 'message' => 'Categoría de tienda inválida.'], 400);
    }
    $update['shop_category'] = $db->escape_string($cat);
}

if ($update === []) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Nada que actualizar.'], 400);
}

$db->update_query('game_cards', $update, "id = {$card_id}");

$row_q = $db->query("SELECT id, name, in_shop, shop_category, cost_jenny FROM {$prefix}game_cards WHERE id = {$card_id} LIMIT 1");
$row = $db->fetch_array($row_q);

GameAjax::json(true, [
    'card' => [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'in_shop' => (int)$row['in_shop'],
        'shop_category' => $row['shop_category'],
        'cost_jenny' => (int)$row['cost_jenny'],
    ],
], null);
