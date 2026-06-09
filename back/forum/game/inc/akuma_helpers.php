<?php
declare(strict_types=1);

/**
 * Akuma no Mi — tier, effects_json ampliado y requisitos de asignación.
 */

/** @return array{1: string, 2: string, 3: string, 4: string, 5: string} */
function game_akuma_tier_rank_map(): array
{
    return [1 => 'D', 2 => 'C', 3 => 'B', 4 => 'A', 5 => 'S'];
}

function game_akuma_rank_for_tier(int $tier): string
{
    $map = game_akuma_tier_rank_map();
    return $map[max(1, min(5, $tier))] ?? 'D';
}

function game_akuma_tier_from_rank(string $rank): int
{
    $flip = array_flip(game_akuma_tier_rank_map());
    return (int)($flip[strtoupper($rank)] ?? 1);
}

/** Tier efectivo de una fila de carta (columna tier o effects_json). */
function game_akuma_tier_from_card(array $card): int
{
    if (isset($card['tier']) && (int)$card['tier'] > 0) {
        return max(1, min(5, (int)$card['tier']));
    }
    $ef = $card['effects'] ?? null;
    if (!is_array($ef) && isset($card['effects_json'])) {
        $ef = json_decode((string)$card['effects_json'], true);
    }
    if (is_array($ef) && isset($ef['tier'])) {
        return max(1, min(5, (int)$ef['tier']));
    }
    if (isset($card['rank'])) {
        return game_akuma_tier_from_rank((string)$card['rank']);
    }
    return 1;
}

/** Plantilla vacía de effects_json ampliado (sin campos de cabecera). */
function game_akuma_structured_defaults(): array
{
    return [
        'pasivas' => [],
        'transformaciones' => [],
        'capacidades_base' => [],
        'inmunidades' => [],
        'debilidades' => [
            'universal_agua_mar' => true,
            'universal_kairoseki' => true,
            'universal_haki_armamento' => true,
            'especificas' => [],
        ],
        'reglas_especiales' => [],
        'potencial_despertar' => [
            'disponible' => false,
            'descripcion' => '',
            'requisito_minimo' => 'Nivel 6 + ESP SS + aprobación staff',
        ],
        'referencia_tecnicas' => 'Las técnicas de esta fruta son cartas separadas de tipo tecnica con tag PARAMECIA/LOGIA/ZOAN. Esta carta no las incluye. El jugador solicita técnicas al staff usando esta carta como base.',
    ];
}

/**
 * Normaliza payload de carta akuma (legacy o ampliado) antes de guardar.
 *
 * @param array<string, mixed> $effects
 * @return array<string, mixed>
 */
function game_akuma_normalize_effects(array $effects, string $cardName, int $tier): array
{
    $akumaType = strtolower((string)($effects['akuma_type'] ?? 'paramecia'));
    if (!in_array($akumaType, ['paramecia', 'logia', 'zoan'], true)) {
        $akumaType = 'paramecia';
    }
    $subtipo = strtolower((string)($effects['subtipo'] ?? 'ninguno'));
    if (!in_array($subtipo, ['ninguno', 'antiguo', 'mitico'], true)) {
        $subtipo = 'ninguno';
    }
    if ($akumaType !== 'zoan') {
        $subtipo = 'ninguno';
    }

    $structured = game_akuma_structured_defaults();
    foreach (array_keys($structured) as $key) {
        if (array_key_exists($key, $effects) && is_array($effects[$key])) {
            $structured[$key] = $effects[$key];
        }
    }
    if (isset($effects['reglas_especiales']) && is_array($effects['reglas_especiales'])) {
        $structured['reglas_especiales'] = $effects['reglas_especiales'];
    }
    if (isset($effects['potencial_despertar']) && is_array($effects['potencial_despertar'])) {
        $structured['potencial_despertar'] = array_merge($structured['potencial_despertar'], $effects['potencial_despertar']);
    }

    // Migración legacy: efectos/limitaciones/debilidades en texto plano
    if (!empty($effects['efectos']) && empty($structured['capacidades_base'])) {
        $structured['capacidades_base'][] = [
            'nombre' => 'Poderes (legacy)',
            'descripcion' => (string)$effects['efectos'],
            'alcance' => 'medio',
            'tier_necesario_para_usar' => $tier,
        ];
    }
    if (!empty($effects['limitaciones']) && empty($structured['reglas_especiales'])) {
        $structured['reglas_especiales'][] = (string)$effects['limitaciones'];
    }
    if (!empty($effects['debilidades']) && is_string($effects['debilidades'])) {
        $legacyDeb = trim($effects['debilidades']);
        if ($legacyDeb !== '' && is_array($structured['debilidades']['especificas'] ?? null)) {
            $structured['debilidades']['especificas'][] = $legacyDeb;
        }
    }

    $identidad = trim((string)($effects['identidad'] ?? ''));
    if ($identidad === '' && !empty($effects['efectos'])) {
        $identidad = mb_substr(trim((string)$effects['efectos']), 0, 200);
    }

    return array_merge($structured, [
        'akuma_type' => $akumaType,
        'subtipo' => $subtipo,
        'tier' => $tier,
        'nombre_fruta' => trim((string)($effects['nombre_fruta'] ?? $cardName)),
        'identidad' => $identidad,
    ]);
}

/**
 * Valida requisitos ESP/nivel para asignar carta akuma. Null = OK; string = mensaje de error.
 */
function game_akuma_assignment_error(int $characterId, array $cardRow): ?string
{
    if (($cardRow['card_type'] ?? '') !== 'akuma_no_mi') {
        return null;
    }

    global $db;
    $prefix = TABLE_PREFIX;
    $pj_q = $db->query("SELECT stats_json, race_name, data_json FROM {$prefix}game_personajes WHERE id = {$characterId} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    if (!$pj) {
        return 'Personaje no encontrado.';
    }

    $tier = game_akuma_tier_from_card($cardRow);
    $minEsp = \Game\Shared\StatScale::minEspRankForAkumaTier($tier);
    $minNivel = \Game\Shared\StatScale::minNivelForAkumaTier($tier);

    $statsRaw = json_decode($pj['stats_json'] ?? '{}', true);
    if (!is_array($statsRaw)) {
        $statsRaw = [];
    }
    $ctx = game_build_stat_context($statsRaw, (string)($pj['race_name'] ?? ''));
    $espEff = (int)($ctx['effective_ranks']['esp'] ?? 1);

    $data = json_decode($pj['data_json'] ?? '{}', true);
    if (!is_array($data)) {
        $data = [];
    }
    $charNivel = game_get_character_nivel($data);

    if ($espEff < $minEsp) {
        return 'ESP efectivo insuficiente para esta Akuma (tier ' . $tier . ', requiere rango '
            . \Game\Shared\StatScale::rankDisplayLabel($minEsp) . ').';
    }
    if ($charNivel < $minNivel) {
        return 'Nivel insuficiente para esta Akuma (tier ' . $tier . ', requiere nivel ' . $minNivel . ').';
    }

    return null;
}

/**
 * Ajusta payload de creación/edición para cartas akuma_no_mi (mutación in-place).
 *
 * @param array<string, mixed> $input
 */
function game_cards_apply_akuma_payload(array &$input): void
{
    if (($input['card_type'] ?? '') !== 'akuma_no_mi') {
        return;
    }

    $effects = is_array($input['effects'] ?? null) ? $input['effects'] : [];
    $tier = max(1, min(5, (int)($input['tier'] ?? $effects['tier'] ?? 1)));
    $name = (string)($input['name'] ?? '');

    $input['effects'] = game_akuma_normalize_effects($effects, $name, $tier);
    $input['tier'] = $tier;
    $input['rank'] = game_akuma_rank_for_tier($tier);
    $input['activation'] = 'pasiva';
    $input['cost_pe'] = '0';
    $input['dice'] = '';
    $input['execution_stat'] = '';
    $input['execution_cost'] = 0;
    $input['reposo'] = 0;
    $input['duracion'] = 0;
}
