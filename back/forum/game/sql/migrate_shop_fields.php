<?php
declare(strict_types=1);

/**
 * Migración: Agregar soporte de Berries y campos de Tienda
 */

require_once __DIR__ . '/../bootstrap.php';
game_require_admin_cp();

global $db;
$prefix = TABLE_PREFIX;

echo "<pre class='rpg-admin-pre'>\n";
echo "=== Migración: Campos de Tienda ===\n\n";

if ($db->table_exists('game_cards')) {
    if (!$db->field_exists('cost_berries', 'game_cards')) {
        $db->write_query("ALTER TABLE {$prefix}game_cards ADD COLUMN cost_berries INT NOT NULL DEFAULT 0;");
        echo "[OK] Columna 'cost_berries' añadida a 'game_cards'\n";
    } else {
        echo "[--] Columna 'cost_berries' ya existe en 'game_cards'\n";
    }

    if (!$db->field_exists('in_shop', 'game_cards')) {
        $db->write_query("ALTER TABLE {$prefix}game_cards ADD COLUMN in_shop TINYINT(1) NOT NULL DEFAULT 0;");
        echo "[OK] Columna 'in_shop' añadida a 'game_cards'\n";
    } else {
        echo "[--] Columna 'in_shop' ya existe en 'game_cards'\n";
    }

    if (!$db->field_exists('shop_category', 'game_cards')) {
        $db->write_query("ALTER TABLE {$prefix}game_cards ADD COLUMN shop_category VARCHAR(50) DEFAULT NULL;");
        echo "[OK] Columna 'shop_category' añadida a 'game_cards'\n";
    } else {
        echo "[--] Columna 'shop_category' ya existe en 'game_cards'\n";
    }
} else {
    echo "[ERROR] La tabla 'game_cards' no existe.\n";
}

if ($db->table_exists('game_personajes')) {
    if (!$db->field_exists('berries', 'game_personajes')) {
        $db->write_query("ALTER TABLE {$prefix}game_personajes ADD COLUMN berries INT NOT NULL DEFAULT 0;");
        echo "[OK] Columna 'berries' añadida a 'game_personajes'\n";
    } else {
        echo "[--] Columna 'berries' ya existe en 'game_personajes'\n";
    }
} else {
    echo "[ERROR] La tabla 'game_personajes' no existe.\n";
}

echo "\n=== Fin de la migración ===\n";
echo "</pre>";
