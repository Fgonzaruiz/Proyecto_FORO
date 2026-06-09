<?php
declare(strict_types=1);

require_once __DIR__ . '/grado_helpers.php';

/**
 * Helpers del sistema de disciplinas de combate (grados I–V).
 */

function game_disciplina_rank_label(int $rank): string
{
    return game_grado_label($rank);
}

function game_disciplina_get_by_slug(string $slug): ?array
{
    global $db;
    if (!$db->table_exists('game_disciplinas')) {
        return null;
    }
    $prefix = TABLE_PREFIX;
    $esc = $db->escape_string($slug);
    $q = $db->query("SELECT * FROM {$prefix}game_disciplinas WHERE slug = '{$esc}' AND is_active = 1 LIMIT 1");
    $row = $db->fetch_array($q);
    return $row ?: null;
}

function game_disciplina_get_by_name(string $name): ?array
{
    global $db;
    $name = trim($name);
    if ($name === '' || !$db->table_exists('game_disciplinas')) {
        return null;
    }
    $prefix = TABLE_PREFIX;
    $esc = $db->escape_string($name);
    $q = $db->query("SELECT * FROM {$prefix}game_disciplinas WHERE name = '{$esc}' AND is_active = 1 LIMIT 1");
    $row = $db->fetch_array($q);
    return $row ?: null;
}

function game_disciplina_name_to_slug(string $name): ?string
{
    $row = game_disciplina_get_by_name($name);
    if ($row) {
        return (string)$row['slug'];
    }
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name) ?? '');
    $slug = trim($slug, '_');
    return $slug !== '' ? $slug : null;
}

function game_disciplina_get_rank(int $characterId, string $slug): int
{
    global $db;
    if ($characterId <= 0 || !$db->table_exists('game_character_disciplinas') || !$db->table_exists('game_disciplinas')) {
        return 0;
    }
    $prefix = TABLE_PREFIX;
    $esc = $db->escape_string($slug);
    $q = $db->query("
        SELECT cd.`rank`
        FROM {$prefix}game_character_disciplinas cd
        JOIN {$prefix}game_disciplinas d ON d.id = cd.disciplina_id
        WHERE cd.character_id = " . (int)$characterId . " AND d.slug = '{$esc}' AND d.is_active = 1
        LIMIT 1
    ");
    $row = $db->fetch_array($q);
    return $row ? (int)$row['rank'] : 0;
}

/** @return list<array> */
function game_disciplina_list_for_character(int $characterId): array
{
    global $db;
    if ($characterId <= 0 || !$db->table_exists('game_character_disciplinas')) {
        return [];
    }
    $prefix = TABLE_PREFIX;
    $q = $db->query("
        SELECT cd.`rank`, cd.learned_at, d.id, d.slug, d.name, d.description, d.category, d.icon
        FROM {$prefix}game_character_disciplinas cd
        JOIN {$prefix}game_disciplinas d ON d.id = cd.disciplina_id
        WHERE cd.character_id = " . (int)$characterId . "
        ORDER BY d.sort_order ASC, d.name ASC
    ");
    $out = [];
    while ($row = $db->fetch_array($q)) {
        $rank = (int)$row['rank'];
        $row['rank_label'] = game_disciplina_rank_label($rank);
        $out[] = $row;
    }
    return $out;
}

/** @return list<array> */
function game_disciplina_list_catalog(bool $activeOnly = true): array
{
    global $db;
    if (!$db->table_exists('game_disciplinas')) {
        return [];
    }
    $prefix = TABLE_PREFIX;
    $where = $activeOnly ? 'WHERE is_active = 1' : '';
    $q = $db->query("SELECT * FROM {$prefix}game_disciplinas {$where} ORDER BY sort_order ASC, name ASC");
    $out = [];
    while ($row = $db->fetch_array($q)) {
        $out[] = $row;
    }
    return $out;
}

function game_disciplina_set_character_rank(int $characterId, int $disciplinaId, int $rank): bool
{
    global $db;
    if ($characterId <= 0 || $disciplinaId <= 0 || !$db->table_exists('game_character_disciplinas')) {
        return false;
    }
    $prefix = TABLE_PREFIX;
    $rank = max(1, min(5, $rank));
    $cid = (int)$characterId;
    $did = (int)$disciplinaId;

    $existing = $db->query("SELECT id FROM {$prefix}game_character_disciplinas WHERE character_id = {$cid} AND disciplina_id = {$did} LIMIT 1");
    if ($db->num_rows($existing)) {
        $db->write_query("UPDATE {$prefix}game_character_disciplinas SET `rank` = {$rank} WHERE character_id = {$cid} AND disciplina_id = {$did}");
    } else {
        $db->write_query("INSERT INTO {$prefix}game_character_disciplinas (character_id, disciplina_id, `rank`) VALUES ({$cid}, {$did}, {$rank})");
    }
    return true;
}

function game_disciplina_remove_from_character(int $characterId, int $disciplinaId): bool
{
    global $db;
    if ($characterId <= 0 || $disciplinaId <= 0) {
        return false;
    }
    $prefix = TABLE_PREFIX;
    $db->write_query("DELETE FROM {$prefix}game_character_disciplinas WHERE character_id = " . (int)$characterId . " AND disciplina_id = " . (int)$disciplinaId);
    return true;
}

function game_disciplina_assign_initial(int $characterId, string $disciplinaInput, int $rank = 1): void
{
    $disciplinaInput = trim($disciplinaInput);
    if ($disciplinaInput === '' || strcasecmp($disciplinaInput, 'Ninguna') === 0) {
        return;
    }
    $disciplina = game_disciplina_get_by_name($disciplinaInput);
    if (!$disciplina) {
        $slug = game_disciplina_name_to_slug($disciplinaInput);
        if ($slug !== null) {
            $disciplina = game_disciplina_get_by_slug($slug);
        }
    }
    if (!$disciplina) {
        return;
    }
    game_disciplina_set_character_rank($characterId, (int)$disciplina['id'], $rank);
}
