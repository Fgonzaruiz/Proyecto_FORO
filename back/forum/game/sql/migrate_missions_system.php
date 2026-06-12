<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $db;
$prefix = TABLE_PREFIX;

echo "<pre class='rpg-admin-pre'>\n";
echo "=== Migración: Sistema de Misiones y Puntos Destino ===\n\n";

// 1. Agregar puntos_destino a game_personajes
$col_check = $db->query("SHOW COLUMNS FROM {$prefix}game_personajes LIKE 'puntos_destino'");
if (!$db->num_rows($col_check)) {
    $db->write_query("ALTER TABLE {$prefix}game_personajes ADD COLUMN puntos_destino INT NOT NULL DEFAULT 0 AFTER berries");
    echo "[OK] Columna 'puntos_destino' agregada a tabla personajes.\n";
} else {
    echo "[--] Columna 'puntos_destino' ya existe.\n";
}

// 2. Crear tabla game_missions
if (!$db->table_exists('game_missions')) {
    $db->write_query("CREATE TABLE {$prefix}game_missions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        `rank` VARCHAR(10) NOT NULL,
        min_level INT NOT NULL DEFAULT 1,
        max_level INT NOT NULL DEFAULT 99,
        points_reward SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        berry_reward INT NOT NULL DEFAULT 0,
        isla VARCHAR(100) NOT NULL,
        categoria VARCHAR(64) NOT NULL DEFAULT 'mision',
        max_posts INT NOT NULL DEFAULT 15,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_active (is_active),
        KEY idx_rank (`rank`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "[OK] Tabla 'game_missions' creada.\n";
} else {
    echo "[--] Tabla 'game_missions' ya existe.\n";
}

// 3. Crear tabla game_missions_active
if (!$db->table_exists('game_missions_active')) {
    $db->write_query("CREATE TABLE {$prefix}game_missions_active (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mission_id INT NOT NULL,
        tid INT NOT NULL DEFAULT 0,
        leader_character_id INT NOT NULL,
        status VARCHAR(24) NOT NULL DEFAULT 'pending',
        post_count INT NOT NULL DEFAULT 0,
        started_at TIMESTAMP NULL DEFAULT NULL,
        completed_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_mission (mission_id),
        KEY idx_thread (tid),
        KEY idx_leader (leader_character_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "[OK] Tabla 'game_missions_active' creada.\n";
} else {
    echo "[--] Tabla 'game_missions_active' ya existe.\n";
}

// 4. Crear tabla game_mission_participants
if (!$db->table_exists('game_mission_participants')) {
    $db->write_query("CREATE TABLE {$prefix}game_mission_participants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        active_mission_id INT NOT NULL,
        character_id INT NOT NULL,
        user_id INT NOT NULL,
        confirmed TINYINT(1) NOT NULL DEFAULT 0,
        last_post_at TIMESTAMP NULL DEFAULT NULL,
        cooldown_until TIMESTAMP NULL DEFAULT NULL,
        UNIQUE KEY uq_active_char (active_mission_id, character_id),
        KEY idx_char_cooldown (character_id, cooldown_until)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "[OK] Tabla 'game_mission_participants' creada.\n";
} else {
    echo "[--] Tabla 'game_mission_participants' ya existe.\n";
}

// 5. Crear tabla game_pd_purchases
if (!$db->table_exists('game_pd_purchases')) {
    $db->write_query("CREATE TABLE {$prefix}game_pd_purchases (
        id INT AUTO_INCREMENT PRIMARY KEY,
        character_id INT NOT NULL,
        pd_cost SMALLINT UNSIGNED NOT NULL,
        item_type VARCHAR(64) NOT NULL,
        item_slug VARCHAR(128) NOT NULL,
        item_name VARCHAR(255) NOT NULL,
        purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_character (character_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "[OK] Tabla 'game_pd_purchases' creada.\n";
} else {
    echo "[--] Tabla 'game_pd_purchases' ya existe.\n";
}

// Seeds por defecto para el tablón de misiones
$seeds = [
    [
        'La Infestación de Ratz',
        'Una horda de ratas gigantes de alcantarilla está asolando las bodegas del muelle este. Deshazte de ellas antes de que infecten la carga de grano.',
        'D', 1, 10, 1, 500, 'Loguetown', 'combate', 15
    ],
    [
        'La Búsqueda del Sextante Perdido',
        'El profesor Clover ha extraviado un antiguo sextante náutico en las dunas de la playa norte. Encuéntralo y devuélvelo para tu recompensa.',
        'D', 1, 12, 1, 400, 'Ohara', 'exploracion', 12
    ],
    [
        'Infiltración en la Mansión de Banchina',
        'Un usurero local posee registros falsificados de propiedades de familias pobres. Entra sin ser detectado y destruye los libros de contabilidad.',
        'C', 5, 20, 2, 1200, 'Syrup', 'sigilo', 15
    ],
    [
        'Escolta en el Desierto de Alabasta',
        'Una caravana de agua dulce cruzará el desierto de Erumalu. Los rebeldes o bandidos de arena podrían atacar. Garantiza su paso seguro.',
        'B', 15, 35, 4, 3500, 'Alabasta', 'escolta', 20
    ],
    [
        'El Despertar de la Bestia Calamar',
        'Un calamar colosal ha bloqueado el estrecho de navegación comercial de Water 7. Requiere un equipo experimentado para calmarlo o abatirlo.',
        'A', 30, 60, 6, 8000, 'Water 7', 'combate', 25
    ],
    [
        'La Anomalía del Triángulo de Florian',
        'Una densa niebla estática ha atrapado tres acorazados de la Marina. Investiga la fuente magnética oscura y escapa antes de que la tripulación enloquezca.',
        'S', 50, 100, 10, 20000, 'Florian Triangle', 'supervivencia', 30
    ]
];

foreach ($seeds as $s) {
    $titleEsc = $db->escape_string($s[0]);
    $descEsc = $db->escape_string($s[1]);
    $rankEsc = $db->escape_string($s[2]);
    $minL = (int)$s[3];
    $maxL = (int)$s[4];
    $pd = (int)$s[5];
    $berry = (int)$s[6];
    $islaEsc = $db->escape_string($s[7]);
    $catEsc = $db->escape_string($s[8]);
    $maxP = (int)$s[9];

    // Verificar si ya existe por título
    $q = $db->query("SELECT 1 FROM {$prefix}game_missions WHERE title = '{$titleEsc}' LIMIT 1");
    if ($db->num_rows($q) == 0) {
        $db->write_query("INSERT INTO {$prefix}game_missions 
            (title, description, `rank`, min_level, max_level, points_reward, berry_reward, isla, categoria, max_posts, is_active)
            VALUES 
            ('{$titleEsc}', '{$descEsc}', '{$rankEsc}', {$minL}, {$maxL}, {$pd}, {$berry}, '{$islaEsc}', '{$catEsc}', {$maxP}, 1)");
        echo "[OK] Semilla de misión agregada: {$s[0]}.\n";
    }
}

echo "\n=== Migración completada ===\n";
echo "</pre>";
