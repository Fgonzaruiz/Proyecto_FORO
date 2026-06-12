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
        SELECT cd.`rank`, cd.learned_at, d.id, d.slug, d.name, d.description, d.category, d.icon,
               d.grado_unlock_json, d.requires_esp_rank, d.staff_grant_only, d.fixed_pp_cost
        FROM {$prefix}game_character_disciplinas cd
        JOIN {$prefix}game_disciplinas d ON d.id = cd.disciplina_id
        WHERE cd.character_id = " . (int)$characterId . "
        ORDER BY d.sort_order ASC, d.name ASC
    ");
    $out = [];
    while ($row = $db->fetch_array($q)) {
        $rank = (int)$row['rank'];
        $row['rank_label'] = game_disciplina_rank_label($rank);
        $row['grado_unlock'] = game_parse_grado_unlock_json($row['grado_unlock_json'] ?? null);
        unset($row['grado_unlock_json']);
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

function game_disciplina_count_for_character(int $characterId): int
{
    global $db;
    if ($characterId <= 0 || !$db->table_exists('game_character_disciplinas')) {
        return 0;
    }
    $prefix = TABLE_PREFIX;
    $q = $db->query("SELECT COUNT(*) AS c FROM {$prefix}game_character_disciplinas WHERE character_id = " . (int)$characterId);
    $row = $db->fetch_array($q);
    return $row ? (int)$row['c'] : 0;
}

function game_disciplina_character_owns(int $characterId, int $disciplinaId): bool
{
    global $db;
    if ($characterId <= 0 || $disciplinaId <= 0 || !$db->table_exists('game_character_disciplinas')) {
        return false;
    }
    $prefix = TABLE_PREFIX;
    $q = $db->query("SELECT 1 FROM {$prefix}game_character_disciplinas
        WHERE character_id = " . (int)$characterId . " AND disciplina_id = " . (int)$disciplinaId . " LIMIT 1");
    return (bool)$db->fetch_array($q);
}

/**
 * @param array<string, mixed> $catalogRow
 * @return array{ok: bool, reason: string}
 */
function game_disciplina_validate_acquire_rules(array $catalogRow, int $espEffectiveRank): array
{
    if (!empty($catalogRow['staff_grant_only'])) {
        return ['ok' => false, 'reason' => 'Solo el staff puede conceder esta disciplina'];
    }
    $reqEsp = $catalogRow['requires_esp_rank'] ?? null;
    if ($reqEsp !== null && $reqEsp !== '' && $espEffectiveRank < (int)$reqEsp) {
        return ['ok' => false, 'reason' => 'ESP efectivo insuficiente (requiere rango ' . (int)$reqEsp . '+)'];
    }
    return ['ok' => true, 'reason' => ''];
}

/**
 * @param array<string, mixed> $catalogRow
 * @return array<string, mixed>
 */
function game_disciplina_enrich_acquire_option(
    array $catalogRow,
    int $alreadyOwned,
    int $charNivel,
    int $ppAvailable,
    int $espEffectiveRank
): array {
    $fixed = $catalogRow['fixed_pp_cost'] ?? null;
    $cost = ($fixed !== null && $fixed !== '') ? (int)$fixed : game_get_acquisition_cost($alreadyOwned, 'disciplina');
    $nivelReq = game_get_acquisition_level_required($alreadyOwned);
    $ruleCheck = game_disciplina_validate_acquire_rules($catalogRow, $espEffectiveRank);
    $reasons = [];
    if (!$ruleCheck['ok']) {
        $reasons[] = $ruleCheck['reason'];
    }
    if ($charNivel < $nivelReq) {
        $reasons[] = 'Requiere nivel ' . $nivelReq;
    }
    if ($ppAvailable < $cost) {
        $reasons[] = 'PP insuficientes (' . $cost . ')';
    }
    $unlocks = game_parse_grado_unlock_json($catalogRow['grado_unlock_json'] ?? null);

    return [
        'id' => (int)$catalogRow['id'],
        'slug' => (string)$catalogRow['slug'],
        'name' => (string)$catalogRow['name'],
        'description' => (string)($catalogRow['description'] ?? ''),
        'category' => (string)($catalogRow['category'] ?? ''),
        'icon' => (string)($catalogRow['icon'] ?? 'fa-crosshairs'),
        'pp_cost' => $cost,
        'nivel_required' => $nivelReq,
        'requires_esp_rank' => isset($catalogRow['requires_esp_rank']) ? (int)$catalogRow['requires_esp_rank'] : null,
        'fixed_pp_cost' => ($fixed !== null && $fixed !== '') ? (int)$fixed : null,
        'can_acquire' => $reasons === [],
        'blocked_reason' => $reasons !== [] ? implode(' · ', $reasons) : '',
        'grado_unlock' => $unlocks,
        'unlock_preview' => (string)($unlocks['1'] ?? ''),
    ];
}

/** @return list<array<string, mixed>> */
function game_disciplina_acquire_catalog_for_character(
    int $characterId,
    int $charNivel,
    int $ppAvailable,
    int $espEffectiveRank
): array {
    $ownedIds = [];
    foreach (game_disciplina_list_for_character($characterId) as $row) {
        $ownedIds[(int)$row['id']] = true;
    }
    $alreadyOwned = count($ownedIds);
    $out = [];
    foreach (game_disciplina_list_catalog(true) as $row) {
        if (!empty($row['staff_grant_only'])) {
            continue;
        }
        if (isset($ownedIds[(int)$row['id']])) {
            continue;
        }
        $out[] = game_disciplina_enrich_acquire_option($row, $alreadyOwned, $charNivel, $ppAvailable, $espEffectiveRank);
    }
    return $out;
}

/** Coste en PP para adquirir una disciplina concreta del catálogo. */
function game_disciplina_acquire_pp_cost(array $catalogRow, int $alreadyOwned): int
{
    $fixed = $catalogRow['fixed_pp_cost'] ?? null;
    if ($fixed !== null && $fixed !== '') {
        return (int)$fixed;
    }
    return game_get_acquisition_cost($alreadyOwned, 'disciplina');
}
