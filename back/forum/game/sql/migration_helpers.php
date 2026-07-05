<?php
declare(strict_types=1);

/**
 * Helpers para runner de migraciones (requiere bootstrap MyBB cargado).
 */

function game_migration_table_exists(): bool
{
    global $db;
    return $db->table_exists('game_schema_migrations');
}

function game_migration_ensure_tracking_table(): void
{
    global $db;
    $prefix = TABLE_PREFIX;
    if ($db->table_exists('game_schema_migrations')) {
        return;
    }
    $db->write_query("CREATE TABLE {$prefix}game_schema_migrations (
        name VARCHAR(128) NOT NULL PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function game_migration_applied(string $name): bool
{
    global $db;
    if (!game_migration_table_exists()) {
        return false;
    }
    $prefix = TABLE_PREFIX;
    $esc = $db->escape_string($name);
    $q = $db->query("SELECT 1 FROM {$prefix}game_schema_migrations WHERE name = '{$esc}' LIMIT 1");
    return (bool)$db->fetch_array($q);
}

function game_migration_mark_applied(string $name): void
{
    global $db;
    game_migration_ensure_tracking_table();
    $prefix = TABLE_PREFIX;
    $esc = $db->escape_string($name);
    $db->write_query("INSERT IGNORE INTO {$prefix}game_schema_migrations (name, applied_at) VALUES ('{$esc}', NOW())");
}

/** @return list<string> */
function game_migration_ordered_scripts(): array
{
    return [
        'migrate_pj_system.php',
        'migrate_notifications.php',
        'migrate_thread_meta.php',
        'migrate_staff_levels.php',
        'migrate_aprobar_pj.php',
        'migrate_busquedas.php',
        'migrate_cards.php',
        'migrate_cards_fields.php',
        'migrate_alter_dice.php',
        'migrate_card_requests_v2.php',
        'migrate_character_cards_quantity.php',
        'migrate_announcements.php',
        'migrate_thread_pj_state.php',
        'migrate_post_modifiers.php',
        'migrate_post_equipped_snapshot.php',
        'migrate_npc_system.php',
        'migrate_hidden_actions.php',
        'migrate_oracles.php',
        'migrate_execution_cost.php',
        'migrate_inventory.php',
        'migrate_direct_messages.php',
        'migrate_import_legacy_pms.php',
        'migrate_shop_fields.php',
        'migrate_forum_islands.php',
        'migrate_forum_islands_v2.php', // columnas v2 incluidas en install_db; no-op si ya aplicado
        'migrate_oficios_system.php',
        'migrate_disciplinas_system.php',
        'migrate_competencias_v2.php',
        'migrate_drop_haki_disciplinas.php',
        'migrate_drop_haki_conquistador.php',
        'migrate_mycode_spoiler.php',
        'migrate_stats_v7.php',
        'migrate_drop_legacy_schema.php',
        'migrate_cards_drop_upgrade.php',
        'migrate_post_pa_declared.php',
        'migrate_missions_system.php',
        'migrate_missions_faction.php',
        'migrate_post_cooldown_mods.php',
        'migrate_hxh_initial.php',
        'migrate_hxh_currency_missions.php',
        'seed_locations_hxh.php',
        'provision_hxh_forums.php',
        'provision_hxh_world_structure.php',
        'migrate_hxh_oficios_disciplinas.php',
    ];
}
