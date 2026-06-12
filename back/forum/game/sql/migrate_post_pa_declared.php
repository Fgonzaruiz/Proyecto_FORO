<?php
declare(strict_types=1);

/**
 * Migración: Añadir columna pa_declared a game_post_characters.
 */
global $db;
$prefix = TABLE_PREFIX;

if ($db->table_exists('game_post_characters')) {
    if (!$db->field_exists('pa_declared', 'game_post_characters')) {
        $db->write_query("ALTER TABLE {$prefix}game_post_characters 
            ADD COLUMN pa_declared TINYINT UNSIGNED NOT NULL DEFAULT 0 
            COMMENT 'PA declarado gastado por el jugador en este post (referencia para staff, no validación automática)'
            AFTER pe_change");
        echo "<p class='ok'>[OK] Columna 'pa_declared' añadida a 'game_post_characters'.</p>";
    } else {
        echo "<p class='skip'>[--] Columna 'pa_declared' ya existe en 'game_post_characters'.</p>";
    }
} else {
    echo "<p class='error'>[ERROR] La tabla 'game_post_characters' no existe.</p>";
}
