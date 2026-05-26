<?php
declare(strict_types=1);

/**
 * Migración: Sistema de Cartas (Catálogo, Inventario y Cartas Jugadas)
 */

require_once __DIR__ . '/../bootstrap.php';

global $db;
$prefix = TABLE_PREFIX;

echo "<pre style='font-family: monospace; background: #0a0c16; color: #e2e8f0; padding: 20px; border-radius: 12px;'>\n";
echo "=== Migración: Sistema de Cartas ===\n\n";

// 1. mybb_game_cards (Catálogo maestro de cartas)
$table = "{$prefix}game_cards";
if (!$db->table_exists('game_cards')) {
    $db->write_query("CREATE TABLE {$table} (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        card_type ENUM('tecnica', 'equipo', 'akuma_no_mi', 'haki', 'npc_menor') NOT NULL,
        rank ENUM('C', 'B', 'A', 'S', 'SS') NOT NULL DEFAULT 'C',
        activation ENUM('activa', 'pasiva', 'reactiva') NOT NULL DEFAULT 'activa',
        tags_json TEXT,
        description TEXT,
        cost_pe VARCHAR(50) DEFAULT '—',
        execution_stat VARCHAR(10) DEFAULT '',
        dice VARCHAR(50) DEFAULT '',
        effects_json TEXT,
        upgrade_json TEXT,
        notes TEXT,
        image_url VARCHAR(500) DEFAULT '',
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_type (card_type),
        KEY idx_rank (rank)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "[OK] Tabla '{$table}' creada\n";
} else {
    echo "[--] Tabla '{$table}' ya existe\n";
}

// 2. mybb_game_character_cards (Inventario de personajes)
$table2 = "{$prefix}game_character_cards";
if (!$db->table_exists('game_character_cards')) {
    $db->write_query("CREATE TABLE {$table2} (
        id INT AUTO_INCREMENT PRIMARY KEY,
        character_id INT NOT NULL,
        card_id INT NOT NULL,
        current_rank ENUM('C', 'B', 'A', 'S', 'SS') NOT NULL DEFAULT 'C',
        assigned_by INT NOT NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY idx_char_card (character_id, card_id),
        KEY idx_char (character_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "[OK] Tabla '{$table2}' creada\n";
} else {
    echo "[--] Tabla '{$table2}' ya existe\n";
}

// 3. mybb_game_post_cards (Cartas jugadas en un post)
$table3 = "{$prefix}game_post_cards";
if (!$db->table_exists('game_post_cards')) {
    $db->write_query("CREATE TABLE {$table3} (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        character_id INT NOT NULL,
        card_id INT NOT NULL,
        played_rank ENUM('C', 'B', 'A', 'S', 'SS') NOT NULL DEFAULT 'C',
        roll_result VARCHAR(255) DEFAULT NULL,
        played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_post (post_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "[OK] Tabla '{$table3}' creada\n";
} else {
    echo "[--] Tabla '{$table3}' ya existe\n";
}

// 4. mybb_game_card_requests (Solicitudes de cartas)
$table4 = "{$prefix}game_card_requests";
if (!$db->table_exists('game_card_requests')) {
    $db->write_query("CREATE TABLE {$table4} (
        id INT AUTO_INCREMENT PRIMARY KEY,
        character_id INT NOT NULL,
        card_id INT NOT NULL,
        request_type ENUM('upgrade', 'delete') NOT NULL,
        status ENUM('pendiente', 'aprobada', 'rechazada') NOT NULL DEFAULT 'pendiente',
        current_rank VARCHAR(10) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        resolved_by INT DEFAULT NULL,
        resolved_at TIMESTAMP NULL DEFAULT NULL,
        staff_message TEXT DEFAULT NULL,
        KEY idx_character (character_id),
        KEY idx_card (card_id),
        KEY idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "[OK] Tabla '{$table4}' creada\n";
} else {
    echo "[--] Tabla '{$table4}' ya existe\n";
}

echo "\n=== Migración completada ===\n";
echo "</pre>";
