<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

header('Content-Type: application/json; charset=utf-8');

global $mybb, $db;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $characterId = isset($_GET['character_id']) ? (int)$_GET['character_id'] : 0;
    if ($characterId <= 0) {
        GameAjax::json(false, null, ['code' => 400, 'message' => 'character_id requerido'], 400);
    }

    $prefix = TABLE_PREFIX;
    $uid = (int)$mybb->user['uid'];

    $q = $db->query("SELECT data_json FROM {$prefix}game_personajes WHERE id = {$characterId} AND user_id = {$uid} LIMIT 1");
    $row = $db->fetch_array($q);

    if (!$row) {
        $q = $db->query("SELECT data_json FROM {$prefix}game_personajes WHERE id = {$characterId} LIMIT 1");
        $row = $db->fetch_array($q);
        if (!$row) {
            GameAjax::json(false, null, ['code' => 404, 'message' => 'Personaje no encontrado'], 404);
        }
    }

    $data = !empty($row['data_json']) ? json_decode($row['data_json'], true) : [];
    $templates = $data['post_templates'] ?? [];

    GameAjax::json(true, ['templates' => $templates]);

} elseif ($method === 'POST') {
    $uid = GameAjax::requireLogin();
    GameAjax::requirePost();
    $input = GameAjax::postJson();
    GameAjax::requireCsrf($input);

    $characterId = (int)($input['character_id'] ?? 0);
    $templates = $input['templates'] ?? [];

    if ($characterId <= 0) {
        GameAjax::json(false, null, ['code' => 400, 'message' => 'character_id requerido'], 400);
    }

    $prefix = TABLE_PREFIX;

    $q = $db->query("SELECT data_json FROM {$prefix}game_personajes WHERE id = {$characterId} AND user_id = {$uid} LIMIT 1");
    $row = $db->fetch_array($q);

    if (!$row) {
        GameAjax::json(false, null, ['code' => 404, 'message' => 'Personaje no encontrado o no te pertenece'], 404);
    }

    $data = !empty($row['data_json']) ? json_decode($row['data_json'], true) : [];
    if (!is_array($data)) {
        $data = [];
    }

    $data['post_templates'] = $templates;

    $dataJsonEsc = $db->escape_string(json_encode($data, JSON_UNESCAPED_UNICODE));
    $db->write_query("UPDATE {$prefix}game_personajes SET data_json = '{$dataJsonEsc}' WHERE id = {$characterId}");

    GameAjax::json(true, ['saved' => true]);

} else {
    GameAjax::json(false, null, ['code' => 405, 'message' => 'Method not allowed'], 405);
}
