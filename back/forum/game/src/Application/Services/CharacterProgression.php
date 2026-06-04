<?php
declare(strict_types=1);

namespace Game\Application\Services;

/**
 * PP, nivel y coste de atributos (gestión de personaje).
 *
 * - Cada 20 puntos de atributo comprados otorgan 1 nivel (máx. 1 aplicación por semana).
 * - Si ya subiste de nivel esta semana, no puedes comprar más stats de los que te
 *   dejen a 1 punto del siguiente umbral (p. ej. máx. 19 si el siguiente nivel es a 20).
 * - Cada 3 niveles el coste por punto de atributo sube +1 PP (base 5).
 */
final class CharacterProgression
{
    public const STAT_POINTS_PER_LEVEL = 10;
    public const BASE_STAT_COST = 3;
    public const WEEK_SECONDS = 604800;

    public static function normalize(array &$data): void
    {
        $bonusLinaje = (int)($data['linaje']['bonusPP'] ?? 0);

        if (!isset($data['pp']) && $bonusLinaje > 0) {
            $data['pp'] = $bonusLinaje;
        }

        $data['nivel'] = max(1, (int)($data['nivel'] ?? 1));
        $data['stat_points_purchased'] = max(0, (int)($data['stat_points_purchased'] ?? 0));

        // Migración: personajes con nivel ya aplicado
        $minPurchased = max(0, ((int)$data['nivel'] - 1) * self::STAT_POINTS_PER_LEVEL);
        if ($data['stat_points_purchased'] < $minPurchased) {
            $data['stat_points_purchased'] = $minPurchased;
        }

        $pp = max(0, (int)($data['pp'] ?? 0));
        $ppLinaje = isset($data['pp_linaje']) ? (int)$data['pp_linaje'] : null;

        if ($ppLinaje === null && $bonusLinaje > 0) {
            $ppLinaje = min($pp, $bonusLinaje);
        } elseif ($ppLinaje === null) {
            $ppLinaje = 0;
        }

        $data['pp'] = $pp;
        $data['pp_linaje'] = min(max(0, $ppLinaje), $pp);
    }

    public static function getStatCost(int $nivel): int
    {
        $nivel = max(1, $nivel);
        return self::BASE_STAT_COST + intdiv($nivel - 1, 5);
    }

    public static function getTargetNivel(int $statPointsPurchased): int
    {
        return 1 + intdiv(max(0, $statPointsPurchased), self::STAT_POINTS_PER_LEVEL);
    }

    /** Puntos de atributo totales necesarios para alcanzar el siguiente nivel aplicado. */
    public static function getNextLevelStatThreshold(int $currentNivel): int
    {
        return $currentNivel * self::STAT_POINTS_PER_LEVEL;
    }

    public static function getPendingLevels(array $data): int
    {
        self::normalize($data);
        return max(0, self::getTargetNivel((int)$data['stat_points_purchased']) - (int)$data['nivel']);
    }

    public static function getProgressInCurrentTier(int $statPointsPurchased): int
    {
        return $statPointsPurchased % self::STAT_POINTS_PER_LEVEL;
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
     * Cuántos puntos de atributo se pueden comprar sin forzar un subida de nivel bloqueada por la semana.
     */
    public static function getMaxStatPointsBuyable(array $data): int
    {
        self::normalize($data);
        $purchased = (int)$data['stat_points_purchased'];
        $nivel = (int)$data['nivel'];

        if (self::canLevelUpThisWeek($data)) {
            return PHP_INT_MAX;
        }

        $maxTotalBeforeNextLevel = self::getNextLevelStatThreshold($nivel) - 1;
        return max(0, $maxTotalBeforeNextLevel - $purchased);
    }

    /**
     * @return string|null Mensaje de error si la compra no está permitida
     */
    public static function validateStatPointPurchase(array $data, int $amount): ?string
    {
        self::normalize($data);
        if ($amount <= 0) {
            return 'Cantidad inválida.';
        }

        $maxBuyable = self::getMaxStatPointsBuyable($data);
        if ($amount > $maxBuyable) {
            $purchased = (int)$data['stat_points_purchased'];
            $nivel = (int)$data['nivel'];
            $nextAt = self::getNextLevelStatThreshold($nivel);
            $allowed = $maxBuyable;
            $nextWeek = self::getNextLevelAvailableAt($data);
            $when = $nextWeek ? date('d/m/Y H:i', $nextWeek) : 'la próxima semana';

            if ($allowed <= 0) {
                return "Ya alcanzaste el tope de puntos de atributo esta semana ({$purchased}/{$nextAt} hacia el nivel " . ($nivel + 1) . "). Podrás comprar más el {$when}.";
            }

            return "Solo puedes comprar {$allowed} punto(s) de atributo más esta semana (máx. " . ($nextAt - 1) . " antes del nivel " . ($nivel + 1) . "). Comprar {$amount} te haría subir de nivel antes del {$when}.";
        }

        return null;
    }

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
     * @return array{from_linaje:int,new_pp:int,new_pp_linaje:int}
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
     * @return array{
     *   nivel:int,
     *   stat_cost:int,
     *   stat_points_purchased:int,
     *   pending_levels:int,
     *   levels_applied:int,
     *   new_pp:int,
     *   new_pp_linaje:int
     * }
     */
    public static function recordStatPurchase(array &$data, int $cost, int $statPointsAmount): array
    {
        self::normalize($data);

        $pp = (int)$data['pp'];
        $ppLinaje = (int)$data['pp_linaje'];

        $alloc = self::allocatePpSpend($cost, $pp, $ppLinaje);
        $data['pp'] = $alloc['new_pp'];
        $data['pp_linaje'] = $alloc['new_pp_linaje'];
        $data['stat_points_purchased'] = (int)$data['stat_points_purchased'] + $statPointsAmount;

        $levelsApplied = self::tryApplyPendingLevels($data);

        return [
            'nivel' => (int)$data['nivel'],
            'stat_cost' => self::getStatCost((int)$data['nivel']),
            'stat_points_purchased' => (int)$data['stat_points_purchased'],
            'pending_levels' => self::getPendingLevels($data),
            'levels_applied' => $levelsApplied,
            'new_pp' => (int)$data['pp'],
            'new_pp_linaje' => (int)$data['pp_linaje'],
        ];
    }

    public static function snapshot(array $data): array
    {
        self::normalize($data);
        $purchased = (int)$data['stat_points_purchased'];
        $nivel = (int)$data['nivel'];
        $nextAt = self::getNextLevelAvailableAt($data);
        $maxBuyable = self::getMaxStatPointsBuyable($data);
        $nextThreshold = self::getNextLevelStatThreshold($nivel);

        return [
            'nivel' => $nivel,
            'pp' => (int)$data['pp'],
            'pp_linaje' => (int)$data['pp_linaje'],
            'stat_points_purchased' => $purchased,
            'stat_cost' => self::getStatCost($nivel),
            'progress_in_tier' => self::getProgressInCurrentTier($purchased),
            'stat_points_per_level' => self::STAT_POINTS_PER_LEVEL,
            'next_level_stat_threshold' => $nextThreshold,
            'max_stat_points_buyable' => $maxBuyable === PHP_INT_MAX ? null : $maxBuyable,
            'pending_levels' => self::getPendingLevels($data),
            'can_level_up_this_week' => self::canLevelUpThisWeek($data),
            'next_level_available_at' => $nextAt,
            'next_level_available_iso' => $nextAt ? date('c', $nextAt) : null,
        ];
    }
}
