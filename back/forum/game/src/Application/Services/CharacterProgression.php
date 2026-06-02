<?php
declare(strict_types=1);

namespace Game\Application\Services;

/**
 * PP, nivel y coste de atributos (gestión de personaje).
 *
 * - Solo el PP gastado en stats que NO proviene del sobrante de linaje cuenta para nivel.
 * - Cada 50 PP elegibles gastados en stats otorgan 1 nivel (máx. 1 aplicación por semana).
 * - Cada 3 niveles el coste por punto de atributo sube +1 PP (base 5).
 */
final class CharacterProgression
{
    public const PP_PER_LEVEL = 50;
    public const BASE_STAT_COST = 5;
    public const WEEK_SECONDS = 604800;

    public static function normalize(array &$data): void
    {
        $bonusLinaje = (int)($data['linaje']['bonusPP'] ?? 0);

        if (!isset($data['pp']) && $bonusLinaje > 0) {
            $data['pp'] = $bonusLinaje;
        }

        $data['nivel'] = max(1, (int)($data['nivel'] ?? 1));
        $data['pp_spent_eligible'] = max(0, (int)($data['pp_spent_eligible'] ?? 0));

        $pp = max(0, (int)($data['pp'] ?? 0));
        $ppLinaje = (int)($data['pp_linaje'] ?? 0);

        if ($ppLinaje === 0 && $bonusLinaje > 0) {
            $ppLinaje = min($pp, $bonusLinaje);
        }

        $data['pp'] = $pp;
        $data['pp_linaje'] = min(max(0, $ppLinaje), $pp);

        if (isset($data['linaje']) && is_array($data['linaje'])) {
            $data['linaje']['bonusPP'] = $data['pp'];
        }
    }

    public static function getStatCost(int $nivel): int
    {
        $nivel = max(1, $nivel);
        return self::BASE_STAT_COST + intdiv($nivel - 1, 3);
    }

    public static function getTargetNivel(int $ppSpentEligible): int
    {
        return 1 + intdiv(max(0, $ppSpentEligible), self::PP_PER_LEVEL);
    }

    public static function getPendingLevels(array $data): int
    {
        self::normalize($data);
        return max(0, self::getTargetNivel((int)$data['pp_spent_eligible']) - (int)$data['nivel']);
    }

    public static function getProgressInCurrentTier(int $ppSpentEligible): int
    {
        return $ppSpentEligible % self::PP_PER_LEVEL;
    }

    public static function canLevelUpThisWeek(array $data): bool
    {
        $last = $data['last_level_up_at'] ?? null;
        if ($last === null || $last === '') {
            return true;
        }
        $ts = is_numeric($last) ? (int)$last : strtotime((string)$last);
        if ($ts === false || $ts <= 0) {
            return true;
        }
        return (time() - $ts) >= self::WEEK_SECONDS;
    }

    public static function getNextLevelAvailableAt(array $data): ?int
    {
        if (self::canLevelUpThisWeek($data)) {
            return null;
        }
        $last = $data['last_level_up_at'] ?? null;
        $ts = is_numeric($last) ? (int)$last : strtotime((string)$last);
        if ($ts === false || $ts <= 0) {
            return null;
        }
        return $ts + self::WEEK_SECONDS;
    }

    /**
     * Aplica como máximo una subida de nivel si hay pendientes y pasó la semana.
     */
    public static function tryApplyPendingLevels(array &$data): int
    {
        self::normalize($data);
        if (self::getPendingLevels($data) < 1) {
            return 0;
        }
        if (!self::canLevelUpThisWeek($data)) {
            return 0;
        }
        $data['nivel'] = (int)$data['nivel'] + 1;
        $data['last_level_up_at'] = date('Y-m-d H:i:s');
        return 1;
    }

    /**
     * Reparte el gasto: primero PP de linaje (no cuenta para nivel), luego el resto.
     *
     * @return array{from_linaje:int,from_eligible:int,new_pp:int,new_pp_linaje:int}
     */
    public static function allocateSpend(int $cost, int $pp, int $ppLinaje): array
    {
        $fromLinaje = min($cost, max(0, $ppLinaje));
        $fromEligible = $cost - $fromLinaje;
        return [
            'from_linaje' => $fromLinaje,
            'from_eligible' => $fromEligible,
            'new_pp' => $pp - $cost,
            'new_pp_linaje' => $ppLinaje - $fromLinaje,
        ];
    }

    /**
     * @return array{
     *   nivel:int,
     *   stat_cost:int,
     *   pp_spent_eligible:int,
     *   pending_levels:int,
     *   levels_applied:int,
     *   from_eligible:int,
     *   new_pp:int,
     *   new_pp_linaje:int
     * }
     */
    public static function recordStatSpend(array &$data, int $cost): array
    {
        self::normalize($data);

        $pp = (int)$data['pp'];
        $ppLinaje = (int)$data['pp_linaje'];

        $alloc = self::allocateSpend($cost, $pp, $ppLinaje);
        $data['pp'] = $alloc['new_pp'];
        $data['pp_linaje'] = $alloc['new_pp_linaje'];
        $data['pp_spent_eligible'] = (int)$data['pp_spent_eligible'] + $alloc['from_eligible'];

        if (isset($data['linaje']) && is_array($data['linaje'])) {
            $data['linaje']['bonusPP'] = $data['pp'];
        }

        $levelsApplied = self::tryApplyPendingLevels($data);

        return [
            'nivel' => (int)$data['nivel'],
            'stat_cost' => self::getStatCost((int)$data['nivel']),
            'pp_spent_eligible' => (int)$data['pp_spent_eligible'],
            'pending_levels' => self::getPendingLevels($data),
            'levels_applied' => $levelsApplied,
            'from_eligible' => $alloc['from_eligible'],
            'new_pp' => (int)$data['pp'],
            'new_pp_linaje' => (int)$data['pp_linaje'],
        ];
    }

    public static function snapshot(array $data): array
    {
        self::normalize($data);
        $ppSpent = (int)$data['pp_spent_eligible'];
        $nivel = (int)$data['nivel'];
        $nextAt = self::getNextLevelAvailableAt($data);

        return [
            'nivel' => $nivel,
            'pp' => (int)$data['pp'],
            'pp_linaje' => (int)$data['pp_linaje'],
            'pp_spent_eligible' => $ppSpent,
            'stat_cost' => self::getStatCost($nivel),
            'progress_in_tier' => self::getProgressInCurrentTier($ppSpent),
            'pp_per_level' => self::PP_PER_LEVEL,
            'pending_levels' => self::getPendingLevels($data),
            'can_level_up_this_week' => self::canLevelUpThisWeek($data),
            'next_level_available_at' => $nextAt,
            'next_level_available_iso' => $nextAt ? date('c', $nextAt) : null,
        ];
    }
}
