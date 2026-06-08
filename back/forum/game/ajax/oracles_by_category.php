<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $db;

$uid = GameAjax::requireLogin();
$prefix = TABLE_PREFIX;

$category = isset($_GET['category']) ? $db->escape_string($_GET['category']) : '';
$type = isset($_GET['type']) ? $db->escape_string($_GET['type']) : '';

$where = ["(o.category = '' OR o.category = '{$category}')"];
if ($type) $where[] = "o.oracle_type = '{$type}'";

$sql_where = 'WHERE ' . implode(' AND ', $where);
$q = $db->query("
    SELECT o.* 
    FROM {$prefix}game_oracles o
    {$sql_where}
    ORDER BY o.name ASC
");

$oracles = [];
while ($row = $db->fetch_array($q)) {
    $row['tags'] = json_decode($row['tags_json'] ?? '[]', true);
    $row['results'] = json_decode($row['results_json'] ?? '[]', true);
    $row['variations'] = json_decode($row['variations_json'] ?? '{}', true);
    $row['auto_invoke_config'] = json_decode($row['auto_invoke_json'] ?? '[]', true);
    unset($row['tags_json'], $row['results_json'], $row['variations_json'], $row['auto_invoke_json']);
    $oracles[] = $row;
}

GameAjax::json(true, $oracles);
