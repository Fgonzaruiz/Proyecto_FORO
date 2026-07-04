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
    GameAjax::json(false, null, ['code' => 403, 'message' => 'No tienes permisos de moderación/staff.'], 403);
}

$abilityId = (int)($input['ability_id'] ?? 0);
$cardId = (int)($input['card_id'] ?? 0);

if ($abilityId <= 0 || $cardId <= 0) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Habilidad o Carta técnica inválidas.'], 400);
}

$service = new NenService();
$ok = $service->aprobarHabilidad($abilityId, $cardId);

if ($ok) {
    GameAjax::json(true, ['message' => 'Habilidad Hatsu aprobada y vinculada a la carta con éxito.'], null);
} else {
    GameAjax::json(false, null, ['code' => 500, 'message' => 'Error al aprobar la habilidad Nen.'], 500);
}
