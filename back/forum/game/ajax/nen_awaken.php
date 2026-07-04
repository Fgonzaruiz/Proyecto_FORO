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

// Verificar que el personaje pertenece al usuario
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
if ($existing !== null) {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Este personaje ya tiene el Nen despierto.'], 400);
}

// Despertar Nen (principios nivel 1, hatsu 0)
$service->despertarNen($charId);

// Asignar tipo aleatorio
$nenTypes = ['enhancement', 'transmutation', 'emission', 'conjuration', 'manipulation', 'specialization'];
$randType = $nenTypes[array_rand($nenTypes)];
$service->setNenType($charId, $randType);

// Color de aura aleatorio
$auraColors = ['#E64A19', '#D81B60', '#43A047', '#1976D2', '#8E24AA', '#F57C00', '#00BCD4', '#FF6F00', '#C62828', '#4A148C', '#004D40', '#33691E'];
$randColor = $auraColors[array_rand($auraColors)];
global $db;
$db->write_query("UPDATE {$prefix}game_nen SET aura_color = '{$db->escape_string($randColor)}' WHERE character_id = {$charId}");

// Calcular control (%)
function calcNenControl(string $mainType): array {
    $types = ['enhancement', 'emission', 'conjuration', 'specialization', 'manipulation', 'transmutation'];
    $idx = array_search($mainType, $types, true);
    $controls = [];
    foreach ($types as $i => $t) {
        $dist = min(abs($i - $idx), 6 - abs($i - $idx));
        $pct = match ($dist) {
            0 => 100,
            1 => 80,
            2 => 60,
            default => 40,
        };
        $controls[$t] = $pct;
    }
    return $controls;
}
$controlPct = calcNenControl($randType);

GameAjax::json(true, [
    'character_id' => $charId,
    'nen_type' => $randType,
    'nen_type_label' => game_get_nen_type_label($randType),
    'nen_type_color' => game_get_nen_type_color($randType),
    'aura_color' => $randColor,
    'control' => $controlPct,
    'control_labels' => array_map(fn($t) => [
        'slug' => $t,
        'label' => game_get_nen_type_label($t),
        'color' => game_get_nen_type_color($t),
        'pct' => $controlPct[$t],
    ], array_keys($controlPct)),
]);
