<?php
declare(strict_types=1);

namespace Game\Infrastructure\Persistence;

/**
 * Acceso a datos de personajes (MyBB $db).
 */
final class PersonajeRepository
{
    public function getActiveCharacterId(int $userId): int
    {
        global $db;
        if ($userId <= 0) {
            return 0;
        }
        $prefix = TABLE_PREFIX;
        $q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$userId} LIMIT 1");
        $row = $db->fetch_array($q);
        return $row ? (int)$row['active_pj_id'] : 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdForUser(int $characterId, int $userId): ?array
    {
        global $db;
        if ($characterId <= 0 || $userId <= 0) {
            return null;
        }
        $prefix = TABLE_PREFIX;
        $q = $db->query("SELECT * FROM {$prefix}game_personajes WHERE id = {$characterId} AND user_id = {$userId} LIMIT 1");
        $row = $db->fetch_array($q);
        return $row ?: null;
    }
}
