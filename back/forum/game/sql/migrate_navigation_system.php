<?php
declare(strict_types=1);

global $db;
$prefix = TABLE_PREFIX;

function game_nav_migration_add_column(string $table, string $column, string $definition): void
{
    global $db, $prefix;
    $cols = $db->query("SHOW COLUMNS FROM {$prefix}{$table} LIKE '{$column}'");
    if ($db->num_rows($cols)) {
        echo "<p class='skip'>[SKIP] {$table}.{$column} ya existe.</p>";
        return;
    }
    $db->write_query("ALTER TABLE {$prefix}{$table} ADD COLUMN {$column} {$definition}");
    echo "<p class='ok'>[OK] {$table}.{$column} añadida.</p>";
}

if ($db->table_exists('game_forum_islands')) {
    game_nav_migration_add_column('game_forum_islands', 'coord_x', 'INT NOT NULL DEFAULT 0');
    game_nav_migration_add_column('game_forum_islands', 'coord_y', 'INT NOT NULL DEFAULT 0');
    game_nav_migration_add_column('game_forum_islands', 'sea_zone', "VARCHAR(50) NOT NULL DEFAULT 'east_blue'");
    game_nav_migration_add_column('game_forum_islands', 'base_danger', 'TINYINT UNSIGNED NOT NULL DEFAULT 1');
    game_nav_migration_add_column('game_forum_islands', 'requires_log_pose', 'TINYINT(1) NOT NULL DEFAULT 0');
    game_nav_migration_add_column('game_forum_islands', 'requires_compass', 'TINYINT(1) NOT NULL DEFAULT 0');
} else {
    echo "<p class='skip'>[SKIP] game_forum_islands no existe.</p>";
}

if (!$db->table_exists('game_navigation_routes')) {
    $db->write_query("CREATE TABLE {$prefix}game_navigation_routes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        island_from_fid INT UNSIGNED NOT NULL,
        island_to_fid INT UNSIGNED NOT NULL,
        distance INT NOT NULL,
        waypoint_fids TEXT DEFAULT NULL,
        danger_override TINYINT UNSIGNED DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_route (island_from_fid, island_to_fid),
        KEY idx_from (island_from_fid),
        KEY idx_to (island_to_fid)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p class='ok'>[OK] Tabla game_navigation_routes creada.</p>";
} else {
    echo "<p class='skip'>[SKIP] game_navigation_routes ya existe.</p>";
}

if (!$db->table_exists('game_navigation_voyages')) {
    $db->write_query("CREATE TABLE {$prefix}game_navigation_voyages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        post_id INT NOT NULL,
        thread_id INT NOT NULL,
        character_id INT NOT NULL,
        ship_card_id INT NOT NULL,
        island_from_fid INT UNSIGNED NOT NULL,
        island_to_fid INT UNSIGNED NOT NULL,
        distance INT NOT NULL,
        danger_level TINYINT UNSIGNED NOT NULL,
        duration_days INT NOT NULL,
        num_events INT NOT NULL,
        navigator_bonus TINYINT UNSIGNED NOT NULL DEFAULT 0,
        instrument_used VARCHAR(100) DEFAULT NULL,
        instrument_bonus TINYINT NOT NULL DEFAULT 0,
        raw_calculation_json TEXT DEFAULT NULL,
        status ENUM('active','arrived','cancelled') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_post (post_id),
        KEY idx_char (character_id),
        KEY idx_thread (thread_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p class='ok'>[OK] Tabla game_navigation_voyages creada.</p>";
} else {
    echo "<p class='skip'>[SKIP] game_navigation_voyages ya existe.</p>";
}

if (!$db->table_exists('game_navigation_events')) {
    $db->write_query("CREATE TABLE {$prefix}game_navigation_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        voyage_id INT NOT NULL,
        post_oracle_id INT NOT NULL,
        event_order TINYINT UNSIGNED NOT NULL,
        danger_tier TINYINT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_voyage (voyage_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p class='ok'>[OK] Tabla game_navigation_events creada.</p>";
} else {
    echo "<p class='skip'>[SKIP] game_navigation_events ya existe.</p>";
}

// Seed navigation oracles
if ($db->table_exists('game_oracles')) {
    $navOracles = [
        [
            'name' => 'Evento de Navegación — Mar Tranquilo',
            'description' => 'Sucesos durante travesías en los Blues y aguas seguras.',
            'subtype' => 'nav_1_2',
            'tags_json' => '["navegacion","basico"]',
            'dice_type' => 'd20',
            'results_json' => '[{"range":"1-5","result":"Viento a favor / Mar calmado (Favorable)","description":"Condiciones óptimas para navegar. Otorga un bonus narrativo de navegación (+ velocidad o ventaja)."},{"range":"6-10","result":"Lluvia moderada / Neblina (Moderado)","description":"Reduce levemente la visibilidad o hace resbaladiza la cubierta. Ligeras penalizaciones a tiradas."},{"range":"11-15","result":"Tormenta menor / Mar picado (Severo)","description":"Vientos fuertes y olas que sacuden la nave. Dificultad para moverse en cubierta."},{"range":"16-19","result":"Mar encalmado total (Extremo)","description":"Cero viento; el barco no avanza si depende de velas. Aumenta considerablemente la duración del viaje."},{"range":"20","result":"Corriente desfavorable fuerte (Singular)","description":"Una corriente empuja el barco en dirección contraria. Atraso significativo o desvío de ruta."}]',
        ],
        [
            'name' => 'Evento de Navegación — Grand Line',
            'description' => 'Sucesos en la Grand Line, el mar más impredecible del mundo.',
            'subtype' => 'nav_3',
            'tags_json' => '["navegacion","grand_line"]',
            'dice_type' => 'd20',
            'results_json' => '[{"range":"1-5","result":"Corriente inversa favorable (Favorable)","description":"Corrientes salvajes que milagrosamente empujan al destino. Acorta el tiempo del viaje."},{"range":"6-10","result":"Nieve en verano / Lluvia cálida (Moderado)","description":"Alteración extrema de la temperatura en minutos. Confusión, necesidad de adaptar vestimenta."},{"range":"11-15","result":"Rayos sin nubes / Calor extremo (Severo)","description":"Descargas eléctricas o soles abrasadores súbitos. Riesgo leve; penalización a acciones físicas prolongadas."},{"range":"16-19","result":"Tornado súbito / Mar de nubes (Extremo)","description":"Fenómenos altamente destructivos que aparecen de la nada. Posible daño directo al barco si no se elude."},{"range":"20","result":"Lluvia de meteoritos / Erupción submarina (Singular)","description":"Catástrofe natural súbita de gran escala. Daño casi seguro a la integridad del barco."}]',
        ],
        [
            'name' => 'Evento de Navegación — New World',
            'description' => 'Sucesos en el New World, donde incluso el tiempo puede matar.',
            'subtype' => 'nav_4_5',
            'tags_json' => '["navegacion","new_world","extremo"]',
            'dice_type' => 'd20',
            'results_json' => '[{"range":"1-5","result":"Ojo del huracán (Favorable)","description":"Una perturbadora e inusual calma total en medio del caos. Respiro vital antes de que todo vuelva a enloquecer."},{"range":"6-10","result":"Niebla desorientadora / Lluvia constante (Moderado)","description":"Niebla espesa magnética. El Log Pose gira erráticamente por unas horas."},{"range":"11-15","result":"Mar de lava / Lluvia de fuego (Severo)","description":"Ascuas del cielo o agua hirviendo. Casco dañado si no está recubierto; imposible pelear en cubierta."},{"range":"16-19","result":"Tornado de hielo / Tormenta eléctrica rastreadora (Extremo)","description":"Escarcha instantánea o rayos apuntan al barco. Inutilización de artillería, daño estructural grave al barco."},{"range":"20","result":"Isla de fuego flotante / Ballena de tormenta / Vórtice gigante (Singular)","description":"Eventos épicos y catastróficos. Amenaza de destrucción inminente. Resetea la aguja del Log Pose."}]',
        ],
    ];

    foreach ($navOracles as $oracle) {
        $escName = $db->escape_string($oracle['name']);
        $q = $db->query("SELECT 1 FROM {$prefix}game_oracles WHERE name = '{$escName}' LIMIT 1");
        if ($db->num_rows($q)) {
            continue;
        }
        $escDesc = $db->escape_string($oracle['description']);
        $escSubtype = $db->escape_string($oracle['subtype']);
        $escTags = $db->escape_string($oracle['tags_json']);
        $escResults = $db->escape_string($oracle['results_json']);
        $escDice = $db->escape_string($oracle['dice_type']);
        $db->write_query("INSERT INTO {$prefix}game_oracles (name, description, oracle_type, subtype, category, tags_json, results_json, dice_type, is_system, created_by)
            VALUES ('{$escName}', '{$escDesc}', 'custom', '{$escSubtype}', '', '{$escTags}', '{$escResults}', '{$escDice}', 1, 0)");
        echo "<p class='ok'>[OK] Oráculo navegación: {$oracle['name']}</p>";
    }
}
