<?php
declare(strict_types=1);

namespace Game\Shared;

/**
 * Escala de atributos v7: 7 stats, rangos 1-6, bonos raciales en runtime.
 */
final class StatScale
{
    public const STAT_KEYS = ['fue', 'res', 'agi', 'des', 'int', 'inst', 'esp'];

    /** @var array<int, string> */
    public const RANK_NAMES = [
        1 => 'D',
        2 => 'C',
        3 => 'B',
        4 => 'A',
        5 => 'S',
        6 => 'SS',
    ];

    /** @var array<int, int> PP acumulados hasta alcanzar cada rango */
    public const RANK_CUMULATIVE_PP = [
        1 => 0,
        2 => 60,
        3 => 240,
        4 => 720,
        5 => 1820,
        6 => 4320,
    ];

    /** @var array<int, int> Coste para subir del rango N al N+1 */
    public const RANK_UPGRADE_COST = [
        1 => 60,
        2 => 180,
        3 => 480,
        4 => 1100,
        5 => 2500,
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
        $extra = $rangoEfectivo - 6;
        $plus = str_repeat('+', min($extra, 3));
        return 'SS' . $plus;
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
        if ($sumaRangos <= 10) {
            return 'D';
        }
        if ($sumaRangos <= 16) {
            return 'C';
        }
        if ($sumaRangos <= 22) {
            return 'B';
        }
        if ($sumaRangos <= 28) {
            return 'A';
        }
        if ($sumaRangos <= 36) {
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

    public static function getStatUpgradeCost(int $rangoActual): int
    {
        if ($rangoActual < 1 || $rangoActual >= 6) {
            return PHP_INT_MAX;
        }
        return self::RANK_UPGRADE_COST[$rangoActual] ?? PHP_INT_MAX;
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
        return [
            'fue' => $clamp($raw['fue'] ?? $raw['str'] ?? 1),
            'res' => $clamp($raw['res'] ?? 1),
            'agi' => $clamp($raw['agi'] ?? 1),
            'des' => $clamp($raw['des'] ?? 1),
            'int' => $clamp($raw['int'] ?? 1),
            'inst' => $clamp($raw['inst'] ?? $raw['vol'] ?? 1),
            'esp' => $clamp($raw['esp'] ?? $raw['vol'] ?? 1),
        ];
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
        return ($values['res'] * 4) + ($values['fue'] * 3) + ($values['esp'] * 2) + ($values['agi'] * 1);
    }

    /**
     * @param array<string, int> $values
     */
    public static function computeMaxPe(array $values): int
    {
        return ($values['esp'] * 4) + ($values['des'] * 3) + ($values['int'] * 2) + ($values['agi'] * 1);
    }

    /** Convierte valor legacy 1-20 a rango 1-6 */
    public static function legacyValueToRank(int $value): int
    {
        $value = max(1, min(20, $value));
        return match (true) {
            $value <= 3 => 1,
            $value <= 6 => 2,
            $value <= 10 => 3,
            $value <= 14 => 4,
            $value <= 18 => 5,
            default => 6,
        };
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

    /** ESP efectivo mínimo (rango) para tipo/nivel de Haki */
    public static function minEspRankForHaki(string $hakiType, string $hakiLevel): int
    {
        $type = strtolower($hakiType);
        $level = strtolower($hakiLevel);
        if ($type === 'busshoku') {
            $type = 'busoshoku';
        }
        if ($type === 'kenboshuko') {
            $type = 'kenbunshoku';
        }

        $map = [
            'kenbunshoku' => ['basico' => 2, 'avanzado' => 4],
            'busoshoku' => ['basico' => 3, 'interno' => 4, 'supremo' => 5, 'fusion' => 6],
            'haoshoku' => ['pasivo' => 5, 'ofensivo' => 6],
        ];

        return (int)($map[$type][$level] ?? 99);
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
