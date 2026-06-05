<?php
declare(strict_types=1);

/**
 * Helpers de inventario equipado — seguro para incluir desde plugins MyBB (sin recargar global.php).
 */

if (!function_exists('game_get_equipped_card_ids')) {
    /**
     * @return list<int>
     */
    function game_get_equipped_card_ids(int $characterId): array
    {
        global $db;
        if ($characterId <= 0 || !$db->table_exists('game_character_inventory')) {
            return [];
        }
        $prefix = TABLE_PREFIX;
        $q = $db->query("SELECT card_id FROM {$prefix}game_character_inventory WHERE character_id = {$characterId}");
        $ids = [];
        while ($row = $db->fetch_array($q)) {
            $ids[] = (int)$row['card_id'];
        }
        return $ids;
    }
}

if (!function_exists('game_inventory_system_active')) {
    function game_inventory_system_active(): bool
    {
        global $db;
        return $db->table_exists('game_character_inventory');
    }
}

if (!function_exists('game_card_requires_equipped_slot')) {
    function game_card_requires_equipped_slot(string $cardType): bool
    {
        return in_array($cardType, ['equipo', 'npc_menor', 'barco'], true);
    }
}
