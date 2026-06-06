<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

header('Content-Type: application/json; charset=utf-8');

global $db;

$uid = GameAjax::requireLogin();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    GameAjax::json(false, null, ['code' => 405, 'message' => 'Method not allowed'], 405);
}

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

$scope = isset($_GET['scope']) ? (string)$_GET['scope'] : 'active';
if (!in_array($scope, ['active', 'pool'], true)) {
    $scope = 'active';
}

$shop_filter = $scope === 'active' ? 'in_shop = 1' : 'in_shop = 0';

$q = $db->query("
    SELECT id, name, card_type, `rank`, image_url, cost_berries, in_shop, shop_category
    FROM {$prefix}game_cards
    WHERE card_type IN ('equipo', 'npc_menor', 'barco')
      AND cost_berries > 0
      AND {$shop_filter}
    ORDER BY name ASC
");
$items = [];
while ($row = $db->fetch_array($q)) {
    $items[] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'card_type' => $row['card_type'],
        'rank' => $row['rank'],
        'image_url' => $row['image_url'] ?? '',
        'cost_berries' => (int)$row['cost_berries'],
        'in_shop' => (int)$row['in_shop'],
        'shop_category' => $row['shop_category'] ?? 'utiles',
    ];
}

GameAjax::json(true, ['items' => $items, 'scope' => $scope], null);
