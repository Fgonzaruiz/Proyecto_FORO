<?php
declare(strict_types=1);

/**
 * Categoría East Blue + islas canónicas de prueba (foros + game_forum_islands + rutas).
 * Idempotente: no duplica si ya existe la categoría.
 */

global $db;
$prefix = TABLE_PREFIX;

$catName = 'East Blue';
$catCheck = $db->query("SELECT fid FROM {$prefix}forums WHERE name = '" . $db->escape_string($catName) . "' AND type = 'c' LIMIT 1");
if ($db->num_rows($catCheck)) {
    echo "<p class='skip'>[SKIP] Categoría «{$catName}» ya existe (fid " . (int)$db->fetch_field($catCheck, 'fid') . ").</p>";
    return;
}

$forumDefaults = [
    'linkto' => '',
    'active' => 1,
    'open' => 1,
    'threads' => 0,
    'posts' => 0,
    'lastpost' => 0,
    'lastposter' => '0',
    'lastposttid' => 0,
    'allowhtml' => 0,
    'allowmycode' => 1,
    'allowsmilies' => 1,
    'allowimgcode' => 1,
    'allowvideocode' => 1,
    'allowpicons' => 1,
    'allowtratings' => 0,
    'usepostcounts' => 1,
    'usethreadcounts' => 1,
    'password' => '',
    'showinjump' => 1,
    'style' => 0,
    'overridestyle' => 0,
    'rulestype' => 0,
    'rulestitle' => '',
    'rules' => '',
];

$maxOrderQ = $db->query("SELECT MAX(disporder) AS m FROM {$prefix}forums WHERE pid = 0");
$catOrder = (int)$db->fetch_field($maxOrderQ, 'm') + 1;

$db->insert_query('forums', array_merge($forumDefaults, [
    'name' => $catName,
    'description' => 'Mar del Este — islas de prueba para navegación y rol.',
    'type' => 'c',
    'pid' => 0,
    'parentlist' => '0',
    'disporder' => $catOrder,
]));
$catFid = (int)$db->insert_id();
$db->update_query('forums', ['parentlist' => (string)$catFid], "fid = {$catFid}");
echo "<p class='ok'>[OK] Categoría «{$catName}» creada (fid {$catFid}).</p>";

$islands = [
    [
        'name' => 'Shells Town',
        'description' => 'Ciudad portuaria bajo el mando de la Marina. Base del capitán Morgan.',
        'coord_x' => 10,
        'coord_y' => 20,
        'base_danger' => 1,
        'leader' => 'Capitán Morgan',
    ],
    [
        'name' => 'Orange Town',
        'description' => 'Pueblo asolado por Buggy el Payaso. Famoso por su feria y naranjas.',
        'coord_x' => 35,
        'coord_y' => 22,
        'base_danger' => 1,
        'leader' => 'Buggy (antes)',
    ],
    [
        'name' => 'Syrup Village',
        'description' => 'Aldea costera tranquila en la isla Gaimon. Hogar de Kaya y Usopp.',
        'coord_x' => 55,
        'coord_y' => 18,
        'base_danger' => 1,
        'leader' => 'Kaya',
    ],
    [
        'name' => 'Loguetown',
        'description' => 'Última isla del East Blue antes de la Grand Line. Donde cayó el Rey de los Piratas.',
        'coord_x' => 85,
        'coord_y' => 28,
        'base_danger' => 2,
        'leader' => 'Smoker',
    ],
];

$createdFids = [];
$order = 1;
foreach ($islands as $island) {
    $escName = $db->escape_string($island['name']);
    $db->insert_query('forums', array_merge($forumDefaults, [
        'name' => $island['name'],
        'description' => $island['description'],
        'type' => 'f',
        'pid' => $catFid,
        'parentlist' => (string)$catFid,
        'disporder' => $order++,
    ]));
    $fid = (int)$db->insert_id();
    $db->update_query('forums', ['parentlist' => "{$catFid},{$fid}"], "fid = {$fid}");
    $createdFids[$island['name']] = $fid;
    echo "<p class='ok'>[OK] Foro-isla «{$island['name']}» (fid {$fid}).</p>";

    if ($db->table_exists('game_forum_islands')) {
        $leader = $db->escape_string($island['leader']);
        $desc = $db->escape_string($island['description']);
        $terrain = $db->escape_string('Costa y llanuras');
        $climate = $db->escape_string('Tropical templado');
        $cx = (int)$island['coord_x'];
        $cy = (int)$island['coord_y'];
        $bd = (int)$island['base_danger'];
        $db->write_query("INSERT INTO {$prefix}game_forum_islands
            (fid, island_image, leader_name, description, terrain, climate, climate_temp, climate_wind, climate_precip, buildings, defenses, resources, coord_x, coord_y, sea_zone, base_danger, requires_log_pose, requires_compass)
            VALUES ({$fid}, '', '{$leader}', '{$desc}', '{$terrain}', '{$climate}', '24-30C', 'Brisas alisias', 'Moderada', '', '', 'Pesca, madera', {$cx}, {$cy}, 'east_blue', {$bd}, 0, 0)
            ON DUPLICATE KEY UPDATE coord_x = VALUES(coord_x), coord_y = VALUES(coord_y), sea_zone = 'east_blue', description = VALUES(description)");
    }
}

if ($db->table_exists('game_navigation_routes') && count($createdFids) >= 2) {
    $routes = [
        ['Shells Town', 'Orange Town', 28],
        ['Orange Town', 'Syrup Village', 22],
        ['Syrup Village', 'Loguetown', 32],
        ['Shells Town', 'Syrup Village', 48],
    ];
    foreach ($routes as [$from, $to, $dist]) {
        if (!isset($createdFids[$from], $createdFids[$to])) {
            continue;
        }
        $fromFid = $createdFids[$from];
        $toFid = $createdFids[$to];
        $exists = $db->query("SELECT 1 FROM {$prefix}game_navigation_routes
            WHERE (island_from_fid = {$fromFid} AND island_to_fid = {$toFid})
               OR (island_from_fid = {$toFid} AND island_to_fid = {$fromFid}) LIMIT 1");
        if ($db->num_rows($exists)) {
            continue;
        }
        $db->write_query("INSERT INTO {$prefix}game_navigation_routes (island_from_fid, island_to_fid, distance, waypoint_fids, danger_override)
            VALUES ({$fromFid}, {$toFid}, {$dist}, NULL, NULL)");
        echo "<p class='ok'>[OK] Ruta {$from} → {$to} ({$dist} u).</p>";
    }
}

if (function_exists('rebuild_forum_cache')) {
    require_once MYBB_ROOT . 'inc/functions.php';
    rebuild_forum_cache();
    echo "<p class='ok'>[OK] Caché de foros regenerada.</p>";
}
