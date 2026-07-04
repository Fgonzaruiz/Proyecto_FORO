<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;
use Game\Application\Services\NenService;

$uid = GameAjax::requireLogin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    GameAjax::json(false, null, ['code' => 405, 'message' => 'Method not allowed'], 405);
}

$pjId = game_get_active_pj_id($uid);
if ($pjId <= 0) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'No tienes un personaje activo.'], 400);
}

$service = new NenService();
$state = $service->getNenState($pjId);

if (!$state) {
    GameAjax::json(true, ['despierto' => false, 'nen' => null], null);
}

// Agregar labels explicativas para facilitar UI
$state['nen_type_label'] = game_get_nen_type_label($state['nen_type']);
$state['nen_type_color'] = game_get_nen_type_color($state['nen_type']);

foreach ($state['principles'] as $p => &$pInfo) {
    $pInfo['principle_label'] = game_get_nen_principle_label($p);
    $pInfo['level_label'] = game_get_nen_principle_level_label($pInfo['level']);
}

GameAjax::json(true, ['despierto' => true, 'nen' => $state], null);
