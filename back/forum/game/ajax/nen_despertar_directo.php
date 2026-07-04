<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;
use Game\Application\Services\NenService;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$staffLevel = game_get_active_staff_level($uid);
if ($staffLevel < 2) {
    GameAjax::json(false, null, ['code' => 403, 'message' => 'No tienes permisos de staff para esta acción.'], 403);
}

$targetPjId = (int)($input['character_id'] ?? 0);
if ($targetPjId <= 0) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'ID de personaje inválido.'], 400);
}

$service = new NenService();
$existing = $service->getNenState($targetPjId);
if ($existing !== null) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Este personaje ya tiene el Nen despertado.'], 400);
}

$service->despertarNen($targetPjId);
GameAjax::json(true, ['message' => 'Nen despertado con éxito. El personaje ya puede entrenar sus principios.'], null);
