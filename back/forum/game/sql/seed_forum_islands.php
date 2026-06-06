<?php
declare(strict_types=1);

/**
 * Seed de ejemplo para el sistema de Islas.
 * Ejecutar desde run_pending_migrations.php o a mano.
 */

global $db;
$prefix = TABLE_PREFIX;

if (!$db->table_exists('game_forum_islands')) {
    echo "<p class='skip'>[SKIP] Tabla game_forum_islands no existe. Ejecuta migrate_forum_islands.php primero.</p>";
    return;
}

// Fetch all forums type 'f'
$fq = $db->query("SELECT fid, name FROM {$prefix}forums WHERE type = 'f' ORDER BY fid");
$forums = [];
while ($f = $db->fetch_array($fq)) {
    $forums[(int)$f['fid']] = $f['name'];
}

if (empty($forums)) {
    echo "<p class='skip'>[SKIP] No hay foros de tipo 'f' para seedear.</p>";
    return;
}

$examples = [
    [
        'fid' => 0,
        'island_image' => '',
        'leader_name' => 'Monkey D. Luffy',
        'description' => 'Una vibrante isla tropical en el centro de la Grand Line. Hogar de aventureros y marinos por igual, su costa este alberga el puerto más activo del Nuevo Mundo.',
        'terrain' => 'Selva tropical, acantilados costeros',
        'climate' => 'Tropical húmedo',
        'climate_temp' => '28-35°C',
        'climate_wind' => 'Brisas alisias del este, monzones en invierno',
        'climate_precip' => 'Abundante, 1800mm anuales',
        'buildings' => 'Castillo de Goa, Puerto Haoshoku, Bosque del Rey, Plaza Central del Amanecer, Dojo Shimotsuki',
        'defenses' => 'Muralla perimetral de 12m, batería costera de cañones, torres de vigilancia cada 2km, guarnición de 500 soldados',
        'resources' => 'Madera tropical, minerales ferrosos, pesca abundante, frutas exóticas',
    ],
    [
        'fid' => 0,
        'island_image' => '',
        'leader_name' => 'Roronoa Zoro',
        'description' => 'Una isla de clima templado ubicada al este del archipiélago. Conocida como la "Isla de la Espada", sus habitantes veneran las artes marciales y la esgrima.',
        'terrain' => 'Montañoso, bosques de bambú, costa rocosa',
        'climate' => 'Templado con brisas marinas',
        'climate_temp' => '15-25°C',
        'climate_wind' => 'Vientos suaves del oeste',
        'climate_precip' => 'Moderado, 800mm anuales',
        'buildings' => 'Dojo de Espadas Shimotsuki, Acantilado de los Mil Guerreros, Aldea del Este, Santuario del Santos',
        'defenses' => 'Desfiladero natural en la entrada, muralla de bambú reforzado, sistema de túneles secretos, vigías en los acantilados',
        'resources' => 'Bambú de alta calidad, acero para espadas, piedra volcánica, cultivos de arroz',
    ],
    [
        'fid' => 0,
        'island_image' => '',
        'leader_name' => 'Nami',
        'description' => 'Un paraíso flotante de islotes conectados por puentes colgantes. El clima cambiante la ha convertido en un centro de estudio meteorológico sin igual.',
        'terrain' => 'Archipiélago de islotes, manglares, calas escondidas',
        'climate' => 'Variable con tormentas frecuentes',
        'climate_temp' => '22-32°C',
        'climate_wind' => 'Ráfagas impredecibles, monzones fuertes en otoño',
        'climate_precip' => 'Muy abundante, 2500mm anuales',
        'buildings' => 'Observatorio Meteorológico Celeste, Biblioteca de Mapas del Mundo, Mercado Flotante de las Especias, Puente de los Suspiros',
        'defenses' => 'Barrera de arrecifes naturales, puentes levadizos, torres de tormenta (generan cortinas de lluvia), red de vigías en cada islote',
        'resources' => 'Especias exóticas, conchas raras, sales marinas, cristales de tormenta',
    ],
];

$count = 0;
foreach ($forums as $fid => $fname) {
    $idx = $count % count($examples);
    $ex = $examples[$idx];
    $ex['fid'] = $fid;

    $img = $db->escape_string($ex['island_image']);
    $leader = $db->escape_string($ex['leader_name']);
    $desc = $db->escape_string($ex['description']);
    $terrain = $db->escape_string($ex['terrain']);
    $clim = $db->escape_string($ex['climate']);
    $ctemp = $db->escape_string($ex['climate_temp']);
    $cwind = $db->escape_string($ex['climate_wind']);
    $cprecip = $db->escape_string($ex['climate_precip']);
    $bld = $db->escape_string($ex['buildings']);
    $def = $db->escape_string($ex['defenses']);
    $res = $db->escape_string($ex['resources']);

    $existing = $db->query("SELECT 1 FROM {$prefix}game_forum_islands WHERE fid = {$fid} LIMIT 1");
    if ($db->num_rows($existing)) {
        $db->write_query("UPDATE {$prefix}game_forum_islands SET island_image='{$img}', leader_name='{$leader}', description='{$desc}', terrain='{$terrain}', climate='{$clim}', climate_temp='{$ctemp}', climate_wind='{$cwind}', climate_precip='{$cprecip}', buildings='{$bld}', defenses='{$def}', resources='{$res}' WHERE fid={$fid}");
        echo "<p class='ok'>[OK] Isla '{$fname}' actualizada.</p>";
    } else {
        $db->write_query("INSERT INTO {$prefix}game_forum_islands (fid, island_image, leader_name, description, terrain, climate, climate_temp, climate_wind, climate_precip, buildings, defenses, resources) VALUES ({$fid}, '{$img}', '{$leader}', '{$desc}', '{$terrain}', '{$clim}', '{$ctemp}', '{$cwind}', '{$cprecip}', '{$bld}', '{$def}', '{$res}')");
        echo "<p class='ok'>[OK] Isla '{$fname}' creada.</p>";
    }
    $count++;
}

echo "<p class='ok'>[OK] Seed completado. {$count} islas configuradas.</p>";
