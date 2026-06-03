<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\Services\CharacterSaveService;
use Game\Http\GameAjax;

header('Content-Type: application/json; charset=utf-8');

global $mybb, $db;

$userId = GameAjax::requireLogin();
GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$editPjId = (int)($input['pj_id'] ?? 0);
$isNpcInput = (int)($input['is_npc'] ?? 0) === 1;
$prefix = TABLE_PREFIX;
$saveService = new CharacterSaveService();

// Check if user is admin (staff_level = 3 on ANY character of this user)
$is_admin = false;
$check_admin_q = $db->query("SELECT COUNT(*) as cnt FROM {$prefix}game_personajes WHERE user_id = {$userId} AND staff_level = 3");
if ($db->fetch_field($check_admin_q, 'cnt') > 0) {
    $is_admin = true;
}

$isNpcMode = $isNpcInput && $is_admin;

if ($editPjId > 0) {
    if ($is_admin) {
        $q = $db->query("SELECT id, status, data_json, is_npc FROM {$prefix}game_personajes WHERE id = {$editPjId} LIMIT 1");
    } else {
        $q = $db->query("SELECT id, status, data_json, is_npc FROM {$prefix}game_personajes WHERE id = {$editPjId} AND user_id = {$userId} LIMIT 1");
    }
    $pj = $db->fetch_array($q);
    if (!$pj) {
        GameAjax::json(false, null, ['code' => 404, 'message' => 'Personaje no encontrado o sin permisos.'], 404);
    }

    $isNpc = (int)$pj['is_npc'] === 1;
    if (!($isNpc && $is_admin)) {
        if ($pj['status'] !== 'pendiente' && $pj['status'] !== 'revision') {
            GameAjax::json(false, null, ['code' => 403, 'message' => 'El personaje no puede ser editado en su estado actual.'], 403);
        }
    }

    $existing = !empty($pj['data_json']) ? json_decode($pj['data_json'], true) : [];
    if (!is_array($existing)) {
        $existing = [];
    }

    $built = $saveService->buildPayloadForUpdate($input, $existing);
    if (!$built['ok']) {
        GameAjax::json(false, null, ['code' => 400, 'message' => $built['message'] ?? 'Datos inválidos.'], 400);
    }

    $cols = $built['columns'];
    $update = [
        'name' => $db->escape_string($cols['name']),
        'race_name' => $db->escape_string($cols['race_name']),
        'faction' => $db->escape_string($cols['faction']),
        'rango' => $db->escape_string($cols['rango']),
        'occupation_name' => $db->escape_string($cols['occupation_name']),
        'avatar' => $db->escape_string($cols['avatar']),
        'data_json' => $db->escape_string(json_encode($built['data_json'], JSON_UNESCAPED_UNICODE)),
        'stats_json' => $db->escape_string(json_encode($built['stats_json'], JSON_UNESCAPED_UNICODE)),
    ];

    if ($isNpc && $is_admin) {
        $update['is_npc'] = 1;
    }

    $db->update_query('game_personajes', $update, "id = {$editPjId}");
    $newPjId = $editPjId;
} else {
    $slotQ = null;
    $maxSlots = 1;
    $actualUsed = 0;

    if (!$isNpcMode) {
        $slotQ = $db->query("SELECT max_slots, slots_used FROM {$prefix}game_user_config WHERE user_id = {$userId} LIMIT 1");
        $slotRow = $db->fetch_array($slotQ);
        $maxSlots = (int)($slotRow['max_slots'] ?? 1);

        $actualQ = $db->query("SELECT COUNT(*) AS cnt FROM {$prefix}game_personajes WHERE user_id = {$userId}");
        $actualUsed = (int)$db->fetch_field($actualQ, 'cnt');

        if ($actualUsed >= $maxSlots) {
            GameAjax::json(false, null, ['code' => 403, 'message' => 'Has alcanzado el límite de personajes.'], 403);
        }
    }

    $built = $saveService->buildPayloadForInsert($userId, $input);
    if (!$built['ok']) {
        GameAjax::json(false, null, ['code' => 400, 'message' => $built['message'] ?? 'Datos inválidos.'], 400);
    }

    $cols = $built['columns'];
    $db->insert_query('game_personajes', [
        'user_id' => $userId,
        'name' => $db->escape_string($cols['name']),
        'race_name' => $db->escape_string($cols['race_name']),
        'faction' => $db->escape_string($cols['faction']),
        'rango' => $db->escape_string($cols['rango']),
        'occupation_name' => $db->escape_string($cols['occupation_name']),
        'avatar' => $db->escape_string($cols['avatar']),
        'data_json' => $db->escape_string(json_encode($built['data_json'], JSON_UNESCAPED_UNICODE)),
        'stats_json' => $db->escape_string(json_encode($built['stats_json'], JSON_UNESCAPED_UNICODE)),
        'approved' => $isNpcMode ? 1 : 0,
        'status' => $isNpcMode ? 'aprobada' : 'pendiente',
        'is_npc' => $isNpcMode ? 1 : 0,
    ]);
    $newPjId = (int)$db->insert_id();

    if (!$isNpcMode) {
        if ($slotQ && $db->num_rows($slotQ) > 0) {
            $db->write_query("UPDATE {$prefix}game_user_config SET slots_used = {$actualUsed} + 1 WHERE user_id = {$userId}");
        } else {
            $db->write_query("INSERT INTO {$prefix}game_user_config (user_id, max_slots, slots_used, active_pj_id) VALUES ({$userId}, 1, 1, {$newPjId})");
        }
    }
}

game_log_action('save_personaje', ['uid' => $userId, 'pj_id' => $newPjId]);
GameAjax::json(true, ['pj_id' => $newPjId], null);
