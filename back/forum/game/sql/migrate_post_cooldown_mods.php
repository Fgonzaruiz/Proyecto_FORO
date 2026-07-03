<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
game_require_admin_cp();

global $db;
$prefix = TABLE_PREFIX;
$table = "{$prefix}game_post_characters";

echo "<pre class='rpg-admin-pre'>\n";
echo "=== Migración: Cooldown Mods por Post (game_post_characters) ===\n\n";

if ($db->table_exists('game_post_characters')) {
    if (!$db->field_exists('cooldown_mods_json', 'game_post_characters')) {
        $db->write_query("ALTER TABLE {$table} ADD cooldown_mods_json TEXT DEFAULT NULL");
        echo "[OK] Columna 'cooldown_mods_json' añadida.\n";
    } else {
        echo "[--] Columna 'cooldown_mods_json' ya existe.\n";
    }
} else {
    echo "[ERROR] La tabla 'game_post_characters' no existe.\n";
}

echo "\n=== Fin de la migración ===\n";
echo "</pre>";
