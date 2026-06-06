<?php
declare(strict_types=1);

global $db;
$prefix = TABLE_PREFIX;

if (!$db->table_exists('game_forum_islands')) {
    echo "<p class='skip'>[SKIP] Tabla game_forum_islands no existe. Ejecuta migrate_forum_islands.php primero.</p>";
    return;
}

// Check if description column already exists
$cols = $db->query("SHOW COLUMNS FROM {$prefix}game_forum_islands LIKE 'description'");
if ($db->num_rows($cols)) {
    echo "<p class='skip'>[SKIP] Columnas v2 ya existen.</p>";
    return;
}

$db->write_query("ALTER TABLE {$prefix}game_forum_islands
    ADD COLUMN description TEXT NOT NULL AFTER leader_name,
    ADD COLUMN terrain VARCHAR(200) NOT NULL DEFAULT '' AFTER description,
    ADD COLUMN climate_temp VARCHAR(100) NOT NULL DEFAULT '' AFTER climate,
    ADD COLUMN climate_wind VARCHAR(100) NOT NULL DEFAULT '' AFTER climate_temp,
    ADD COLUMN climate_precip VARCHAR(100) NOT NULL DEFAULT '' AFTER climate_wind,
    ADD COLUMN defenses TEXT NOT NULL AFTER buildings,
    ADD COLUMN resources VARCHAR(300) NOT NULL DEFAULT '' AFTER defenses,
    MODIFY COLUMN climate VARCHAR(300) NOT NULL DEFAULT '',
    MODIFY COLUMN buildings TEXT NOT NULL
");

echo "<p class='ok'>[OK] Columnas v2 agregadas a {$prefix}game_forum_islands.</p>";
