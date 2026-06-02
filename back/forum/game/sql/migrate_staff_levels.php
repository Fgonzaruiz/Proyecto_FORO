<?php
declare(strict_types=1);

/**
 * Migración: Agrega staff_level (1=Narrador, 2=Moderador, 3=Administrador)
 * y asigna niveles a personajes existentes.
 */

require_once __DIR__ . '/../bootstrap.php';
game_require_admin_cp();

global $db;
$prefix = TABLE_PREFIX;

echo "<pre style='font-family: monospace; background: #0a0c16; color: #e2e8f0; padding: 20px; border-radius: 12px;'>\n";
echo "=== Migración: staff_level ===\n\n";

// 1. Agregar columna staff_level si no existe
$col_check = $db->query("SHOW COLUMNS FROM {$prefix}game_personajes LIKE 'staff_level'");
if (!$db->num_rows($col_check)) {
    $db->write_query("ALTER TABLE {$prefix}game_personajes ADD COLUMN staff_level TINYINT(1) NOT NULL DEFAULT 0 AFTER is_staff");
    echo "[OK] Columna 'staff_level' agregada\n";
} else {
    echo "[--] Columna 'staff_level' ya existe\n";
}

// 2. Asignar staff_level a personajes existentes
// Imu (user_id=1, is_staff=1) → Administrador (3)
$db->write_query("UPDATE {$prefix}game_personajes SET is_staff = 1, staff_level = 3 WHERE user_id = 1 AND name = 'Imu'");
echo "[OK] Imu → Administrador (staff_level=3)\n";

// Kazan (user_id=1) → Narrador (1)
$db->write_query("UPDATE {$prefix}game_personajes SET is_staff = 1, staff_level = 1 WHERE user_id = 1 AND name = 'Kazan'");
echo "[OK] Kazan → Narrador (staff_level=1)\n";

echo "\n=== Migración completada ===\n";
echo "</pre>";
