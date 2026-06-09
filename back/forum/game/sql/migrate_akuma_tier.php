<?php
declare(strict_types=1);

/**
 * Tier y subtipo en biblioteca Akuma; rango D en cartas para tier 1.
 */
global $db;
$prefix = TABLE_PREFIX;

if ($db->table_exists('game_akuma_no_mi')) {
    if (!$db->field_exists('tier', 'game_akuma_no_mi')) {
        $db->write_query("ALTER TABLE {$prefix}game_akuma_no_mi
            ADD COLUMN tier TINYINT UNSIGNED NOT NULL DEFAULT 1
                COMMENT '1-5, tier de poder de la fruta según escala canónica'");
        echo "<p class='ok'>[OK] game_akuma_no_mi.tier añadida.</p>";
    }
    if (!$db->field_exists('subtipo', 'game_akuma_no_mi')) {
        $db->write_query("ALTER TABLE {$prefix}game_akuma_no_mi
            ADD COLUMN subtipo ENUM('ninguno','antiguo','mitico') NOT NULL DEFAULT 'ninguno'
                COMMENT 'Subtipo Zoan; Paramecia/Logia usan ninguno'"
            );
        echo "<p class='ok'>[OK] game_akuma_no_mi.subtipo añadida.</p>";
    }
} else {
    echo "<p class='rpg-admin-warn'>[WARN] Tabla game_akuma_no_mi no existe.</p>";
}

$rankEnum = "'D','C','B','A','S','SS'";
if ($db->table_exists('game_cards')) {
    $db->write_query("ALTER TABLE {$prefix}game_cards
        MODIFY `rank` ENUM({$rankEnum}) NOT NULL DEFAULT 'C'");
    echo "<p class='ok'>[OK] game_cards.rank incluye D.</p>";
}
if ($db->table_exists('game_character_cards')) {
    $db->write_query("ALTER TABLE {$prefix}game_character_cards
        MODIFY current_rank ENUM({$rankEnum}) NOT NULL DEFAULT 'C'");
    echo "<p class='ok'>[OK] game_character_cards.current_rank incluye D.</p>";
}
if ($db->table_exists('game_post_cards')) {
    $db->write_query("ALTER TABLE {$prefix}game_post_cards
        MODIFY played_rank ENUM({$rankEnum}) NOT NULL DEFAULT 'C'");
    echo "<p class='ok'>[OK] game_post_cards.played_rank incluye D.</p>";
}
