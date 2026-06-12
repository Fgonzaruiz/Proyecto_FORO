<?php
declare(strict_types=1);
define('IN_MYBB', 1);
require_once dirname(__DIR__, 2) . '/inc/init.php';
global $db;
$prefix = TABLE_PREFIX;

try {
    $db->query("ALTER TABLE {$prefix}game_tripulaciones ADD COLUMN relations TEXT AFTER description, ADD COLUMN ost_url VARCHAR(500) DEFAULT '' AFTER relations");
    echo "Campos relations y ost_url añadidos.\n";
} catch (Exception $e) {
    echo "Error o ya existen: " . $e->getMessage() . "\n";
}
