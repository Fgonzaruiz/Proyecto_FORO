<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $mybb, $db;

$uid = GameAjax::requireLogin();
$prefix = TABLE_PREFIX;

$fid = (int)($_GET['fid'] ?? 0);
$charId = (int)($_GET['character_id'] ?? 0);
$tid = (int)($_GET['tid'] ?? 0);

if ($fid <= 0 && $tid > 0) {
    $tr = $db->fetch_array($db->query("SELECT fid FROM {$prefix}threads WHERE tid = {$tid} LIMIT 1"));
    $fid = $tr ? (int)$tr['fid'] : 0;
}

if ($charId <= 0) {
    $cfg = $db->fetch_array($db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1"));
    $charId = $cfg ? (int)$cfg['active_pj_id'] : 0;
}

$island = $fid > 0 ? game_nav_get_island_from_forum($fid) : null;
$ships = $charId > 0 ? game_nav_ships_for_character($charId) : [];
$naveganteRank = $charId > 0 ? game_oficio_get_rank($charId, 'navegante') : 0;

$instruments = $charId > 0 ? game_nav_instruments_for_character($charId) : [];

GameAjax::json(true, [
    'island_fid' => $island ? (int)$island['fid'] : 0,
    'island_name' => $island ? ($island['forum_name'] ?? '') : '',
    'has_island' => $island !== null,
    'has_ship' => count($ships) > 0,
    'ships_count' => count($ships),
    'ships' => $ships,
    'instruments' => $instruments,
    'character_id' => $charId,
    'navegante_rank' => $naveganteRank,
    'navegante_label' => $naveganteRank > 0 ? game_oficio_rank_label($naveganteRank) : null,
    'navegante_bonus' => game_oficio_rank_bonus($naveganteRank),
    'can_navigate' => $island !== null && count($ships) > 0,
]);
