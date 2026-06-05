<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
game_require_admin_cp();

global $db;
$prefix = TABLE_PREFIX;
$table = "{$prefix}game_post_characters";

echo "<pre class='rpg-admin-pre'>\n";
echo "=== Migración: Snapshot de equipamiento por post ===\n\n";

if ($db->table_exists('game_post_characters')) {
    if (!$db->field_exists('equipped_snapshot_json', 'game_post_characters')) {
        if ($db->field_exists('hidden_actions_json', 'game_post_characters')) {
            $db->write_query("ALTER TABLE {$table} ADD equipped_snapshot_json TEXT DEFAULT NULL AFTER hidden_actions_json");
        } else {
            $db->write_query("ALTER TABLE {$table} ADD equipped_snapshot_json TEXT DEFAULT NULL");
        }
        echo "[OK] Columna 'equipped_snapshot_json' añadida.\n";
    } else {
        echo "[--] Columna 'equipped_snapshot_json' ya existe.\n";
    }
} else {
    echo "[ERROR] La tabla 'game_post_characters' no existe.\n";
}

echo "\n=== Fin de la migración ===\n";
echo "</pre>";
