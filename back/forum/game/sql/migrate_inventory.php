<?php
declare(strict_types=1);

/**
 * Migración: Sistema de Inventario y Carga
 */

require_once __DIR__ . '/../bootstrap.php';
game_require_admin_cp();

global $db;
$prefix = TABLE_PREFIX;

echo "<pre class='rpg-admin-pre'>\n";
echo "=== Migración: Sistema de Inventario y Carga ===\n\n";

// 1. Crear tabla game_character_inventory
$table = "{$prefix}game_character_inventory";
if (!$db->table_exists('game_character_inventory')) {
    $db->write_query("CREATE TABLE {$table} (
        character_id INT NOT NULL,
        card_id INT NOT NULL,
        slot_type ENUM('carga', 'companero', 'barco') NOT NULL,
        equipped_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        peso INT NOT NULL DEFAULT 0,
        PRIMARY KEY (character_id, card_id),
        INDEX idx_char_slot (character_id, slot_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "[OK] Tabla '{$table}' creada\n";
} else {
    echo "[--] Tabla '{$table}' ya existe\n";
}

// 2. Agregar columna peso a game_cards
$table_cards = "{$prefix}game_cards";
if ($db->table_exists('game_cards')) {
    if (!$db->field_exists('peso', 'game_cards')) {
        $db->write_query("ALTER TABLE {$table_cards} ADD peso INT NOT NULL DEFAULT 1;");
        echo "[OK] Columna 'peso' añadida a '{$table_cards}'\n";
    } else {
        echo "[--] Columna 'peso' ya existe en '{$table_cards}'\n";
    }
} else {
    echo "[ERROR] La tabla 'game_cards' no existe. Ejecuta primero migrate_cards.php\n";
}

echo "\n=== Fin de la migración ===\n";
echo "</pre>";
