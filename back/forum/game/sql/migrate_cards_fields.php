<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
game_require_admin_cp();

global $db;
$prefix = TABLE_PREFIX;
$table = "{$prefix}game_cards";

echo "<pre class='rpg-admin-pre'>\n";
echo "=== Migración: Campos Reposo y Duración en Cartas ===\n\n";

if ($db->table_exists('game_cards')) {
    if (!$db->field_exists('reposo', 'game_cards')) {
        $db->write_query("ALTER TABLE {$table} ADD reposo INT NOT NULL DEFAULT 0;");
        echo "[OK] Columna 'reposo' añadida a '{$table}'\n";
    } else {
        echo "[--] Columna 'reposo' ya existe en '{$table}'\n";
    }

    if (!$db->field_exists('duracion', 'game_cards')) {
        $db->write_query("ALTER TABLE {$table} ADD duracion INT NOT NULL DEFAULT 0;");
        echo "[OK] Columna 'duracion' añadida a '{$table}'\n";
    } else {
        echo "[--] Columna 'duracion' ya existe en '{$table}'\n";
    }
} else {
    echo "[ERROR] La tabla 'game_cards' no existe. Ejecuta primero migrate_cards.php\n";
}

echo "\n=== Fin de la migración ===\n";
echo "</pre>";
