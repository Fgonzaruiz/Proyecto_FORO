<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\Services\CharacterProgression;
use Game\Http\GameAjax;
use Game\Shared\StatScale;

global $db, $mybb;

$uid = GameAjax::requireLogin();
$prefix = TABLE_PREFIX;
$charId = (int)($_GET['character_id'] ?? 0);

if ($charId <= 0) {
    GameAjax::fail(400, 'character_id inválido');
}

$pj = $db->fetch_array($db->query(
    "SELECT id, user_id, data_json, stats_json, race_name, berries FROM {$prefix}game_personajes WHERE id = {$charId} LIMIT 1"
));
if (!$pj) {
    GameAjax::fail(404, 'Personaje no encontrado');
}

$isOwner = (int)$pj['user_id'] === $uid;
$isStaff = game_get_active_staff_level($uid) >= 2;
if (!$isOwner && !$isStaff) {
    GameAjax::fail(403, 'Sin permiso');
}

$data = !empty($pj['data_json']) ? json_decode($pj['data_json'], true) : [];
if (!is_array($data)) {
    $data = [];
}
CharacterProgression::normalize($data);

$stats = !empty($pj['stats_json']) ? json_decode($pj['stats_json'], true) : [];
if (!is_array($stats)) {
    $stats = [];
}
$statCtx = game_build_stat_context(StatScale::sanitizeRanks($stats), (string)($pj['race_name'] ?? ''));

$charLevel = game_character_level_from_data($data);
$charNivel = game_get_character_nivel($data);
$ppAvailable = (int)($data['pp'] ?? 0);
$espEffectiveRank = (int)($statCtx['effective_ranks']['esp'] ?? 1);
$lastUpgrade = game_grado_last_upgrade_at($data);
$lastUpgradeRank = game_grado_last_upgrade_rank($data);
$disciplinas = [];
foreach (game_disciplina_list_for_character($charId) as $row) {
    $disciplinas[] = game_grado_enrich_row($row, 'disciplina', $charNivel, $lastUpgrade, $lastUpgradeRank, $ppAvailable);
}

$oficios = [];
foreach (game_oficio_list_for_character($charId) as $row) {
    $oficios[] = game_grado_enrich_row($row, 'oficio', $charNivel, $lastUpgrade, $lastUpgradeRank, $ppAvailable);
}

$nivelReqs = [];
for ($g = 1; $g <= 5; $g++) {
    $nivelReqs[] = [
        'rank' => $g,
        'label' => game_grado_label($g),
        'nivel_required' => game_grado_nivel_required($g),
        'upgrade_price_pp_disciplina' => $g > 1 ? game_grado_upgrade_price($g, 'disciplina') : 0,
        'upgrade_price_pp_oficio' => $g > 1 ? game_grado_upgrade_price($g, 'oficio') : 0,
    ];
}

$nextOficioCost = game_get_acquisition_cost(game_oficio_count_for_character($charId), 'oficio');
$nextDisciplinaCost = game_get_acquisition_cost(game_disciplina_count_for_character($charId), 'disciplina');
$nextOficioNivel = game_get_acquisition_level_required(game_oficio_count_for_character($charId));
$nextDisciplinaNivel = game_get_acquisition_level_required(game_disciplina_count_for_character($charId));

$acquire = null;
if ($isOwner) {
    $acquire = [
        'pp' => $ppAvailable,
        'nivel' => $charNivel,
        'esp_effective_rank' => $espEffectiveRank,
        'oficios_owned' => game_oficio_count_for_character($charId),
        'disciplinas_owned' => game_disciplina_count_for_character($charId),
        'next_oficio' => [
            'pp_cost' => $nextOficioCost,
            'nivel_required' => $nextOficioNivel,
        ],
        'next_disciplina' => [
            'pp_cost' => $nextDisciplinaCost,
            'nivel_required' => $nextDisciplinaNivel,
        ],
        'catalog_oficios' => game_oficio_acquire_catalog_for_character($charId, $charNivel, $ppAvailable),
        'catalog_disciplinas' => game_disciplina_acquire_catalog_for_character($charId, $charNivel, $ppAvailable, $espEffectiveRank),
    ];
}

GameAjax::json(true, [
    'disciplinas' => $disciplinas,
    'oficios' => $oficios,
    'character_level' => $charLevel,
    'character_nivel' => $charNivel,
    'pp' => $ppAvailable,
    'berries' => (int)($pj['berries'] ?? 0),
    'cooldown_days' => game_grado_cooldown_days(),
    'cooldown_days_by_rank' => game_grado_cooldown_days_map(),
    'cooldown_ok' => game_grado_cooldown_ok($lastUpgrade, $lastUpgradeRank),
    'cooldown_days_left' => game_grado_cooldown_remaining_days($lastUpgrade, $lastUpgradeRank),
    'can_request_grado' => $isOwner,
    'nivel_requirements' => $nivelReqs,
    'can_edit' => $isStaff,
    'can_acquire' => $isOwner,
    'acquire' => $acquire,
]);
