<?php
declare(strict_types=1);

define('IN_MYBB', 1);
require_once dirname(__DIR__, 2) . '/inc/init.php';

global $db;
$prefix = TABLE_PREFIX;

echo "Migrando Tripulaciones v3 (Facciones y Navío)...\n";

$columns = [
    'factions' => "VARCHAR(255) DEFAULT '' AFTER description",
    'ship_name' => "VARCHAR(150) DEFAULT '' AFTER ost_url",
    'ship_image_url' => "VARCHAR(255) DEFAULT '' AFTER ship_name",
    'ship_data' => "TEXT AFTER ship_image_url"
];

foreach ($columns as $col => $def) {
    try {
        $db->query("ALTER TABLE {$prefix}game_tripulaciones ADD COLUMN {$col} {$def}");
        echo "Columna {$col} añadida.\n";
    } catch (Exception $e) {
        echo "La columna {$col} posiblemente ya existe.\n";
    }
}

echo "Migracion Tripulaciones v3 completada.\n";
