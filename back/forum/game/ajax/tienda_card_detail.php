<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

header('Content-Type: application/json; charset=utf-8');

global $db;

GameAjax::requireLogin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    GameAjax::json(false, null, ['code' => 405, 'message' => 'Method not allowed'], 405);
}

$card_id = isset($_GET['card_id']) ? (int)$_GET['card_id'] : 0;
if ($card_id <= 0) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'ID de carta inválido.'], 400);
}

$prefix = TABLE_PREFIX;
$q = $db->query("SELECT * FROM {$prefix}game_cards WHERE id = {$card_id} LIMIT 1");
$row = $db->fetch_array($q);
if (!$row) {
    GameAjax::json(false, null, ['code' => 404, 'message' => 'Carta no encontrada.'], 404);
}

$effects = json_decode($row['effects_json'] ?? '{}', true) ?: [];
$card = [
    'id' => (int)$row['id'],
    'name' => $row['name'],
    'card_type' => $row['card_type'],
    'rank' => $row['rank'],
    'image_url' => $row['image_url'] ?? '',
    'description' => $row['description'] ?? '',
    'tags' => json_decode($row['tags_json'] ?? '[]', true) ?: [],
    'effects' => $effects,
    'dice' => $row['dice'] ?? '',
    'cost_pe' => $row['cost_pe'] ?? '',
    'execution_cost' => (int)($row['execution_cost'] ?? 0),
    'execution_stat' => $row['execution_stat'] ?? '',
    'activation' => $row['activation'] ?? 'activa',
    'reposo' => (int)($row['reposo'] ?? 0),
    'duracion' => (int)($row['duracion'] ?? 0),
    'cost_berries' => (int)($row['cost_berries'] ?? 0),
    'in_shop' => (int)($row['in_shop'] ?? 0),
    'shop_category' => $row['shop_category'] ?? null,
];

if ($card['card_type'] === 'equipo' && strtolower((string)($effects['equipo_type'] ?? '')) === 'util') {
    $card['is_consumible'] = true;
}

GameAjax::json(true, ['card' => $card], null);
