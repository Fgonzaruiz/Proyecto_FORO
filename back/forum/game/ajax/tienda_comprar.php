<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;
use Game\Infrastructure\Persistence\PersonajeRepository;

header('Content-Type: application/json; charset=utf-8');

global $db;
$prefix = TABLE_PREFIX;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$character_id = (int)($input['character_id'] ?? 0);
$cart = $input['cart'] ?? [];

if ($character_id <= 0 || !is_array($cart) || empty($cart)) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Parámetros de compra inválidos.'], 400);
}

// Verificar que el personaje existe y pertenece al usuario
$repo = new PersonajeRepository();
$character = $repo->findByIdForUser($character_id, $uid);

if ($character === null) {
    GameAjax::json(false, null, ['code' => 404, 'message' => 'Personaje no encontrado.'], 404);
}

if ($character['status'] !== 'aprobada') {
    GameAjax::json(false, null, ['code' => 403, 'message' => 'El personaje debe estar aprobado para comprar en la tienda.'], 403);
}

// Cargar la información de las cartas
$card_ids = [];
foreach ($cart as $item) {
    $cid = (int)($item['card_id'] ?? 0);
    if ($cid > 0) {
        $card_ids[] = $cid;
    }
}

if (empty($card_ids)) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'El carrito está vacío.'], 400);
}

$card_ids_str = implode(',', $card_ids);
$cards_q = $db->query("SELECT * FROM {$prefix}game_cards WHERE id IN ({$card_ids_str}) AND in_shop = 1");
$cards_db = [];
while ($row = $db->fetch_array($cards_q)) {
    $cards_db[(int)$row['id']] = $row;
}

// Calcular costes y validar propiedad/existencia
$total_cost = 0;
$items_to_buy = [];

foreach ($cart as $item) {
    $card_id = (int)($item['card_id'] ?? 0);
    $qty = (int)($item['cantidad'] ?? 1);
    if ($qty <= 0) {
        $qty = 1;
    }

    if (!isset($cards_db[$card_id])) {
        GameAjax::json(false, null, ['code' => 400, 'message' => 'Uno de los objetos seleccionados no está a la venta.'], 400);
    }

    $card = $cards_db[$card_id];
    
    // Validar tipo de carta comerciable
    $valid_types = ['equipo', 'npc_menor'];
    if (!in_array($card['card_type'], $valid_types, true)) {
        GameAjax::json(false, null, ['code' => 400, 'message' => 'No está permitido comerciar con este tipo de carta.'], 400);
    }

    $cost_jenny = (int)$card['cost_jenny'];
    if ($cost_jenny <= 0) {
        GameAjax::json(false, null, ['code' => 400, 'message' => 'Uno de los objetos no tiene precio válido.'], 400);
    }
    $total_cost += $cost_jenny * $qty;

    // Determinar si es consumible
    $is_consumable = false;
    if ($card['card_type'] === 'equipo') {
        $effects = json_decode($card['effects_json'] ?? '{}', true);
        if (($effects['equipo_type'] ?? '') === 'util') {
            $is_consumable = true;
        }
    }

    // Si es un objeto único, verificar que no lo tenga ya en su inventario
    if (!$is_consumable) {
        $check_owned = $db->query("SELECT 1 FROM {$prefix}game_character_cards WHERE character_id = {$character_id} AND card_id = {$card_id} LIMIT 1");
        if ($db->num_rows($check_owned) > 0) {
            GameAjax::json(false, null, ['code' => 400, 'message' => "Ya posees el objeto único: {$card['name']}."], 400);
        }
        // Fuerza cantidad a 1 para únicos
        $qty = 1;
    }

    $items_to_buy[] = [
        'card' => $card,
        'cantidad' => $qty,
        'cost_jenny' => $cost_jenny,
        'rank' => $card['rank'],
        'is_consumable' => $is_consumable
    ];
}

// Validar saldo de Jenny
$current_jenny = (int)($character['jenny'] ?? 0);
if ($current_jenny < $total_cost) {
    GameAjax::json(false, null, [
        'code' => 400,
        'message' => "Saldo insuficiente. Necesitas " . number_format($total_cost, 0, ',', '.') . " Jenny y posees " . number_format($current_jenny, 0, ',', '.') . " Jenny."
    ], 400);
}

// Proceder con la compra atómica
$db->write_query("UPDATE {$prefix}game_personajes SET jenny = jenny - {$total_cost} WHERE id = {$character_id}");

// Insertar/actualizar cartas
foreach ($items_to_buy as $item) {
    $card_id = (int)$item['card']['id'];
    $qty = $item['cantidad'];
    $rank = $db->escape_string($item['rank']);

    $db->write_query("
        INSERT INTO {$prefix}game_character_cards (character_id, card_id, current_rank, assigned_by, cantidad)
        VALUES ({$character_id}, {$card_id}, '{$rank}', {$uid}, {$qty})
        ON DUPLICATE KEY UPDATE cantidad = cantidad + {$qty}
    ");
}

$new_jenny = $current_jenny - $total_cost;

// Log acción
game_log_action('tienda_compra', [
    'user_id' => $uid,
    'character_id' => $character_id,
    'total_cost' => $total_cost,
    'items_count' => count($items_to_buy)
]);

GameAjax::json(true, [
    'new_jenny' => $new_jenny,
    'message' => 'Compra realizada correctamente.'
], null);
