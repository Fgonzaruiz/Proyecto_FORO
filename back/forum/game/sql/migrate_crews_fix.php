<?php
declare(strict_types=1);

define('IN_MYBB', 1);
require_once dirname(__DIR__, 2) . '/inc/init.php';

global $db;
$prefix = TABLE_PREFIX;

echo "Arreglando Tripulaciones...\n";

// Drop the old table that was preventing the new schema from applying
$db->query("DROP TABLE IF EXISTS {$prefix}game_tripulaciones");

// Recreate with correct schema
$db->query("CREATE TABLE {$prefix}game_tripulaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    image_url VARCHAR(255) DEFAULT '',
    description TEXT,
    relations TEXT,
    ost_url VARCHAR(500) DEFAULT '',
    leader_pj_id INT DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'aprobada',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
echo "Tabla game_tripulaciones (re)creada con esquema completo.\n";
