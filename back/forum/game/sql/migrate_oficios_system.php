<?php
declare(strict_types=1);

global $db;
$prefix = TABLE_PREFIX;

if (!$db->table_exists('game_oficios')) {
    $db->write_query("CREATE TABLE {$prefix}game_oficios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(64) NOT NULL,
        name VARCHAR(120) NOT NULL,
        description TEXT,
        category VARCHAR(64) NOT NULL DEFAULT 'oficio',
        icon VARCHAR(64) NOT NULL DEFAULT 'fa-briefcase',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_slug (slug),
        KEY idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p class='ok'>[OK] Tabla {$prefix}game_oficios creada.</p>";
} else {
    echo "<p class='skip'>[SKIP] Tabla game_oficios ya existe.</p>";
}

if (!$db->table_exists('game_character_oficios')) {
    $db->write_query("CREATE TABLE {$prefix}game_character_oficios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        character_id INT NOT NULL,
        oficio_id INT NOT NULL,
        `rank` TINYINT UNSIGNED NOT NULL DEFAULT 1,
        learned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_char_oficio (character_id, oficio_id),
        KEY idx_character (character_id),
        KEY idx_oficio (oficio_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p class='ok'>[OK] Tabla {$prefix}game_character_oficios creada.</p>";
} else {
    echo "<p class='skip'>[SKIP] Tabla game_character_oficios ya existe.</p>";
}

$seeds = [
    ['navegante', 'Navegante', 'Domina la cartografía, las corrientes y el rumbo en mar abierto.', 'mar', 'fa-compass', 10],
    ['herrero', 'Herrero', 'Forja y repara armas y herramientas metálicas.', 'artesania', 'fa-hammer', 20],
    ['medico', 'Médico', 'Diagnostica y trata heridas y enfermedades.', 'sanacion', 'fa-user-md', 30],
    ['cocinero', 'Cocinero', 'Prepara comidas que restauran y motivan a la tripulación.', 'artesania', 'fa-utensils', 40],
    ['cientifico', 'Científico', 'Investiga fenómenos, inventa y analiza.', 'ciencia', 'fa-flask', 50],
];

foreach ($seeds as [$slug, $name, $desc, $cat, $icon, $sort]) {
    $escSlug = $db->escape_string($slug);
    $q = $db->query("SELECT 1 FROM {$prefix}game_oficios WHERE slug = '{$escSlug}' LIMIT 1");
    if ($db->num_rows($q)) {
        continue;
    }
    $escName = $db->escape_string($name);
    $escDesc = $db->escape_string($desc);
    $escCat = $db->escape_string($cat);
    $escIcon = $db->escape_string($icon);
    $db->write_query("INSERT INTO {$prefix}game_oficios (slug, name, description, category, icon, is_active, sort_order)
        VALUES ('{$escSlug}', '{$escName}', '{$escDesc}', '{$escCat}', '{$escIcon}', 1, {$sort})");
    echo "<p class='ok'>[OK] Oficio seed: {$slug}</p>";
}
