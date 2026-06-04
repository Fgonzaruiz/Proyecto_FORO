<?php
declare(strict_types=1);

// Habilitar reporte de errores detallado para diagnosticar el Error 500
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;
$prefix = TABLE_PREFIX;

echo "<pre class='rpg-admin-pre'>\n";
echo "=== Migración: Execution Cost (PA) ===\n\n";

// Debug info del usuario logueado en MyBB
echo "DEBUG USUARIO:\n";
echo "User ID (uid): " . ($mybb->user['uid'] ?? 'No logueado') . "\n";
echo "Usergroup: " . ($mybb->user['usergroup'] ?? 'Sin grupo') . "\n";
echo "Can Access CP (cancp): " . ($mybb->usergroup['cancp'] ?? '0') . "\n\n";

// Ejecutar validación de permisos
game_require_admin_cp();

if ($db->table_exists('game_cards')) {
    if (!$db->field_exists('execution_cost', 'game_cards')) {
        $db->write_query("ALTER TABLE {$prefix}game_cards ADD COLUMN execution_cost INT NOT NULL DEFAULT 0 AFTER rank");
        echo "[OK] Columna 'execution_cost' añadida a 'game_cards'.\n";
    } else {
        echo "[--] Columna 'execution_cost' ya existe en 'game_cards'.\n";
    }
} else {
    echo "[ERROR] La tabla 'game_cards' no existe.\n";
}

echo "\n=== Fin de la migración ===\n";
echo "</pre>";
