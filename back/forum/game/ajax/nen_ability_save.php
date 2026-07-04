<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;
use Game\Application\Services\NenService;
use Game\Application\Services\AdminRequestService;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$pjId = game_get_active_pj_id($uid);
if ($pjId <= 0) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'No tienes un personaje activo.'], 400);
}

$name = trim((string)($input['name'] ?? ''));
$desc = trim((string)($input['description'] ?? ''));
$rank = trim((string)($input['rank'] ?? 'D'));
$cost = (int)($input['cost'] ?? 0);
$conditions = isset($input['conditions']) && is_array($input['conditions']) ? $input['conditions'] : [];

if ($name === '' || $desc === '') {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'El nombre y descripción de la habilidad son obligatorios.'], 400);
}

$validRanks = ['D', 'C', 'B', 'A', 'S', 'SS'];
if (!in_array($rank, $validRanks, true)) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Rango de habilidad inválido.'], 400);
}

if ($cost < 0) {
    $cost = 0;
}

$service = new NenService();
$state = $service->getNenState($pjId);
if (!$state || !$state['nen_type_locked']) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Debes despertar tu Nen y pasar la Prueba de la Taza antes de crear habilidades Hatsu.'], 400);
}

// Guardar la habilidad como propuesta (approved = 0)
$abilityId = $service->proponerHabilidad($pjId, $name, $desc, $rank, $cost, $conditions);

if ($abilityId <= 0) {
    GameAjax::json(false, null, ['code' => 500, 'message' => 'Error al registrar la propuesta de habilidad en la base de datos.'], 500);
}

// Crear petición para aprobación del Staff
$title = "Habilidad Nen (Hatsu): {$name} (Rango {$rank})";
$reqDesc = "El personaje propone una habilidad Nen:\n\n"
    . "**Nombre:** {$name}\n"
    . "**Rango:** {$rank}\n"
    . "**Coste de Aura:** {$cost} PE\n"
    . "**Descripción:** {$desc}\n"
    . "**Restricciones / Condiciones:** " . implode(', ', $conditions);

$requestId = AdminRequestService::create(
        $uid,
        $pjId,
        'nen',
        'nen_hatsu',
        $title,
        $reqDesc,
        '/game/public/peticiones_admin.php',
        [
            'ability_id' => $abilityId,
            'name' => $name,
            'rank' => $rank,
            'cost' => $cost
        ]
    );

AdminRequestService::notifyStaffPending($title, '/game/public/peticiones_admin.php');

GameAjax::json(true, [
    'message' => 'Propuesta de habilidad Nen (Hatsu) enviada al staff correctamente.',
    'ability_id' => $abilityId,
    'request_id' => $requestId
], null);
