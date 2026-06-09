<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $db, $mybb;

$uid = GameAjax::requireLogin();
$prefix = TABLE_PREFIX;
$charId = (int)($_GET['character_id'] ?? 0);

if ($charId <= 0) {
    GameAjax::fail(400, 'character_id inválido');
}

$pj = $db->fetch_array($db->query(
    "SELECT id, user_id, data_json, berries FROM {$prefix}game_personajes WHERE id = {$charId} LIMIT 1"
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

$charLevel = game_character_level_from_data($data);
$lastUpgrade = game_grado_last_upgrade_at($data);

$disciplinas = [];
foreach (game_disciplina_list_for_character($charId) as $row) {
    $disciplinas[] = game_grado_enrich_row($row, 'disciplina', $charLevel, $lastUpgrade);
}

$oficios = [];
foreach (game_oficio_list_for_character($charId) as $row) {
    $oficios[] = game_grado_enrich_row($row, 'oficio', $charLevel, $lastUpgrade);
}

$nivelReqs = [];
for ($g = 1; $g <= 5; $g++) {
    $nivelReqs[] = [
        'rank' => $g,
        'label' => game_grado_label($g),
        'nivel_required' => game_grado_nivel_required($g),
        'upgrade_price' => $g > 1 ? game_grado_upgrade_price($g) : 0,
    ];
}

GameAjax::json(true, [
    'disciplinas' => $disciplinas,
    'oficios' => $oficios,
    'character_level' => $charLevel,
    'berries' => (int)($pj['berries'] ?? 0),
    'cooldown_days' => game_grado_cooldown_days(),
    'cooldown_ok' => game_grado_cooldown_ok($lastUpgrade),
    'cooldown_days_left' => game_grado_cooldown_remaining_days($lastUpgrade),
    'nivel_requirements' => $nivelReqs,
    'can_edit' => $isStaff,
]);
