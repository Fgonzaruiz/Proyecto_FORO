<?php
declare(strict_types=1);

/**
 * Migración: Agrega la columna is_npc a game_personajes.
 */

require_once __DIR__ . '/../bootstrap.php';
game_require_admin_cp();

global $db;
$prefix = TABLE_PREFIX;

echo "<pre class='rpg-admin-pre'>\n";
echo "=== Migración: is_npc ===\n\n";

// 1. Agregar columna is_npc si no existe
$col_check = $db->query("SHOW COLUMNS FROM {$prefix}game_personajes LIKE 'is_npc'");
if (!$db->num_rows($col_check)) {
    $db->write_query("ALTER TABLE {$prefix}game_personajes ADD COLUMN is_npc TINYINT(1) NOT NULL DEFAULT 0 AFTER staff_level");
    echo "[OK] Columna 'is_npc' agregada\n";
} else {
    echo "[--] Columna 'is_npc' ya existe\n";
}

echo "\n=== Migración completada ===\n";
echo "</pre>";
