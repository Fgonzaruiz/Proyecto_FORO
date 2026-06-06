<?php
declare(strict_types=1);

global $db;
$prefix = TABLE_PREFIX;

if ($db->table_exists('game_forum_islands')) {
    echo "<p class='skip'>[SKIP] Tabla {$prefix}game_forum_islands ya existe.</p>";
    return;
}

$db->write_query("CREATE TABLE {$prefix}game_forum_islands (
    fid INT UNSIGNED NOT NULL PRIMARY KEY,
    island_image VARCHAR(500) NOT NULL DEFAULT '',
    leader_name VARCHAR(200) NOT NULL DEFAULT '',
    buildings TEXT NOT NULL,
    climate VARCHAR(200) NOT NULL DEFAULT '',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

echo "<p class='ok'>[OK] Tabla {$prefix}game_forum_islands creada.</p>";
