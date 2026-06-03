<?php
declare(strict_types=1);

/**
 * Migración: Agrega is_narrator a personajes y crea la tabla de asignaciones de NPCs.
 */

require_once __DIR__ . '/../bootstrap.php';
game_require_admin_cp();

global $db;
$prefix = TABLE_PREFIX;

echo "<pre class='rpg-admin-pre'>\n";
echo "=== Migración: Sistema de Narradores y Asignaciones ===\n\n";

// 1. Agregar columna is_narrator si no existe
$col_check = $db->query("SHOW COLUMNS FROM {$prefix}game_personajes LIKE 'is_narrator'");
if (!$db->num_rows($col_check)) {
    $db->write_query("ALTER TABLE {$prefix}game_personajes ADD COLUMN is_narrator TINYINT(1) NOT NULL DEFAULT 0 AFTER is_npc");
    echo "[OK] Columna 'is_narrator' agregada a tabla personajes.\n";
} else {
    echo "[--] Columna 'is_narrator' ya existe.\n";
}

// 2. Crear tabla game_npc_assignments si no existe
$table_exists = $db->table_exists('game_npc_assignments');
if (!$table_exists) {
    $db->write_query("CREATE TABLE {$prefix}game_npc_assignments (
        character_id INT NOT NULL,
        narrator_id INT NOT NULL,
        PRIMARY KEY (character_id, narrator_id),
        INDEX idx_narrator_id (narrator_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "[OK] Tabla 'game_npc_assignments' creada.\n";
} else {
    echo "[--] Tabla 'game_npc_assignments' ya existe.\n";
}

echo "\n=== Migración completada ===\n";
echo "</pre>";
