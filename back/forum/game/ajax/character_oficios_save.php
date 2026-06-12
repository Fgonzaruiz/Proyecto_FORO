<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $db;

$uid = GameAjax::requireLogin();
if (game_get_active_staff_level($uid) < 2) {
    GameAjax::fail(403, 'Solo staff puede asignar oficios');
}

GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$charId = (int)($input['character_id'] ?? 0);
$oficioId = (int)($input['oficio_id'] ?? 0);
$rank = (int)($input['rank'] ?? 1);
$remove = !empty($input['remove']);

if ($charId <= 0 || $oficioId <= 0) {
    GameAjax::fail(400, 'Datos inválidos');
}

$prefix = TABLE_PREFIX;
$pj = $db->fetch_array($db->query("SELECT id FROM {$prefix}game_personajes WHERE id = {$charId} LIMIT 1"));
if (!$pj) {
    GameAjax::fail(404, 'Personaje no encontrado');
}

if ($remove) {
    game_oficio_remove_from_character($charId, $oficioId);
    GameAjax::json(true, ['removed' => true]);
}

$newRank = max(1, min(5, $rank));
$oldRank = 0;
$oldQ = $db->query("SELECT `rank` FROM {$prefix}game_character_oficios WHERE character_id = {$charId} AND oficio_id = {$oficioId} LIMIT 1");
if ($oldRow = $db->fetch_array($oldQ)) {
    $oldRank = (int)$oldRow['rank'];
}
if ($newRank > $oldRank) {
    $ecoErr = game_grado_staff_apply_rank_change($charId, $oldRank, $newRank, 'oficio');
    if ($ecoErr !== null) {
        GameAjax::fail(400, $ecoErr);
    }
}

game_oficio_set_character_rank($charId, $oficioId, $newRank);
GameAjax::json(true, [
    'character_id' => $charId,
    'oficio_id' => $oficioId,
    'rank' => $newRank,
    'rank_label' => game_oficio_rank_label($newRank),
]);
