<?php
declare(strict_types=1);

/**
 * Migración: Soporte de cantidad para cartas en inventario (consumibles/munición)
 */

require_once __DIR__ . '/../bootstrap.php';
game_require_admin_cp();

global $db;
$prefix = TABLE_PREFIX;
$table = "{$prefix}game_character_cards";

echo "<pre class='rpg-admin-pre'>\n";
echo "=== Migración: Agregar Columna Cantidad a Inventario de Cartas ===\n\n";

if ($db->table_exists('game_character_cards')) {
    if (!$db->field_exists('cantidad', 'game_character_cards')) {
        $db->write_query("ALTER TABLE {$table} ADD COLUMN cantidad INT NOT NULL DEFAULT 1;");
        echo "[OK] Columna 'cantidad' agregada a '{$table}'\n";
    } else {
        echo "[--] La columna 'cantidad' ya existe en '{$table}'\n";
    }
} else {
    echo "[ERROR] La tabla 'game_character_cards' no existe.\n";
}

echo "\n=== Fin de la migración ===\n";
echo "</pre>";
