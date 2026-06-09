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

game_oficio_set_character_rank($charId, $oficioId, $rank);
GameAjax::json(true, [
    'character_id' => $charId,
    'oficio_id' => $oficioId,
    'rank' => max(1, min(5, $rank)),
    'rank_label' => game_oficio_rank_label($rank),
]);
