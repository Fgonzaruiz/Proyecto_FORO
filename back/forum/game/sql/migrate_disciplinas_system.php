<?php
declare(strict_types=1);

global $db;
$prefix = TABLE_PREFIX;

if (!$db->table_exists('game_disciplinas')) {
    $db->write_query("CREATE TABLE {$prefix}game_disciplinas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(64) NOT NULL,
        name VARCHAR(120) NOT NULL,
        description TEXT,
        category VARCHAR(64) NOT NULL DEFAULT 'combate',
        icon VARCHAR(64) NOT NULL DEFAULT 'fa-crosshairs',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_slug (slug),
        KEY idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p class='ok'>[OK] Tabla {$prefix}game_disciplinas creada.</p>";
} else {
    echo "<p class='skip'>[SKIP] Tabla game_disciplinas ya existe.</p>";
}

if (!$db->table_exists('game_character_disciplinas')) {
    $db->write_query("CREATE TABLE {$prefix}game_character_disciplinas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        character_id INT NOT NULL,
        disciplina_id INT NOT NULL,
        `rank` TINYINT UNSIGNED NOT NULL DEFAULT 1,
        learned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_char_disciplina (character_id, disciplina_id),
        KEY idx_character (character_id),
        KEY idx_disciplina (disciplina_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "<p class='ok'>[OK] Tabla {$prefix}game_character_disciplinas creada.</p>";
} else {
    echo "<p class='skip'>[SKIP] Tabla game_character_disciplinas ya existe.</p>";
}

$seeds = [
    ['cuerpo_a_cuerpo', 'Cuerpo a Cuerpo', 'Combate sin armas: puños, patadas, presas y técnicas físicas corporales.', 'combate', 'fa-hand-fist', 10],
    ['armas_de_filo', 'Armas de Filo', 'Espadas, sables, cuchillos, katanas y cualquier hoja cortante.', 'combate', 'fa-khanda', 20],
    ['armas_de_asta', 'Armas de Asta', 'Lanzas, alabardas, tridentes, naginatas y armas de asta larga.', 'combate', 'fa-staff-snake', 30],
    ['armas_contundentes', 'Armas Contundentes', 'Mazas, bastones, martillos, anclas y objetos de impacto masivo.', 'combate', 'fa-hammer', 40],
    ['armas_a_distancia', 'Armas a Distancia', 'Arcos, tirachinas, slings y proyectiles lanzados con el cuerpo.', 'combate', 'fa-bullseye', 50],
    ['armas_de_fuego', 'Armas de Fuego', 'Pistolas, rifles, cañones portátiles, bazucas y armas de pólvora.', 'combate', 'fa-fire', 60],
    ['armas_exoticas', 'Armas Exóticas', 'Todo lo que no encaja: látigos, yoyós, paraguas, instrumentos, armas únicas.', 'combate', 'fa-wand-magic-sparkles', 70],
    ['escudo', 'Escudo', 'Defensa con escudos, brazales, capas, armaduras y técnicas de protección.', 'combate', 'fa-shield-alt', 80],
];

foreach ($seeds as [$slug, $name, $desc, $cat, $icon, $sort]) {
    $escSlug = $db->escape_string($slug);
    $q = $db->query("SELECT 1 FROM {$prefix}game_disciplinas WHERE slug = '{$escSlug}' LIMIT 1");
    if ($db->num_rows($q)) {
        continue;
    }
    $escName = $db->escape_string($name);
    $escDesc = $db->escape_string($desc);
    $escCat = $db->escape_string($cat);
    $escIcon = $db->escape_string($icon);
    $db->write_query("INSERT INTO {$prefix}game_disciplinas (slug, name, description, category, icon, is_active, sort_order)
        VALUES ('{$escSlug}', '{$escName}', '{$escDesc}', '{$escCat}', '{$escIcon}', 1, {$sort})");
    echo "<p class='ok'>[OK] Disciplina seed: {$slug}</p>";
}
