<?php
declare(strict_types=1);

require_once __DIR__ . '/grado_helpers.php';

/**
 * Helpers del sistema de oficios (grados I–V).
 */

function game_oficio_rank_label(int $rank): string
{
    return game_grado_label($rank);
}

function game_oficio_rank_bonus(int $rank): float
{
    return game_grado_bonus($rank);
}

function game_oficio_get_by_slug(string $slug): ?array
{
    global $db;
    if (!$db->table_exists('game_oficios')) {
        return null;
    }
    $prefix = TABLE_PREFIX;
    $esc = $db->escape_string($slug);
    $q = $db->query("SELECT * FROM {$prefix}game_oficios WHERE slug = '{$esc}' AND is_active = 1 LIMIT 1");
    $row = $db->fetch_array($q);
    return $row ?: null;
}

function game_oficio_get_rank(int $characterId, string $slug): int
{
    global $db;
    if ($characterId <= 0 || !$db->table_exists('game_character_oficios') || !$db->table_exists('game_oficios')) {
        return 0;
    }
    $prefix = TABLE_PREFIX;
    $esc = $db->escape_string($slug);
    $q = $db->query("
        SELECT co.`rank`
        FROM {$prefix}game_character_oficios co
        JOIN {$prefix}game_oficios o ON o.id = co.oficio_id
        WHERE co.character_id = " . (int)$characterId . " AND o.slug = '{$esc}' AND o.is_active = 1
        LIMIT 1
    ");
    $row = $db->fetch_array($q);
    return $row ? (int)$row['rank'] : 0;
}

/** @return list<array> */
function game_oficio_list_for_character(int $characterId): array
{
    global $db;
    if ($characterId <= 0 || !$db->table_exists('game_character_oficios')) {
        return [];
    }
    $prefix = TABLE_PREFIX;
    $q = $db->query("
        SELECT co.`rank`, co.learned_at, o.id, o.slug, o.name, o.description, o.category, o.icon
        FROM {$prefix}game_character_oficios co
        JOIN {$prefix}game_oficios o ON o.id = co.oficio_id
        WHERE co.character_id = " . (int)$characterId . "
        ORDER BY o.sort_order ASC, o.name ASC
    ");
    $out = [];
    while ($row = $db->fetch_array($q)) {
        $rank = (int)$row['rank'];
        $row['rank_label'] = game_oficio_rank_label($rank);
        $row['rank_bonus'] = game_oficio_rank_bonus($rank);
        $out[] = $row;
    }
    return $out;
}

/** @return list<array> */
function game_oficio_list_catalog(bool $activeOnly = true): array
{
    global $db;
    if (!$db->table_exists('game_oficios')) {
        return [];
    }
    $prefix = TABLE_PREFIX;
    $where = $activeOnly ? 'WHERE is_active = 1' : '';
    $q = $db->query("SELECT * FROM {$prefix}game_oficios {$where} ORDER BY sort_order ASC, name ASC");
    $out = [];
    while ($row = $db->fetch_array($q)) {
        $out[] = $row;
    }
    return $out;
}

function game_oficio_set_character_rank(int $characterId, int $oficioId, int $rank): bool
{
    global $db;
    if ($characterId <= 0 || $oficioId <= 0 || !$db->table_exists('game_character_oficios')) {
        return false;
    }
    $prefix = TABLE_PREFIX;
    $rank = max(1, min(5, $rank));
    $cid = (int)$characterId;
    $oid = (int)$oficioId;

    $existing = $db->query("SELECT id FROM {$prefix}game_character_oficios WHERE character_id = {$cid} AND oficio_id = {$oid} LIMIT 1");
    if ($db->num_rows($existing)) {
        $db->write_query("UPDATE {$prefix}game_character_oficios SET `rank` = {$rank} WHERE character_id = {$cid} AND oficio_id = {$oid}");
    } else {
        $db->write_query("INSERT INTO {$prefix}game_character_oficios (character_id, oficio_id, `rank`) VALUES ({$cid}, {$oid}, {$rank})");
    }
    return true;
}

function game_oficio_remove_from_character(int $characterId, int $oficioId): bool
{
    global $db;
    if ($characterId <= 0 || $oficioId <= 0) {
        return false;
    }
    $prefix = TABLE_PREFIX;
    $db->write_query("DELETE FROM {$prefix}game_character_oficios WHERE character_id = " . (int)$characterId . " AND oficio_id = " . (int)$oficioId);
    return true;
}

function game_oficio_job_to_slug(string $job): ?string
{
    $job = trim($job);
    if ($job === '' || strcasecmp($job, 'Ninguno') === 0 || strcasecmp($job, 'Ninguno / Aprendiz') === 0) {
        return null;
    }
    $map = [
        'navegante' => 'navegante',
        'medico' => 'medico',
        'médico' => 'medico',
        'cocinero' => 'cocinero',
        'herrero' => 'herrero',
        'cientifico' => 'cientifico',
        'científico' => 'cientifico',
        'erudito' => 'cientifico',
        'timonel' => 'navegante',
    ];
    $key = strtolower(preg_replace('/[^a-záéíóúñ]/iu', '', $job) ?? '');
    if (isset($map[$key])) {
        return $map[$key];
    }
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $job) ?? '');
    $slug = trim($slug, '_');
    return $slug !== '' ? $slug : null;
}

function game_oficio_assign_initial_from_job(int $characterId, string $job, int $rank = 1): void
{
    $slug = game_oficio_job_to_slug($job);
    if ($slug === null) {
        return;
    }
    $oficio = game_oficio_get_by_slug($slug);
    if (!$oficio) {
        return;
    }
    game_oficio_set_character_rank($characterId, (int)$oficio['id'], $rank);
}
