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

$principle = (string)($input['principle'] ?? '');
$newLevel = (int)($input['level'] ?? 0);

$validPrinciples = ['ten', 'zetsu', 'ren', 'hatsu'];
if (!in_array($principle, $validPrinciples, true)) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Principio Nen inválido.'], 400);
}

if ($newLevel < 1 || $newLevel > 4) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Nivel inválido (debe ser 1-4).'], 400);
}

$service = new NenService();
$state = $service->getNenState($pjId);
if (!$state) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Debes despertar tu Nen primero.'], 400);
}

$currentLevel = (int)($state['principles'][$principle]['level'] ?? 0);
if ($newLevel !== $currentLevel + 1) {
    GameAjax::json(false, null, ['code' => 400, 'message' => "Entrenamiento no lineal. Nivel actual en {$principle} es {$currentLevel}, no puedes subir a {$newLevel} de golpe."], 400);
}

// Validar que no se puede entrenar Hatsu si no tiene un tipo definido (la prueba de la taza cerrada)
if ($principle === 'hatsu' && !$state['nen_type_locked']) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Debes pasar la Prueba de la Taza para definir tu categoría Nen antes de entrenar Hatsu.'], 400);
}

$staffLevel = game_get_active_staff_level($uid);

if ($staffLevel >= 3) {
    // Si es Staff de rango alto, actualizar directamente
    $ok = $service->trainPrinciple($pjId, $principle, $newLevel);
    if ($ok) {
        GameAjax::json(true, ['message' => 'Principio Nen actualizado directamente.'], null);
    } else {
        GameAjax::json(false, null, ['code' => 500, 'message' => 'Error al actualizar el principio Nen.'], 500);
    }
} else {
    // Si es jugador ordinario, crear petición para aprobación del Staff
    $pLabel = game_get_nen_principle_label($principle);
    $lLabel = game_get_nen_principle_level_label($newLevel);
    
    $title = "Entrenamiento Nen: {$pLabel} al nivel {$lLabel}";
    $desc = "El personaje solicita entrenar su {$pLabel} para alcanzar el rango de {$lLabel}.";
    
    $requestId = AdminRequestService::create(
        $uid,
        $pjId,
        'nen',
        'nen_entrenamiento',
        $title,
        $desc,
        '/game/public/peticiones_admin.php',
        [
            'principle' => $principle,
            'level' => $newLevel
        ]
    );

    AdminRequestService::notifyStaffPending($title, '/game/public/peticiones_admin.php');

    GameAjax::json(true, [
        'message' => 'Solicitud de entrenamiento enviada al staff correctamente.',
        'request_id' => $requestId
    ], null);
}
