<?php
declare(strict_types=1);

namespace Game\Shared;

/**
 * Escala de atributos v7: 7 stats, rangos 1-6, bonos raciales en runtime.
 */
final class StatScale
{
    /** Palabras de rol necesarias para ganar 1 PP (posts no Off_Rol). */
    public const WORDS_PER_PP = 100;

    public const STAT_KEYS = [
        'fuerza',
        'intelecto',
        'caudal',
        'destreza',
        'ingenio',
        'control',
        'vigor',
        'concentracion',
        'voluntad',
        'agilidad',
        'percepcion',
        'sensibilidad'
    ];

    /** @var array<int, string> */
    public const RANK_NAMES = [
        1 => 'D',
        2 => 'C',
        3 => 'B',
        4 => 'A',
        5 => 'S',
        6 => 'SS',
    ];

    /** @var array<int, int> PP acumulados hasta alcanzar cada rango (costes base, sin multiplicador RG) */
    public const RANK_CUMULATIVE_PP = [
        1 => 0,
        2 => 50,
        3 => 180,
        4 => 530,
        5 => 1330,
        6 => 3130,
    ];

    /** @var array<int, int> Coste base para subir del rango N al N+1 */
    public const RANK_UPGRADE_COST = [
        1 => 50,
        2 => 130,
        3 => 350,
        4 => 800,
        5 => 1800,
    ];

    /** @var array<string, float> Multiplicador de coste según Rango Global (M1.3) */
    public const RANK_GLOBAL_MULTIPLIERS = [
        'D' => 1.00,
        'C' => 1.07,
        'B' => 1.15,
        'A' => 1.35,
        'S' => 1.60,
        'SS' => 2.00,
    ];

    /** @var array<string, float> Multiplicador de PV/PE según Rango Global (M2.1) */
    public const PV_PE_MULTIPLIERS = [
        'D' => 1.00,
        'C' => 1.05,
        'B' => 1.10,
        'A' => 1.20,
        'S' => 1.35,
        'SS' => 1.50,
    ];

    private static ?array $catalogCache = null;

    public static function rangoAValor(int $rango): int
    {
        return match ($rango) {
            1 => 4,
            2 => 8,
            3 => 15,
            4 => 26,
            5 => 40,
            6 => 60,
            default => 4,
        };
    }

    public static function rangoEfectivoAValor(int $rangoEntrenado, int $bonoRacial): int
    {
        $efectivo = $rangoEntrenado + $bonoRacial;
        if ($efectivo <= 0) {
            return 0;
        }
        if ($efectivo <= 6) {
            return self::rangoAValor($efectivo);
        }
        return self::rangoAValor(6) + (($efectivo - 6) * 20);
    }

    public static function rankDisplayLabel(int $rangoEfectivo): string
    {
        if ($rangoEfectivo <= 0) {
            return '—';
        }
        if ($rangoEfectivo <= 6) {
            return self::RANK_NAMES[$rangoEfectivo] ?? 'D';
        }
        if ($rangoEfectivo === 7) {
            return 'SS+';
        }
        if ($rangoEfectivo === 8) {
            return 'SS++';
        }
        return 'M';
    }

    public static function rankDisplayCssClass(int $rangoEfectivo): string
    {
        if ($rangoEfectivo <= 0) {
            return 'rpg-stat-rank--none';
        }
        if ($rangoEfectivo <= 6) {
            $slug = strtolower(self::RANK_NAMES[$rangoEfectivo] ?? 'd');
            return 'rpg-stat-rank--' . $slug;
        }
        if ($rangoEfectivo === 7) {
            return 'rpg-stat-rank--ss-plus';
        }
        if ($rangoEfectivo === 8) {
            return 'rpg-stat-rank--ss-plus-plus';
        }
        return 'rpg-stat-rank--ss-beyond';
    }

    public static function globalRankFromSum(int $sumaRangos): string
    {
        if ($sumaRangos <= 17) {
            return 'D';
        }
        if ($sumaRangos <= 27) {
            return 'C';
        }
        if ($sumaRangos <= 37) {
            return 'B';
        }
        if ($sumaRangos <= 47) {
            return 'A';
        }
        if ($sumaRangos <= 61) {
            return 'S';
        }
        return 'SS';
    }

    public static function globalNivelFromRank(string $rank): int
    {
        return match (strtoupper($rank)) {
            'D' => 1,
            'C' => 2,
            'B' => 3,
            'A' => 4,
            'S' => 5,
            'SS' => 6,
            default => 1,
        };
    }

    public static function globalRankCssClass(string $rank): string
    {
        $slug = strtolower(preg_replace('/[^a-z0-9+]/i', '', $rank) ?: 'd');
        return 'pj-global-rank-badge--' . $slug;
    }

    public static function getStatUpgradeCost(int $rangoActual, string $rangoGlobal = 'D'): int
    {
        if ($rangoActual < 1 || $rangoActual >= 6) {
            return PHP_INT_MAX;
        }
        $base = self::RANK_UPGRADE_COST[$rangoActual] ?? PHP_INT_MAX;
        $mult = self::RANK_GLOBAL_MULTIPLIERS[$rangoGlobal] ?? 1.0;
        return (int) round($base * $mult);
    }

    public static function getMultiplicadorPvPe(string $rangoGlobal): float
    {
        return self::PV_PE_MULTIPLIERS[$rangoGlobal] ?? 1.00;
    }

    /** @return array<string, int> */
    public static function defaultRanks(): array
    {
        $out = [];
        foreach (self::STAT_KEYS as $key) {
            $out[$key] = 1;
        }
        return $out;
    }

    /** @param array<string, mixed> $raw */
    public static function sanitizeRanks(array $raw): array
    {
        $clamp = static fn($v): int => max(1, min(6, (int)$v));
        $out = [];
        foreach (self::STAT_KEYS as $key) {
            $out[$key] = $clamp($raw[$key] ?? 1);
        }
        return $out;
    }

    /** @return array<string, int> */
    public static function getRacialBonuses(string $raceName): array
    {
        $catalog = self::loadCatalog();
        $race = $catalog['races'][$raceName] ?? null;
        $bonuses = is_array($race) ? ($race['stat_bonuses'] ?? []) : [];
        $out = [];
        foreach (self::STAT_KEYS as $key) {
            $out[$key] = (int)($bonuses[$key] ?? 0);
        }
        return $out;
    }

    /**
     * @param array<string, int> $ranks
     * @return array<string, int>
     */
    public static function effectiveValues(array $ranks, string $raceName): array
    {
        $bonuses = self::getRacialBonuses($raceName);
        $out = [];
        foreach (self::STAT_KEYS as $key) {
            $trained = (int)($ranks[$key] ?? 1);
            $out[$key] = self::rangoEfectivoAValor($trained, (int)($bonuses[$key] ?? 0));
        }
        return $out;
    }

    /**
     * @param array<string, int> $ranks
     * @return array<string, int>
     */
    public static function effectiveRanks(array $ranks, string $raceName): array
    {
        $bonuses = self::getRacialBonuses($raceName);
        $out = [];
        foreach (self::STAT_KEYS as $key) {
            $out[$key] = (int)($ranks[$key] ?? 1) + (int)($bonuses[$key] ?? 0);
        }
        return $out;
    }

    /**
     * @param array<string, int> $values
     */
    public static function computeMaxPv(array $values): int
    {
        return (($values['vigor'] ?? 4) * 4) + (($values['fuerza'] ?? 4) * 3) + (($values['agilidad'] ?? 4) * 2) + (($values['destreza'] ?? 4) * 1);
    }

    /**
     * @param array<string, int> $values
     */
    public static function computeMaxPe(array $values): int
    {
        return (($values['caudal'] ?? 4) * 4) + (($values['control'] ?? 4) * 3) + (($values['concentracion'] ?? 4) * 2) + (($values['voluntad'] ?? 4) * 1);
    }

    /** @param array<string, int> $ranks */
    public static function sumRanks(array $ranks): int
    {
        $sum = 0;
        foreach (self::STAT_KEYS as $key) {
            $sum += (int)($ranks[$key] ?? 1);
        }
        return $sum;
    }

    /** @param array<string, int> $ranks */
    public static function ppSpentOnRanks(array $ranks): int
    {
        $total = 0;
        foreach (self::STAT_KEYS as $key) {
            $r = max(1, min(6, (int)($ranks[$key] ?? 1)));
            $total += self::RANK_CUMULATIVE_PP[$r] ?? 0;
        }
        return $total;
    }

    private static function loadCatalog(): array
    {
        if (self::$catalogCache !== null) {
            return self::$catalogCache;
        }
        $path = dirname(__DIR__, 2) . '/data/linaje_catalog.json';
        if (!is_file($path)) {
            self::$catalogCache = ['races' => []];
            return self::$catalogCache;
        }
        $decoded = json_decode((string)file_get_contents($path), true);
        self::$catalogCache = is_array($decoded) ? $decoded : ['races' => []];
        return self::$catalogCache;
    }
}
