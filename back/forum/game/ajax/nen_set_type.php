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

$nenType = (string)($input['nen_type'] ?? '');
$targetPjId = isset($input['character_id']) ? (int)$input['character_id'] : $pjId;

$validTypes = [
    'enhancement', 'transmutation', 'emission',
    'conjuration', 'manipulation', 'specialization'
];

if (!in_array($nenType, $validTypes, true)) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Tipo de Nen inválido.'], 400);
}

$service = new NenService();

// Comprobar si el usuario actual es staff_level >= 3 para aplicar directamente
$staffLevel = game_get_active_staff_level($uid);

if ($staffLevel >= 3) {
    $ok = $service->setNenType($targetPjId, $nenType);
    if ($ok) {
        GameAjax::json(true, ['message' => 'Tipo de Nen fijado con éxito.'], null);
    } else {
        GameAjax::json(false, null, ['code' => 500, 'message' => 'No se pudo fijar el tipo de Nen. ¿Tiene el Nen despierto?'], 500);
    }
} else {
    // Si es jugador, solicita la Prueba de la Taza al staff
    $state = $service->getNenState($pjId);
    if (!$state) {
        GameAjax::json(false, null, ['code' => 400, 'message' => 'Debes despertar tu Nen primero.'], 400);
    }

    if ($state['nen_type_locked']) {
        GameAjax::json(false, null, ['code' => 400, 'message' => 'Tu tipo de Nen ya se encuentra bloqueado de forma irreversible.'], 400);
    }

    $tLabel = game_get_nen_type_label($nenType);
    $title = "Prueba de la Taza: Categoria {$tLabel}";
    $desc = "El jugador solicita fijar su tipo de Nen definitivo a: {$tLabel}.";

    $requestId = AdminRequestService::create(
        $uid,
        $pjId,
        'nen',
        'nen_taza',
        $title,
        $desc,
        '/game/public/peticiones_admin.php',
        [
            'nen_type' => $nenType
        ]
    );

    AdminRequestService::notifyStaffPending($title, '/game/public/peticiones_admin.php');

    GameAjax::json(true, [
        'message' => 'Solicitud de tipo Nen (Prueba de la Taza) enviada al staff correctamente.',
        'request_id' => $requestId
    ], null);
}
