<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
game_require_admin_cp();

global $db;
$prefix = TABLE_PREFIX;

echo "<pre class='rpg-admin-pre'>\n";
echo "=== Migración: Modificadores de Tirada ===\n\n";

// Añadir roll_modifiers_json a game_post_cards
$table = "{$prefix}game_post_cards";
if ($db->table_exists('game_post_cards')) {
    if (!$db->field_exists('roll_modifiers_json', 'game_post_cards')) {
        $db->write_query("ALTER TABLE {$table} ADD roll_modifiers_json TEXT DEFAULT NULL");
        echo "[OK] Columna 'roll_modifiers_json' añadida a 'game_post_cards'.\n";
    } else {
        echo "[--] Columna 'roll_modifiers_json' ya existe en 'game_post_cards'.\n";
    }
} else {
    echo "[ERROR] La tabla 'game_post_cards' no existe.\n";
}

echo "\n=== Fin de la migración ===\n";
echo "</pre>";
