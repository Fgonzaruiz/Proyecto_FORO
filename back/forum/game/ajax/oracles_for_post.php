<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Http\GameAjax;

global $db;

$post_id = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
if ($post_id <= 0) {
    GameAjax::fail(400, 'post_id inválido.');
}

$prefix = TABLE_PREFIX;

$q = $db->query("
    SELECT po.id, po.roll_value, po.result_range, po.result_text, po.result_description,
           po.auto_invoked, po.invoked_by_post_oracle_id, po.rolled_at,
           o.id AS oracle_id, o.name, o.description AS oracle_description,
           o.oracle_type, o.subtype, o.dice_type, o.image_url
    FROM {$prefix}game_post_oracles po
    JOIN {$prefix}game_oracles o ON po.oracle_id = o.id
    WHERE po.post_id = {$post_id}
    ORDER BY po.auto_invoked ASC, po.id ASC
");

$oracles = [];
while ($row = $db->fetch_array($q)) {
    $oracles[] = [
        'id' => (int)$row['id'],
        'oracle_id' => (int)$row['oracle_id'],
        'name' => $row['name'],
        'description' => $row['oracle_description'],
        'oracle_type' => $row['oracle_type'],
        'subtype' => $row['subtype'],
        'dice_type' => $row['dice_type'],
        'image_url' => $row['image_url'],
        'roll_value' => $row['roll_value'],
        'result_range' => $row['result_range'],
        'result_text' => $row['result_text'],
        'result_description' => $row['result_description'],
        'auto_invoked' => (int)$row['auto_invoked'],
        'invoked_by_post_oracle_id' => $row['invoked_by_post_oracle_id'] ? (int)$row['invoked_by_post_oracle_id'] : null,
        'rolled_at' => $row['rolled_at'],
    ];
}

GameAjax::json(true, $oracles);
