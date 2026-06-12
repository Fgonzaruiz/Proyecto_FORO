<?php
define('IN_MYBB', 1);
require_once dirname(__DIR__, 2) . '/inc/init.php';
global $db;

$prefix = TABLE_PREFIX;

$queries = [
    "CREATE TABLE IF NOT EXISTS {$prefix}game_wanted (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(20) NOT NULL DEFAULT 'pj',
        entity_id INT DEFAULT NULL,
        name VARCHAR(150) NOT NULL,
        epithet VARCHAR(150) DEFAULT '',
        bounty BIGINT NOT NULL DEFAULT 0,
        image_url VARCHAR(255) DEFAULT '',
        reason TEXT,
        status VARCHAR(20) DEFAULT 'active'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS {$prefix}game_sucesos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        pj_id INT NOT NULL,
        thread_url VARCHAR(255) NOT NULL,
        title VARCHAR(150) NOT NULL,
        description TEXT,
        status VARCHAR(20) DEFAULT 'pendiente',
        created_at INT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

foreach ($queries as $q) {
    try {
        $db->query($q);
        echo "Exito: " . substr($q, 0, 50) . "...\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
echo "Migración completada.\n";
