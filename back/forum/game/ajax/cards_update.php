<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

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
$card_id = (int)($input['card_id'] ?? 0);
if (empty($input['name']) || $card_id <= 0) {
    echo json_encode(['ok' => false, 'error' => ['code' => 400, 'message' => 'Payload inválido o ID faltante.']]);
    exit;
}

$name = $db->escape_string($input['name']);
$card_type = $db->escape_string($input['card_type'] ?? 'tecnica');
$rank = $db->escape_string($input['rank'] ?? 'C');
$activation = $db->escape_string($input['activation'] ?? 'activa');
$tags_json = $db->escape_string(json_encode($input['tags'] ?? [], JSON_UNESCAPED_UNICODE));
$description = $db->escape_string($input['description'] ?? '');
$cost_pe = $db->escape_string($input['cost_pe'] ?? '—');
$execution_stat = $db->escape_string($input['execution_stat'] ?? '');
$dice = $db->escape_string($input['dice'] ?? '');
$effects_json = $db->escape_string(json_encode($input['effects'] ?? [], JSON_UNESCAPED_UNICODE));
$upgrade_json = $db->escape_string(json_encode($input['upgrade'] ?? [], JSON_UNESCAPED_UNICODE));
$notes = $db->escape_string($input['notes'] ?? '');
$image_url = $db->escape_string($input['image_url'] ?? '');
$reposo = isset($input['reposo']) ? (int)$input['reposo'] : 0;
$duracion = isset($input['duracion']) ? (int)$input['duracion'] : 0;

$update = [
    'name' => $name,
    'card_type' => $card_type,
    'rank' => $rank,
    'activation' => $activation,
    'tags_json' => $tags_json,
    'description' => $description,
    'cost_pe' => $cost_pe,
    'execution_stat' => $execution_stat,
    'dice' => $dice,
    'effects_json' => $effects_json,
    'upgrade_json' => $upgrade_json,
    'notes' => $notes,
    'image_url' => $image_url,
    'reposo' => $reposo,
    'duracion' => $duracion
];

$db->update_query('game_cards', $update, "id = {$card_id}");

echo json_encode(['ok' => true, 'data' => ['card_id' => $card_id], 'error' => null]);
