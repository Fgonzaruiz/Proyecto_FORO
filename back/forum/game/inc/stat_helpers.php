<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/autoload.php';

use Game\Shared\StatScale;

/**
 * @param array<string, mixed> $statsRaw
 * @param array<string, int> $turnModifiers rangos +/- por stat en este turno
 * @return array{trained: array<string,int>, effective_ranks: array<string,int>, values: array<string,int>, display: array<string,string>}
 */
function game_build_stat_context(array $statsRaw, string $raceName, array $turnModifiers = []): array
{
    $trained = StatScale::sanitizeRanks($statsRaw);
    $racial = StatScale::getRacialBonuses($raceName);
    $effectiveRanks = [];
    $values = [];
    $display = [];

    foreach (StatScale::STAT_KEYS as $key) {
        $mod = (int)($turnModifiers[$key] ?? 0);
        $effRank = (int)$trained[$key] + (int)($racial[$key] ?? 0) + $mod;
        $effectiveRanks[$key] = $effRank;
        $values[$key] = StatScale::rangoEfectivoAValor((int)$trained[$key], (int)($racial[$key] ?? 0) + $mod);
        $display[$key] = StatScale::rankDisplayLabel($effRank);
    }

    return [
        'trained' => $trained,
        'effective_ranks' => $effectiveRanks,
        'values' => $values,
        'display' => $display,
    ];
}

function game_compute_pv_pe_from_context(array $values): array
{
    return [
        'max_pv' => StatScale::computeMaxPv($values),
        'max_pe' => StatScale::computeMaxPe($values),
    ];
}
