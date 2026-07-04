<?php
declare(strict_types=1);

namespace Game\Infrastructure\Persistence;

/**
 * Acceso a datos de Nen (MyBB $db).
 */
final class NenRepository
{
    /**
     * Obtener el registro principal Nen del personaje.
     */
    public function getNen(int $pjId): ?array
    {
        global $db;
        if ($pjId <= 0) {
            return null;
        }
        $prefix = TABLE_PREFIX;
        $q = $db->query("SELECT * FROM {$prefix}game_nen WHERE character_id = {$pjId} LIMIT 1");
        $row = $db->fetch_array($q);
        return $row ?: null;
    }

    /**
     * Obtener el nivel de todos los principios Nen del personaje.
     */
    public function getPrinciples(int $pjId): array
    {
        global $db;
        if ($pjId <= 0) {
            return [];
        }
        $prefix = TABLE_PREFIX;
        $q = $db->query("SELECT * FROM {$prefix}game_nen_progress WHERE character_id = {$pjId}");
        $principles = [];
        while ($row = $db->fetch_array($q)) {
            $principles[$row['principle']] = [
                'level' => (int)$row['level'],
                'experience' => (int)$row['experience'],
                'unlocked_at' => $row['unlocked_at'] ? (int)$row['unlocked_at'] : null
            ];
        }
        return $principles;
    }

    /**
     * Obtener todas las habilidades (Hatsu) propuestas/aprobadas del personaje.
     */
    public function getAbilities(int $pjId, bool $approvedOnly = false): array
    {
        global $db;
        if ($pjId <= 0) {
            return [];
        }
        $prefix = TABLE_PREFIX;
        $whereApproved = $approvedOnly ? " AND approved = 1" : "";
        $q = $db->query("SELECT * FROM {$prefix}game_nen_abilities WHERE character_id = {$pjId}{$whereApproved} ORDER BY id ASC");
        $abilities = [];
        while ($row = $db->fetch_array($q)) {
            $abilities[] = $row;
        }
        return $abilities;
    }

    /**
     * Guardar o actualizar el registro principal de Nen.
     */
    public function saveNen(int $pjId, ?string $type, int $locked, ?string $color = null, ?string $vows = null, ?string $notes = null): void
    {
        global $db;
        if ($pjId <= 0) {
            return;
        }
        $prefix = TABLE_PREFIX;
        $typeEscaped = $type !== null ? "'" . $db->escape_string($type) . "'" : "NULL";
        $colorEscaped = $color !== null ? "'" . $db->escape_string($color) . "'" : "NULL";
        $vowsEscaped = $vows !== null ? "'" . $db->escape_string($vows) . "'" : "NULL";
        $notesEscaped = $notes !== null ? "'" . $db->escape_string($notes) . "'" : "NULL";
        $now = time();

        $db->write_query("
            INSERT INTO {$prefix}game_nen (character_id, nen_type, nen_type_locked, aura_color, vows_json, notes, created_at, updated_at)
            VALUES ({$pjId}, {$typeEscaped}, {$locked}, {$colorEscaped}, {$vowsEscaped}, {$notesEscaped}, {$now}, {$now})
            ON DUPLICATE KEY UPDATE 
                nen_type = {$typeEscaped},
                nen_type_locked = {$locked},
                aura_color = {$colorEscaped},
                vows_json = {$vowsEscaped},
                notes = {$notesEscaped},
                updated_at = {$now}
        ");
    }

    /**
     * Guardar o actualizar la progresión de un principio fundamental.
     */
    public function savePrincipleProgress(int $pjId, string $principle, int $level): void
    {
        global $db;
        if ($pjId <= 0) {
            return;
        }
        $prefix = TABLE_PREFIX;
        $principleEscaped = $db->escape_string($principle);
        $now = time();

        $db->write_query("
            INSERT INTO {$prefix}game_nen_progress (character_id, principle, level, experience, unlocked_at)
            VALUES ({$pjId}, '{$principleEscaped}', {$level}, 0, {$now})
            ON DUPLICATE KEY UPDATE 
                level = {$level}
        ");
    }

    /**
     * Guardar o proponer una nueva habilidad (Hatsu).
     */
    public function saveAbility(int $pjId, string $name, string $desc, string $rank, int $cost, ?string $conditions = null, ?int $cardId = null, int $approved = 0): int
    {
        global $db;
        if ($pjId <= 0) {
            return 0;
        }
        $prefix = TABLE_PREFIX;
        $nameEsc = $db->escape_string($name);
        $descEsc = $db->escape_string($desc);
        $rankEsc = $db->escape_string($rank);
        $condEsc = $conditions !== null ? "'" . $db->escape_string($conditions) . "'" : "NULL";
        $cardIdEsc = $cardId !== null ? (int)$cardId : "NULL";
        $now = time();

        $db->write_query("
            INSERT INTO {$prefix}game_nen_abilities (character_id, name, description, `rank`, nen_cost, conditions_json, card_id, approved, created_at)
            VALUES ({$pjId}, '{$nameEsc}', '{$descEsc}', '{$rankEsc}', {$cost}, {$condEsc}, {$cardIdEsc}, {$approved}, {$now})
        ");
        return (int)$db->insert_id();
    }

    /**
     * Obtener una habilidad Nen concreta por su ID.
     */
    public function getAbility(int $abilityId): ?array
    {
        global $db;
        if ($abilityId <= 0) {
            return null;
        }
        $prefix = TABLE_PREFIX;
        $q = $db->query("SELECT * FROM {$prefix}game_nen_abilities WHERE id = {$abilityId} LIMIT 1");
        $row = $db->fetch_array($q);
        return $row ?: null;
    }

    /**
     * Aprobar una habilidad Nen y conectarla a una carta técnica del juego.
     */
    public function approveAbility(int $abilityId, int $cardId): void
    {
        global $db;
        if ($abilityId <= 0 || $cardId <= 0) {
            return;
        }
        $prefix = TABLE_PREFIX;
        $db->write_query("UPDATE {$prefix}game_nen_abilities SET approved = 1, card_id = {$cardId} WHERE id = {$abilityId}");
    }

    /**
     * Eliminar o rechazar una habilidad Nen.
     */
    public function deleteAbility(int $abilityId): void
    {
        global $db;
        if ($abilityId <= 0) {
            return;
        }
        $prefix = TABLE_PREFIX;
        $db->write_query("DELETE FROM {$prefix}game_nen_abilities WHERE id = {$abilityId}");
    }

    /**
     * Elimina por completo el Nen de un personaje (prueba, progreso y habilidades).
     */
    public function resetNen(int $pjId): void
    {
        global $db;
        if ($pjId <= 0) {
            return;
        }
        $prefix = TABLE_PREFIX;
        $db->write_query("DELETE FROM {$prefix}game_nen_abilities WHERE character_id = {$pjId}");
        $db->write_query("DELETE FROM {$prefix}game_nen_progress WHERE character_id = {$pjId}");
        $db->write_query("DELETE FROM {$prefix}game_nen WHERE character_id = {$pjId}");
    }
}
