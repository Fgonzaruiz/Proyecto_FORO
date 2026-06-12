<?php
declare(strict_types=1);

namespace Game\Application\Services;

use Game\Shared\StatScale;

/**
 * Carga y normaliza datos de ficha para personaje.php.
 */
final class CharacterSheetLoader
{
    /** @var array<string, string> */
    public const TAG_COLORS = [
        'Amigo' => '#10b981', 'Compañero' => '#3b82f6', 'Aliado' => '#3b82f6',
        'Rival' => '#f59e0b', 'Enemigo' => '#ef4444', 'Némesis' => '#ef4444',
        'Familiar' => '#ec4899', 'Hermano' => '#ec4899', 'Hermana' => '#ec4899',
        'Padre' => '#8b5cf6', 'Madre' => '#8b5cf6',
        'Maestro' => '#f97316', 'Mentor' => '#f97316',
        'Aprendiz' => '#06b6d4', 'Protegido' => '#06b6d4',
        'Interés Romántico' => '#ec4899', 'Cónyuge' => '#ec4899', 'Amante' => '#ec4899',
        'Conocido' => '#6b7280', 'Socio' => '#8b5cf6', 'Cómplice' => '#8b5cf6',
        'Subordinado' => '#64748b', 'Superior' => '#64748b',
        'Adversario' => '#f59e0b', 'Seguidor' => '#06b6d4', 'Líder' => '#f97316',
        'Miembro' => '#6b7280',
    ];

    /**
     * @return array{
     *   char: ?array,
     *   row: ?array,
     *   active_id: int,
     *   cfg: ?array,
     *   pj_progression: array,
     *   pp_available: int,
     *   can_edit: bool,
     *   can_view_private: bool,
     *   is_active_pj: bool,
     *   active_char_is_staff: bool
     * }
     */
    public function load(
        object $db,
        string $prefix,
        int $userId,
        int $reqPjId
    ): array {
        $cfg = null;
        if ($userId > 0) {
            $db->write_query(
                "INSERT INTO {$prefix}game_user_config (user_id, max_slots, slots_used) VALUES ({$userId}, 1, 0) ON DUPLICATE KEY UPDATE user_id=user_id"
            );
            $cfgQ = $db->query("SELECT * FROM {$prefix}game_user_config WHERE user_id = {$userId}");
            $cfg = $db->fetch_array($cfgQ);
        }

        $activeId = $cfg ? (int)$cfg['active_pj_id'] : 0;
        $loadId = $reqPjId ?: $activeId;

        $char = null;
        $row = null;

        if ($loadId > 0) {
            $query = $db->query("SELECT * FROM {$prefix}game_personajes WHERE id = {$loadId} LIMIT 1");
            $row = $db->fetch_array($query);
            if ($row) {
                $char = $this->mapRowToChar($row);
            }
        }

        $activeCharIsStaff = false;
        if ($activeId && $char && $activeId !== (int)$char['id']) {
            $activeQ = $db->query("SELECT is_staff FROM {$prefix}game_personajes WHERE id = {$activeId} LIMIT 1");
            if ($aRow = $db->fetch_array($activeQ)) {
                $activeCharIsStaff = (bool)$aRow['is_staff'];
            }
        } elseif ($activeId && $char && $activeId === (int)$char['id']) {
            $activeCharIsStaff = (bool)$char['is_staff'];
        }

        $isActivePj = ($char && $activeId === (int)$char['id']);
        $canEdit = $isActivePj;
        $canViewPrivate = ($isActivePj || $activeCharIsStaff);

        $pjProgression = CharacterProgression::snapshot([]);
        $ppAvailable = 0;
        if ($char && $loadId > 0) {
            $dataForProg = !empty($row['data_json']) ? json_decode($row['data_json'], true) : [];
            if (!is_array($dataForProg)) {
                $dataForProg = [];
            }
            $raceName = (string)($char['race_name'] ?? '');
            $dataBeforeSync = $dataForProg;
            CharacterProgression::syncLinajeBonusPp($dataForProg, $raceName);
            CharacterProgression::normalize($dataForProg);

            if (
                json_encode($dataBeforeSync) !== json_encode($dataForProg)
                && $userId > 0
                && (int)$char['user_id'] === $userId
            ) {
                $dataJsonEsc = $db->escape_string(json_encode($dataForProg, JSON_UNESCAPED_UNICODE));
                $db->write_query("UPDATE {$prefix}game_personajes SET data_json = '{$dataJsonEsc}' WHERE id = {$loadId} LIMIT 1");
            }

            $statsForProg = StatScale::sanitizeRanks($char['stats'] ?? []);
            $pjProgression = CharacterProgression::snapshot($dataForProg, $statsForProg);
            $ppAvailable = (int)$pjProgression['pp'];
        }

        return [
            'char' => $char,
            'row' => $row,
            'active_id' => $activeId,
            'cfg' => $cfg,
            'pj_progression' => $pjProgression,
            'pp_available' => $ppAvailable,
            'can_edit' => $canEdit,
            'can_view_private' => $canViewPrivate,
            'is_active_pj' => $isActivePj,
            'active_char_is_staff' => $activeCharIsStaff,
        ];
    }

    /** @return list<array{id:int,name:string}> */
    public function loadAllCharacterNames(object $db, string $prefix): array
    {
        $all = [];
        $q = $db->query("SELECT id, name FROM {$prefix}game_personajes WHERE name != 'Narrador' ORDER BY name ASC");
        while ($c = $db->fetch_array($q)) {
            $all[] = $c;
        }
        return $all;
    }

    /** @param array<string, mixed> $row */
    private function mapRowToChar(array $row): array
    {
        $data = !empty($row['data_json']) ? json_decode($row['data_json'], true) : [];
        if (!is_array($data)) {
            $data = [];
        }
        $stats = !empty($row['stats_json']) ? json_decode($row['stats_json'], true) : [];
        if (!is_array($stats)) {
            $stats = [];
        }
        $cronologia = !empty($row['cronologia_json']) ? json_decode($row['cronologia_json'], true) : [];
        if (!is_array($cronologia)) {
            $cronologia = [];
        }
        $cronologia['diario'] = $cronologia['diario'] ?? [];
        $cronologia['relaciones'] = $cronologia['relaciones'] ?? [];
        $cronologia['groups'] = $cronologia['groups'] ?? [];
        $cronologia['connections'] = $cronologia['connections'] ?? [];

        $char = [
            'id' => (int)$row['id'],
            'user_id' => (int)$row['user_id'],
            'name' => $row['name'],
            'race_name' => !empty($row['race_name']) ? $row['race_name'] : ($data['race'] ?? 'Desconocida'),
            'is_staff' => (bool)$row['is_staff'],
            'job_name' => !empty($row['occupation_name']) ? $row['occupation_name'] : ($data['job'] ?? 'Ninguno'),
            'faction_rank' => !empty($data['faction_rank']) ? (string)$data['faction_rank'] : (string)($row['rango'] ?? ''),
            'rango' => !empty($data['faction_rank']) ? (string)$data['faction_rank'] : (string)($row['rango'] ?? ''),
            'avatar' => !empty($row['avatar']) ? $row['avatar'] : ($data['avatar'] ?? ''),
            'faction' => !empty($row['faction']) ? $row['faction'] : ($data['faction'] ?? ''),
            'approved' => (bool)($row['approved'] ?? 0),
            'status' => $row['status'] ?? 'pendiente',
            'desc' => $row['desc'] ?? '',
            'details' => $row['details'] ?? '',
            'age' => $data['age'] ?? 'Desconocida',
            'origin' => $data['origin'] ?? 'Desconocido',
            'pb' => $data['pb'] ?? 'Desconocido',
            'physique' => $data['physique'] ?? '',
            'psychology' => $data['psychology'] ?? '',
            'extras' => $data['extras'] ?? '',
            'history' => $data['history'] ?? '',
            'arquetipo' => $data['arquetipo'] ?? 'Desconocido',
            'disciplina' => $data['disciplina'] ?? ($data['arquetipo'] ?? ''),
            'is_npc' => (bool)($row['is_npc'] ?? 0),
            'disciplinas' => function_exists('game_disciplina_list_for_character')
                ? game_disciplina_list_for_character((int)$row['id'])
                : [],
            'oficios' => function_exists('game_oficio_list_for_character')
                ? game_oficio_list_for_character((int)$row['id'])
                : [],
            'linaje' => $data['linaje'] ?? [],
            'cronologia' => $cronologia,
            'stats' => StatScale::sanitizeRanks($stats),
            'stat_context' => game_build_stat_context(
                $stats,
                !empty($row['race_name']) ? (string)$row['race_name'] : (string)($data['race'] ?? '')
            ),
        ];

        usort($char['cronologia']['diario'], static function ($a, $b) {
            $pesoA = ((int)($a['year'] ?? 0) * 400) + ((int)($a['season'] ?? 0) * 100) + (int)($a['day'] ?? 0);
            $pesoB = ((int)($b['year'] ?? 0) * 400) + ((int)($b['season'] ?? 0) * 100) + (int)($b['day'] ?? 0);
            return $pesoA <=> $pesoB;
        });

        return $char;
    }
}
