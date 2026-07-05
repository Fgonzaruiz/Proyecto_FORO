<?php
declare(strict_types=1);

require_once __DIR__ . '/hxh_locations_catalog.php';

function game_hxh_is_generic_category_name(string $name): bool
{
    $plain = html_entity_decode(strip_tags($name), ENT_QUOTES, 'UTF-8');
    $n = function_exists('mb_strtolower') ? mb_strtolower(trim($plain)) : strtolower(trim($plain));
    if (in_array($n, ['my category', 'uncategorized', 'general', 'general discussion', 'categoria general', 'sin categoría', 'sin categoria'], true)) {
        return true;
    }
    return (bool)preg_match('/^(my\s+category|categor[ií]a\s+general)/iu', $n);
}

function game_hxh_find_parent_category_fid(): int
{
    global $db;
    $prefix = TABLE_PREFIX;

    $q = $db->query("SELECT fid, name FROM {$prefix}forums WHERE type = 'c' AND pid = 0 AND active = 1 ORDER BY disporder, fid");
    while ($row = $db->fetch_array($q)) {
        if (game_hxh_is_generic_category_name((string)$row['name'])) {
            return (int)$row['fid'];
        }
    }

    $q2 = $db->query("SELECT fid FROM {$prefix}forums WHERE type = 'c' AND pid = 0 AND active = 1 ORDER BY disporder, fid LIMIT 1");
    $row2 = $db->fetch_array($q2);
    return $row2 ? (int)$row2['fid'] : 1;
}

function game_hxh_find_location_fid(string $regionSlug, string $displayName): int
{
    global $db;
    $prefix = TABLE_PREFIX;
    $slugEsc = $db->escape_string($regionSlug);
    $nameEsc = $db->escape_string($displayName);

    if ($db->table_exists('game_forum_islands')) {
        $iq = $db->query("SELECT f.fid FROM {$prefix}forums f
            INNER JOIN {$prefix}game_forum_islands i ON i.fid = f.fid
            WHERE i.region_slug = '{$slugEsc}' AND f.type = 'f' LIMIT 1");
        if ($row = $db->fetch_array($iq)) {
            return (int)$row['fid'];
        }
    }

    $nq = $db->query("SELECT fid FROM {$prefix}forums WHERE type = 'f' AND name = '{$nameEsc}' LIMIT 1");
    if ($row = $db->fetch_array($nq)) {
        return (int)$row['fid'];
    }

    return 0;
}

function game_hxh_forum_row_exists(int $fid): bool
{
    global $db;
    $prefix = TABLE_PREFIX;
    $q = $db->query("SELECT fid FROM {$prefix}forums WHERE fid = {$fid} LIMIT 1");
    return (bool)$db->fetch_array($q);
}

function game_hxh_build_parentlist(int $pid, int $fid): string
{
    global $db;
    $prefix = TABLE_PREFIX;
    if ($pid <= 0) {
        return (string)$fid;
    }
    $q = $db->query("SELECT parentlist FROM {$prefix}forums WHERE fid = {$pid} LIMIT 1");
    $row = $db->fetch_array($q);
    $base = $row && trim((string)$row['parentlist']) !== '' ? trim((string)$row['parentlist']) : (string)$pid;
    return $base . ',' . $fid;
}

function game_hxh_next_forum_disporder(int $pid): int
{
    global $db;
    $prefix = TABLE_PREFIX;
    $q = $db->query("SELECT MAX(disporder) AS m FROM {$prefix}forums WHERE pid = {$pid}");
    $row = $db->fetch_array($q);
    return (int)($row['m'] ?? 0) + 1;
}

function game_hxh_copy_forum_permissions(int $sourceFid, int $targetFid): void
{
    global $db;
    $prefix = TABLE_PREFIX;
    if ($sourceFid <= 0 || $targetFid <= 0 || $sourceFid === $targetFid) {
        return;
    }

    $existing = $db->query("SELECT 1 FROM {$prefix}forumpermissions WHERE fid = {$targetFid} LIMIT 1");
    if ($db->num_rows($existing)) {
        return;
    }

    $pq = $db->query("SELECT * FROM {$prefix}forumpermissions WHERE fid = {$sourceFid}");
    while ($perm = $db->fetch_array($pq)) {
        unset($perm['pid']);
        $perm['fid'] = $targetFid;
        $db->insert_query('forumpermissions', $perm);
    }
}

function game_hxh_create_forum(int $pid, string $name, string $description = '', int $preferredFid = 0): int
{
    global $db;
    $prefix = TABLE_PREFIX;

    $templateQ = $db->query("SELECT * FROM {$prefix}forums WHERE type = 'f' ORDER BY fid LIMIT 1");
    $template = $db->fetch_array($templateQ);
    if (!$template) {
        $template = [
            'allowhtml' => 0,
            'allowmycode' => 1,
            'allowsmilies' => 1,
            'allowimgcode' => 1,
            'allowvideocode' => 1,
            'allowpicons' => 1,
            'allowtratings' => 1,
            'usepostcounts' => 1,
            'usethreadcounts' => 1,
            'requireprefix' => 0,
            'showinjump' => 1,
            'style' => 0,
            'overridestyle' => 0,
            'rulestype' => 0,
            'defaultdatecut' => 0,
            'defaultsortby' => '',
            'defaultsortorder' => '',
        ];
    }

    $disporder = game_hxh_next_forum_disporder($pid);
    $insert = [
        'name' => $db->escape_string($name),
        'description' => $db->escape_string($description),
        'linkto' => '',
        'type' => 'f',
        'pid' => $pid,
        'parentlist' => '',
        'disporder' => $disporder,
        'active' => 1,
        'open' => 1,
        'threads' => 0,
        'posts' => 0,
        'lastpost' => 0,
        'lastposter' => '',
        'lastposteruid' => 0,
        'lastposttid' => 0,
        'lastpostsubject' => '',
        'allowhtml' => (int)($template['allowhtml'] ?? 0),
        'allowmycode' => (int)($template['allowmycode'] ?? 1),
        'allowsmilies' => (int)($template['allowsmilies'] ?? 1),
        'allowimgcode' => (int)($template['allowimgcode'] ?? 1),
        'allowvideocode' => (int)($template['allowvideocode'] ?? 1),
        'allowpicons' => (int)($template['allowpicons'] ?? 1),
        'allowtratings' => (int)($template['allowtratings'] ?? 1),
        'usepostcounts' => (int)($template['usepostcounts'] ?? 1),
        'usethreadcounts' => (int)($template['usethreadcounts'] ?? 1),
        'requireprefix' => (int)($template['requireprefix'] ?? 0),
        'password' => '',
        'showinjump' => (int)($template['showinjump'] ?? 1),
        'style' => (int)($template['style'] ?? 0),
        'overridestyle' => (int)($template['overridestyle'] ?? 0),
        'rulestype' => (int)($template['rulestype'] ?? 0),
        'rulestitle' => '',
        'rules' => '',
        'defaultdatecut' => (int)($template['defaultdatecut'] ?? 0),
        'defaultsortby' => $db->escape_string((string)($template['defaultsortby'] ?? '')),
        'defaultsortorder' => $db->escape_string((string)($template['defaultsortorder'] ?? '')),
    ];

    $fid = (int)$db->insert_query('forums', $insert);
    $parentlist = game_hxh_build_parentlist($pid, $fid);
    $db->update_query('forums', ['parentlist' => $parentlist], "fid='{$fid}'");

    $permSource = game_hxh_find_location_fid('dark-continent', 'Continente Oscuro');
    if ($permSource <= 0) {
        $permSource = (int)($template['fid'] ?? 0);
    }
    game_hxh_copy_forum_permissions($permSource, $fid);

    echo "[OK] Foro creado: {$name} (fid {$fid}, padre {$pid})\n";
    return $fid;
}

function game_hxh_upsert_island(int $fid, array $loc): void
{
    global $db;
    $prefix = TABLE_PREFIX;

    if (!$db->table_exists('game_forum_islands')) {
        echo "[SKIP] game_forum_islands no existe.\n";
        return;
    }

    $slug = $db->escape_string((string)$loc['region_slug']);
    $country = $db->escape_string((string)$loc['country']);
    $diff = (int)$loc['travel_difficulty'];
    $img = $db->escape_string((string)$loc['island_image']);
    $leader = $db->escape_string((string)$loc['leader_name']);
    $desc = $db->escape_string((string)$loc['description']);
    $terrain = $db->escape_string((string)$loc['terrain']);
    $clim = $db->escape_string((string)$loc['climate']);
    $ctemp = $db->escape_string((string)$loc['climate_temp']);
    $cwind = $db->escape_string((string)$loc['climate_wind']);
    $cprecip = $db->escape_string((string)$loc['climate_precip']);
    $bld = $db->escape_string((string)$loc['buildings']);
    $def = $db->escape_string((string)$loc['defenses']);
    $res = $db->escape_string((string)$loc['resources']);
    $cx = (int)$loc['coord_x'];
    $cy = (int)$loc['coord_y'];
    $danger = (int)$loc['base_danger'];

    $existing = $db->query("SELECT 1 FROM {$prefix}game_forum_islands WHERE fid = {$fid} LIMIT 1");
    if ($db->num_rows($existing)) {
        $db->write_query("UPDATE {$prefix}game_forum_islands SET
            region_slug = '{$slug}',
            country = '{$country}',
            travel_difficulty = {$diff},
            island_image = '{$img}',
            leader_name = '{$leader}',
            description = '{$desc}',
            terrain = '{$terrain}',
            climate = '{$clim}',
            climate_temp = '{$ctemp}',
            climate_wind = '{$cwind}',
            climate_precip = '{$cprecip}',
            buildings = '{$bld}',
            defenses = '{$def}',
            resources = '{$res}',
            coord_x = {$cx},
            coord_y = {$cy},
            base_danger = {$danger}
            WHERE fid = {$fid}");
        echo "[OK] Metadatos actualizados para fid {$fid} ({$loc['name']}).\n";
    } else {
        $db->write_query("INSERT INTO {$prefix}game_forum_islands (
            fid, region_slug, country, travel_difficulty, island_image, leader_name, description,
            terrain, climate, climate_temp, climate_wind, climate_precip, buildings, defenses, resources,
            coord_x, coord_y, base_danger
        ) VALUES (
            {$fid}, '{$slug}', '{$country}', {$diff}, '{$img}', '{$leader}', '{$desc}',
            '{$terrain}', '{$clim}', '{$ctemp}', '{$cwind}', '{$cprecip}', '{$bld}', '{$def}', '{$res}',
            {$cx}, {$cy}, {$danger}
        )");
        echo "[OK] Metadatos creados para fid {$fid} ({$loc['name']}).\n";
    }
}

/**
 * Crea foros MyBB faltantes y sincroniza metadatos HxH. Idempotente.
 */
function game_hxh_provision_all_locations(): void
{
    require_once __DIR__ . '/hxh_world_structure.php';
    game_hxh_provision_world_structure();
}
