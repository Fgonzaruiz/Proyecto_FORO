<?php
declare(strict_types=1);

/**
 * Migración: Soporte de barcos en el Sistema de Cartas
 */

require_once __DIR__ . '/../bootstrap.php';
game_require_admin_cp();

global $db;
$prefix = TABLE_PREFIX;
$table = "{$prefix}game_cards";

echo "<pre class='rpg-admin-pre'>\n";
echo "=== Migración: Agregar Tipo Barco a Cartas ===\n\n";

if ($db->table_exists('game_cards')) {
    $db->write_query("ALTER TABLE {$table} MODIFY COLUMN card_type ENUM('tecnica', 'equipo', 'akuma_no_mi', 'haki', 'npc_menor', 'barco') NOT NULL;");
    echo "[OK] Columna 'card_type' alterada para admitir 'barco'\n";
} else {
    echo "[ERROR] La tabla 'game_cards' no existe. Ejecuta primero migrate_cards.php\n";
}

echo "\n=== Fin de la migración ===\n";
echo "</pre>";
