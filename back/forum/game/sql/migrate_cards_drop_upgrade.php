<?php
declare(strict_types=1);

/**
 * Elimina upgrade_json de cartas y el tipo de petición upgrade (obsoleto).
 */

require_once __DIR__ . '/../bootstrap.php';
game_require_admin_cp();

global $db;
$prefix = TABLE_PREFIX;

echo "<pre class='rpg-admin-pre'>\n";
echo "=== Migración: eliminar upgrade de cartas ===\n\n";

$tableCards = "{$prefix}game_cards";
if ($db->table_exists('game_cards') && $db->field_exists('upgrade_json', 'game_cards')) {
    $db->write_query("ALTER TABLE {$tableCards} DROP COLUMN upgrade_json");
    echo "[OK] Columna upgrade_json eliminada de game_cards\n";
} else {
    echo "[--] upgrade_json ya ausente en game_cards\n";
}

$tableReq = "{$prefix}game_card_requests";
if ($db->table_exists('game_card_requests')) {
    $db->write_query("DELETE FROM {$tableReq} WHERE request_type = 'upgrade'");
    echo "[OK] Peticiones históricas request_type=upgrade eliminadas\n";

    $db->write_query("ALTER TABLE {$tableReq} MODIFY COLUMN request_type ENUM('delete', 'create', 'add_existing') NOT NULL");
    echo "[OK] request_type sin valor 'upgrade'\n";
} else {
    echo "[--] Tabla game_card_requests no existe\n";
}

echo "\n=== Migración completada ===\n";
echo "</pre>";
