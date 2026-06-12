<?php
declare(strict_types=1);

define('IN_MYBB', 1);
require_once dirname(__DIR__, 2) . '/inc/init.php';

global $db;
$prefix = TABLE_PREFIX;

echo "Migrando Tripulaciones...\n";

$db->query("CREATE TABLE IF NOT EXISTS {$prefix}game_tripulaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    image_url VARCHAR(255) DEFAULT '',
    description TEXT,
    leader_pj_id INT DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'aprobada',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
echo "Tabla game_tripulaciones creada.\n";

$db->query("CREATE TABLE IF NOT EXISTS {$prefix}game_tripulacion_miembros (
    pj_id INT PRIMARY KEY,
    tripulacion_id INT NOT NULL,
    role VARCHAR(50) DEFAULT 'Miembro',
    status_peticion VARCHAR(20) DEFAULT 'aprobada',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_trip (tripulacion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
echo "Tabla game_tripulacion_miembros creada.\n";

try {
    $db->query("ALTER TABLE {$prefix}game_personajes ADD COLUMN tripulacion_id INT DEFAULT NULL AFTER rango");
    echo "Columna tripulacion_id añadida a game_personajes.\n";
} catch (Exception $e) {
    echo "Columna tripulacion_id (posiblemente ya existe).\n";
}

echo "Migracion de Tripulaciones completada.\n";
