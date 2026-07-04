<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;
use Game\Infrastructure\Persistence\PersonajeRepository;

header('Content-Type: application/json; charset=utf-8');

global $db;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$characterId = (int)($input['character_id'] ?? 0);
$itemType = trim((string)($input['item_type'] ?? ''));
$itemName = trim((string)($input['item_name'] ?? ''));

if ($characterId <= 0 || $itemType === '' || $itemName === '') {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Parámetros inválidos.'], 400);
}

if (game_get_active_pj_id($uid) !== $characterId) {
    GameAjax::json(false, null, ['code' => 403, 'message' => 'Debes usar tu personaje activo.'], 403);
}

$personajes = new PersonajeRepository();
$character = $personajes->findByIdForUser($characterId, $uid);

if ($character === null) {
    GameAjax::json(false, null, ['code' => 404, 'message' => 'Personaje no encontrado.'], 404);
}

if (($character['status'] ?? '') !== 'aprobada') {
    GameAjax::json(false, null, ['code' => 403, 'message' => 'El personaje debe estar aprobado para realizar compras.'], 403);
}

// Cost mapping
$costs = [
    'estilo_secundario'  => 2,
    'estilo_terciario'   => 4,
    'tecnica_prohibida'  => 3,
    'habilidad_elemental'=> 2,
    'barco_narrativo'    => 3,
    'poder_especial'     => 4,
];

if (!array_key_exists($itemType, $costs)) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Tipo de artículo no válido.'], 400);
}

$cost = $costs[$itemType];
$availablePd = game_get_character_pd_available($characterId);

if ($availablePd < $cost) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Puntos Destino (PD) insuficientes (tienes ' . $availablePd . ' PD, necesitas ' . $cost . ' PD).'], 400);
}

$itemSlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $itemType . '_' . $itemName));

// Register purchase
if (game_register_pd_purchase($characterId, $cost, $itemType, $itemSlug, $itemName)) {
    $newAvailable = game_get_character_pd_available($characterId);

    // Send system notification
    try {
        $notifService = new \Game\Application\Services\NotificationService();
        $notifService->create(
            $uid,
            'system',
            "Compra PD Confirmada",
            "Has desbloqueado '{$itemName}' gastando {$cost} PD.",
            "game/public/personaje.php?pj={$characterId}",
            $characterId
        );
    } catch (\Throwable $e) {
        // Ignore
    }

    GameAjax::json(true, [
        'character_id' => $characterId,
        'item_type' => $itemType,
        'item_name' => $itemName,
        'pd_spent' => $cost,
        'new_pd_available' => $newAvailable,
    ], null);
} else {
    GameAjax::json(false, null, ['code' => 500, 'message' => 'Error al guardar la compra en la base de datos.'], 500);
}
