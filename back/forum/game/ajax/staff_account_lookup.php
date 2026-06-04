<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\Services\StaffAccountService;
use Game\Http\GameAjax;

header('Content-Type: application/json; charset=utf-8');

global $mybb, $db;

$uid = GameAjax::requireLogin();
if (game_get_active_staff_level($uid) < 3) {
    GameAjax::json(false, null, ['code' => 403, 'message' => 'Se requiere nivel de staff 3 (Administrador).'], 403);
}

$q = trim((string)($_GET['q'] ?? ''));
if ($q === '') {
    GameAjax::json(false, null, ['code' => 400, 'message' => 'Indica un nombre de usuario o UID.'], 400);
}

$service = new StaffAccountService($db, TABLE_PREFIX, $uid);
$user = $service->findUserByQuery($q);
if ($user === null) {
    GameAjax::json(false, null, ['code' => 404, 'message' => 'No se encontró ningún usuario.'], 404);
}

try {
    $details = $service->getAccountDetails((int)$user['uid']);
} catch (\InvalidArgumentException $e) {
    GameAjax::json(false, null, ['code' => 403, 'message' => $e->getMessage()], 403);
}

GameAjax::json(true, $details);
