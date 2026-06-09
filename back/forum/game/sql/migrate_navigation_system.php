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
            'results_json' => '[{"range":"1-3","result":"Delfines de buen augurio","description":"Una manada de delfines escolta el barco. El viaje transcurre sin percances."},{"range":"4-6","result":"Viento favorable","description":"Un viento constante y favorable acelera la travesía."},{"range":"7-9","result":"Niebla ligera","description":"Una niebla matutina retrasa levemente la navegación."},{"range":"10-12","result":"Lluvia pasajera","description":"Una llovizna de pocas horas moja la cubierta pero no impide avanzar."},{"range":"13-15","result":"Barco mercante","description":"Un carguero mercante cruza el camino. Posible fuente de información o comercio."},{"range":"16-17","result":"Isla desconocida a la vista","description":"Una pequeña isla sin cartografiar aparece en el horizonte."},{"range":"18-19","result":"Tormenta menor","description":"Una tormenta de corta duración sacude el barco. Sin daños graves."},{"range":"20","result":"Encuentro pirata","description":"Un pequeño barco pirata avista vuestra embarcación e intenta dar caza."}]',
        ],
        [
            'name' => 'Evento de Navegación — Grand Line',
            'description' => 'Sucesos en la Grand Line, el mar más impredecible del mundo.',
            'subtype' => 'nav_3',
            'tags_json' => '["navegacion","grand_line"]',
            'dice_type' => 'd20',
            'results_json' => '[{"range":"1-2","result":"Clima absurdo","description":"En cuestión de minutos pasa de sol radiante a nieve y luego a lluvia. El Log Pose vibra erráticamente."},{"range":"3-4","result":"Corriente submarina","description":"Una corriente oculta arrastra el barco varios kilómetros fuera de rumbo."},{"range":"5-6","result":"Isla magnética","description":"El Log Pose cambia de objetivo inesperadamente. Habrá que decidir si seguirlo."},{"range":"7-9","result":"Tormenta eléctrica","description":"Rayos constantes durante horas. Posibilidad de daños en el mástil."},{"range":"10-11","result":"Criatura marina menor","description":"Una criatura de tamaño considerable choca con el casco o rodea el barco."},{"range":"12-13","result":"Bancos de niebla densa","description":"Imposible ver a más de 5 metros. Riesgo de colisión con arrecifes."},{"range":"14-15","result":"Geyser marino","description":"Columnas de agua hirviente brotan del mar. Maniobrar para evitarlas."},{"range":"16-17","result":"Barco fantasma","description":"Una embarcación sin tripulación visible se aproxima. ¿Trampa? ¿Misterio?"},{"range":"18-19","result":"Tormenta brutal","description":"Una tormenta severa. Daños posibles. Requiere acción inmediata de la tripulación."},{"range":"20","result":"Bestia marina","description":"Una criatura de proporciones enormes emerge bajo el barco."}]',
        ],
        [
            'name' => 'Evento de Navegación — New World',
            'description' => 'Sucesos en el New World, donde incluso el tiempo puede matar.',
            'subtype' => 'nav_4_5',
            'tags_json' => '["navegacion","new_world","extremo"]',
            'dice_type' => 'd20',
            'results_json' => '[{"range":"1-2","result":"Lluvia de fuego","description":"Meteoros ardientes caen del cielo. Daños garantizados si no se maniobra bien."},{"range":"3-4","result":"Tormenta de hielo","description":"El mar se congela parcialmente. La quilla corre peligro."},{"range":"5-6","result":"Anomalía gravitacional","description":"El barco flota durante minutos sin control. Tripulación en pánico."},{"range":"7-8","result":"Tsunami","description":"Una ola colosal requiere acciones desesperadas para sobrevivir."},{"range":"9-10","result":"Mar de magma","description":"Zonas del mar literalmente en llamas. Hay que encontrar un paso seguro."},{"range":"11-12","result":"Yonko territory","description":"Señales de que este mar está bajo el dominio de uno de los Cuatro Emperadores."},{"range":"13-14","result":"Armada Marina","description":"Varias fragatas de la Marina patrullan la zona. Avistamiento casi seguro."},{"range":"15-16","result":"Kraken","description":"Tentáculos descomunales emergen. Combate naval o huida a máxima velocidad."},{"range":"17-18","result":"Tormenta negra","description":"Una tormenta de naturaleza desconocida, oscura como la tinta, engulle el barco."},{"range":"19","result":"Barco de un Yonko","description":"El navío de uno de los Cuatro Emperadores aparece en el horizonte."},{"range":"20","result":"Fenómeno inexplicable","description":"Algo sin nombre ni categoría ocurre. El narrador decide su naturaleza."}]',
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
