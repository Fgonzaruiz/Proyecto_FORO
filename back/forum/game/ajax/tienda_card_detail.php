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

$effects = json_decode($row['effects_json'] ?? '{}', true);
if (!is_array($effects) || array_is_list($effects)) {
    $effects = [];
}

$tags = json_decode($row['tags_json'] ?? '[]', true);
if (!is_array($tags)) {
    $tags = [];
}
$tags = array_values(array_filter(array_map('strval', $tags)));

$cost_pe = trim((string)($row['cost_pe'] ?? ''));
if ($cost_pe === '') {
    $cost_pe = '—';
}

$card_type = (string)($row['card_type'] ?? 'equipo');
$card = [
    'id' => (int)$row['id'],
    'name' => (string)($row['name'] ?? 'Carta'),
    'card_type' => $card_type,
    'rank' => (string)($row['rank'] ?? 'C'),
    'image_url' => (string)($row['image_url'] ?? ''),
    'description' => (string)($row['description'] ?? ''),
    'tags' => $tags,
    'effects' => $effects,
    'dice' => (string)($row['dice'] ?? ''),
    'cost_pe' => $cost_pe,
    'execution_cost' => (int)($row['execution_cost'] ?? 0),
    'execution_stat' => (string)($row['execution_stat'] ?? ''),
    'activation' => (string)($row['activation'] ?? 'activa'),
    'reposo' => (int)($row['reposo'] ?? 0),
    'duracion' => (int)($row['duracion'] ?? 0),
    'cost_jenny' => (int)($row['cost_jenny'] ?? 0),
    'in_shop' => (int)($row['in_shop'] ?? 0),
    'shop_category' => $row['shop_category'] ?? null,
];

if ($card_type === 'equipo' && strtolower((string)($effects['equipo_type'] ?? '')) === 'util') {
    $card['is_consumible'] = true;
}

GameAjax::json(true, ['card' => $card], null);
