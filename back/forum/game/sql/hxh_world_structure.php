<?php
declare(strict_types=1);

require_once __DIR__ . '/hxh_location_provision.php';

/**
 * Árbol Mundo Conocido: categoría → foros → subforos (temas en subforos o foros hoja).
 *
 * @return array<string, mixed>
 */
function game_hxh_world_structure(): array
{
    return [
        'mundo_conocido' => [
            'name' => 'Mundo Conocido',
            'disporder' => 1,
            'continents' => [
                [
                    'label' => 'Continente Yorbian',
                    'forums' => [
                        [
                            'name' => 'Estados Unidos de Saherta',
                            'slug' => 'saherta',
                            'subs' => [
                                ['name' => 'Yorknew City', 'slug' => 'yorknew'],
                                ['name' => 'Desierto de Gordeau', 'slug' => 'desierto-gordeau'],
                                ['name' => 'Glam Gas Land', 'slug' => 'glam-gas-land'],
                            ],
                        ],
                        [
                            'name' => 'Islas Balsa — Unión Mitene',
                            'slug' => 'balsa-union-mitene',
                            'subs' => [
                                ['name' => 'Neo-Green Life (NGL)', 'slug' => 'ngl'],
                                ['name' => 'República de Rokario', 'slug' => 'rokario'],
                                ['name' => 'República de Hass', 'slug' => 'hass'],
                                ['name' => 'República de Gorteau Oeste', 'slug' => 'gorteau-oeste'],
                                ['name' => 'República de Gorteau Este', 'slug' => 'gorteau-este'],
                            ],
                        ],
                        ['name' => 'Isla Ballena', 'slug' => 'whale-island', 'location' => true],
                        ['name' => 'Ciudad Meteoro', 'slug' => 'ciudad-meteoro', 'location' => true],
                        ['name' => 'Provincia de Lukso', 'slug' => 'provincia-lukso', 'location' => true],
                    ],
                ],
                [
                    'label' => 'Continente Azian',
                    'forums' => [
                        ['name' => 'Imperio Kakin', 'slug' => 'imperio-kakin', 'location' => true],
                        [
                            'name' => 'Greed Island',
                            'slug' => 'greed-island',
                            'subs' => [
                                ['name' => 'Limeiro', 'slug' => 'limeiro'],
                                ['name' => 'Masadora', 'slug' => 'masadora'],
                                ['name' => 'Puerto / Yermos / Punto de Partida de Greed Island', 'slug' => 'greed-island-start'],
                            ],
                        ],
                    ],
                ],
                [
                    'label' => 'Continente Afrian',
                    'forums' => [
                        [
                            'name' => 'República de Padokea',
                            'slug' => 'padokea',
                            'subs' => [
                                ['name' => 'Región de Dentora — Monte Kukuroo', 'slug' => 'dentora-kukuroo'],
                                ['name' => 'Parasta', 'slug' => 'parasta'],
                            ],
                        ],
                        ['name' => 'República Mimbo', 'slug' => 'republica-mimbo', 'location' => true],
                        ['name' => 'Heavens Arena', 'slug' => 'heavens-arena', 'location' => true],
                    ],
                ],
                [
                    'label' => 'Continente Oriens',
                    'forums' => [
                        [
                            'name' => 'Reino Kukan\'yu',
                            'slug' => 'reino-kukanyu',
                            'subs' => [
                                ['name' => 'Zaban City', 'slug' => 'zaban-city'],
                                ['name' => 'Trick Tower', 'slug' => 'trick-tower'],
                            ],
                        ],
                        ['name' => 'Jappon', 'slug' => 'jappon', 'location' => true],
                    ],
                ],
                [
                    'label' => 'Continente Ochimar',
                    'forums' => [
                        ['name' => 'Federación de Ochima', 'slug' => 'federacion-ochima', 'location' => true],
                    ],
                ],
                [
                    'label' => 'Continente Meridian',
                    'forums' => [
                        ['name' => 'Unión Begerossé', 'slug' => 'union-begerosse', 'location' => true],
                    ],
                ],
            ],
        ],
        'continente_oscuro' => [
            'name' => 'Continente Oscuro',
            'disporder' => 99,
            'reserved' => true,
            'continents' => [],
        ],
    ];
}

function game_hxh_continent_tag(string $continentLabel): string
{
    return '[hxh-continent:' . $continentLabel . ']';
}

function game_hxh_find_forum_by_slug(string $slug): int
{
    global $db;
    $prefix = TABLE_PREFIX;
    $slugEsc = $db->escape_string($slug);

    if ($db->table_exists('game_forum_islands')) {
        $iq = $db->query("SELECT fid FROM {$prefix}game_forum_islands WHERE region_slug = '{$slugEsc}' LIMIT 1");
        if ($row = $db->fetch_array($iq)) {
            return (int)$row['fid'];
        }
    }

    $tag = $db->escape_string('[hxh-slug:' . $slug . ']');
    $q = $db->query("SELECT fid FROM {$prefix}forums WHERE description LIKE '%{$tag}%' LIMIT 1");
    if ($row = $db->fetch_array($q)) {
        return (int)$row['fid'];
    }

    return 0;
}

function game_hxh_forum_description_with_meta(string $continentLabel, string $slug, string $desc = ''): string
{
    $parts = [
        game_hxh_continent_tag($continentLabel),
        '[hxh-slug:' . $slug . ']',
    ];
    if ($desc !== '') {
        $parts[] = $desc;
    }
    return implode(' ', $parts);
}

function game_hxh_create_category(string $name, int $pid = 0, int $disporder = 1): int
{
    global $db;
    $prefix = TABLE_PREFIX;
    $nameEsc = $db->escape_string($name);

    $q = $db->query("SELECT fid FROM {$prefix}forums WHERE type = 'c' AND name = '{$nameEsc}' AND pid = {$pid} LIMIT 1");
    if ($row = $db->fetch_array($q)) {
        $fid = (int)$row['fid'];
        $db->update_query('forums', ['active' => 1, 'disporder' => $disporder], "fid='{$fid}'");
        return $fid;
    }

    $insert = [
        'name' => $nameEsc,
        'description' => '',
        'linkto' => '',
        'type' => 'c',
        'pid' => $pid,
        'parentlist' => '',
        'disporder' => $disporder,
        'active' => 1,
        'open' => 1,
        'allowhtml' => 0,
        'allowmycode' => 1,
        'allowsmilies' => 1,
        'allowimgcode' => 1,
        'allowvideocode' => 1,
        'allowpicons' => 1,
        'allowtratings' => 1,
        'usepostcounts' => 0,
        'usethreadcounts' => 0,
        'requireprefix' => 0,
        'password' => '',
        'showinjump' => 1,
        'style' => 0,
        'overridestyle' => 0,
        'rulestype' => 0,
        'rulestitle' => '',
        'rules' => '',
        'defaultdatecut' => 0,
        'defaultsortby' => '',
        'defaultsortorder' => '',
        'threads' => 0,
        'posts' => 0,
        'lastpost' => 0,
        'lastposter' => '',
        'lastposteruid' => 0,
        'lastposttid' => 0,
        'lastpostsubject' => '',
    ];

    $fid = (int)$db->insert_query('forums', $insert);
    $parentlist = game_hxh_build_parentlist($pid, $fid);
    $db->update_query('forums', ['parentlist' => $parentlist], "fid='{$fid}'");

    $permQ = $db->query("SELECT fid FROM {$prefix}forums WHERE type = 'f' AND active = 1 ORDER BY fid LIMIT 1");
    $permRow = $db->fetch_array($permQ);
    if ($permRow) {
        game_hxh_copy_forum_permissions((int)$permRow['fid'], $fid);
    }

    return $fid;
}

function game_hxh_ensure_forum_node(
    int $parentFid,
    string $name,
    string $slug,
    string $continentLabel,
    string $desc = '',
    bool $isLocation = false,
    ?array $locationMeta = null
): int {
    global $db;

    $description = game_hxh_forum_description_with_meta($continentLabel, $slug, $desc);
    $fid = game_hxh_find_forum_by_slug($slug);

    if ($fid <= 0) {
        $nameEsc = $db->escape_string($name);
        $q = $db->query("SELECT fid FROM " . TABLE_PREFIX . "forums WHERE type = 'f' AND name = '{$nameEsc}' AND pid = {$parentFid} LIMIT 1");
        if ($row = $db->fetch_array($q)) {
            $fid = (int)$row['fid'];
        }
    }

    if ($fid <= 0) {
        $fid = game_hxh_create_forum($parentFid, $name, $description);
    } else {
        $db->update_query('forums', [
            'name' => $db->escape_string($name),
            'description' => $db->escape_string($description),
            'pid' => $parentFid,
            'active' => 1,
            'open' => 1,
        ], "fid='{$fid}'");
        $parentlist = game_hxh_build_parentlist($parentFid, $fid);
        $db->update_query('forums', ['parentlist' => $parentlist], "fid='{$fid}'");
    }

    if ($isLocation && $db->table_exists('game_forum_islands')) {
        $meta = $locationMeta ?? game_hxh_default_location_meta($slug, $name, $continentLabel);
        $meta['region_slug'] = $slug;
        $meta['name'] = $name;
        $meta['country'] = $continentLabel;
        game_hxh_upsert_island($fid, $meta);
    }

    return $fid;
}

function game_hxh_default_location_meta(string $slug, string $name, string $continent): array
{
    $catalog = function_exists('game_hxh_locations_catalog') ? game_hxh_locations_catalog() : [];
    if (isset($catalog[$slug])) {
        $entry = $catalog[$slug];
        $entry['country'] = $continent;
        return $entry;
    }

    $slugFile = preg_replace('/[^a-z0-9-]+/', '_', $slug);
    return [
        'name' => $name,
        'region_slug' => $slug,
        'country' => $continent,
        'travel_difficulty' => 2,
        'island_image' => 'images/game/locations/' . $slugFile . '.svg',
        'leader_name' => '',
        'description' => 'Ubicación del Mundo Conocido: ' . $name . '.',
        'terrain' => '',
        'climate' => '',
        'climate_temp' => '',
        'climate_wind' => '',
        'climate_precip' => '',
        'buildings' => '',
        'defenses' => '',
        'resources' => '',
        'coord_x' => 100,
        'coord_y' => 100,
        'base_danger' => 2,
    ];
}

function game_hxh_slug_from_name(string $name): string
{
    $plain = html_entity_decode($name, ENT_QUOTES, 'UTF-8');
    $lower = function_exists('mb_strtolower') ? mb_strtolower($plain) : strtolower($plain);
    $slug = preg_replace('/[^a-z0-9]+/u', '-', $lower) ?? $lower;
    return trim((string)$slug, '-') ?: 'forum';
}

function game_hxh_deactivate_legacy_categories(): void
{
    global $db;
    $prefix = TABLE_PREFIX;

    $legacyPatterns = [
        'east blue', 'eastblue', 'east_blue',
        'grand line', 'north blue', 'south blue', 'west blue',
        'my category', 'my forum', 'uncategorized',
    ];

    $q = $db->query("SELECT fid, name, parentlist FROM {$prefix}forums WHERE active = 1");
    while ($row = $db->fetch_array($q)) {
        $name = function_exists('mb_strtolower') ? mb_strtolower((string)$row['name']) : strtolower((string)$row['name']);
        $deactivate = false;
        foreach ($legacyPatterns as $pat) {
            if ($name === $pat || str_contains($name, $pat)) {
                $deactivate = true;
                break;
            }
        }
        if (!$deactivate) {
            continue;
        }
        $fid = (int)$row['fid'];
        $db->update_query('forums', ['active' => 0], "fid='{$fid}'");
        $db->write_query("UPDATE {$prefix}forums SET active = 0 WHERE parentlist LIKE '%,{$fid},%' OR parentlist LIKE '{$fid},%' OR parentlist LIKE '%,{$fid}' OR parentlist = '{$fid}'");
    }
}

function game_hxh_deactivate_orphan_forums_not_in_tree(array $keptFids): void
{
    global $db;
    $prefix = TABLE_PREFIX;

    $mundoCat = game_hxh_find_category_by_name('Mundo Conocido');
    $oscuroCat = game_hxh_find_category_by_name('Continente Oscuro');
    $allowedCats = array_filter([$mundoCat, $oscuroCat]);

    if (empty($allowedCats)) {
        return;
    }

    $q = $db->query("SELECT fid, pid, parentlist, name FROM {$prefix}forums WHERE type = 'f' AND active = 1");
    while ($row = $db->fetch_array($q)) {
        $fid = (int)$row['fid'];
        if (in_array($fid, $keptFids, true)) {
            continue;
        }
        $parentlist = (string)$row['parentlist'];
        $underHxh = false;
        foreach ($allowedCats as $catFid) {
            if ($catFid > 0 && (str_contains($parentlist, (string)$catFid) || (int)$row['pid'] === $catFid)) {
                $underHxh = true;
                break;
            }
        }
        if (!$underHxh) {
            continue;
        }
        $db->update_query('forums', ['active' => 0], "fid='{$fid}'");
    }
}

function game_hxh_find_category_by_name(string $name): int
{
    global $db;
    $prefix = TABLE_PREFIX;
    $esc = $db->escape_string($name);
    $q = $db->query("SELECT fid FROM {$prefix}forums WHERE type = 'c' AND name = '{$esc}' LIMIT 1");
    $row = $db->fetch_array($q);
    return $row ? (int)$row['fid'] : 0;
}

/**
 * Provisiona categorías, foros y subforos del mundo HxH. Idempotente.
 */
function game_hxh_provision_world_structure(): void
{
    global $db, $cache, $mybb;

    if (!$db->table_exists('forums')) {
        return;
    }

    game_hxh_deactivate_legacy_categories();

    $structure = game_hxh_world_structure();
    $keptFids = [];
    $order = 0;

    foreach (['mundo_conocido', 'continente_oscuro'] as $catKey) {
        if (empty($structure[$catKey])) {
            continue;
        }
        $catDef = $structure[$catKey];
        $catFid = game_hxh_create_category(
            (string)$catDef['name'],
            0,
            (int)($catDef['disporder'] ?? 1)
        );

        if (!empty($catDef['reserved'])) {
            continue;
        }

        foreach ($catDef['continents'] as $continent) {
            $continentLabel = (string)$continent['label'];
            foreach ($continent['forums'] as $forumDef) {
                $order++;
                $name = (string)$forumDef['name'];
                $slug = (string)($forumDef['slug'] ?? game_hxh_slug_from_name($name));
                $isLocation = !empty($forumDef['location']) && empty($forumDef['subs']);
                $fid = game_hxh_ensure_forum_node(
                    $catFid,
                    $name,
                    $slug,
                    $continentLabel,
                    '',
                    $isLocation
                );
                $keptFids[] = $fid;
                $db->update_query('forums', ['disporder' => $order], "fid='{$fid}'");

                if (empty($forumDef['subs']) || !is_array($forumDef['subs'])) {
                    continue;
                }

                $subOrder = 0;
                foreach ($forumDef['subs'] as $subDef) {
                    $subOrder++;
                    if (is_string($subDef)) {
                        $subName = $subDef;
                        $subSlug = game_hxh_slug_from_name($name . '-' . $subName);
                    } else {
                        $subName = (string)$subDef['name'];
                        $subSlug = (string)($subDef['slug'] ?? game_hxh_slug_from_name($name . '-' . $subName));
                    }
                    $subFid = game_hxh_ensure_forum_node(
                        $fid,
                        $subName,
                        $subSlug,
                        $continentLabel,
                        '',
                        true
                    );
                    $keptFids[] = $subFid;
                    $db->update_query('forums', ['disporder' => $subOrder], "fid='{$subFid}'");
                }
            }
        }
    }

    game_hxh_deactivate_orphan_forums_not_in_tree($keptFids);

    if (isset($mybb->settings['subforumsindex']) && (int)$mybb->settings['subforumsindex'] < 10) {
        $db->update_query('settings', ['value' => '10'], "name='subforumsindex'");
        if (function_exists('rebuild_settings')) {
            rebuild_settings();
        }
    }

    if (isset($cache) && is_object($cache) && method_exists($cache, 'update_forums')) {
        $cache->update_forums();
    }
}

function game_hxh_auto_provision_world(): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    global $db;
    if (!isset($db) || !$db->table_exists('forums')) {
        return;
    }

    $helpers = dirname(__DIR__) . '/sql/migration_helpers.php';
    if (!is_file($helpers)) {
        return;
    }
    require_once $helpers;

    $version = 'provision_hxh_world_structure_v1';
    if (game_migration_applied($version) || game_migration_applied('provision_hxh_world_structure.php')) {
        return;
    }

    game_migration_ensure_tracking_table();
    game_hxh_provision_world_structure();
    game_migration_mark_applied($version);
}
