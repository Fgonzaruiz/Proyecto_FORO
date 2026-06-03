<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
game_require_admin_cp();

global $db;
$prefix = TABLE_PREFIX;
$table = "{$prefix}game_post_characters";

echo "<pre class='rpg-admin-pre'>\n";
echo "=== Migración: Modificaciones por Post (game_post_characters) ===\n\n";

if ($db->table_exists('game_post_characters')) {
    if (!$db->field_exists('pv_change', 'game_post_characters')) {
        $db->write_query("ALTER TABLE {$table} ADD pv_change INT NOT NULL DEFAULT 0");
        echo "[OK] Columna 'pv_change' añadida.\n";
    } else {
        echo "[--] Columna 'pv_change' ya existe.\n";
    }

    if (!$db->field_exists('pe_change', 'game_post_characters')) {
        $db->write_query("ALTER TABLE {$table} ADD pe_change INT NOT NULL DEFAULT 0");
        echo "[OK] Columna 'pe_change' añadida.\n";
    } else {
        echo "[--] Columna 'pe_change' ya existe.\n";
    }

    if (!$db->field_exists('modifiers_json', 'game_post_characters')) {
        $db->write_query("ALTER TABLE {$table} ADD modifiers_json TEXT DEFAULT NULL");
        echo "[OK] Columna 'modifiers_json' añadida.\n";
    } else {
        echo "[--] Columna 'modifiers_json' ya existe.\n";
    }
} else {
    echo "[ERROR] La tabla 'game_post_characters' no existe.\n";
}

echo "\n=== Fin de la migración ===\n";
echo "</pre>";
