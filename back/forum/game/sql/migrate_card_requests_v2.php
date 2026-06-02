<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $db;
$prefix = TABLE_PREFIX;
$table = "{$prefix}game_card_requests";

echo "<pre style='font-family: monospace; background: #0a0c16; color: #e2e8f0; padding: 20px; border-radius: 12px;'>\n";
echo "=== Migración: Peticiones de Cartas v2 ===\n\n";

if ($db->table_exists('game_card_requests')) {
    // 1. Modify request_type ENUM
    // First, let's query the column to see if we can perform the ALTER safely
    $db->write_query("ALTER TABLE {$table} MODIFY COLUMN request_type ENUM('upgrade', 'delete', 'create', 'add_existing') NOT NULL;");
    echo "[OK] Modificado 'request_type' a ENUM('upgrade', 'delete', 'create', 'add_existing')\n";

    // 2. Modify status ENUM
    $db->write_query("ALTER TABLE {$table} MODIFY COLUMN status ENUM('pendiente', 'aprobada', 'rechazada', 'conforme') NOT NULL DEFAULT 'pendiente';");
    echo "[OK] Modificado 'status' a ENUM('pendiente', 'aprobada', 'rechazada', 'conforme')\n";

    // 3. Add card_details_json if not exists
    if (!$db->field_exists('card_details_json', 'game_card_requests')) {
        $db->write_query("ALTER TABLE {$table} ADD COLUMN card_details_json TEXT DEFAULT NULL AFTER current_rank;");
        echo "[OK] Columna 'card_details_json' añadida\n";
    } else {
        echo "[--] Columna 'card_details_json' ya existe\n";
    }

    // 4. Add discussion_json if not exists
    if (!$db->field_exists('discussion_json', 'game_card_requests')) {
        $db->write_query("ALTER TABLE {$table} ADD COLUMN discussion_json TEXT DEFAULT NULL AFTER card_details_json;");
        echo "[OK] Columna 'discussion_json' añadida\n";
    } else {
        echo "[--] Columna 'discussion_json' ya existe\n";
    }

    // 5. Change card_id to allow 0 or make it nullable
    // By default, if it is NOT NULL but we want it to support 0, it already does because card_id is INT.
    // Let's ensure card_id can default to 0.
    $db->write_query("ALTER TABLE {$table} ALTER COLUMN card_id SET DEFAULT 0;");
    echo "[OK] Default de 'card_id' establecido a 0\n";

} else {
    echo "[ERROR] La tabla 'game_card_requests' no existe.\n";
}

echo "\n=== Fin de la migración ===\n";
echo "</pre>";
