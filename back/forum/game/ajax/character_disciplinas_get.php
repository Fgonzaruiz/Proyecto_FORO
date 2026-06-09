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

$pj = $db->fetch_array($db->query("SELECT id, user_id FROM {$prefix}game_personajes WHERE id = {$charId} LIMIT 1"));
if (!$pj) {
    GameAjax::fail(404, 'Personaje no encontrado');
}

$isOwner = (int)$pj['user_id'] === $uid;
$isStaff = game_get_active_staff_level($uid) >= 2;
if (!$isOwner && !$isStaff) {
    GameAjax::fail(403, 'Sin permiso');
}

$disciplinas = game_disciplina_list_for_character($charId);

GameAjax::json(true, [
    'disciplinas' => $disciplinas,
    'can_edit' => $isStaff,
]);
