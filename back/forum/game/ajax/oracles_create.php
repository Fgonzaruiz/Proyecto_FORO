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
    GameAjax::fail(403, 'Permisos insuficientes.');
}

$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

if (empty($input['name']) || empty($input['results'])) {
    GameAjax::fail(400, 'Payload inválido: nombre y results son obligatorios.');
}

$name = $db->escape_string($input['name']);
$description = $db->escape_string($input['description'] ?? '');
$oracle_type = $db->escape_string($input['oracle_type'] ?? 'custom');
$subtype = $db->escape_string($input['subtype'] ?? '');
$category = $db->escape_string($input['category'] ?? '');
$tags_json = $db->escape_string(json_encode($input['tags'] ?? [], JSON_UNESCAPED_UNICODE));
$results_json = $db->escape_string(json_encode($input['results'], JSON_UNESCAPED_UNICODE));
$variations_json = $db->escape_string(json_encode($input['variations'] ?? [], JSON_UNESCAPED_UNICODE));
$auto_invoke_json = $db->escape_string(json_encode($input['auto_invoke'] ?? [], JSON_UNESCAPED_UNICODE));
$dice_type = $db->escape_string($input['dice_type'] ?? 'd100');
$image_url = $db->escape_string($input['image_url'] ?? '');

$insert = [
    'name' => $name,
    'description' => $description,
    'oracle_type' => $oracle_type,
    'subtype' => $subtype,
    'category' => $category,
    'tags_json' => $tags_json,
    'results_json' => $results_json,
    'variations_json' => $variations_json,
    'auto_invoke_json' => $auto_invoke_json,
    'dice_type' => $dice_type,
    'image_url' => $image_url,
    'created_by' => $uid,
];

$db->insert_query('game_oracles', $insert);
$oracle_id = $db->insert_id();

GameAjax::json(true, ['oracle_id' => $oracle_id]);
