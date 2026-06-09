<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $db;

$uid = GameAjax::requireLogin();
if (game_get_active_staff_level($uid) < 2) {
    GameAjax::fail(403, 'Sin permiso');
}

if (!$db->table_exists('game_navigation_voyages')) {
    GameAjax::json(true, ['voyages' => []]);
}

$prefix = TABLE_PREFIX;
$threadId = (int)($_GET['thread_id'] ?? 0);
$charId = (int)($_GET['character_id'] ?? 0);
$where = [];
if ($threadId > 0) {
    $where[] = 'v.thread_id = ' . $threadId;
}
if ($charId > 0) {
    $where[] = 'v.character_id = ' . $charId;
}
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$reviewFilter = (string)($_GET['staff_review'] ?? '');
if ($reviewFilter !== '' && in_array($reviewFilter, ['pending', 'approved', 'denied'], true)) {
    if ($db->field_exists('staff_review', 'game_navigation_voyages')) {
        $where[] = "v.staff_review = '" . $db->escape_string($reviewFilter) . "'";
        $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    }
}

// Only show voyages where on-rol time has passed (expected_end_rol_days <= current rol days)
$currentRolDays = function_exists('game_rol_days_at') ? game_rol_days_at() : 0;
if ($currentRolDays > 0) {
    $where[] = 'v.expected_end_rol_days <= ' . $currentRolDays;
    $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
}

$q = $db->query("SELECT v.*, p.name AS char_name, f1.name AS from_name, f2.name AS to_name, c.name AS ship_name
    FROM {$prefix}game_navigation_voyages v
    JOIN {$prefix}game_personajes p ON p.id = v.character_id
    JOIN {$prefix}forums f1 ON f1.fid = v.island_from_fid
    JOIN {$prefix}forums f2 ON f2.fid = v.island_to_fid
    JOIN {$prefix}game_cards c ON c.id = v.ship_card_id
    {$sqlWhere}
    ORDER BY v.id DESC
    LIMIT 100");

$voyages = [];
$pendingCount = 0;
if ($db->field_exists('staff_review', 'game_navigation_voyages')) {
    $pendingCount = (int)$db->fetch_field(
        $db->query("SELECT COUNT(*) AS c FROM {$prefix}game_navigation_voyages WHERE staff_review = 'pending'"),
        'c'
    );
}

while ($row = $db->fetch_array($q)) {
    $voyages[] = function_exists('game_navigation_voyage_enrich_row')
        ? game_navigation_voyage_enrich_row($row)
        : $row;
}

GameAjax::json(true, ['voyages' => $voyages, 'pending_count' => $pendingCount]);
