<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
game_require_admin_cp();

global $db;
$prefix = TABLE_PREFIX;

echo "<pre class='rpg-admin-pre'>\n";
echo "=== Migración: Acciones Ocultas (Oculto) ===\n\n";

// 1. Añadir hidden_actions_json a game_post_characters
$table_chars = "{$prefix}game_post_characters";
if ($db->table_exists('game_post_characters')) {
    if (!$db->field_exists('hidden_actions_json', 'game_post_characters')) {
        $db->write_query("ALTER TABLE {$table_chars} ADD hidden_actions_json TEXT DEFAULT NULL");
        echo "[OK] Columna 'hidden_actions_json' añadida a 'game_post_characters'.\n";
    } else {
        echo "[--] Columna 'hidden_actions_json' ya existe en 'game_post_characters'.\n";
    }
} else {
    echo "[ERROR] La tabla 'game_post_characters' no existe.\n";
}

// 2. Añadir hidden_action_index a game_post_cards
$table_cards = "{$prefix}game_post_cards";
if ($db->table_exists('game_post_cards')) {
    if (!$db->field_exists('hidden_action_index', 'game_post_cards')) {
        $db->write_query("ALTER TABLE {$table_cards} ADD hidden_action_index INT NOT NULL DEFAULT 0");
        echo "[OK] Columna 'hidden_action_index' añadida a 'game_post_cards'.\n";
    } else {
        echo "[--] Columna 'hidden_action_index' ya existe en 'game_post_cards'.\n";
    }
} else {
    echo "[ERROR] La tabla 'game_post_cards' no existe.\n";
}

echo "\n=== Fin de la migración ===\n";
echo "</pre>";
