<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $mybb, $db;

$uid = GameAjax::requireLogin();
$prefix = TABLE_PREFIX;
$charId = (int)($_GET['character_id'] ?? 0);

if ($charId <= 0) {
    $cfg = $db->fetch_array($db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1"));
    $charId = $cfg ? (int)$cfg['active_pj_id'] : 0;
}

if ($charId <= 0) {
    GameAjax::fail(400, 'Personaje no encontrado');
}

$pj = $db->fetch_array($db->query("SELECT user_id FROM {$prefix}game_personajes WHERE id = {$charId} LIMIT 1"));
if (!$pj || ((int)$pj['user_id'] !== $uid && game_get_active_staff_level($uid) < 2)) {
    GameAjax::fail(403, 'Sin permiso');
}

GameAjax::json(true, ['ships' => game_nav_ships_for_character($charId)]);
