<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
game_require_admin_cp();

global $db;
$prefix = TABLE_PREFIX;
$table = "{$prefix}game_cards";

echo "<pre class='rpg-admin-pre'>\n";
echo "=== Migración: Modificar longitud de la columna dice ===\n\n";

if ($db->table_exists('game_cards')) {
    $db->write_query("ALTER TABLE {$table} MODIFY dice VARCHAR(150) DEFAULT '';");
    echo "[OK] Columna 'dice' modificada a VARCHAR(150) en '{$table}'\n";
} else {
    echo "[ERROR] La tabla 'game_cards' no existe.\n";
}

echo "\n=== Fin de la migración ===\n";
echo "</pre>";
