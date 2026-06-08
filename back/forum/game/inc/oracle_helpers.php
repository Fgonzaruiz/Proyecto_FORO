<?php
declare(strict_types=1);

/**
 * Helper para el sistema de oráculos.
 * Funciones de tirada, matching de resultados, auto-invocación.
 */

/**
 * Obtiene la categoría/isla de un post (basada en el foro del hilo).
 */
function game_get_post_category(int $postId): string
{
    global $db;
    $prefix = TABLE_PREFIX;
    $q = $db->query("
        SELECT t.tid, f.fid, f.name AS forum_name, f.pid,
               p.name AS category_name
        FROM {$prefix}posts pst
        JOIN {$prefix}threads t ON t.tid = pst.tid
        JOIN {$prefix}forums f ON f.fid = t.fid
        LEFT JOIN {$prefix}forums p ON p.fid = f.pid AND p.type = 'c'
        WHERE pst.pid = {$postId}
        LIMIT 1
    ");
    $row = $db->fetch_array($q);
    if (!$row) return '';
    // Retorna el nombre de la categoría padre si existe, o el nombre del foro.
    return ($row['category_name'] ?? '') ?: ($row['forum_name'] ?? '');
}

/**
 * Busca el resultado de un oráculo dado un valor de tirada.
 */
function game_find_oracle_result(array $results, int $roll): ?array
{
    foreach ($results as $entry) {
        $range = $entry['range'] ?? '';
        if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $range, $m)) {
            if ($roll >= (int)$m[1] && $roll <= (int)$m[2]) {
                return [
                    'range' => $range,
                    'result' => $entry['result'] ?? '',
                    'description' => $entry['description'] ?? '',
                    'auto_invoke' => $entry['auto_invoke'] ?? null,
                ];
            }
        }
    }
    // Fallback: result exacto
    foreach ($results as $entry) {
        if ((int)$entry['range'] === $roll) {
            return [
                'range' => (string)$roll,
                'result' => $entry['result'] ?? '',
                'description' => $entry['description'] ?? '',
                'auto_invoke' => $entry['auto_invoke'] ?? null,
            ];
        }
    }
    return null;
}

/**
 * Ejecuta la tirada de un oráculo.
 */
function game_roll_oracle(array $oracleRow, ?string $category = null): array
{
    $results = json_decode($oracleRow['results_json'] ?? '[]', true);
    if (!is_array($results)) $results = [];

    // Aplicar variación si existe para la categoría
    if ($category) {
        $variations = json_decode($oracleRow['variations_json'] ?? '{}', true);
        if (is_array($variations) && isset($variations[$category]) && is_array($variations[$category])) {
            $results = $variations[$category];
        }
    }

    // Determinar tipo de dado
    $diceType = $oracleRow['dice_type'] ?? 'd100';
    $max = max(1, (int)substr($diceType, 1));

    // Tirada
    $roll = mt_rand(1, $max);

    // Buscar resultado
    $matched = game_find_oracle_result($results, $roll);

    return [
        'roll' => $roll,
        'roll_display' => (string)$roll,
        'range' => $matched['range'] ?? "{$roll}-{$roll}",
        'result' => $matched['result'] ?? '—',
        'description' => $matched['description'] ?? '',
        'auto_invoke' => $matched['auto_invoke'] ?? null,
    ];
}
