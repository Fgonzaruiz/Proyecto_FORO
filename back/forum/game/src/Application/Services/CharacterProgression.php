<?php
declare(strict_types=1);

namespace Game\Application\Services;

use Game\Shared\StatScale;

/**
 * PP, rangos de atributos y rango global del personaje (v7).
 */
final class CharacterProgression
{
    /**
     * Recalcula bonus de linaje en servidor y asigna PP si aún no se otorgaron.
     */
    public static function syncLinajeBonusPp(array &$data, string $raceName): void
    {
        $linaje = is_array($data['linaje'] ?? null) ? $data['linaje'] : [];
        if ((int)($linaje['version'] ?? 0) < 2 || trim($raceName) === '') {
            return;
        }

        $built = (new LinajeValidator())->validateAndBuild($raceName, $linaje);
        if (!($built['ok'] ?? false)) {
            return;
        }

        $data['linaje'] = $built['linaje'];
        $bonus = (int)($built['linaje']['bonusPP'] ?? 0);
        if ($bonus <= 0) {
            return;
        }

        $pp = (int)($data['pp'] ?? 0);
        $ppLinaje = (int)($data['pp_linaje'] ?? 0);

        if ($pp < $bonus) {
            $data['pp'] = $bonus;
            $data['pp_linaje'] = $bonus;
            return;
        }

        if ($ppLinaje === 0 && $pp > 0) {
            $data['pp_linaje'] = min($pp, $bonus);
        }
    }

    public static function normalize(array &$data): void
    {
        $bonusLinaje = (int)($data['linaje']['bonusPP'] ?? 0);

        if (!isset($data['pp']) && $bonusLinaje > 0) {
            $data['pp'] = $bonusLinaje;
        }

        $data['pp'] = max(0, (int)($data['pp'] ?? 0));
        $ppLinaje = isset($data['pp_linaje']) ? (int)$data['pp_linaje'] : null;

        if ($ppLinaje === null && $bonusLinaje > 0) {
            $ppLinaje = min((int)$data['pp'], $bonusLinaje);
        } elseif ($ppLinaje === null) {
            $ppLinaje = 0;
        }

        $data['pp_linaje'] = min(max(0, $ppLinaje), (int)$data['pp']);

        $rank = trim((string)($data['rank'] ?? ''));
        if ($rank === '') {
            $data['rank'] = 'D';
        }
        $data['nivel'] = StatScale::globalNivelFromRank((string)$data['rank']);
    }

    public static function recalculateGlobalRank(array $stats, array &$data): string
    {
        $sum = StatScale::sumRanks($stats);
        $rank = StatScale::globalRankFromSum($sum);
        $data['rank'] = $rank;
        $data['nivel'] = StatScale::globalNivelFromRank($rank);
        $data['last_rank_change_at'] = date('Y-m-d H:i:s');
        return $rank;
    }

    public static function getStatUpgradeCost(int $rangoActual, string $rangoGlobal = 'D'): int
    {
        return StatScale::getStatUpgradeCost($rangoActual, $rangoGlobal);
    }

    /**
     * @param array<string, int> $stats
     * @return array{ok:bool, error?:string, coste?:int}
     */
    public static function validateStatUpgrade(array $data, array $stats, string $stat): array
    {
        self::normalize($data);
        if (!in_array($stat, StatScale::STAT_KEYS, true)) {
            return ['ok' => false, 'error' => 'Atributo inválido.'];
        }

        $rangoActual = (int)($stats[$stat] ?? 1);
        if ($rangoActual >= 6) {
            return ['ok' => false, 'error' => 'Este atributo ya está en rango máximo (SS).'];
        }

        $rangoGlobal = StatScale::globalRankFromSum(StatScale::sumRanks($stats));
        $coste = self::getStatUpgradeCost($rangoActual, $rangoGlobal);
        $ppDisponibles = (int)($data['pp'] ?? 0);
        if ($ppDisponibles < $coste) {
            return ['ok' => false, 'error' => "Necesitas {$coste} PP. Tienes {$ppDisponibles}."];
        }

        return ['ok' => true, 'coste' => $coste];
    }

    /**
     * @param array<string, int> $stats
     * @return array{from_linaje:int, new_pp:int, new_pp_linaje:int}
     */
    public static function allocatePpSpend(int $cost, int $pp, int $ppLinaje): array
    {
        $fromLinaje = min($cost, max(0, $ppLinaje));
        return [
            'from_linaje' => $fromLinaje,
            'new_pp' => $pp - $cost,
            'new_pp_linaje' => $ppLinaje - $fromLinaje,
        ];
    }

    /**
     * @param array<string, int> $stats
     * @return array{new_pp:int, new_pp_linaje:int, new_rank:string, upgrade_cost:int}
     */
    public static function applyStatUpgrade(array &$data, array &$stats, string $stat): array
    {
        self::normalize($data);
        $validation = self::validateStatUpgrade($data, $stats, $stat);
        if (!($validation['ok'] ?? false)) {
            throw new \InvalidArgumentException($validation['error'] ?? 'Compra no permitida.');
        }

        $coste = (int)$validation['coste'];
        $alloc = self::allocatePpSpend($coste, (int)$data['pp'], (int)$data['pp_linaje']);
        $data['pp'] = $alloc['new_pp'];
        $data['pp_linaje'] = $alloc['new_pp_linaje'];
        $stats[$stat] = (int)($stats[$stat] ?? 1) + 1;

        $newRank = self::recalculateGlobalRank($stats, $data);

        return [
            'new_pp' => (int)$data['pp'],
            'new_pp_linaje' => (int)$data['pp_linaje'],
            'new_rank' => $newRank,
            'upgrade_cost' => $coste,
        ];
    }

    public static function snapshot(array $data, array $stats = []): array
    {
        self::normalize($data);
        $rangoGlobal = StatScale::globalRankFromSum(StatScale::sumRanks($stats));
        $nextCosts = [];
        foreach (StatScale::STAT_KEYS as $key) {
            $r = (int)($stats[$key] ?? 1);
            $nextCosts[$key] = $r >= 6 ? null : self::getStatUpgradeCost($r, $rangoGlobal);
        }

        return [
            'nivel' => (int)($data['nivel'] ?? 1),
            'rank' => (string)($data['rank'] ?? 'D'),
            'pp' => (int)($data['pp'] ?? 0),
            'pp_linaje' => (int)($data['pp_linaje'] ?? 0),
            'sum_ranks' => StatScale::sumRanks($stats),
            'next_upgrade_costs' => $nextCosts,
        ];
    }
}
