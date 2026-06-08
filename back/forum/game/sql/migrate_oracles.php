<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
game_require_admin_cp();

global $db;
$prefix = TABLE_PREFIX;

echo "<pre class='rpg-admin-pre'>\n";
echo "=== Migración: Sistema de Oráculos ===\n\n";

// 1. mybb_game_oracles (Catálogo maestro de oráculos)
$table = "{$prefix}game_oracles";
if (!$db->table_exists('game_oracles')) {
    $db->write_query("CREATE TABLE {$table} (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        description TEXT,
        oracle_type VARCHAR(30) NOT NULL DEFAULT 'custom',
        subtype VARCHAR(100) DEFAULT '',
        category VARCHAR(100) DEFAULT '',
        tags_json TEXT,
        results_json TEXT NOT NULL,
        variations_json TEXT,
        auto_invoke_json TEXT,
        dice_type VARCHAR(10) NOT NULL DEFAULT 'd100',
        is_system TINYINT(1) NOT NULL DEFAULT 0,
        image_url VARCHAR(500) DEFAULT '',
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_type (oracle_type),
        KEY idx_category (category),
        KEY idx_subtype (subtype)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "[OK] Tabla '{$table}' creada\n";
} else {
    echo "[--] Tabla '{$table}' ya existe\n";
}

// 2. mybb_game_post_oracles (Oráculos ejecutados en posts)
$table2 = "{$prefix}game_post_oracles";
if (!$db->table_exists('game_post_oracles')) {
    $db->write_query("CREATE TABLE {$table2} (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        character_id INT NOT NULL,
        oracle_id INT NOT NULL,
        roll_value VARCHAR(20) NOT NULL,
        result_range VARCHAR(20) NOT NULL DEFAULT '',
        result_text TEXT NOT NULL,
        result_description TEXT,
        auto_invoked TINYINT(1) NOT NULL DEFAULT 0,
        invoked_by_post_oracle_id INT DEFAULT NULL,
        rolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_post (post_id),
        KEY idx_oracle (oracle_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    echo "[OK] Tabla '{$table2}' creada\n";
} else {
    echo "[--] Tabla '{$table2}' ya existe\n";
}

echo "\n=== Migración completada ===\n";
echo "</pre>";
