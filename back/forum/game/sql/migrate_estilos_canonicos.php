<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/migration_helpers.php';
require_once __DIR__ . '/estilos_canonicos_seed_data.php';

$migrationName = 'migrate_estilos_canonicos.php';

if (game_migration_applied($migrationName)) {
    echo '<p class="skip">[SKIP] Ya aplicada.</p>';
    return;
}

global $db;
$prefix = TABLE_PREFIX;

if (!$db->table_exists('game_estilos_canonicos')) {
    $db->write_query("CREATE TABLE {$prefix}game_estilos_canonicos (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo '<p class="ok">[OK] Tabla game_estilos_canonicos creada.</p>';
}

if ($db->table_exists('game_cards') && !$db->field_exists('estilo_canonico_slug', 'game_cards')) {
    $db->write_query("ALTER TABLE {$prefix}game_cards
        ADD COLUMN estilo_canonico_slug VARCHAR(64) NULL AFTER disciplina_slug,
        ADD KEY idx_estilo_canonico (estilo_canonico_slug)");
    echo '<p class="ok">[OK] game_cards.estilo_canonico_slug añadida.</p>';
}

$seed = game_estilos_canonicos_seed_data();
$styleCount = 0;
foreach ($seed['estilos'] as $row) {
    $slug = $db->escape_string((string)$row['slug']);
    $exists = $db->fetch_array($db->query(
        "SELECT id FROM {$prefix}game_estilos_canonicos WHERE slug = '{$slug}' LIMIT 1"
    ));
    if ($exists) {
        continue;
    }
    $db->insert_query('game_estilos_canonicos', [
        'slug' => (string)$row['slug'],
        'name' => (string)$row['name'],
        'category' => (string)$row['category'],
        'category_label' => (string)$row['category_label'],
        'disciplina_slug' => (string)($row['disciplina_slug'] ?? ''),
        'primary_stat' => (string)($row['primary_stat'] ?? ''),
        'short_desc' => (string)$row['short_desc'],
        'description' => (string)$row['description'],
        'requirements_json' => json_encode($row['requirements'] ?? [], JSON_UNESCAPED_UNICODE),
        'advantages_json' => json_encode($row['advantages'] ?? [], JSON_UNESCAPED_UNICODE),
        'sort_order' => (int)($row['sort_order'] ?? 0),
        'is_active' => 1,
    ]);
    $styleCount++;
}
echo '<p class="ok">[OK] Estilos canónicos insertados: ' . $styleCount . '.</p>';

$adminUid = 1;
$adminRow = $db->fetch_array($db->query("SELECT uid FROM {$prefix}users WHERE usergroup = 4 ORDER BY uid ASC LIMIT 1"));
if ($adminRow) {
    $adminUid = (int)$adminRow['uid'];
}

$cardCount = 0;
if ($db->table_exists('game_cards')) {
    foreach ($seed['cartas'] as $card) {
        $estiloSlug = (string)$card['estilo'];
        $name = $db->escape_string((string)$card['name']);
        $exists = $db->fetch_array($db->query(
            "SELECT id FROM {$prefix}game_cards WHERE name = '{$name}' AND estilo_canonico_slug = '"
            . $db->escape_string($estiloSlug) . "' LIMIT 1"
        ));
        if ($exists) {
            continue;
        }
        $styleRow = $db->fetch_array($db->query(
            "SELECT disciplina_slug FROM {$prefix}game_estilos_canonicos WHERE slug = '"
            . $db->escape_string($estiloSlug) . "' LIMIT 1"
        ));
        $disciplina = $styleRow ? (string)($styleRow['disciplina_slug'] ?? '') : '';
        $db->insert_query('game_cards', [
            'name' => (string)$card['name'],
            'card_type' => 'tecnica',
            'rank' => (string)$card['rank'],
            'activation' => 'activa',
            'tags_json' => json_encode(['TECNICA'], JSON_UNESCAPED_UNICODE),
            'description' => (string)$card['description'],
            'cost_pe' => (string)$card['cost_pe'],
            'dice' => (string)($card['dice'] ?? ''),
            'created_by' => $adminUid,
            'disciplina_slug' => $disciplina !== '' ? $disciplina : null,
            'estilo_canonico_slug' => $estiloSlug,
        ]);
        $cardCount++;
    }
}
echo '<p class="ok">[OK] Cartas técnicas de ejemplo insertadas: ' . $cardCount . '.</p>';

game_migration_mark_applied($migrationName);
echo '<p class="ok">[OK] migrate_estilos_canonicos.php registrada.</p>';
