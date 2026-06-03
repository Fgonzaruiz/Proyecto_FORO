<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/autoload.php';

use Game\Application\Services\AdminRequestService;
use Game\Http\GameAjax;

global $db, $mybb;

$uid = GameAjax::requireLogin();
GameAjax::requirePost();
GameAjax::requireCsrf($_POST);

if (!$db->table_exists('game_admin_requests')) {
    GameAjax::fail(503, 'Sistema de peticiones no instalado. Ejecuta migrate_akuma_peticiones.php');
}

try {
    $characterId = AdminRequestService::requireActiveCharacter($uid);
} catch (\RuntimeException $e) {
    GameAjax::fail(400, $e->getMessage());
}

$prefix = TABLE_PREFIX;
$occupiedCol = $db->field_exists('is_occupied', 'game_akuma_no_mi') ? 'is_occupied' : null;

$reservedCol = $db->field_exists('is_reserved', 'game_akuma_no_mi');
if ($occupiedCol && $reservedCol) {
    $q = $db->query("SELECT id, name, class, class_name, `desc`, power_range FROM {$prefix}game_akuma_no_mi WHERE is_occupied = 0 AND is_reserved = 0 ORDER BY id ASC");
} elseif ($occupiedCol) {
    $q = $db->query("SELECT id, name, class, class_name, `desc`, power_range FROM {$prefix}game_akuma_no_mi WHERE is_occupied = 0 ORDER BY id ASC");
} else {
    $q = $db->query("SELECT id, name, class, class_name, `desc` FROM {$prefix}game_akuma_no_mi WHERE status = 'disponible' ORDER BY id ASC");
}

$pool = [];
while ($row = $db->fetch_array($q)) {
    $pool[] = $row;
}

if (count($pool) === 0) {
    GameAjax::fail(409, 'No hay Akuma no Mi disponibles para tirada aleatoria.');
}

$pick = $pool[random_int(0, count($pool) - 1)];
$fruitId = (int)$pick['id'];
$fruitName = (string)$pick['name'];

$pj_q = $db->query("SELECT name FROM {$prefix}game_personajes WHERE id = {$characterId} LIMIT 1");
$pj = $db->fetch_array($pj_q);
$pjName = $pj['name'] ?? 'Personaje';

$title = "Akuma aleatoria: {$fruitName}";
$description = "El personaje {$pjName} ha obtenido la fruta «{$fruitName}» mediante tirada aleatoria. "
    . "Clase: " . ($pick['class_name'] ?? $pick['class'] ?? '') . ". "
    . "Pendiente de validación del staff.";

$payload = [
    'mode' => 'random',
    'fruit_id' => $fruitId,
    'fruit_name' => $fruitName,
    'class' => $pick['class'] ?? '',
    'class_name' => $pick['class_name'] ?? '',
];

try {
    AdminRequestService::reserveAkumaFruit($fruitId);
} catch (\RuntimeException $e) {
    GameAjax::fail(409, $e->getMessage());
}

$requestId = AdminRequestService::create(
    $uid,
    $characterId,
    'akuma_random',
    'fruta_diablo',
    $title,
    $description,
    null,
    $payload,
    $fruitId
);

AdminRequestService::notifyStaffPending(
    "Nueva petición Akuma (aleatoria): {$fruitName} — {$pjName}",
    '/game/public/zona_staff_peticiones.php'
);

$powerRange = '';
if ($db->field_exists('power_range', 'game_akuma_no_mi')) {
    $r_q = $db->query("SELECT power_range FROM {$prefix}game_akuma_no_mi WHERE id = {$fruitId} LIMIT 1");
    $r = $db->fetch_array($r_q);
    $powerRange = $r['power_range'] ?? '';
}

GameAjax::json(true, [
    'request_id' => $requestId,
    'fruit' => [
        'id' => $fruitId,
        'name' => $fruitName,
        'class' => $pick['class'] ?? '',
        'class_name' => $pick['class_name'] ?? '',
        'desc' => $pick['desc'] ?? '',
        'power_range' => $powerRange,
    ],
]);
