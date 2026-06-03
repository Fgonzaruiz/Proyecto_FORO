<?php
declare(strict_types=1);

/**
 * Migración: estado PV/PE y modificadores de turno por hilo y personaje
 */

require_once __DIR__ . '/../bootstrap.php';
game_require_admin_cp();

global $db;
$prefix = TABLE_PREFIX;
$table = "{$prefix}game_thread_pj_state";

echo "<pre class='rpg-admin-pre'>\n";
echo "=== Migración: Estado PV/PE por hilo (game_thread_pj_state) ===\n\n";

if (!$db->table_exists('game_thread_pj_state')) {
    $db->write_query("
        CREATE TABLE {$table} (
            thread_id INT NOT NULL,
            character_id INT NOT NULL,
            current_pv INT NOT NULL,
            current_pe INT NOT NULL,
            stat_mods_json TEXT,
            last_post_id INT DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (thread_id, character_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "[OK] Tabla '{$table}' creada.\n";
} else {
    echo "[--] La tabla 'game_thread_pj_state' ya existe.\n";
}

echo "\n=== Fin de la migración ===\n";
echo "</pre>";
