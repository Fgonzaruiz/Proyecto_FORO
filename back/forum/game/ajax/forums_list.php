<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $db;

$uid = GameAjax::requireLogin();
$prefix = TABLE_PREFIX;

$q = $db->query("
    SELECT f.fid, f.name, f.type, f.pid,
           p.name AS parent_name
    FROM {$prefix}forums f
    LEFT JOIN {$prefix}forums p ON p.fid = f.pid
    WHERE f.active != 0
    ORDER BY f.pid, f.disporder
");

$forums = [];
while ($row = $db->fetch_array($q)) {
    $forums[] = [
        'fid' => (int)$row['fid'],
        'name' => $row['name'],
        'type' => $row['type'],
        'pid' => (int)$row['pid'],
        'parent_name' => $row['parent_name'] ?: '',
    ];
}

GameAjax::json(true, $forums);
