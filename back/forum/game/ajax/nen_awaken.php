<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;
use Game\Application\Services\NenService;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$charId = (int)($input['character_id'] ?? 0);
if ($charId <= 0) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'ID de personaje inválido.'], 400);
}

global $db;
$prefix = TABLE_PREFIX;
$pj_q = $db->query("SELECT id, user_id, status FROM {$prefix}game_personajes WHERE id = {$charId} LIMIT 1");
$pj = $db->fetch_array($pj_q);
if (!$pj || (int)$pj['user_id'] !== $uid) {
    GameAjax::json(false, null, ['code' => 403, 'message' => 'Personaje no válido.'], 403);
}
if ($pj['status'] !== 'aprobada') {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'El personaje debe estar aprobado.'], 400);
}

$service = new NenService();
$existing = $service->getNenState($charId);

if ($existing !== null && $existing['nen_type_locked']) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Este personaje ya completó la prueba del agua.'], 400);
}

// Prueba Mizushigure: despertar (si hace falta) + tipo aleatorio al tocar el vaso
if ($existing === null) {
    $service->despertarNen($charId);
}

$nenTypes = ['enhancement', 'transmutation', 'emission', 'conjuration', 'manipulation', 'specialization'];
$randType = $nenTypes[array_rand($nenTypes)];

$auraColors = [
    '#E64A19', '#D81B60', '#43A047', '#1976D2', '#8E24AA',
    '#F57C00', '#00BCD4', '#FF6F00', '#C62828', '#4A148C', '#004D40', '#33691E',
];
$randColor = $auraColors[array_rand($auraColors)];

$service->setNenType($charId, $randType);
$db->write_query("UPDATE {$prefix}game_nen SET aura_color = '{$db->escape_string($randColor)}' WHERE character_id = {$charId}");

GameAjax::json(true, game_nen_awakening_payload($charId, $randType, $randColor));
