<?php
declare(strict_types=1);

global $db;
$prefix = TABLE_PREFIX;

if (!$db->table_exists('game_navigation_voyages')) {
    echo "<p class='skip'>[SKIP] game_navigation_voyages no existe.</p>";
    return;
}

$add = static function (string $col, string $def) use ($db, $prefix): void {
    if (!$db->field_exists($col, 'game_navigation_voyages')) {
        $db->write_query("ALTER TABLE {$prefix}game_navigation_voyages ADD COLUMN {$col} {$def}");
        echo "<p class='ok'>[OK] Columna {$col} añadida.</p>";
    } else {
        echo "<p class='skip'>[SKIP] Columna {$col} ya existe.</p>";
    }
};

$add('staff_review', "ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending'");
$add('start_rol_days', 'INT UNSIGNED NOT NULL DEFAULT 0');
$add('expected_end_rol_days', 'INT UNSIGNED NOT NULL DEFAULT 0');
$add('reviewed_at', 'INT UNSIGNED DEFAULT NULL');
$add('reviewed_by_uid', 'INT UNSIGNED DEFAULT NULL');
$add('staff_notice_post_id', 'INT UNSIGNED DEFAULT NULL');
