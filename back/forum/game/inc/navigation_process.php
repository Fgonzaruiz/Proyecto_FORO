<?php
declare(strict_types=1);

require_once __DIR__ . '/navigation_helpers.php';
require_once __DIR__ . '/rol_calendar_helpers.php';

if (!function_exists('game_roll_oracle')) {
    require_once __DIR__ . '/oracle_helpers.php';
}

function game_navigation_process_post(int $postId, int $threadId, int $characterId, array $input): ?int
{
    global $db;

    if (empty($input['rpg_nav_enabled']) || (string)$input['rpg_nav_enabled'] !== '1') {
        return null;
    }

    // Navigation only allowed in Presente threads
    $prefix = TABLE_PREFIX;
    $metaQ = $db->query("SELECT thread_type FROM {$prefix}game_thread_meta WHERE thread_id = " . (int)$threadId . " LIMIT 1");
    if ($metaRow = $db->fetch_array($metaQ)) {
        if ($metaRow['thread_type'] !== 'Presente') {
            return null;
        }
    }

    $islandToFid = (int)($input['rpg_nav_destination'] ?? $input['nav_destination_island_id'] ?? 0);
    $shipCardId = (int)($input['rpg_nav_ship'] ?? $input['nav_ship_card_id'] ?? 0);
    $instrument = preg_replace('/[^a-z_]/', '', (string)($input['rpg_nav_instrument'] ?? $input['nav_instrument'] ?? 'none'));
    if ($instrument === '') {
        $instrument = 'none';
    }
    if ($instrument !== 'none' && function_exists('game_nav_instruments_for_character')) {
        $allowed = array_column(game_nav_instruments_for_character($characterId), 'instrument_key');
        if (!in_array($instrument, $allowed, true)) {
            $instrument = 'none';
        }
    }

    if ($islandToFid <= 0 || $shipCardId <= 0) {
        return null;
    }

    $prefix = TABLE_PREFIX;
    $postRow = $db->fetch_array($db->query("SELECT fid FROM {$prefix}posts WHERE pid = " . (int)$postId . " LIMIT 1"));
    if (!$postRow) {
        return null;
    }

    $islandFrom = game_nav_get_island_from_forum((int)$postRow['fid']);
    if (!$islandFrom) {
        return null;
    }

    $fromFid = (int)$islandFrom['fid'];
    if ($fromFid === $islandToFid) {
        return null;
    }

    $islandTo = $db->fetch_array($db->query("SELECT * FROM {$prefix}game_forum_islands WHERE fid = " . (int)$islandToFid . " LIMIT 1"));
    if (!$islandTo) {
        return null;
    }

    $shipCard = $db->fetch_array($db->query("SELECT * FROM {$prefix}game_cards WHERE id = " . (int)$shipCardId . " AND card_type = 'barco' LIMIT 1"));
    if (!$shipCard) {
        return null;
    }

    $equipped = $db->query("SELECT 1 FROM {$prefix}game_character_inventory
        WHERE character_id = " . (int)$characterId . " AND card_id = " . (int)$shipCardId . " AND slot_type = 'barco' LIMIT 1");
    if (!$db->num_rows($equipped)) {
        return null;
    }

    $shipEffects = json_decode($shipCard['effects_json'] ?? '{}', true);
    if (!is_array($shipEffects)) {
        $shipEffects = [];
    }

    $navigatorRank = game_oficio_get_rank($characterId, 'navegante');

    $route = game_nav_calculate_distance($fromFid, $islandToFid);
    $distance = (int)$route['distance'];
    $danger = game_nav_calculate_danger($islandFrom, $islandTo, $route['waypoints'], $route['danger_override']);
    $seaZone = $danger >= 3 ? ($islandTo['sea_zone'] ?? 'grand_line') : ($islandFrom['sea_zone'] ?? 'east_blue');
    $effSpeed = game_nav_effective_speed($shipEffects, (string)$seaZone, $navigatorRank, $instrument);
    $duration = game_nav_calculate_duration($distance, $effSpeed);
    $numEvents = game_nav_calculate_events($danger, $duration, true);

    $baseSpeed = (float)($shipEffects['velocidad_base'] ?? $shipEffects['velocidad'] ?? 5);
    $instrumentBonus = (int)round($effSpeed - $baseSpeed - game_oficio_rank_bonus($navigatorRank));

    $raw = json_encode([
        'island_from' => $islandFrom,
        'island_to' => $islandTo,
        'route' => $route,
        'danger' => $danger,
        'ship_effects' => $shipEffects,
        'sea_zone' => $seaZone,
        'effective_speed' => $effSpeed,
        'navigator_rank' => $navigatorRank,
    ], JSON_UNESCAPED_UNICODE);

    $startRolDays = game_rol_days_at();
    $expectedEndRolDays = $startRolDays + max(1, $duration);

    $insert = [
        'post_id' => $postId,
        'thread_id' => $threadId,
        'character_id' => $characterId,
        'ship_card_id' => $shipCardId,
        'island_from_fid' => $fromFid,
        'island_to_fid' => $islandToFid,
        'distance' => $distance,
        'danger_level' => $danger,
        'duration_days' => $duration,
        'num_events' => $numEvents,
        'navigator_bonus' => $navigatorRank,
        'instrument_used' => $instrument,
        'instrument_bonus' => $instrumentBonus,
        'raw_calculation_json' => $raw,
        'status' => 'active',
    ];
    if ($db->field_exists('staff_review', 'game_navigation_voyages')) {
        $insert['staff_review'] = 'pending';
        $insert['start_rol_days'] = $startRolDays;
        $insert['expected_end_rol_days'] = $expectedEndRolDays;
    }
    $db->insert_query('game_navigation_voyages', $insert);

    $voyageId = (int)$db->insert_id();
    if ($voyageId > 0 && $numEvents > 0) {
        game_navigation_generate_events($voyageId, $postId, $characterId, $numEvents, $danger);
    }

    return $voyageId > 0 ? $voyageId : null;
}

function game_navigation_insert_post_oracle(
    int $postId,
    int $characterId,
    array $oracle,
    array $rollResult,
    int $autoInvoked = 1,
    ?int $invokedByPostOracleId = null
): int {
    global $db;

    $insert = [
        'post_id' => $postId,
        'character_id' => $characterId,
        'oracle_id' => (int)$oracle['id'],
        'roll_value' => $db->escape_string((string)$rollResult['roll']),
        'result_range' => $db->escape_string((string)$rollResult['range']),
        'result_text' => $db->escape_string((string)$rollResult['result']),
        'result_description' => $db->escape_string((string)($rollResult['description'] ?? '')),
        'auto_invoked' => $autoInvoked,
    ];
    if ($invokedByPostOracleId !== null && $invokedByPostOracleId > 0) {
        $insert['invoked_by_post_oracle_id'] = $invokedByPostOracleId;
    }
    $db->insert_query('game_post_oracles', $insert);

    return (int)$db->insert_id();
}

function game_navigation_maybe_invoke_chain(
    int $postId,
    int $characterId,
    array $rollResult,
    string $category,
    int $parentPostOracleId
): void {
    global $db;

    $autoInvoke = $rollResult['auto_invoke'] ?? null;
    if (!$autoInvoke || empty($autoInvoke['oracle_id'])) {
        return;
    }

    $invokeId = (int)$autoInvoke['oracle_id'];
    $prefix = TABLE_PREFIX;
    $autoQ = $db->query("SELECT * FROM {$prefix}game_oracles WHERE id = {$invokeId} LIMIT 1");
    if (!$autoRow = $db->fetch_array($autoQ)) {
        return;
    }

    $autoResult = game_roll_oracle($autoRow, $category);
    game_navigation_insert_post_oracle($postId, $characterId, $autoRow, $autoResult, 1, $parentPostOracleId);
}

function game_navigation_generate_events(int $voyageId, int $postId, int $characterId, int $numEvents, int $danger): void
{
    global $db;

    $category = game_get_post_category($postId);
    $available = game_nav_get_oracles_for_danger($danger);
    if (empty($available)) {
        return;
    }

    for ($i = 1; $i <= $numEvents; $i++) {
        $oracle = $available[array_rand($available)];
        $rollResult = game_roll_oracle($oracle, $category);

        $postOracleId = game_navigation_insert_post_oracle($postId, $characterId, $oracle, $rollResult, 1);
        if ($postOracleId > 0) {
            $db->insert_query('game_navigation_events', [
                'voyage_id' => $voyageId,
                'post_oracle_id' => $postOracleId,
                'event_order' => $i,
                'danger_tier' => $danger,
            ]);
            game_navigation_maybe_invoke_chain($postId, $characterId, $rollResult, $category, $postOracleId);
        }
    }
}

/** @return ?array Voyage payload for cards_for_post */
function game_navigation_voyage_for_post(int $postId): ?array
{
    global $db;
    if (!$db->table_exists('game_navigation_voyages') || $postId <= 0) {
        return null;
    }

    $prefix = TABLE_PREFIX;
    $voyage = $db->fetch_array($db->query("SELECT * FROM {$prefix}game_navigation_voyages WHERE post_id = " . (int)$postId . " LIMIT 1"));
    if (!$voyage) {
        return null;
    }

    $events = [];
    $navPostOracleIds = [];
    $vq = $db->query("
        SELECT ne.event_order, ne.danger_tier, ne.post_oracle_id, po.roll_value, po.result_range, po.result_text,
               po.result_description, po.oracle_id, o.name AS oracle_name, o.oracle_type, o.dice_type, o.subtype
        FROM {$prefix}game_navigation_events ne
        JOIN {$prefix}game_post_oracles po ON po.id = ne.post_oracle_id
        JOIN {$prefix}game_oracles o ON o.id = po.oracle_id
        WHERE ne.voyage_id = " . (int)$voyage['id'] . "
        ORDER BY ne.event_order ASC
    ");
    while ($ev = $db->fetch_array($vq)) {
        $postOracleId = (int)$ev['post_oracle_id'];
        $navPostOracleIds[] = $postOracleId;
        $invoked = [];
        $iq = $db->query("
            SELECT po.id, po.roll_value, po.result_range, po.result_text, po.result_description, po.auto_invoked,
                   o.name AS oracle_name, o.dice_type, o.subtype
            FROM {$prefix}game_post_oracles po
            JOIN {$prefix}game_oracles o ON o.id = po.oracle_id
            WHERE po.invoked_by_post_oracle_id = {$postOracleId}
            ORDER BY po.id ASC
        ");
        while ($child = $db->fetch_array($iq)) {
            $navPostOracleIds[] = (int)$child['id'];
            $invoked[] = $child;
        }
        $ev['invoked'] = $invoked;
        $events[] = $ev;
    }

    $fromFid = (int)$voyage['island_from_fid'];
    $toFid = (int)$voyage['island_to_fid'];
    $islandFrom = $db->fetch_array($db->query("
        SELECT i.fid, f.name, i.island_image AS image_url, i.sea_zone, i.base_danger
        FROM {$prefix}game_forum_islands i
        JOIN {$prefix}forums f ON f.fid = i.fid
        WHERE i.fid = {$fromFid} LIMIT 1
    "));
    $islandTo = $db->fetch_array($db->query("
        SELECT i.fid, f.name, i.island_image AS image_url, i.sea_zone, i.base_danger
        FROM {$prefix}game_forum_islands i
        JOIN {$prefix}forums f ON f.fid = i.fid
        WHERE i.fid = {$toFid} LIMIT 1
    "));
    $shipCard = $db->fetch_array($db->query("SELECT id, name, image_url FROM {$prefix}game_cards WHERE id = " . (int)$voyage['ship_card_id'] . " LIMIT 1"));

    $staffReview = (string)($voyage['staff_review'] ?? '');
    $startRolDays = (int)($voyage['start_rol_days'] ?? 0);
    $expectedEndRolDays = (int)($voyage['expected_end_rol_days'] ?? 0);

    return [
        'id' => (int)$voyage['id'],
        'island_from' => $islandFrom ?: null,
        'island_to' => $islandTo ?: null,
        'ship' => $shipCard ?: null,
        'distance' => (int)$voyage['distance'],
        'danger_level' => (int)$voyage['danger_level'],
        'duration_days' => (int)$voyage['duration_days'],
        'num_events' => (int)$voyage['num_events'],
        'navigator_bonus' => (int)$voyage['navigator_bonus'],
        'instrument' => (string)($voyage['instrument_used'] ?? 'none'),
        'events' => $events,
        'status' => (string)($voyage['status'] ?? 'active'),
        'staff_review' => $staffReview !== '' ? $staffReview : null,
        'start_rol_days' => $startRolDays,
        'expected_end_rol_days' => $expectedEndRolDays,
        'start_rol_label' => $startRolDays > 0 && function_exists('game_rol_date_label')
            ? game_rol_date_label($startRolDays) : '',
        'expected_end_rol_label' => $expectedEndRolDays > 0 && function_exists('game_rol_date_label')
            ? game_rol_date_label($expectedEndRolDays) : '',
        'navigation_post_oracle_ids' => $navPostOracleIds,
    ];
}
