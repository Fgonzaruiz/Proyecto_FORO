<?php
declare(strict_types=1);

global $db;
$prefix = TABLE_PREFIX;

echo "=== Migración: Facción para Misiones ===\n\n";

if (!$db->table_exists('game_missions')) {
    echo "[SKIP] Tabla game_missions no existe.\n";
    return;
}

if (!$db->field_exists('faction', 'game_missions')) {
    $db->write_query("ALTER TABLE {$prefix}game_missions ADD COLUMN faction VARCHAR(64) NOT NULL DEFAULT 'Global' AFTER categoria");
    echo "[OK] Columna 'faction' agregada a game_missions.\n";
} else {
    echo "[--] Columna 'faction' ya existe.\n";
}

echo "\n[OK] Migración completada.\n";
