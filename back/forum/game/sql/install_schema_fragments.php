<?php
declare(strict_types=1);

/**
 * Definiciones SQL para install_db.php — esquema completo alineado con migrate_*.php.
 * No ejecutar directamente; usar install_db.php o run_pending_migrations.php en instalaciones existentes.
 *
 * @return list<string> nombres de tabla game_* (sin prefijo) para DROP en orden seguro
 */
function game_install_drop_table_order(): array
{
    return [
        'game_navigation_events',
        'game_navigation_voyages',
        'game_navigation_routes',
        'game_character_disciplinas',
        'game_disciplinas',
        'game_estilos_canonicos',
        'game_character_oficios',
        'game_oficios',
        'game_post_oracles',
        'game_oracles',
        'game_post_cards',
        'game_post_characters',
        'game_card_requests',
        'game_character_cards',
        'game_character_inventory',
        'game_thread_pj_state',
        'game_npc_assignments',
        'game_personajes_revisiones',
        'game_direct_messages',
        'game_notifications',
        'game_busquedas',
        'game_admin_requests',
        'game_announcements',
        'game_forum_islands',
        'game_thread_meta',
        'game_user_config',
        'game_cards',
        'game_npc_profiles',
        'game_personajes',
        'game_tripulaciones',
        'game_akuma_no_mi',
        'game_schema_migrations',
    ];
}

/**
 * @return array<string, string> clave = descripción legible, valor = SQL CREATE TABLE
 */
function game_install_create_tables(string $prefix): array
{
    return [
        'NPCs (perfiles JSON)' => "CREATE TABLE {$prefix}game_npc_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    imagen VARCHAR(500) NOT NULL DEFAULT '',
    tripulacion_id INT DEFAULT NULL,
    banner VARCHAR(255) NOT NULL DEFAULT 'images/game/npc_banner.png',
    identificacion JSON NOT NULL,
    perfil_fisico JSON NOT NULL,
    psicologia JSON NOT NULL,
    motivaciones JSON NOT NULL,
    perfil_estrategico JSON NOT NULL,
    cronologia JSON NOT NULL,
    relaciones JSON NOT NULL,
    stats JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_npc_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'Tripulaciones' => "CREATE TABLE {$prefix}game_tripulaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    imagen VARCHAR(500) NOT NULL DEFAULT '',
    descripcion TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'Personajes' => "CREATE TABLE {$prefix}game_personajes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    race VARCHAR(50) NOT NULL,
    race_name VARCHAR(100) NOT NULL,
    occupation VARCHAR(50) NOT NULL,
    occupation_name VARCHAR(100) NOT NULL,
    `desc` TEXT NOT NULL,
    details TEXT NOT NULL,
    rango VARCHAR(100) NOT NULL,
    tripulacion VARCHAR(255) NOT NULL,
    recompensa VARCHAR(100) NOT NULL,
    banner VARCHAR(255) NOT NULL,
    avatar VARCHAR(500) NOT NULL DEFAULT '',
    firma TEXT DEFAULT NULL,
    is_staff TINYINT(1) NOT NULL DEFAULT 0,
    staff_level TINYINT(1) NOT NULL DEFAULT 0,
    is_npc TINYINT(1) NOT NULL DEFAULT 0,
    is_narrator TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'pendiente',
    postnum INT NOT NULL DEFAULT 0,
    threadnum INT NOT NULL DEFAULT 0,
    data_json LONGTEXT,
    stats_json LONGTEXT,
    faction VARCHAR(100) DEFAULT '',
    approved TINYINT(1) DEFAULT 0,
    cronologia_json LONGTEXT,
    berries INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'Configuración de usuarios' => "CREATE TABLE {$prefix}game_user_config (
    user_id INT PRIMARY KEY,
    max_slots INT NOT NULL DEFAULT 1,
    slots_used INT NOT NULL DEFAULT 0,
    active_pj_id INT DEFAULT NULL,
    is_narrator TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'Personajes por post' => "CREATE TABLE {$prefix}game_post_characters (
    post_id INT PRIMARY KEY,
    thread_id INT DEFAULT NULL,
    user_id INT NOT NULL,
    character_id INT NOT NULL,
    pv_change INT NOT NULL DEFAULT 0,
    pe_change INT NOT NULL DEFAULT 0,
    pa_declared TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'PA declarado gastado por el jugador en este post (referencia para staff, no validación automática)',
    modifiers_json TEXT DEFAULT NULL,
    hidden_actions_json TEXT DEFAULT NULL,
    equipped_snapshot_json TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_thread_id (thread_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'Asignaciones NPC narrador' => "CREATE TABLE {$prefix}game_npc_assignments (
    character_id INT NOT NULL,
    narrator_id INT NOT NULL,
    PRIMARY KEY (character_id, narrator_id),
    INDEX idx_narrator_id (narrator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'Revisiones de personajes' => "CREATE TABLE {$prefix}game_personajes_revisiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personaje_id INT NOT NULL,
    staff_user_id INT NOT NULL,
    staff_char_id INT NOT NULL,
    status_anterior VARCHAR(20) NOT NULL DEFAULT '',
    status_nuevo VARCHAR(20) NOT NULL DEFAULT '',
    mensaje TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_personaje (personaje_id),
    INDEX idx_staff (staff_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'Akuma no Mi' => "CREATE TABLE {$prefix}game_akuma_no_mi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    class VARCHAR(50) NOT NULL,
    class_name VARCHAR(100) NOT NULL,
    status VARCHAR(50) NOT NULL,
    status_name VARCHAR(100) NOT NULL,
    `desc` TEXT NOT NULL,
    details TEXT NOT NULL,
    tipo_fruta VARCHAR(100) NOT NULL,
    usuario_actual VARCHAR(255) NOT NULL,
    habilidad_clave VARCHAR(255) NOT NULL,
    precio VARCHAR(100) NOT NULL,
    banner VARCHAR(255) NOT NULL,
    is_occupied TINYINT(1) NOT NULL DEFAULT 0,
    power_range VARCHAR(32) NOT NULL DEFAULT 'Sin asignar',
    is_reserved TINYINT(1) NOT NULL DEFAULT 0,
    tier TINYINT UNSIGNED NOT NULL DEFAULT 1,
    subtipo ENUM('ninguno','antiguo','mitico') NOT NULL DEFAULT 'ninguno'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'Metadatos de hilos' => "CREATE TABLE {$prefix}game_thread_meta (
    thread_id INT PRIMARY KEY,
    thread_type VARCHAR(20) NOT NULL DEFAULT 'Presente',
    day INT NOT NULL DEFAULT 1,
    season INT NOT NULL DEFAULT 0,
    year INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'Solicitudes de cartas' => "CREATE TABLE {$prefix}game_card_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    character_id INT NOT NULL,
    card_id INT NOT NULL DEFAULT 0,
    request_type ENUM('delete', 'create', 'add_existing') NOT NULL,
    status ENUM('pendiente', 'aprobada', 'rechazada', 'conforme') NOT NULL DEFAULT 'pendiente',
    current_rank VARCHAR(10) NOT NULL,
    card_details_json TEXT DEFAULT NULL,
    discussion_json TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_by INT DEFAULT NULL,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    staff_message TEXT DEFAULT NULL,
    KEY idx_character (character_id),
    KEY idx_card (card_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'Catálogo de cartas' => "CREATE TABLE {$prefix}game_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    card_type ENUM('tecnica', 'equipo', 'akuma_no_mi', 'haki', 'npc_menor', 'barco') NOT NULL,
    `rank` ENUM('D', 'C', 'B', 'A', 'S', 'SS') NOT NULL DEFAULT 'C',
    activation ENUM('activa', 'pasiva', 'reactiva') NOT NULL DEFAULT 'activa',
    tags_json TEXT,
    description TEXT,
    cost_pe VARCHAR(50) DEFAULT '—',
    execution_cost INT NOT NULL DEFAULT 0,
    execution_stat VARCHAR(10) DEFAULT '',
    dice VARCHAR(150) DEFAULT '',
    effects_json TEXT,
    notes TEXT,
    image_url VARCHAR(500) DEFAULT '',
    cost_berries INT NOT NULL DEFAULT 0,
    in_shop TINYINT(1) NOT NULL DEFAULT 0,
    shop_category VARCHAR(50) DEFAULT 'utiles',
    peso INT NOT NULL DEFAULT 1,
    created_by INT NOT NULL,
    reposo INT NOT NULL DEFAULT 0,
    duracion INT NOT NULL DEFAULT 0,
    tier TINYINT UNSIGNED NOT NULL DEFAULT 1,
    disciplina_slug VARCHAR(64) NULL,
    estilo_canonico_slug VARCHAR(64) NULL,
    oficio_slug VARCHAR(64) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_type (card_type),
    KEY idx_rank (`rank`),
    KEY idx_shop (in_shop, card_type, cost_berries),
    KEY idx_estilo_canonico (estilo_canonico_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'Estilos canónicos (biblioteca IC)' => "CREATE TABLE {$prefix}game_estilos_canonicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(64) NOT NULL,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(32) NOT NULL DEFAULT 'artes_marciales',
    category_label VARCHAR(100) NOT NULL,
    disciplina_slug VARCHAR(64) NULL,
    primary_stat VARCHAR(32) NOT NULL DEFAULT '',
    short_desc TEXT NOT NULL,
    description TEXT NOT NULL,
    requirements_json TEXT NOT NULL,
    advantages_json TEXT NOT NULL,
    image_url VARCHAR(500) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_slug (slug),
    KEY idx_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'Cartas de personajes' => "CREATE TABLE {$prefix}game_character_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    character_id INT NOT NULL,
    card_id INT NOT NULL,
    current_rank ENUM('D', 'C', 'B', 'A', 'S', 'SS') NOT NULL DEFAULT 'C',
    assigned_by INT NOT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_char_card (character_id, card_id),
    KEY idx_char (character_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'Notificaciones' => "CREATE TABLE {$prefix}game_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    character_id INT DEFAULT NULL,
    `type` VARCHAR(50) NOT NULL DEFAULT 'system',
    title VARCHAR(255) NOT NULL,
    body TEXT,
    link VARCHAR(500) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    is_dismissed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'Mensajes directos' => "CREATE TABLE {$prefix}game_direct_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_character_id INT NOT NULL,
    to_character_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    sender_deleted TINYINT(1) NOT NULL DEFAULT 0,
    recipient_deleted TINYINT(1) NOT NULL DEFAULT 0,
    legacy_pmid INT DEFAULT NULL,
    thread_id INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_legacy_pmid (legacy_pmid),
    INDEX idx_to_char (to_character_id, recipient_deleted, is_read),
    INDEX idx_from_char (from_character_id, sender_deleted),
    INDEX idx_thread (thread_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'Búsquedas de rol' => "CREATE TABLE {$prefix}game_busquedas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    character_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT NOT NULL,
    imagen_url VARCHAR(500) DEFAULT NULL,
    `status` ENUM('pendiente','aprobada','denegada') DEFAULT 'pendiente',
    staff_nota TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'Peticiones administrativas' => "CREATE TABLE {$prefix}game_admin_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    character_id INT NOT NULL,
    source VARCHAR(32) NOT NULL,
    request_kind VARCHAR(64) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    link VARCHAR(500) DEFAULT NULL,
    payload_json TEXT DEFAULT NULL,
    akuma_fruit_id INT DEFAULT NULL,
    `status` ENUM('pendiente','aprobada','denegada') NOT NULL DEFAULT 'pendiente',
    staff_nota TEXT DEFAULT NULL,
    staff_user_id INT DEFAULT NULL,
    staff_char_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (`status`),
    INDEX idx_character (character_id),
    INDEX idx_akuma (akuma_fruit_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'Inventario equipado' => "CREATE TABLE {$prefix}game_character_inventory (
    character_id INT NOT NULL,
    card_id INT NOT NULL,
    slot_type ENUM('carga', 'companero', 'barco') NOT NULL,
    equipped_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    peso INT NOT NULL DEFAULT 0,
    PRIMARY KEY (character_id, card_id),
    INDEX idx_char_slot (character_id, slot_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'Cartas jugadas en posts' => "CREATE TABLE {$prefix}game_post_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    character_id INT NOT NULL,
    card_id INT NOT NULL,
    played_rank ENUM('D', 'C', 'B', 'A', 'S', 'SS') NOT NULL DEFAULT 'C',
    roll_result VARCHAR(255) DEFAULT NULL,
    hidden_action_index INT NOT NULL DEFAULT 0,
    played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_post (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'Estado PV/PE por hilo' => "CREATE TABLE {$prefix}game_thread_pj_state (
    thread_id INT NOT NULL,
    character_id INT NOT NULL,
    current_pv INT NOT NULL,
    current_pe INT NOT NULL,
    stat_mods_json TEXT,
    last_post_id INT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (thread_id, character_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'Anuncios staff' => "CREATE TABLE {$prefix}game_announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'Oráculos (catálogo)' => "CREATE TABLE {$prefix}game_oracles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    oracle_type VARCHAR(30) NOT NULL DEFAULT 'custom',
    subtype VARCHAR(100) DEFAULT '',
    category VARCHAR(100) DEFAULT '',
    tags_json TEXT,
    results_json TEXT NOT NULL,
    variations_json TEXT,
    auto_invoke_json TEXT,
    dice_type VARCHAR(10) NOT NULL DEFAULT 'd100',
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    image_url VARCHAR(500) DEFAULT '',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_type (oracle_type),
    KEY idx_category (category),
    KEY idx_subtype (subtype)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'Oráculos en posts' => "CREATE TABLE {$prefix}game_post_oracles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    character_id INT NOT NULL,
    oracle_id INT NOT NULL,
    roll_value VARCHAR(20) NOT NULL,
    result_range VARCHAR(20) NOT NULL DEFAULT '',
    result_text TEXT NOT NULL,
    result_description TEXT,
    auto_invoked TINYINT(1) NOT NULL DEFAULT 0,
    invoked_by_post_oracle_id INT DEFAULT NULL,
    rolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_post (post_id),
    KEY idx_oracle (oracle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'Islas del foro' => "CREATE TABLE {$prefix}game_forum_islands (
    fid INT UNSIGNED NOT NULL PRIMARY KEY,
    island_image VARCHAR(500) NOT NULL DEFAULT '',
    leader_name VARCHAR(200) NOT NULL DEFAULT '',
    description TEXT NOT NULL,
    terrain VARCHAR(200) NOT NULL DEFAULT '',
    climate VARCHAR(300) NOT NULL DEFAULT '',
    climate_temp VARCHAR(100) NOT NULL DEFAULT '',
    climate_wind VARCHAR(100) NOT NULL DEFAULT '',
    climate_precip VARCHAR(100) NOT NULL DEFAULT '',
    buildings TEXT NOT NULL,
    defenses TEXT NOT NULL,
    resources VARCHAR(300) NOT NULL DEFAULT '',
    coord_x INT NOT NULL DEFAULT 0,
    coord_y INT NOT NULL DEFAULT 0,
    sea_zone VARCHAR(50) NOT NULL DEFAULT 'east_blue',
    base_danger TINYINT UNSIGNED NOT NULL DEFAULT 1,
    requires_log_pose TINYINT(1) NOT NULL DEFAULT 0,
    requires_compass TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'Catálogo de disciplinas' => "CREATE TABLE {$prefix}game_disciplinas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(64) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description TEXT,
    category VARCHAR(64) NOT NULL DEFAULT 'combate',
    icon VARCHAR(64) NOT NULL DEFAULT 'fa-crosshairs',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    grado_unlock_json JSON NULL,
    requires_esp_rank TINYINT UNSIGNED NULL,
    staff_grant_only TINYINT(1) NOT NULL DEFAULT 0,
    fixed_pp_cost INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'Disciplinas por personaje' => "CREATE TABLE {$prefix}game_character_disciplinas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    character_id INT NOT NULL,
    disciplina_id INT NOT NULL,
    `rank` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    learned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_char_disciplina (character_id, disciplina_id),
    KEY idx_character (character_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'Catálogo de oficios' => "CREATE TABLE {$prefix}game_oficios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(64) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description TEXT,
    category VARCHAR(64) NOT NULL DEFAULT 'oficio',
    icon VARCHAR(64) NOT NULL DEFAULT 'fa-briefcase',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    grado_unlock_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'Oficios por personaje' => "CREATE TABLE {$prefix}game_character_oficios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    character_id INT NOT NULL,
    oficio_id INT NOT NULL,
    `rank` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    learned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_char_oficio (character_id, oficio_id),
    KEY idx_character (character_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'Rutas de navegación' => "CREATE TABLE {$prefix}game_navigation_routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    island_from_fid INT UNSIGNED NOT NULL,
    island_to_fid INT UNSIGNED NOT NULL,
    distance INT NOT NULL,
    waypoint_fids TEXT DEFAULT NULL,
    danger_override TINYINT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_route (island_from_fid, island_to_fid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'Viajes de navegación' => "CREATE TABLE {$prefix}game_navigation_voyages (
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
    staff_review ENUM('pending','approved','denied') NOT NULL DEFAULT 'pending',
    start_rol_days INT UNSIGNED NOT NULL DEFAULT 0,
    expected_end_rol_days INT UNSIGNED NOT NULL DEFAULT 0,
    reviewed_at INT UNSIGNED DEFAULT NULL,
    reviewed_by_uid INT UNSIGNED DEFAULT NULL,
    staff_notice_post_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_post (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'Eventos de navegación' => "CREATE TABLE {$prefix}game_navigation_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voyage_id INT NOT NULL,
    post_oracle_id INT NOT NULL,
    event_order TINYINT UNSIGNED NOT NULL,
    danger_tier TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_voyage (voyage_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'Control de migraciones' => "CREATE TABLE {$prefix}game_schema_migrations (
    name VARCHAR(128) NOT NULL PRIMARY KEY,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
}
