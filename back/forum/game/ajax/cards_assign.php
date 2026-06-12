<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;
use Game\Shared\StatScale;

global $db;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();

$prefix = TABLE_PREFIX;
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;
if ($active_pj_id > 0) {
    $pj_q = $db->query("SELECT staff_level FROM {$prefix}game_personajes WHERE id = {$active_pj_id} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    $staff_level = $pj ? (int)$pj['staff_level'] : 0;
}

if ($staff_level < 3) {
    echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => 'Permisos insuficientes.']]);
    exit;
}

$input = GameAjax::postJson();
GameAjax::requireCsrf($input);
$character_id = (int)($input['character_id'] ?? 0);
$card_id = (int)($input['card_id'] ?? 0);
$cantidad = max(1, (int)($input['cantidad'] ?? 1));

if ($character_id <= 0 || $card_id <= 0) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'IDs inválidos.']]);
    exit;
}

// Buscar el rango original de la carta en la base de datos
$extraCols = '';
if ($db->field_exists('tier', 'game_cards')) {
    $extraCols .= ', tier';
}
if ($db->field_exists('disciplina_slug', 'game_cards')) {
    $extraCols .= ', disciplina_slug';
}
if ($db->field_exists('oficio_slug', 'game_cards')) {
    $extraCols .= ', oficio_slug';
}
$card_q = $db->query("SELECT `rank`, card_type, effects_json, tags_json{$extraCols} FROM {$prefix}game_cards WHERE id = {$card_id} LIMIT 1");
$card = $db->fetch_array($card_q);
if (!$card) {
    echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'La carta seleccionada no existe.']]);
    exit;
}
$rank = $db->escape_string($card['rank']);

if (($card['card_type'] ?? '') === 'akuma_no_mi') {
    $akumaErr = game_akuma_assignment_error($character_id, $card);
    if ($akumaErr !== null) {
        echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => $akumaErr]]);
        exit;
    }
}

$compErr = game_card_assignment_competencia_error($character_id, $card);
if ($compErr !== null) {
    echo json_encode(['ok' => false, 'error' => ['code' => 403, 'message' => $compErr]]);
    exit;
}

if (($card['card_type'] ?? '') === 'haki') {
    $efCheck = json_decode($card['effects_json'] ?? '{}', true);
    $hakiType = (string)($efCheck['haki_type'] ?? 'busoshoku');
    $hakiLevel = (string)($efCheck['haki_level'] ?? 'basico');

    $levelMap = [
        'obs_latente' => 1, 'arm_latente' => 1, 'rey_latente' => 1,
        'obs_basico' => 2, 'arm_basico' => 2, 'rey_basico' => 2,
        'obs_medio' => 3, 'arm_medio' => 3, 'rey_medio' => 3,
        'obs_avanzado' => 4, 'arm_interno' => 4, 'rey_avanzado' => 4,
        'obs_futuro' => 5, 'arm_supremo' => 5, 'rey_supremo' => 5,
    ];
    $minHakiLevel = $levelMap[$hakiLevel] ?? 5;

    // Consultar game_haki_progress
    $haki_q = $db->query("
        SELECT nivel FROM {$prefix}game_haki_progress 
        WHERE character_id = {$character_id} AND haki_type = '{$db->escape_string($hakiType)}' 
        LIMIT 1
    ");
    $haki_row = $db->fetch_array($haki_q);
    $playerHakiLevel = $haki_row ? (int)$haki_row['nivel'] : 0;

    if ($playerHakiLevel < $minHakiLevel) {
        $hakiName = $hakiType === 'kenbunshoku' ? 'Observación' : ($hakiType === 'busoshoku' ? 'Armamento' : 'Conquistador');
        echo json_encode([
            'ok' => false, 
            'error' => [
                'code' => 403, 
                'message' => "Nivel de Haki de {$hakiName} insuficiente. Requerido: Grado {$minHakiLevel}, Tienes: Grado {$playerHakiLevel}."
            ]
        ]);
        exit;
    }
}

$ef = json_decode($card['effects_json'] ?? '{}', true);
$tags = json_decode($card['tags_json'] ?? '[]', true);
$is_consumible = ($card['card_type'] === 'equipo' && strtolower((string)($ef['equipo_type'] ?? '')) === 'util');
if (!$is_consumible && is_array($tags)) {
    foreach ($tags as $t) {
        $u = strtoupper((string)$t);
        if (in_array($u, ['CONSUMIBLE', 'MUNICION', 'AMMO'], true)) {
            $is_consumible = true;
            break;
        }
    }
}
$assign_qty = $is_consumible ? $cantidad : 1;

$has_cantidad = $db->field_exists('cantidad', 'game_character_cards');
$cantidad_sql = $has_cantidad ? ", cantidad = cantidad + {$assign_qty}" : '';

// Insert or update on duplicate key
$db->write_query("
    INSERT INTO {$prefix}game_character_cards (character_id, card_id, current_rank, assigned_by" . ($has_cantidad ? ", cantidad" : "") . ")
    VALUES ({$character_id}, {$card_id}, '{$rank}', {$uid}" . ($has_cantidad ? ", {$assign_qty}" : "") . ")
    ON DUPLICATE KEY UPDATE current_rank = '{$rank}', assigned_by = {$uid}{$cantidad_sql}
");

echo json_encode(['ok' => true, 'data' => null, 'error' => null]);
