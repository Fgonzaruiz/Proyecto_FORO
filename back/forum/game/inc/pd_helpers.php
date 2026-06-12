<?php
declare(strict_types=1);

/**
 * Helpers para el sistema de Puntos Destino (PD) y compras.
 */

function game_get_character_pd_total(int $characterId): int
{
    global $db;
    $prefix = TABLE_PREFIX;
    $q = $db->query("SELECT puntos_destino FROM {$prefix}game_personajes WHERE id = {$characterId} LIMIT 1");
    $pj = $db->fetch_array($q);
    return $pj ? (int)$pj['puntos_destino'] : 0;
}

function game_get_character_pd_spent(int $characterId): int
{
    global $db;
    $prefix = TABLE_PREFIX;
    $q = $db->query("SELECT SUM(pd_cost) AS total_spent FROM {$prefix}game_pd_purchases WHERE character_id = {$characterId}");
    $res = $db->fetch_array($q);
    return $res ? (int)$res['total_spent'] : 0;
}

function game_get_character_pd_available(int $characterId): int
{
    $total = game_get_character_pd_total($characterId);
    $spent = game_get_character_pd_spent($characterId);
    return max(0, $total - $spent);
}

function game_get_character_purchases(int $characterId): array
{
    global $db;
    $prefix = TABLE_PREFIX;
    $purchases = [];
    $q = $db->query("
        SELECT id, pd_cost, item_type, item_slug, item_name, purchased_at 
        FROM {$prefix}game_pd_purchases 
        WHERE character_id = {$characterId} 
        ORDER BY purchased_at DESC
    ");
    while ($p = $db->fetch_array($q)) {
        $purchases[] = $p;
    }
    return $purchases;
}

function game_register_pd_purchase(int $characterId, int $cost, string $itemType, string $itemSlug, string $itemName): bool
{
    global $db;
    $prefix = TABLE_PREFIX;
    $escType = $db->escape_string($itemType);
    $escSlug = $db->escape_string($itemSlug);
    $escName = $db->escape_string($itemName);

    return (bool)$db->write_query("
        INSERT INTO {$prefix}game_pd_purchases (character_id, pd_cost, item_type, item_slug, item_name, purchased_at)
        VALUES ({$characterId}, {$cost}, '{$escType}', '{$escSlug}', '{$escName}', NOW())
    ");
}
