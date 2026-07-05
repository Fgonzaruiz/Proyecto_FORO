<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/migration_helpers.php';

game_require_admin_cp();

global $db;
$prefix = TABLE_PREFIX;
$migrationName = 'migrate_hxh_currency_missions.php';

echo "<pre class='rpg-admin-pre'>\n";
echo "=== Migración: Renombrar Recompensa de Misión (Berries a Jenny) ===\n\n";

if ($db->field_exists('berry_reward', 'game_missions')) {
    $db->write_query("ALTER TABLE {$prefix}game_missions CHANGE berry_reward jenny_reward INT NOT NULL DEFAULT 0");
    echo "[OK] Renombrada columna 'berry_reward' a 'jenny_reward' en game_missions\n";
} else {
    echo "[--] Columna 'berry_reward' ya renombrada o no existe en game_missions\n";
}

echo "\n=== Migración completada ===\n</pre>";
