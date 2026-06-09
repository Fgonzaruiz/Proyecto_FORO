<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $db;

GameAjax::requireLogin();

$fromFid = (int)($_GET['island_from'] ?? 0);
$toFid = (int)($_GET['island_to'] ?? 0);
$shipCardId = (int)($_GET['ship_card_id'] ?? 0);
$charId = (int)($_GET['character_id'] ?? 0);
$instrument = preg_replace('/[^a-z_]/', '', (string)($_GET['instrument'] ?? 'none'));
if ($instrument === '') {
    $instrument = 'none';
}

if ($fromFid <= 0 || $toFid <= 0 || $shipCardId <= 0) {
    GameAjax::fail(400, 'Parámetros incompletos');
}

$prefix = TABLE_PREFIX;
$card = $db->fetch_array($db->query("SELECT effects_json FROM {$prefix}game_cards WHERE id = {$shipCardId} AND card_type = 'barco' LIMIT 1"));
if (!$card) {
    GameAjax::fail(404, 'Barco no encontrado');
}

$effects = json_decode($card['effects_json'] ?? '{}', true);
if (!is_array($effects)) {
    $effects = [];
}

$navigatorRank = $charId > 0 ? game_oficio_get_rank($charId, 'navegante') : 0;
$result = game_nav_compute_voyage($fromFid, $toFid, $effects, $navigatorRank, $instrument);

if (empty($result['ok'])) {
    GameAjax::fail(400, $result['error'] ?? 'No se pudo calcular');
}

GameAjax::json(true, $result);
