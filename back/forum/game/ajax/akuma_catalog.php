<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/autoload.php';

use Game\Application\Services\AdminRequestService;
use Game\Http\GameAjax;

global $db, $mybb;

$uid = GameAjax::requireLogin();

$prefix = TABLE_PREFIX;

if (!$db->table_exists('game_akuma_no_mi')) {
    GameAjax::json(true, ['fruits' => [], 'categories' => []]);
}

$hasOccupied = $db->field_exists('is_occupied', 'game_akuma_no_mi');
$hasReserved = $db->field_exists('is_reserved', 'game_akuma_no_mi');
$hasRange = $db->field_exists('power_range', 'game_akuma_no_mi');

$cols = 'id, name, class, class_name, `desc`, details, tipo_fruta, status, status_name';
if ($hasOccupied) {
    $cols .= ', is_occupied';
}
if ($hasReserved) {
    $cols .= ', is_reserved';
}
if ($hasRange) {
    $cols .= ', power_range';
}

$q = $db->query("SELECT {$cols} FROM {$prefix}game_akuma_no_mi ORDER BY class_name ASC, name ASC");
$fruits = [];
while ($row = $db->fetch_array($q)) {
    $class = (string)($row['class'] ?? '');
    $category = 'paramecia';
    if (strpos($class, 'logia') === 0) {
        $category = 'logia';
    } elseif (strpos($class, 'zoan') === 0) {
        $category = 'zoan';
    } elseif ($class === 'paramecia' || strpos($class, 'paramecia') === 0) {
        $category = 'paramecia';
    }

    $fruits[] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'class' => $class,
        'class_name' => $row['class_name'] ?? '',
        'category' => $category,
        'desc' => $row['desc'] ?? '',
        'details' => $row['details'] ?? '',
        'tipo_fruta' => $row['tipo_fruta'] ?? '',
        'status' => $row['status'] ?? '',
        'status_name' => $row['status_name'] ?? '',
        'is_occupied' => $hasOccupied ? (bool)(int)($row['is_occupied'] ?? 0) : ($row['status'] ?? '') !== 'disponible',
        'is_reserved' => $hasReserved ? (bool)(int)($row['is_reserved'] ?? 0) : false,
        'power_range' => $hasRange ? (string)($row['power_range'] ?? 'Sin asignar') : 'Sin asignar',
    ];
}

$available = array_values(array_filter($fruits, static function (array $f): bool {
    return !$f['is_occupied'] && !$f['is_reserved'];
}));

$rollState = ['can_roll' => true, 'reason' => '', 'request_id' => null, 'status' => null];
try {
    $characterId = AdminRequestService::requireActiveCharacter($uid);
    $rollState = AdminRequestService::characterAkumaRandomRollState($characterId);
} catch (\RuntimeException $e) {
    $rollState = ['can_roll' => false, 'reason' => $e->getMessage(), 'request_id' => null, 'status' => null];
}

$stats = ['total' => count($fruits), 'libre' => 0, 'reservada' => 0, 'ocupada' => 0];
foreach ($fruits as $f) {
    if ($f['is_occupied']) {
        $stats['ocupada']++;
    } elseif ($f['is_reserved']) {
        $stats['reservada']++;
    } else {
        $stats['libre']++;
    }
}

GameAjax::json(true, [
    'fruits' => $fruits,
    'available_count' => count($available),
    'stats' => $stats,
    'roll' => $rollState,
    'categories' => [
        ['key' => 'logia', 'label' => 'Logia'],
        ['key' => 'zoan', 'label' => 'Zoan'],
        ['key' => 'paramecia', 'label' => 'Paramecia'],
    ],
]);
