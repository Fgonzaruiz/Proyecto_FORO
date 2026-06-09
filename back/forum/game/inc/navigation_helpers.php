<?php
declare(strict_types=1);

require_once __DIR__ . '/navigation_config.php';

if (!function_exists('game_oficio_get_rank')) {
    require_once __DIR__ . '/oficios_helpers.php';
}

function game_nav_get_island_from_forum(int $fid): ?array
{
    global $db;
    if ($fid <= 0 || !$db->table_exists('game_forum_islands')) {
        return null;
    }
    $prefix = TABLE_PREFIX;
    $q = $db->query("SELECT i.*, f.name AS forum_name FROM {$prefix}game_forum_islands i
        JOIN {$prefix}forums f ON f.fid = i.fid
        WHERE i.fid = " . (int)$fid . " LIMIT 1");
    if ($row = $db->fetch_array($q)) {
        return $row;
    }

    $forum = get_forum($fid);
    if ($forum && (int)($forum['pid'] ?? 0) > 0) {
        return game_nav_get_island_from_forum((int)$forum['pid']);
    }
    return null;
}

/** @return array{distance:int, waypoints:array, is_precalculated:bool, danger_override:?int} */
function game_nav_calculate_distance(int $islandFromFid, int $islandToFid): array
{
    global $db;
    $prefix = TABLE_PREFIX;
    $from = (int)$islandFromFid;
    $to = (int)$islandToFid;

    if ($db->table_exists('game_navigation_routes')) {
        $q = $db->query("SELECT * FROM {$prefix}game_navigation_routes
            WHERE (island_from_fid = {$from} AND island_to_fid = {$to})
               OR (island_from_fid = {$to} AND island_to_fid = {$from})
            LIMIT 1");
        if ($route = $db->fetch_array($q)) {
            $wps = json_decode($route['waypoint_fids'] ?? '[]', true);
            return [
                'distance' => (int)$route['distance'],
                'waypoints' => is_array($wps) ? array_map('intval', $wps) : [],
                'is_precalculated' => true,
                'danger_override' => $route['danger_override'] !== null ? (int)$route['danger_override'] : null,
            ];
        }
    }

    $fromRow = $db->fetch_array($db->query("SELECT coord_x, coord_y FROM {$prefix}game_forum_islands WHERE fid = {$from} LIMIT 1"));
    $toRow = $db->fetch_array($db->query("SELECT coord_x, coord_y FROM {$prefix}game_forum_islands WHERE fid = {$to} LIMIT 1"));
    if (!$fromRow || !$toRow) {
        return ['distance' => 100, 'waypoints' => [], 'is_precalculated' => false, 'danger_override' => null];
    }

    $dx = (int)$fromRow['coord_x'] - (int)$toRow['coord_x'];
    $dy = (int)$fromRow['coord_y'] - (int)$toRow['coord_y'];
    $dist = (int)round(sqrt($dx * $dx + $dy * $dy));

    return [
        'distance' => max(1, $dist),
        'waypoints' => [],
        'is_precalculated' => false,
        'danger_override' => null,
    ];
}

function game_nav_calculate_danger(array $islandFrom, array $islandTo, array $waypointFids, ?int $dangerOverride): int
{
    if ($dangerOverride !== null) {
        return max(1, min(5, $dangerOverride));
    }

    $dangers = [(int)($islandFrom['base_danger'] ?? 1), (int)($islandTo['base_danger'] ?? 1)];

    if (!empty($waypointFids)) {
        global $db;
        $prefix = TABLE_PREFIX;
        $ids = implode(',', array_map('intval', $waypointFids));
        $q = $db->query("SELECT base_danger FROM {$prefix}game_forum_islands WHERE fid IN ({$ids})");
        while ($wp = $db->fetch_array($q)) {
            $dangers[] = (int)$wp['base_danger'];
        }
    }

    $max = max($dangers);
    $avg = array_sum($dangers) / count($dangers);
    $interpolated = ($max * 0.4) + ($avg * 0.6);

    return max(1, min(5, (int)round($interpolated)));
}

function game_nav_effective_speed(array $shipEffects, string $seaZone, int $navigatorRank, string $instrument): float
{
    $base = (float)($shipEffects['velocidad_base'] ?? $shipEffects['velocidad'] ?? 5);
    if ($base <= 0) {
        $base = 5.0;
    }

    $zoneKey = 'nav_bonus_' . preg_replace('/[^a-z_]/', '', $seaZone);
    $zoneMod = (float)($shipEffects[$zoneKey] ?? 0);
    $navMod = game_oficio_rank_bonus($navigatorRank);

    $instrumentBonus = match ($instrument) {
        'compass' => 0.0,
        'log_pose' => 0.5,
        'eternal_pose' => 1.0,
        default => -GAME_NAV_NO_INSTRUMENT_SPEED_PENALTY,
    };

    return max(1.0, $base + $zoneMod + $navMod + $instrumentBonus);
}

function game_nav_calculate_duration(int $distance, float $effectiveSpeed): int
{
    $factor = defined('GAME_NAV_SPEED_FACTOR') ? GAME_NAV_SPEED_FACTOR : 10;
    return max(1, (int)ceil($distance / ($effectiveSpeed * $factor)));
}

function game_nav_calculate_events(int $danger, int $duration, bool $withRandom = true): int
{
    $base = match ($danger) {
        1 => 0,
        2 => 1,
        3 => 2,
        4 => 3,
        5 => 4,
        default => 0,
    };

    if ($duration >= 5) {
        $base++;
    }
    if ($duration >= 10) {
        $base++;
    }

    if ($withRandom) {
        $base += mt_rand(0, 2);
    }

    return max(GAME_NAV_EVENTS_MIN, min(GAME_NAV_EVENTS_MAX, $base));
}

/** @return array{min:int, max:int} */
function game_nav_events_range(int $danger, int $duration): array
{
    $min = game_nav_calculate_events($danger, $duration, false);
    return ['min' => $min, 'max' => min(GAME_NAV_EVENTS_MAX, $min + 2)];
}

function game_nav_danger_label(int $level): string
{
    return match ($level) {
        1 => 'Tranquilo',
        2 => 'Moderado',
        3 => 'Peligroso',
        4 => 'Muy peligroso',
        5 => 'EXTREMO',
        default => '—',
    };
}

/** @return list<array> */
function game_nav_get_oracles_for_danger(int $danger): array
{
    global $db;
    if (!$db->table_exists('game_oracles')) {
        return [];
    }
    $prefix = TABLE_PREFIX;
    $q = $db->query("SELECT * FROM {$prefix}game_oracles
        WHERE (tags_json LIKE '%navegacion%' AND (subtype LIKE 'nav_%' OR subtype = 'navegacion'))
        ORDER BY id ASC");

    $all = [];
    while ($row = $db->fetch_array($q)) {
        $all[] = $row;
    }

    $matching = [];
    foreach ($all as $oracle) {
        $subtype = (string)($oracle['subtype'] ?? '');
        if ($subtype === 'navegacion') {
            $matching[] = $oracle;
            continue;
        }
        if (!str_starts_with($subtype, 'nav_')) {
            continue;
        }
        $parts = explode('_', str_replace('nav_', '', $subtype));
        foreach ($parts as $st) {
            if (is_numeric($st) && (int)$st <= $danger) {
                $matching[] = $oracle;
                break;
            }
        }
    }

    return !empty($matching) ? $matching : $all;
}

/** @return list<array> */
function game_nav_list_islands(int $excludeFid = 0): array
{
    global $db;
    if (!$db->table_exists('game_forum_islands')) {
        return [];
    }
    $prefix = TABLE_PREFIX;
    $exclude = $excludeFid > 0 ? 'WHERE i.fid != ' . (int)$excludeFid : '';
    $q = $db->query("SELECT i.*, f.name FROM {$prefix}game_forum_islands i
        JOIN {$prefix}forums f ON f.fid = i.fid
        {$exclude}
        ORDER BY f.name ASC");
    $out = [];
    while ($row = $db->fetch_array($q)) {
        $out[] = [
            'fid' => (int)$row['fid'],
            'name' => $row['name'] ?? '',
            'sea_zone' => $row['sea_zone'] ?? 'east_blue',
            'base_danger' => (int)($row['base_danger'] ?? 1),
            'requires_log_pose' => (int)($row['requires_log_pose'] ?? 0),
            'requires_compass' => (int)($row['requires_compass'] ?? 0),
            'coord_x' => (int)($row['coord_x'] ?? 0),
            'coord_y' => (int)($row['coord_y'] ?? 0),
            'image_url' => $row['island_image'] ?? '',
        ];
    }
    return $out;
}

/** @return list<array> */
function game_nav_ships_for_character(int $characterId): array
{
    global $db;
    if ($characterId <= 0 || !$db->table_exists('game_character_inventory')) {
        return [];
    }
    $prefix = TABLE_PREFIX;
    $q = $db->query("
        SELECT c.id AS card_id, c.name, c.effects_json, c.image_url
        FROM {$prefix}game_character_inventory i
        JOIN {$prefix}game_cards c ON c.id = i.card_id
        WHERE i.character_id = " . (int)$characterId . "
          AND i.slot_type = 'barco'
          AND c.card_type = 'barco'
        ORDER BY i.equipped_at ASC
    ");
    $ships = [];
    while ($row = $db->fetch_array($q)) {
        $effects = json_decode($row['effects_json'] ?? '{}', true);
        if (!is_array($effects)) {
            $effects = [];
        }
        $ships[] = [
            'card_id' => (int)$row['card_id'],
            'name' => $row['name'],
            'image_url' => $row['image_url'] ?? '',
            'velocidad' => (int)($effects['velocidad_base'] ?? $effects['velocidad'] ?? 5),
            'effects' => $effects,
        ];
    }
    return $ships;
}

/** @return list<string> */
function game_nav_instrument_keys(): array
{
    return ['compass', 'log_pose', 'eternal_pose'];
}

function game_nav_instrument_meta(string $key): array
{
    $catalog = [
        'compass' => ['label' => 'Brújula', 'subtitle' => 'Blues', 'icon' => 'fa-compass'],
        'log_pose' => ['label' => 'Log Pose', 'subtitle' => 'Grand Line', 'icon' => 'fa-map-marked-alt'],
        'eternal_pose' => ['label' => 'Eternal Pose', 'subtitle' => 'Isla fija', 'icon' => 'fa-map-pin'],
    ];

    return $catalog[$key] ?? ['label' => $key, 'subtitle' => '', 'icon' => 'fa-location-arrow'];
}

function game_nav_detect_instrument_from_card(array $cardRow): ?string
{
    $effects = json_decode($cardRow['effects_json'] ?? '{}', true);
    if (is_array($effects) && !empty($effects['nav_instrument'])) {
        $key = preg_replace('/[^a-z_]/', '', strtolower((string)$effects['nav_instrument']));
        if (in_array($key, game_nav_instrument_keys(), true)) {
            return $key;
        }
    }

    $tags = json_decode($cardRow['tags_json'] ?? '[]', true);
    if (!is_array($tags)) {
        $tags = [];
    }
    $haystack = mb_strtoupper(implode(' ', array_merge($tags, [(string)($cardRow['name'] ?? '')])));

    if (str_contains($haystack, 'ETERNAL POSE') || str_contains($haystack, 'ETERNAL_POSE')) {
        return 'eternal_pose';
    }
    if (str_contains($haystack, 'LOG POSE') || str_contains($haystack, 'LOG_POSE')) {
        return 'log_pose';
    }
    if (str_contains($haystack, 'BRÚJULA') || str_contains($haystack, 'BRUJULA') || str_contains($haystack, 'COMPASS')) {
        return 'compass';
    }

    return null;
}

/** @return list<array> */
function game_nav_instruments_for_character(int $characterId): array
{
    global $db;
    if ($characterId <= 0 || !$db->table_exists('game_character_inventory')) {
        return [];
    }

    $prefix = TABLE_PREFIX;
    $q = $db->query("
        SELECT c.id AS card_id, c.name, c.image_url, c.effects_json, c.tags_json
        FROM {$prefix}game_character_inventory i
        JOIN {$prefix}game_cards c ON c.id = i.card_id
        WHERE i.character_id = " . (int)$characterId . "
          AND i.slot_type = 'equipo'
        ORDER BY i.equipped_at ASC
    ");

    $out = [];
    $seen = [];
    while ($row = $db->fetch_array($q)) {
        $key = game_nav_detect_instrument_from_card($row);
        if ($key === null || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $meta = game_nav_instrument_meta($key);
        $out[] = [
            'card_id' => (int)$row['card_id'],
            'instrument_key' => $key,
            'name' => $row['name'],
            'image_url' => $row['image_url'] ?? '',
            'label' => $meta['label'],
            'subtitle' => $meta['subtitle'],
            'icon' => $meta['icon'],
        ];
    }

    return $out;
}

function game_nav_compute_voyage(
    int $fromFid,
    int $toFid,
    array $shipEffects,
    int $navigatorRank,
    string $instrument
): array {
    global $db;
    $prefix = TABLE_PREFIX;

    $islandFrom = $db->fetch_array($db->query("SELECT * FROM {$prefix}game_forum_islands WHERE fid = " . (int)$fromFid . " LIMIT 1"));
    $islandTo = $db->fetch_array($db->query("SELECT * FROM {$prefix}game_forum_islands WHERE fid = " . (int)$toFid . " LIMIT 1"));
    if (!$islandFrom || !$islandTo) {
        return ['ok' => false, 'error' => 'Isla no encontrada'];
    }

    $route = game_nav_calculate_distance($fromFid, $toFid);
    $danger = game_nav_calculate_danger($islandFrom, $islandTo, $route['waypoints'], $route['danger_override']);
    $seaZone = $danger >= 3 ? ($islandTo['sea_zone'] ?? 'grand_line') : ($islandFrom['sea_zone'] ?? 'east_blue');
    $effSpeed = game_nav_effective_speed($shipEffects, (string)$seaZone, $navigatorRank, $instrument);
    $duration = game_nav_calculate_duration((int)$route['distance'], $effSpeed);
    $eventsRange = game_nav_events_range($danger, $duration);

    return [
        'ok' => true,
        'distance' => (int)$route['distance'],
        'danger_level' => $danger,
        'danger_label' => game_nav_danger_label($danger),
        'effective_speed' => round($effSpeed, 2),
        'duration_days' => $duration,
        'events_min' => $eventsRange['min'],
        'events_max' => $eventsRange['max'],
        'sea_zone' => $seaZone,
        'route' => $route,
    ];
}
