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
    /**
     * Consumibles (munición, útiles) no requieren slot equipado para jugarse en posts.
     */
    function game_card_requires_equipped_slot(string $cardType, bool $isConsumible = false): bool
    {
        if ($isConsumible) {
            return false;
        }
        return in_array($cardType, ['equipo', 'npc_menor', 'barco'], true);
    }
}

if (!function_exists('game_log_equipped_debug')) {
    /**
     * @param array<string, mixed> $context
     */
    function game_log_equipped_debug(string $event, array $context = []): void
    {
        $debug = (int)($_GET['debug_equipped'] ?? $_POST['debug_equipped'] ?? 0) === 1;
        if (!$debug && !(defined('GAME_DEBUG') && GAME_DEBUG)) {
            return;
        }
        $payload = array_merge(['event' => $event, 'ts' => date('c')], $context);
        error_log('[game_equipped] ' . json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
}
