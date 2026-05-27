<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $db;
$prefix = TABLE_PREFIX;

echo "Iniciando migracion de anuncios...<br>";

if (!$db->table_exists('game_announcements')) {
    $sql = "
    CREATE TABLE {$prefix}game_announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_active TINYINT(1) DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $db->write_query($sql);
    echo "Tabla game_announcements creada correctamente.<br>";
} else {
    echo "La tabla game_announcements ya existe.<br>";
}

echo "Migracion completada.<br>";
