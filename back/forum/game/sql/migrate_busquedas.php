<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $db;
$prefix = TABLE_PREFIX;

echo "<h2>Migracion: Tabla game_busquedas</h2>";

if (!$db->table_exists('game_busquedas')) {
    $sql = "
    CREATE TABLE {$prefix}game_busquedas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        character_id INT NOT NULL,
        titulo VARCHAR(255) NOT NULL,
        descripcion TEXT NOT NULL,
        imagen_url VARCHAR(500) DEFAULT NULL,
        status ENUM('pendiente','aprobada','denegada') DEFAULT 'pendiente',
        staff_nota TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $db->write_query($sql);
    echo "<p style='color:green'>[OK] Tabla game_busquedas creada correctamente.</p>";
} else {
    echo "<p style='color:orange'>[INFO] La tabla game_busquedas ya existe.</p>";
}

echo "<p>Migracion completada.</p>";
