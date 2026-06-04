<?php
define('IN_MYBB', 1);
require_once __DIR__ . '/../../inc/config.php';
require_once __DIR__ . '/../../inc/db_mysqli.php';

$db = new DB_MySQLi;
$db->connect($config['database']);

$prefix = $config['database']['table_prefix'];

// Check if execution_cost column already exists
$query = $db->simple_select("information_schema.COLUMNS", "COLUMN_NAME", 
    "TABLE_SCHEMA = '{$config['database']['database']}' 
     AND TABLE_NAME = '{$prefix}game_cards' 
     AND COLUMN_NAME = 'execution_cost'");

if ($db->num_rows($query) == 0) {
    $db->write_query("
        ALTER TABLE {$prefix}game_cards 
        ADD COLUMN execution_cost INT NOT NULL DEFAULT 0 AFTER rank
    ");
    echo "Added execution_cost column to game_cards\n";
} else {
    echo "execution_cost column already exists, skipping\n";
}

echo "Migration complete: execution_cost\n";
