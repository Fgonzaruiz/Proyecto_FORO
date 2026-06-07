<?php
declare(strict_types=1);

namespace Game\Application\Services;

use Game\Shared\StatScale;

/**
 * Persistencia segura de fichas (crear / editar pendiente).
 */
final class CharacterSaveService
{
    private const FORBIDDEN_DATA_KEYS = [
        'pp', 'pp_linaje', 'nivel', 'stat_points_purchased', 'pp_spent_eligible',
        'last_level_up_at', 'is_staff', 'staff_level', 'approved',
    ];

    private LinajeValidator $linajeValidator;

    public function __construct(?LinajeValidator $linajeValidator = null)
    {
        $this->linajeValidator = $linajeValidator ?? new LinajeValidator();
    }

    /**
     * @param array<string, mixed> $input Payload del wizard
     * @return array{ok:bool, message?:string, data_json?:array, stats_json?:array, columns?:array}
     */
    public function buildPayloadForInsert(int $userId, array $input): array
    {
        $race = trim((string)($input['race'] ?? ''));
        $linajeIn = is_array($input['linaje'] ?? null) ? $input['linaje'] : [];
        $linajeResult = $this->linajeValidator->validateAndBuild($race, $linajeIn);
        if (!$linajeResult['ok']) {
            return ['ok' => false, 'message' => $linajeResult['message'] ?? 'Linaje inválido.'];
        }

        $stats = $this->sanitizeStats($input['stats'] ?? []);
        $data = $this->buildBioData($input);
        $data['linaje'] = $linajeResult['linaje'];
        $factionRank = trim((string)($input['rank'] ?? ''));
        if ($factionRank !== '') {
            $data['faction_rank'] = $factionRank;
        }

        $bonusPp = (int)($linajeResult['linaje']['bonusPP'] ?? 0);
        if ($bonusPp > 0) {
            $data['pp'] = $bonusPp;
            $data['pp_linaje'] = $bonusPp;
        }
        $globalRank = StatScale::globalRankFromSum(StatScale::sumRanks($stats));
        $data['rank'] = $globalRank;
        $data['nivel'] = StatScale::globalNivelFromRank($globalRank);

        return [
            'ok' => true,
            'data_json' => $data,
            'stats_json' => $stats,
            'columns' => $this->mapColumns($input, $race),
        ];
    }

    /**
     * Recalcula linaje y PP de creación al aprobar ficha (staff).
     *
     * @param array<string, mixed> $data data_json actual
     * @param array<string, mixed> $stats stats_json actual
     * @return array{ok:bool, message?:string, data_json?:array, stats_json?:array}
     */
    public function recalculateOnApprove(string $raceName, array $data, array $stats): array
    {
        $linajeIn = is_array($data['linaje'] ?? null) ? $data['linaje'] : [];
        $linajeResult = $this->linajeValidator->validateAndBuild($raceName, $linajeIn);
        if (!$linajeResult['ok']) {
            return ['ok' => false, 'message' => $linajeResult['message'] ?? 'Linaje inválido.'];
        }

        $data['linaje'] = $linajeResult['linaje'];
        $bonusPp = (int)($linajeResult['linaje']['bonusPP'] ?? 0);
        $sanitizedStats = $this->sanitizeStats($stats);
        $ppSpent = StatScale::ppSpentOnRanks($sanitizedStats);

        if ($ppSpent === 0) {
            $data['pp'] = $bonusPp;
            $data['pp_linaje'] = $bonusPp;
        } else {
            $data['pp_linaje'] = min(max(0, (int)($data['pp_linaje'] ?? 0)), $bonusPp);
            $data['pp'] = max((int)($data['pp'] ?? 0), 0);
        }

        $globalRank = StatScale::globalRankFromSum(StatScale::sumRanks($sanitizedStats));
        $data['rank'] = $globalRank;
        $data['nivel'] = StatScale::globalNivelFromRank($globalRank);
        CharacterProgression::normalize($data);

        return [
            'ok' => true,
            'data_json' => $data,
            'stats_json' => $sanitizedStats,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $existingData data_json actual
     */
    public function buildPayloadForUpdate(array $input, array $existingData): array
    {
        $race = trim((string)($input['race'] ?? $existingData['race'] ?? ''));
        $linajeIn = is_array($input['linaje'] ?? null) ? $input['linaje'] : [];
        $linajeResult = $this->linajeValidator->validateAndBuild($race, $linajeIn);
        if (!$linajeResult['ok']) {
            return ['ok' => false, 'message' => $linajeResult['message'] ?? 'Linaje inválido.'];
        }

        $data = $this->buildBioData($input);
        $data['linaje'] = $linajeResult['linaje'];

        foreach (self::FORBIDDEN_DATA_KEYS as $key) {
            if (array_key_exists($key, $existingData)) {
                $data[$key] = $existingData[$key];
            }
        }

        return [
            'ok' => true,
            'data_json' => $data,
            'stats_json' => $this->sanitizeStats($input['stats'] ?? []),
            'columns' => $this->mapColumns($input, $race),
        ];
    }

    /** @return array<string, mixed> */
    private function buildBioData(array $input): array
    {
        return [
            'age' => trim((string)($input['age'] ?? 'Desconocida')),
            'origin' => trim((string)($input['origin'] ?? 'Desconocido')),
            'pb' => trim((string)($input['pb'] ?? 'Desconocido')),
            'physique' => trim((string)($input['physique'] ?? '')),
            'psychology' => trim((string)($input['psychology'] ?? '')),
            'extras' => trim((string)($input['extras'] ?? '')),
            'arquetipo' => trim((string)($input['arquetipo'] ?? 'Desconocido')),
            'job' => trim((string)($input['job'] ?? 'Ninguno')),
            'race' => trim((string)($input['race'] ?? '')),
            'faction_rank' => trim((string)($input['rank'] ?? '')),
            'faction' => trim((string)($input['faction'] ?? '')),
            'avatar' => trim((string)($input['avatar'] ?? '')),
        ];
    }

    /** @return array{fue:int,res:int,agi:int,des:int,int:int,inst:int,esp:int} */
    private function sanitizeStats($raw): array
    {
        return \Game\Shared\StatScale::sanitizeRanks(is_array($raw) ? $raw : []);
    }

    /** @return array<string, string> */
    private function mapColumns(array $input, string $race): array
    {
        return [
            'name' => trim((string)($input['name'] ?? 'Sin Nombre')),
            'race_name' => $race,
            'faction' => trim((string)($input['faction'] ?? '')),
            'rango' => trim((string)($input['rank'] ?? '')),
            'occupation_name' => trim((string)($input['job'] ?? '')),
            'avatar' => trim((string)($input['avatar'] ?? '')),
        ];
    }
}
