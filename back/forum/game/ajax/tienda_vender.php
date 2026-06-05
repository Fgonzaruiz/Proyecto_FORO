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
$card_id      = (int)($input['card_id'] ?? 0);
$cantidad     = (int)($input['cantidad'] ?? 1);

if ($character_id <= 0 || $card_id <= 0 || $cantidad <= 0) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Parámetros de venta inválidos.'], 400);
}

// Verificar que el personaje existe y pertenece al usuario
$repo      = new PersonajeRepository();
$character = $repo->findByIdForUser($character_id, $uid);

if ($character === null) {
    GameAjax::json(false, null, ['code' => 404, 'message' => 'Personaje no encontrado.'], 404);
}

if ($character['status'] !== 'aprobada') {
    GameAjax::json(false, null, ['code' => 403, 'message' => 'El personaje debe estar aprobado para vender en la tienda.'], 403);
}

// Verificar que la carta existe, está en tienda y es de tipo comerciable
$card_q = $db->query("SELECT * FROM {$prefix}game_cards WHERE id = {$card_id} AND in_shop = 1 LIMIT 1");
$card   = $db->fetch_array($card_q);

if (!$card) {
    GameAjax::json(false, null, ['code' => 404, 'message' => 'El objeto no existe o no está disponible para venta.'], 404);
}

$valid_types = ['equipo', 'npc_menor', 'barco'];
if (!in_array($card['card_type'], $valid_types, true)) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'No está permitido comerciar con este tipo de carta.'], 400);
}

// Verificar que el personaje posee la carta
$owned_q = $db->query("SELECT * FROM {$prefix}game_character_cards WHERE character_id = {$character_id} AND card_id = {$card_id} LIMIT 1");
$owned   = $db->fetch_array($owned_q);

if (!$owned) {
    GameAjax::json(false, null, ['code' => 404, 'message' => 'No posees este objeto.'], 404);
}

$owned_cantidad = (int)($owned['cantidad'] ?? 1);

if ($cantidad > $owned_cantidad) {
    GameAjax::json(false, null, [
        'code'    => 400,
        'message' => "Solo posees {$owned_cantidad} unidad(es) de este objeto.",
    ], 400);
}

// Calcular ganancias: 50 % del precio de compra por unidad
$cost_berries = (int)$card['cost_berries'];
$refund_each  = (int)floor($cost_berries * 0.5);
$total_refund = $refund_each * $cantidad;

// Actualizar inventario: borrar si vende todo, o decrementar
if ($cantidad >= $owned_cantidad) {
    $db->write_query("DELETE FROM {$prefix}game_character_cards WHERE character_id = {$character_id} AND card_id = {$card_id}");
} else {
    $remaining = $owned_cantidad - $cantidad;
    $db->write_query("UPDATE {$prefix}game_character_cards SET cantidad = {$remaining} WHERE character_id = {$character_id} AND card_id = {$card_id}");
}

// Sumar berries al personaje (atómico)
$db->write_query("UPDATE {$prefix}game_personajes SET berries = berries + {$total_refund} WHERE id = {$character_id}");

// Obtener saldo actualizado
$new_q       = $db->query("SELECT berries FROM {$prefix}game_personajes WHERE id = {$character_id} LIMIT 1");
$new_row     = $db->fetch_array($new_q);
$new_berries = (int)($new_row['berries'] ?? 0);

// Log acción
game_log_action('tienda_venta', [
    'user_id'      => $uid,
    'character_id' => $character_id,
    'card_id'      => $card_id,
    'cantidad'     => $cantidad,
    'total_refund' => $total_refund,
]);

GameAjax::json(true, [
    'new_berries' => $new_berries,
    'message'     => 'Venta realizada correctamente.',
], null);
