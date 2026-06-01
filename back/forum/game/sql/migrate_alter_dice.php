<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $db;
$prefix = TABLE_PREFIX;
$table = "{$prefix}game_cards";

echo "<pre style='font-family: monospace; background: #0a0c16; color: #e2e8f0; padding: 20px; border-radius: 12px;'>\n";
echo "=== Migración: Modificar longitud de la columna dice ===\n\n";

if ($db->table_exists('game_cards')) {
    $db->write_query("ALTER TABLE {$table} MODIFY dice VARCHAR(150) DEFAULT '';");
    echo "[OK] Columna 'dice' modificada a VARCHAR(150) en '{$table}'\n";
} else {
    echo "[ERROR] La tabla 'game_cards' no existe.\n";
}

echo "\n=== Fin de la migración ===\n";
echo "</pre>";
