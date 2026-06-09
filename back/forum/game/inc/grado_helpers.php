<?php
declare(strict_types=1);

/**
 * Grados I–V compartidos por disciplinas y oficios.
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

/** Nivel de personaje requerido para alcanzar cada grado (I–V). */
function game_grado_nivel_required(int $targetRank): int
{
    $map = [1 => 1, 2 => 10, 3 => 20, 4 => 30, 5 => 50];
    return $map[max(1, min(5, $targetRank))] ?? 1;
}

/** Precio orientativo en Berries para subir al grado indicado. */
function game_grado_upgrade_price(int $targetRank): int
{
    $prices = [2 => 2500, 3 => 7500, 4 => 18000, 5 => 45000];
    return $prices[max(2, min(5, $targetRank))] ?? 0;
}

function game_grado_cooldown_days(): int
{
    return 14;
}

/** Nivel de personaje para reglas de grado (character_level o escala desde nivel global). */
function game_character_level_from_data(array $data): int
{
    if (isset($data['character_level'])) {
        return max(1, (int)$data['character_level']);
    }
    $n = max(1, min(6, (int)($data['nivel'] ?? 1)));
    $scale = [1 => 1, 2 => 10, 3 => 20, 4 => 30, 5 => 40, 6 => 50];
    return $scale[$n] ?? 1;
}

function game_grado_last_upgrade_at(array $data): ?string
{
    $ts = trim((string)($data['grado_last_upgrade_at'] ?? ''));
    return $ts !== '' ? $ts : null;
}

function game_grado_cooldown_ok(?string $lastUpgradeAt): bool
{
    if ($lastUpgradeAt === null || $lastUpgradeAt === '') {
        return true;
    }
    $last = strtotime($lastUpgradeAt);
    if ($last === false) {
        return true;
    }
    $days = game_grado_cooldown_days();
    return (time() - $last) >= ($days * 86400);
}

function game_grado_cooldown_remaining_days(?string $lastUpgradeAt): int
{
    if ($lastUpgradeAt === null || $lastUpgradeAt === '') {
        return 0;
    }
    $last = strtotime($lastUpgradeAt);
    if ($last === false) {
        return 0;
    }
    $elapsed = time() - $last;
    $required = game_grado_cooldown_days() * 86400;
    if ($elapsed >= $required) {
        return 0;
    }
    return (int)ceil(($required - $elapsed) / 86400);
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function game_grado_enrich_row(array $row, string $type, int $charLevel, ?string $lastGlobalUpgrade): array
{
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
    $cooldownOk = game_grado_cooldown_ok($lastGlobalUpgrade);
    $nivelOk = $charLevel >= $reqNivel;
    $cooldownDaysLeft = game_grado_cooldown_remaining_days($lastGlobalUpgrade);

    $reasons = [];
    if (!$nivelOk) {
        $reasons[] = 'Requiere nivel ' . $reqNivel;
    }
    if (!$cooldownOk) {
        $reasons[] = 'Cooldown: ' . $cooldownDaysLeft . ' día(s)';
    }

    $row['upgrade'] = [
        'available' => $nivelOk && $cooldownOk,
        'next_rank' => $nextRank,
        'next_rank_label' => game_grado_label($nextRank),
        'required_nivel' => $reqNivel,
        'price_berries' => $price,
        'cooldown_days' => game_grado_cooldown_days(),
        'cooldown_ok' => $cooldownOk,
        'cooldown_days_left' => $cooldownDaysLeft,
        'nivel_ok' => $nivelOk,
        'character_level' => $charLevel,
        'reason' => $reasons !== [] ? implode(' · ', $reasons) : '',
        'max_rank' => 5,
    ];
    return $row;
}
