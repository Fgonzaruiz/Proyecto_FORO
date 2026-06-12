<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;
use Game\Infrastructure\Persistence\PersonajeRepository;

header('Content-Type: application/json; charset=utf-8');

global $db;

$uid = GameAjax::requireLogin();
$charId = (int)($_GET['character_id'] ?? 0);

if ($charId <= 0) {
    GameAjax::fail(400, 'character_id inválido');
}

$personajes = new PersonajeRepository();
$character = $personajes->findByIdForUser($charId, $uid);

// Or if they are staff
if ($character === null && game_get_active_staff_level($uid) < 2) {
    GameAjax::fail(403, 'Sin permiso');
}

$totalPd = game_get_character_pd_total($charId);
$spentPd = game_get_character_pd_spent($charId);
$availablePd = game_get_character_pd_available($charId);
$purchases = game_get_character_purchases($charId);

GameAjax::json(true, [
    'character_id' => $charId,
    'total_pd' => $totalPd,
    'spent_pd' => $spentPd,
    'available_pd' => $availablePd,
    'purchases' => $purchases,
]);
