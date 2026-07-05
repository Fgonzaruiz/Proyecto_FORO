<?php
/**
 * Contexto compartido de ficha — stats, vitals, metadatos visuales.
 * Incluido por page.php y partials de tabs.
 */
declare(strict_types=1);

$pj_resolve_img = static function (?string $path) use ($bburl): string {
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return rtrim((string)$bburl, '/') . '/' . ltrim($path, '/');
};

$pj_portrait_url = $pj_resolve_img($char['avatar'] ?? '');
if ($pj_portrait_url === '') {
    $pj_portrait_url = 'https://placehold.co/400x600/e8dcc8/1a3d2e?text=Retrato';
}

/** Retrato del nexus (centro del radar) — columna banner, editable aparte del avatar */
$pj_radar_url = $pj_resolve_img($char['banner'] ?? '');
if ($pj_radar_url === '') {
    $pj_radar_url = 'https://placehold.co/200x200/e8dcc8/1a3d2e?text=Nexus';
}

/** Alias retrocompat */
$pj_avatar_url = $pj_portrait_url;
$globalRank      = (string)($pj_progression['rank'] ?? 'D');
$globalRankClass = \Game\Shared\StatScale::globalRankCssClass($globalRank);
$pj_nivel        = (int)($pj_progression['nivel'] ?? 1);
$pj_pp           = (int)($pj_progression['pp'] ?? 0);
$pj_jenny        = (int)($row['jenny'] ?? 0);

$ctx    = $char['stat_context'] ?? game_build_stat_context($char['stats'], (string)($char['race_name'] ?? ''));
$vitals = game_compute_pv_pe_from_context($ctx['values'], $ctx['trained']);
$pv     = $vitals['max_pv'];
$pe     = $vitals['max_pe'];

$cuerpo_keys   = ['fuerza', 'destreza', 'vigor', 'agilidad'];
$mente_keys    = ['intelecto', 'ingenio', 'concentracion', 'percepcion'];
$espiritu_keys = ['caudal', 'control', 'voluntad', 'sensibilidad'];
$cuerpo_sum = 0; $mente_sum = 0; $espiritu_sum = 0;
foreach ($cuerpo_keys   as $k) $cuerpo_sum   += (int)($ctx['effective_ranks'][$k] ?? 1);
foreach ($mente_keys    as $k) $mente_sum    += (int)($ctx['effective_ranks'][$k] ?? 1);
foreach ($espiritu_keys as $k) $espiritu_sum += (int)($ctx['effective_ranks'][$k] ?? 1);
$total_sum = $cuerpo_sum + $mente_sum + $espiritu_sum;

$statMetaMap = [
    'fuerza'        => ['Fuerza',       'fa-dumbbell',      'cuerpo'],
    'destreza'      => ['Destreza',     'fa-bullseye',      'cuerpo'],
    'vigor'         => ['Vigor',        'fa-heartbeat',     'cuerpo'],
    'agilidad'      => ['Agilidad',     'fa-running',       'cuerpo'],
    'intelecto'     => ['Intelecto',    'fa-brain',         'mente'],
    'ingenio'       => ['Ingenio',      'fa-lightbulb',     'mente'],
    'concentracion' => ['Concentración','fa-crosshairs',    'mente'],
    'percepcion'    => ['Percepción',   'fa-eye',           'mente'],
    'caudal'        => ['Caudal Aura',  'fa-fire',          'espiritu'],
    'control'       => ['Control',      'fa-hand-sparkles', 'espiritu'],
    'voluntad'      => ['Voluntad',     'fa-fingerprint',   'espiritu'],
    'sensibilidad'  => ['Sensibilidad', 'fa-compass',       'espiritu'],
];

$statusLabels = [
    'aprobada'  => ['Aprobada',    'hxh-badge--ok'],
    'revision'  => ['En Revisión', 'hxh-badge--warn'],
    'rechazada' => ['Rechazada',   'hxh-badge--err'],
    'muerto'    => ['Muerto',      'hxh-badge--dead'],
];
$statusInfo = $statusLabels[$char['status']] ?? ['Pendiente', 'hxh-badge--err'];

$sidebar_disciplinas = $char['disciplinas'] ?? [];
$sidebar_oficios     = $char['oficios'] ?? [];

$char_epithet = $char['rango'] ?: ($char['job_name'] ?: '');

$rowData = [];
if (!empty($row['data_json'])) {
    $decoded = json_decode((string)$row['data_json'], true);
    $rowData = is_array($decoded) ? $decoded : [];
}
$char_gender = (string)($rowData['gender'] ?? $rowData['sexo'] ?? '—');

// Licencia de cazador: solo si facción o flag explícito lo indican
$has_hunter_license = !empty($rowData['hunter_license']) || !empty($rowData['licencia_cazador']);
if (!$has_hunter_license) {
    $facLower = strtolower((string)($char['faction'] ?? ''));
    $has_hunter_license = (str_contains($facLower, 'cazador') || str_contains($facLower, 'hunter'));
}
$doc_stamp_label = $has_hunter_license
    ? 'LICENCIA DE CAZADOR · RANGO ' . $globalRank
    : 'EXPEDIENTE DE PERSONAJE · RANGO ' . $globalRank;
$doc_id_prefix = $has_hunter_license ? 'H' : 'REG';
$doc_id_badge = $doc_id_prefix . '–' . str_pad((string)(int)$char['id'], 5, '0', STR_PAD_LEFT);

// Stats derivados de combate (aproximación narrativa desde atributos)
$vals = $ctx['values'];
$combatDerived = [
    ['Defensa Pasiva',       (int)round(($vals['vigor'] ?? 10) * 0.65 + ($vals['destreza'] ?? 10) * 0.35)],
    ['Fortaleza Espiritual', (int)round(($vals['voluntad'] ?? 10) * 0.7 + ($vals['control'] ?? 10) * 0.3)],
    ['Regeneración PE',      (int)round(($vals['caudal'] ?? 10) * 0.08)],
    ['Concentración',        (int)($vals['concentracion'] ?? 10)],
    ['Umbral del Dolor',     (int)round(($vals['vigor'] ?? 10) * 3.5 + ($vals['voluntad'] ?? 10) * 0.5)],
    ['Movimiento',           (int)round(($vals['agilidad'] ?? 10) * 0.35) . ' m'],
    ['Salto',                (int)round(($vals['fuerza'] ?? 10) * 0.06 + ($vals['agilidad'] ?? 10) * 0.08) . ' m'],
    ['Trepar',               (int)round(($vals['destreza'] ?? 10) * 0.08) . ' m'],
    ['Nadar',                (int)round(($vals['vigor'] ?? 10) * 0.05 + ($vals['agilidad'] ?? 10) * 0.12) . ' m'],
    ['Distancia Lanzamiento',(int)round(($vals['fuerza'] ?? 10) * 0.12 + ($vals['destreza'] ?? 10) * 0.08) . ' m'],
    ['Límite de Caída',      (int)round(($vals['vigor'] ?? 10) * 0.18) . ' m'],
];

// Rasgos rápidos desde linaje v2
$portada_rasgos = [];
$linajeData = $char['linaje'] ?? [];
if (!empty($linajeData['pasivas']) || !empty($linajeData['elegidos_racial']) || !empty($linajeData['elegidos_general'])) {
    $catalog_path = dirname(__DIR__, 2) . '/data/linaje_system.json';
    if (file_exists($catalog_path)) {
        $catalog = json_decode((string)file_get_contents($catalog_path), true);
        $allPerkIds = array_merge(
            $linajeData['pasivas'] ?? [],
            $linajeData['elegidos_racial'] ?? [],
            $linajeData['elegidos_general'] ?? []
        );
        $findPerkName = static function (string $id, array $cat): ?string {
            foreach (['arbol_general', 'arboles_raciales', 'pasivas_primarias', 'pasivas_secundarias'] as $section) {
                if (!isset($cat[$section])) {
                    continue;
                }
                $nodes = $cat[$section];
                if ($section === 'arbol_general' || $section === 'arboles_raciales') {
                    foreach ($nodes as $tree) {
                        foreach (($tree['perks'] ?? []) as $p) {
                            if (($p['id'] ?? '') === $id) {
                                return (string)($p['name'] ?? $id);
                            }
                        }
                    }
                } else {
                    foreach ($nodes as $list) {
                        if (!is_array($list)) {
                            continue;
                        }
                        foreach ($list as $p) {
                            if (($p['id'] ?? '') === $id) {
                                return (string)($p['name'] ?? $id);
                            }
                        }
                    }
                }
            }
            return null;
        };
        foreach ($allPerkIds as $pid) {
            $name = $findPerkName((string)$pid, is_array($catalog) ? $catalog : []);
            if ($name) {
                $portada_rasgos[] = $name;
            }
            if (count($portada_rasgos) >= 8) {
                break;
            }
        }
    }
}

// Mascotas / acompañantes desde cronología
$companions = [];
foreach ($char['cronologia']['relaciones'] ?? [] as $rel) {
    $tags = $rel['tags'] ?? [];
    if (!empty($rel['is_npc']) || in_array('Mascota', $tags, true) || in_array('Compañero', $tags, true)) {
        $companions[] = $rel;
    }
}

// Mapa slug → disciplina del personaje
$ownedDiscMap = [];
foreach ($sidebar_disciplinas as $d) {
    $ownedDiscMap[(string)($d['slug'] ?? '')] = $d;
}

// Catálogo completo para grid de combate
$disciplina_catalog = function_exists('game_disciplina_list_catalog')
    ? game_disciplina_list_catalog(true)
    : $sidebar_disciplinas;

$discCategoryGradients = [
    'combate'  => 'linear-gradient(135deg, #1a3d2e 0%, #2e7d32 55%, #1b5e20 100%)',
    'defensa'  => 'linear-gradient(135deg, #1e3a5f 0%, #2563eb 55%, #1e40af 100%)',
    'nen'      => 'linear-gradient(135deg, #4a148c 0%, #7c3aed 55%, #5b21b6 100%)',
    'movimiento' => 'linear-gradient(135deg, #0d9488 0%, #14b8a6 55%, #0f766e 100%)',
    'default'  => 'linear-gradient(135deg, #334155 0%, #475569 55%, #1e293b 100%)',
];
