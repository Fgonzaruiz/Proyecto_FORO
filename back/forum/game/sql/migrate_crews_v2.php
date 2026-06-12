<?php
declare(strict_types=1);

define('IN_MYBB', 1);
require_once dirname(__DIR__, 2) . '/inc/init.php';

global $db;
$prefix = TABLE_PREFIX;

echo "Migrando Tripulaciones v2...\n";

// 1. Lema de la tripulación
try {
    $db->query("ALTER TABLE {$prefix}game_tripulaciones 
        ADD COLUMN motto VARCHAR(255) DEFAULT '' AFTER name");
    echo "Columna motto añadida.\n";
} catch (Exception $e) {
    echo "motto posiblemente ya existe.\n";
}

// 2. Rol personalizado en miembros
try {
    $db->query("ALTER TABLE {$prefix}game_tripulacion_miembros 
        ADD COLUMN role_custom VARCHAR(80) DEFAULT '' AFTER role");
    echo "Columna role_custom añadida.\n";
} catch (Exception $e) {
    echo "role_custom posiblemente ya existe.\n";
}

echo "Migracion Tripulaciones v2 completada.\n";
