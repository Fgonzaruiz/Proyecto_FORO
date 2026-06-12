<?php
declare(strict_types=1);

/** @return list<array<string, mixed>> */
function game_estilos_canonicos_list(bool $activeOnly = true): array
{
    global $db;
    if (!$db->table_exists('game_estilos_canonicos')) {
        return [];
    }
    $prefix = TABLE_PREFIX;
    $where = $activeOnly ? 'WHERE is_active = 1' : '';
    $q = $db->query("SELECT * FROM {$prefix}game_estilos_canonicos {$where} ORDER BY sort_order ASC, name ASC");
    $out = [];
    while ($row = $db->fetch_array($q)) {
        $out[] = game_estilos_canonicos_normalize_row($row);
    }
    return $out;
}

/** @param array<string, mixed> $row */
function game_estilos_canonicos_normalize_row(array $row): array
{
    $req = $row['requirements_json'] ?? '[]';
    $adv = $row['advantages_json'] ?? '[]';
    if (is_string($req)) {
        $req = json_decode($req, true);
    }
    if (is_string($adv)) {
        $adv = json_decode($adv, true);
    }

    return [
        'id' => (int)$row['id'],
        'slug' => (string)$row['slug'],
        'name' => (string)$row['name'],
        'category' => (string)$row['category'],
        'category_label' => (string)$row['category_label'],
        'disciplina_slug' => (string)($row['disciplina_slug'] ?? ''),
        'primary_stat' => (string)($row['primary_stat'] ?? ''),
        'short_desc' => (string)$row['short_desc'],
        'description' => (string)$row['description'],
        'requirements' => is_array($req) ? array_values($req) : [],
        'advantages' => is_array($adv) ? array_values($adv) : [],
        'image_url' => (string)($row['image_url'] ?? ''),
        'sort_order' => (int)($row['sort_order'] ?? 0),
        'is_active' => (int)($row['is_active'] ?? 1) === 1,
    ];
}

/** @return list<array<string, mixed>> */
function game_estilos_canonicos_cards_for_slug(string $slug): array
{
    global $db;
    if ($slug === '' || !$db->table_exists('game_cards') || !$db->field_exists('estilo_canonico_slug', 'game_cards')) {
        return [];
    }
    $prefix = TABLE_PREFIX;
    $esc = $db->escape_string($slug);
    $q = $db->query("SELECT id, name, `rank`, card_type, dice, cost_pe, description, activation
        FROM {$prefix}game_cards
        WHERE estilo_canonico_slug = '{$esc}' AND card_type = 'tecnica'
        ORDER BY FIELD(`rank`, 'D','C','B','A','S','SS'), name ASC");
    $out = [];
    while ($row = $db->fetch_array($q)) {
        $out[] = [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'rank' => (string)$row['rank'],
            'dice' => (string)($row['dice'] ?? ''),
            'cost_pe' => (string)($row['cost_pe'] ?? '—'),
            'description' => (string)($row['description'] ?? ''),
            'activation' => (string)($row['activation'] ?? 'activa'),
        ];
    }
    return $out;
}

/** @return array<string, list<array<string, mixed>>> */
function game_estilos_canonicos_cards_by_slug(): array
{
    global $db;
    $map = [];
    if (!$db->table_exists('game_cards') || !$db->field_exists('estilo_canonico_slug', 'game_cards')) {
        return $map;
    }
    $prefix = TABLE_PREFIX;
    $q = $db->query("SELECT id, name, `rank`, dice, cost_pe, description, activation, estilo_canonico_slug
        FROM {$prefix}game_cards
        WHERE estilo_canonico_slug IS NOT NULL AND estilo_canonico_slug != '' AND card_type = 'tecnica'
        ORDER BY estilo_canonico_slug, FIELD(`rank`, 'D','C','B','A','S','SS'), name ASC");
    while ($row = $db->fetch_array($q)) {
        $slug = (string)$row['estilo_canonico_slug'];
        $map[$slug][] = [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'rank' => (string)$row['rank'],
            'dice' => (string)($row['dice'] ?? ''),
            'cost_pe' => (string)($row['cost_pe'] ?? '—'),
            'description' => (string)($row['description'] ?? ''),
            'activation' => (string)($row['activation'] ?? 'activa'),
        ];
    }
    return $map;
}

/** @param array<string, mixed> $style */
function game_estilos_canonicos_requirements_display(array $style): array
{
    $items = $style['requirements'] ?? [];
    return is_array($items) ? $items : [];
}

/** @param array<string, mixed> $style */
function game_estilos_canonicos_advantages_display(array $style): array
{
    $items = $style['advantages'] ?? [];
    return is_array($items) ? $items : [];
}
