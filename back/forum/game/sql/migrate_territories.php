<?php
declare(strict_types=1);

define('IN_MYBB', 1);
require_once dirname(__DIR__, 2) . '/inc/init.php';

global $db;
$prefix = TABLE_PREFIX;

echo "Migrando Territorios...\n";

try {
    $db->query("ALTER TABLE {$prefix}game_forum_islands 
        ADD COLUMN controlling_type VARCHAR(20) DEFAULT NULL AFTER requires_log_pose,
        ADD COLUMN controlling_id INT DEFAULT NULL AFTER controlling_type");
    echo "Columnas controlling_* añadidas a game_forum_islands.\n";
} catch (Exception $e) {
    echo "Columnas controlling_* (posiblemente ya existen).\n";
}

echo "Migracion de Territorios completada.\n";
