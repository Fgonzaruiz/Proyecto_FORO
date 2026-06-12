<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $db;

$uid = GameAjax::requireLogin();
if (game_get_active_staff_level($uid) < 2) {
    GameAjax::fail(403, 'Solo staff puede asignar disciplinas');
}

GameAjax::requirePost();
$input = GameAjax::postJson();
GameAjax::requireCsrf($input);

$charId = (int)($input['character_id'] ?? 0);
$disciplinaId = (int)($input['disciplina_id'] ?? 0);
$rank = (int)($input['rank'] ?? 1);
$remove = !empty($input['remove']);

if ($charId <= 0 || $disciplinaId <= 0) {
    GameAjax::fail(400, 'Datos inválidos');
}

$prefix = TABLE_PREFIX;
$pj = $db->fetch_array($db->query("SELECT id FROM {$prefix}game_personajes WHERE id = {$charId} LIMIT 1"));
if (!$pj) {
    GameAjax::fail(404, 'Personaje no encontrado');
}

if ($remove) {
    game_disciplina_remove_from_character($charId, $disciplinaId);
    GameAjax::json(true, ['removed' => true]);
}

$newRank = max(1, min(5, $rank));
$oldRank = 0;
$oldQ = $db->query("SELECT `rank` FROM {$prefix}game_character_disciplinas WHERE character_id = {$charId} AND disciplina_id = {$disciplinaId} LIMIT 1");
if ($oldRow = $db->fetch_array($oldQ)) {
    $oldRank = (int)$oldRow['rank'];
}
if ($newRank > $oldRank) {
    $ecoErr = game_grado_staff_apply_rank_change($charId, $oldRank, $newRank, 'disciplina');
    if ($ecoErr !== null) {
        GameAjax::fail(400, $ecoErr);
    }
}

game_disciplina_set_character_rank($charId, $disciplinaId, $newRank);
GameAjax::json(true, [
    'character_id' => $charId,
    'disciplina_id' => $disciplinaId,
    'rank' => $newRank,
    'rank_label' => game_disciplina_rank_label($newRank),
]);
