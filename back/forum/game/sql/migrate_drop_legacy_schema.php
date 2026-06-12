<?php
declare(strict_types=1);

/**
 * Elimina tabla y columnas legacy que ya no usa la aplicación.
 * - game_historia → reemplazada por lore.json (LoreService)
 * - tecnicas_json / gestion_json → mecánicas en game_cards
 */

require_once __DIR__ . '/../bootstrap.php';
game_require_admin_cp();

global $db;
$prefix = TABLE_PREFIX;

echo "<pre class='rpg-admin-pre'>\n";
echo "=== Migración: limpieza esquema legacy ===\n\n";

if ($db->table_exists('game_historia')) {
    $db->write_query("DROP TABLE {$prefix}game_historia");
    echo "[OK] Tabla game_historia eliminada\n";
} else {
    echo "[SKIP] game_historia no existe\n";
}

foreach (['tecnicas_json', 'gestion_json'] as $col) {
    if ($db->field_exists($col, 'game_personajes')) {
        $db->write_query("ALTER TABLE {$prefix}game_personajes DROP COLUMN {$col}");
        echo "[OK] Columna game_personajes.{$col} eliminada\n";
    } else {
        echo "[SKIP] game_personajes.{$col} no existe\n";
    }
}

foreach (['stat_fp', 'stat_dp', 'stat_rp', 'stat_ip', 'stat_vp', 'stat_hp'] as $col) {
    if ($db->field_exists($col, 'game_personajes')) {
        $db->write_query("ALTER TABLE {$prefix}game_personajes DROP COLUMN {$col}");
        echo "[OK] Columna game_personajes.{$col} eliminada\n";
    }
}

foreach (['game_tecnicas', 'game_estilos', 'game_objetos'] as $table) {
    if ($db->table_exists($table)) {
        $db->write_query("DROP TABLE {$prefix}{$table}");
        echo "[OK] Tabla {$table} eliminada\n";
    } else {
        echo "[SKIP] {$table} no existe\n";
    }
}

echo "\n=== Completado ===\n</pre>";
