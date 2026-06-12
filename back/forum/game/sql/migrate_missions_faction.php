<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
global $db;
$prefix = TABLE_PREFIX;

echo "<pre class='rpg-admin-pre'>\n";
echo "=== Migración: Facción para Misiones ===\n\n";

$col_check = $db->query("SHOW COLUMNS FROM {$prefix}game_missions LIKE 'faction'");
if (!$db->num_rows($col_check)) {
    $db->write_query("ALTER TABLE {$prefix}game_missions ADD COLUMN faction VARCHAR(64) NOT NULL DEFAULT 'Global' AFTER categoria");
    echo "[OK] Columna 'faction' agregada a tabla game_missions.\n";
} else {
    echo "[--] Columna 'faction' ya existe.\n";
}

echo "\n=== Migración completada ===\n";
echo "</pre>";
