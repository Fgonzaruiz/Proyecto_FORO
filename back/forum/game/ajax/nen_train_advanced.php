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

$technique = (string)($input['technique'] ?? '');
$validIds = array_column(game_get_nen_advanced_techniques(), 'id');
if (!in_array($technique, $validIds, true)) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Técnica avanzada inválida.'], 400);
}

$service = new NenService();
$state = $service->getNenState($pjId);
if (!$state || !$state['nen_type_locked']) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Debes completar la prueba del agua primero.'], 400);
}

$catalog = game_get_nen_advanced_techniques();
$name = $technique;
foreach ($catalog as $t) {
    if ($t['id'] === $technique) {
        $name = $t['name'];
        break;
    }
}

$title = "Entrenamiento técnica avanzada: {$name}";
$desc = "El personaje solicita entrenar la técnica avanzada {$name}.";

$requestId = AdminRequestService::create(
    $uid,
    $pjId,
    'nen',
    'nen_advanced',
    $title,
    $desc,
    '/game/public/peticiones_admin.php',
    ['technique' => $technique]
);

AdminRequestService::notifyStaffPending($title, '/game/public/peticiones_admin.php');

GameAjax::json(true, [
    'message' => 'Solicitud de entrenamiento enviada al staff.',
    'request_id' => $requestId,
], null);
