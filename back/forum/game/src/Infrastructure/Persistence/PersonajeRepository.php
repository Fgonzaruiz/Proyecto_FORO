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
        
        // Primero, intentar encontrar si pertenece directamente al usuario
        $q = $db->query("SELECT * FROM {$prefix}game_personajes WHERE id = {$characterId} AND user_id = {$userId} LIMIT 1");
        $row = $db->fetch_array($q);
        if ($row) {
            return $row;
        }

        // Si no pertenece al usuario pero es NPC, verificar si el usuario tiene un personaje con staff_level = 3
        $npc_q = $db->query("SELECT * FROM {$prefix}game_personajes WHERE id = {$characterId} AND is_npc = 1 LIMIT 1");
        $npc = $db->fetch_array($npc_q);
        if ($npc) {
            $staff_q = $db->query("SELECT COUNT(*) as cnt FROM {$prefix}game_personajes WHERE user_id = {$userId} AND staff_level = 3");
            $is_admin = (int)$db->fetch_field($staff_q, 'cnt') > 0;
            if ($is_admin) {
                return $npc;
            }

            // O si el usuario tiene un personaje narrador con este NPC asignado
            $narr_q = $db->query("SELECT COUNT(*) as cnt FROM {$prefix}game_personajes p
                INNER JOIN {$prefix}game_npc_assignments a ON p.id = a.narrator_id
                WHERE p.user_id = {$userId} AND p.is_narrator = 1 AND a.character_id = {$characterId}");
            $is_assigned = (int)$db->fetch_field($narr_q, 'cnt') > 0;
            if ($is_assigned) {
                return $npc;
            }
        }

        return null;
    }
}
