<?php
declare(strict_types=1);

/**
 * Grados I–V compartidos por disciplinas y oficios (escala v2).
 */

function game_grado_label(int $rank): string
{
    $labels = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V'];
    return $labels[max(1, min(5, $rank))] ?? 'I';
}

function game_grado_bonus(int $rank): float
{
    if ($rank <= 0) {
        return 0.0;
    }
    return (float)max(1, min(5, $rank)) * 0.5;
}

/** Nivel global del PJ (1–6) requerido para alcanzar cada grado (I–V). */
function game_grado_nivel_required(int $targetRank): int
{
    $map = [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5];
    return $map[max(1, min(5, $targetRank))] ?? 1;
}

/** Coste en PP para subir al grado indicado (II–V). */
function game_grado_upgrade_price(int $targetRank): int
{
    $prices = [2 => 100, 3 => 200, 4 => 200, 5 => 300];
    return $prices[max(2, min(5, $targetRank))] ?? 0;
}

/** Cooldown en días reales tras alcanzar el grado indicado (II–V). */
function game_grado_cooldown_days_for_rank(int $targetRank): int
{
    $map = [2 => 7, 3 => 14, 4 => 21, 5 => 30];
    return $map[max(2, min(5, $targetRank))] ?? 7;
}

/** @return array<int, int> */
function game_grado_cooldown_days_map(): array
{
    return [2 => 7, 3 => 14, 4 => 21, 5 => 30];
}

/** Mínimo cooldown (grado II) — hint genérico en UI. */
function game_grado_cooldown_days(): int
{
    return 7;
}

/** Nivel global del PJ (1–6) desde data_json. */
function game_get_character_nivel(array $data): int
{
    return max(1, min(6, (int)($data['nivel'] ?? 1)));
}

/** Coste en PP de adquirir la (already_owned + 1)-ésima competencia del mismo tipo. */
function game_get_acquisition_cost(int $alreadyOwned): int
{
    $costs = [0, 0, 200, 600, 1500, 3500, 8000];
    if ($alreadyOwned < count($costs) - 1) {
        return $costs[$alreadyOwned + 1];
    }
    return (int)round(8000 * pow(2, $alreadyOwned - 5));
}

/** Nivel mínimo del PJ para adquirir la (already_owned + 1)-ésima competencia. */
function game_get_acquisition_level_required(int $alreadyOwned): int
{
    if ($alreadyOwned === 0) {
        return 1;
    }
    return min($alreadyOwned + 1, 6);
}

/**
 * @param array<string, mixed> $unlocks
 * @return array<string, string>
 */
function game_parse_grado_unlock_json(mixed $unlocks): array
{
    if (is_string($unlocks) && $unlocks !== '') {
        $decoded = json_decode($unlocks, true);
        return is_array($decoded) ? $decoded : [];
    }
    return is_array($unlocks) ? $unlocks : [];
}

/** Nivel de personaje legacy (character_level 1–50) — solo display heredado. */
function game_character_level_from_data(array $data): int
{
    if (isset($data['character_level'])) {
        return max(1, (int)$data['character_level']);
    }
    $n = game_get_character_nivel($data);
    $scale = [1 => 1, 2 => 10, 3 => 20, 4 => 30, 5 => 40, 6 => 50];
    return $scale[$n] ?? 1;
}

function game_grado_last_upgrade_at(array $data): ?string
{
    $ts = trim((string)($data['grado_last_upgrade_at'] ?? ''));
    return $ts !== '' ? $ts : null;
}

function game_grado_last_upgrade_rank(array $data): ?int
{
    if (!isset($data['grado_last_upgrade_rank'])) {
        return null;
    }
    $rank = (int)$data['grado_last_upgrade_rank'];
    return ($rank >= 2 && $rank <= 5) ? $rank : null;
}

function game_grado_cooldown_ok(?string $lastUpgradeAt, ?int $lastReachedRank = null): bool
{
    if ($lastUpgradeAt === null || $lastUpgradeAt === '') {
        return true;
    }
    $last = strtotime($lastUpgradeAt);
    if ($last === false) {
        return true;
    }
    $days = $lastReachedRank !== null
        ? game_grado_cooldown_days_for_rank($lastReachedRank)
        : 14;
    return (time() - $last) >= ($days * 86400);
}

function game_grado_cooldown_remaining_days(?string $lastUpgradeAt, ?int $lastReachedRank = null): int
{
    if ($lastUpgradeAt === null || $lastUpgradeAt === '') {
        return 0;
    }
    $last = strtotime($lastUpgradeAt);
    if ($last === false) {
        return 0;
    }
    $days = $lastReachedRank !== null
        ? game_grado_cooldown_days_for_rank($lastReachedRank)
        : 14;
    $required = $days * 86400;
    $elapsed = time() - $last;
    if ($elapsed >= $required) {
        return 0;
    }
    return (int)ceil(($required - $elapsed) / 86400);
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function game_grado_enrich_row(
    array $row,
    string $type,
    int $charNivel,
    ?string $lastGlobalUpgrade,
    ?int $lastUpgradeRank,
    int $ppAvailable
): array {
    $rank = max(0, (int)($row['rank'] ?? 0));
    $nextRank = $rank + 1;
    $row['competencia_type'] = $type;

    if ($rank <= 0) {
        $row['upgrade'] = [
            'available' => false,
            'reason' => 'Sin registrar',
            'max_rank' => 5,
        ];
        return $row;
    }

    if ($nextRank > 5) {
        $row['upgrade'] = [
            'available' => false,
            'reason' => 'Grado máximo (V)',
            'max_rank' => 5,
        ];
        return $row;
    }

    $reqNivel = game_grado_nivel_required($nextRank);
    $price = game_grado_upgrade_price($nextRank);
    $cooldownOk = game_grado_cooldown_ok($lastGlobalUpgrade, $lastUpgradeRank);
    $nivelOk = $charNivel >= $reqNivel;
    $ppOk = $ppAvailable >= $price;
    $cooldownDaysLeft = game_grado_cooldown_remaining_days($lastGlobalUpgrade, $lastUpgradeRank);
    $cooldownAfter = game_grado_cooldown_days_for_rank($nextRank);

    $reasons = [];
    if (!$nivelOk) {
        $reasons[] = 'Requiere nivel ' . $reqNivel;
    }
    if (!$ppOk) {
        $reasons[] = 'PP insuficientes (' . number_format($price, 0, ',', '.') . ' PP)';
    }
    if (!$cooldownOk) {
        $reasons[] = 'Cooldown: ' . $cooldownDaysLeft . ' día(s)';
    }

    $row['upgrade'] = [
        'available' => $nivelOk && $ppOk && $cooldownOk,
        'next_rank' => $nextRank,
        'next_rank_label' => game_grado_label($nextRank),
        'required_nivel' => $reqNivel,
        'price_pp' => $price,
        'cooldown_days' => $cooldownAfter,
        'cooldown_ok' => $cooldownOk,
        'cooldown_days_left' => $cooldownDaysLeft,
        'nivel_ok' => $nivelOk,
        'pp_ok' => $ppOk,
        'character_nivel' => $charNivel,
        'pp' => $ppAvailable,
        'requires_staff' => true,
        'reason' => $reasons !== [] ? implode(' · ', $reasons) : '',
        'max_rank' => 5,
    ];
    return $row;
}

/** Coste total en PP para subir de $oldRank a $newRank (solo incrementos). */
function game_grado_upgrade_total_price(int $oldRank, int $newRank): int
{
    $cost = 0;
    for ($r = $oldRank + 1; $r <= $newRank; $r++) {
        if ($r > 5) {
            break;
        }
        $cost += game_grado_upgrade_price($r);
    }
    return $cost;
}

/**
 * Al subir grado vía staff: valida nivel, PP y cooldown; descuenta PP y registra cooldown.
 * Devuelve mensaje de error o null si OK.
 */
function game_grado_staff_apply_rank_change(int $characterId, int $oldRank, int $newRank): ?string
{
    if ($newRank <= $oldRank || $newRank > 5) {
        return null;
    }

    global $db;
    $prefix = TABLE_PREFIX;
    $pj = $db->fetch_array($db->query(
        "SELECT data_json FROM {$prefix}game_personajes WHERE id = " . (int)$characterId . " LIMIT 1"
    ));
    if (!$pj) {
        return 'Personaje no encontrado.';
    }

    $data = !empty($pj['data_json']) ? json_decode($pj['data_json'], true) : [];
    if (!is_array($data)) {
        $data = [];
    }

    $charNivel = game_get_character_nivel($data);
    for ($r = $oldRank + 1; $r <= $newRank; $r++) {
        if ($charNivel < game_grado_nivel_required($r)) {
            return 'Nivel insuficiente para grado ' . game_grado_label($r)
                . ' (requiere nivel ' . game_grado_nivel_required($r) . ').';
        }
    }

    $cost = game_grado_upgrade_total_price($oldRank, $newRank);
    $pp = (int)($data['pp'] ?? 0);
    if ($pp < $cost) {
        return 'PP insuficientes (requiere ' . number_format($cost, 0, ',', '.') . ' PP).';
    }

    $lastUpgrade = game_grado_last_upgrade_at($data);
    $lastUpgradeRank = game_grado_last_upgrade_rank($data);
    if (!game_grado_cooldown_ok($lastUpgrade, $lastUpgradeRank)) {
        $left = game_grado_cooldown_remaining_days($lastUpgrade, $lastUpgradeRank);
        return 'Cooldown activo (' . $left . ' día(s) restantes).';
    }

    $data['pp'] = max(0, $pp - $cost);
    $data['grado_last_upgrade_at'] = date('Y-m-d H:i:s');
    $data['grado_last_upgrade_rank'] = $newRank;
    $dataJsonEsc = $db->escape_string(json_encode($data, JSON_UNESCAPED_UNICODE));
    $db->write_query("UPDATE {$prefix}game_personajes SET data_json = '{$dataJsonEsc}' WHERE id = " . (int)$characterId);

    return null;
}

/** Validación §8.1 / §10.5: disciplina/oficio requeridos para asignar carta al PJ. */
function game_card_assignment_competencia_error(int $characterId, array $card): ?string
{
    $tier = max(1, min(5, (int)($card['tier'] ?? 1)));
    $discSlug = trim((string)($card['disciplina_slug'] ?? ''));
    if ($discSlug !== '') {
        $rank = game_disciplina_get_rank($characterId, $discSlug);
        if ($rank < $tier) {
            return 'Requiere disciplina «' . $discSlug . '» grado '
                . game_grado_label($tier) . ' o superior (actual: '
                . ($rank > 0 ? game_grado_label($rank) : 'ninguno') . ').';
        }
    }

    $ofSlug = trim((string)($card['oficio_slug'] ?? ''));
    if ($ofSlug !== '') {
        if (game_oficio_get_rank($characterId, $ofSlug) < 1) {
            return 'Requiere oficio «' . $ofSlug . '».';
        }
    }

    return null;
}
