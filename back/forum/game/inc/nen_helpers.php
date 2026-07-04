<?php
declare(strict_types=1);

use Game\Application\Services\NenService;

/**
 * Helpers globales para consultas rápidas de Nen en vistas y plantillas.
 */

if (!function_exists('game_get_nen_state')) {
    function game_get_nen_state(int $pjId): ?array
    {
        static $cache = [];
        if ($pjId <= 0) {
            return null;
        }
        if (isset($cache[$pjId])) {
            return $cache[$pjId];
        }
        $service = new NenService();
        $cache[$pjId] = $service->getNenState($pjId);
        return $cache[$pjId];
    }
}

if (!function_exists('game_has_nen_despierto')) {
    function game_has_nen_despierto(int $pjId): bool
    {
        return game_get_nen_state($pjId) !== null;
    }
}

if (!function_exists('game_get_nen_type_label')) {
    function game_get_nen_type_label(?string $slug): string
    {
        if (!$slug) {
            return 'Sin Determinar (Prueba de la Taza Pendiente)';
        }
        $labels = [
            'enhancement' => 'Intensificación (Kyōka)',
            'transmutation' => 'Transmutación (Henka)',
            'emission' => 'Emisión (Hōshutsu)',
            'conjuration' => 'Materialización (Geshitsu)',
            'manipulation' => 'Manipulación (Sōsa)',
            'specialization' => 'Especialización (Tokushitsu)'
        ];
        return $labels[$slug] ?? ucfirst($slug);
    }
}

if (!function_exists('game_get_nen_type_color')) {
    function game_get_nen_type_color(?string $slug): string
    {
        if (!$slug) {
            return '#757575';
        }
        $colors = [
            'enhancement' => '#E64A19', // Naranja/Rojo fuerza
            'transmutation' => '#D81B60', // Rosa/Magnetismo/Cambio
            'emission' => '#43A047', // Verde proyectil
            'conjuration' => '#1976D2', // Azul creación/estructura
            'manipulation' => '#8E24AA', // Púrpura control/mente
            'specialization' => '#F57C00' // Dorado/Único
        ];
        return $colors[$slug] ?? '#00E5FF';
    }
}

if (!function_exists('game_get_nen_principle_level')) {
    function game_get_nen_principle_level(int $pjId, string $principle): int
    {
        $state = game_get_nen_state($pjId);
        if (!$state) {
            return 0;
        }
        return (int)($state['principles'][$principle]['level'] ?? 0);
    }
}

if (!function_exists('game_get_nen_principle_label')) {
    function game_get_nen_principle_label(string $principle): string
    {
        $labels = [
            'ten' => 'Ten (Envoltura)',
            'zetsu' => 'Zetsu (Clausura)',
            'ren' => 'Ren (Templanza)',
            'hatsu' => 'Hatsu (Acción/Técnica)'
        ];
        return $labels[$principle] ?? ucfirst($principle);
    }
}

if (!function_exists('game_get_nen_principle_level_label')) {
    function game_get_nen_principle_level_label(int $level): string
    {
        $labels = [
            0 => 'Bloqueado',
            1 => 'Básico',
            2 => 'Intermedio',
            3 => 'Avanzado',
            4 => 'Maestría (Gō)'
        ];
        return $labels[$level] ?? 'Desconocido';
    }
}

if (!function_exists('game_get_nen_fundamental_principles')) {
    /**
     * Catálogo de los 4 principios fundamentales (sección 4 del manual Nen).
     *
     * @return list<array{id: string, num: string, name: string, japanese: string, desc: string, is_hatsu: bool}>
     */
    function game_get_nen_fundamental_principles(): array
    {
        return [
            [
                'id' => 'ten',
                'num' => '4.1',
                'name' => 'Ten',
                'japanese' => 'Envoltura',
                'desc' => 'Concentrar el flujo del aura en los puntos del cuerpo para mantener la energía contenida.',
                'is_hatsu' => false,
            ],
            [
                'id' => 'zetsu',
                'num' => '4.2',
                'name' => 'Zetsu',
                'japanese' => 'Clausura',
                'desc' => 'Cerrar por completo los nodos de aura para ocultar tu presencia y conservar energía.',
                'is_hatsu' => false,
            ],
            [
                'id' => 'ren',
                'num' => '4.3',
                'name' => 'Ren',
                'japanese' => 'Templanza',
                'desc' => 'Liberar el aura de forma constante, aumentando la cantidad disponible y fortaleciendo el cuerpo.',
                'is_hatsu' => false,
            ],
            [
                'id' => 'hatsu',
                'num' => '4.4',
                'name' => 'Hatsu',
                'japanese' => 'Acción / Técnica personal',
                'desc' => 'Expresar el aura como habilidad única. Tu Hatsu es irrepetible: la firma de tu Nen.',
                'is_hatsu' => true,
            ],
        ];
    }
}

if (!function_exists('game_get_nen_advanced_techniques')) {
    /**
     * Catálogo de técnicas avanzadas (sección 5 del manual Nen).
     *
     * @return list<array{id: string, num: string, name: string, desc: string}>
     */
    function game_get_nen_advanced_techniques(): array
    {
        return [
            ['id' => 'gyo', 'num' => '5.1', 'name' => 'Gyo', 'desc' => 'Intensificar el aura en una zona concreta del cuerpo para ver lo oculto o aumentar el impacto.'],
            ['id' => 'in', 'num' => '5.2', 'name' => 'In', 'desc' => 'Ocultar el aura dentro de un objeto, enmascarando técnicas y trampas.'],
            ['id' => 'en', 'num' => '5.3', 'name' => 'En', 'desc' => 'Expandir el aura en una esfera de percepción para detectar presencias.'],
            ['id' => 'shu', 'num' => '5.4', 'name' => 'Shu', 'desc' => 'Envolver un objeto con aura para reforzarlo o dotarlo de propiedades Nen.'],
            ['id' => 'ko', 'num' => '5.5', 'name' => 'Ko', 'desc' => 'Concentrar el 100% del aura en un único punto de ataque o defensa.'],
            ['id' => 'ken', 'num' => '5.6', 'name' => 'Ken', 'desc' => 'Combinación de En y Ten: mantener un radio de alerta defensivo constante.'],
            ['id' => 'ryu', 'num' => '5.7', 'name' => 'Ryu', 'desc' => 'Distribuir el aura entre ataque y defensa en tiempo real (ej. 80/20, 50/50).'],
        ];
    }
}

if (!function_exists('game_get_nen_type_affinities')) {
    /**
     * Maestría por tipo Nen (1–5). El tipo principal empieza en 5; el resto según distancia en el hexágono.
     *
     * @return list<array{slug: string, label: string, color: string, maestria: int, is_primary: bool}>
     */
    function game_get_nen_type_affinities(string $mainType): array
    {
        $types = ['enhancement', 'transmutation', 'conjuration', 'specialization', 'manipulation', 'emission'];
        $idx = array_search($mainType, $types, true);
        if ($idx === false) {
            $idx = 0;
        }

        $result = [];
        foreach ($types as $i => $t) {
            if ($t === 'specialization' && $mainType !== 'specialization') {
                $result[] = [
                    'slug' => $t,
                    'label' => game_get_nen_type_label($t),
                    'color' => game_get_nen_type_color($t),
                    'maestria' => 0,
                    'is_primary' => false,
                    'unavailable' => true,
                ];
                continue;
            }

            $dist = min(abs($i - $idx), 6 - abs($i - $idx));
            $maestria = match ($dist) {
                0 => 5,
                1 => 4,
                2 => 3,
                default => 2,
            };
            $result[] = [
                'slug' => $t,
                'label' => game_get_nen_type_label($t),
                'color' => game_get_nen_type_color($t),
                'maestria' => $maestria,
                'is_primary' => $t === $mainType,
                'unavailable' => false,
            ];
        }
        return $result;
    }
}

if (!function_exists('game_get_nen_maestria_label')) {
    function game_get_nen_maestria_label(int $level): string
    {
        return match ($level) {
            5 => 'Maestría V (Natural)',
            4 => 'Maestría IV',
            3 => 'Maestría III',
            2 => 'Maestría II',
            1 => 'Maestría I',
            default => 'Sin afinidad',
        };
    }
}

if (!function_exists('game_nen_awakening_payload')) {
    /**
     * Payload JSON estándar tras completar la prueba Mizushigure.
     */
    function game_nen_awakening_payload(int $charId, string $nenType, string $auraColor): array
    {
        $types = ['enhancement', 'transmutation', 'conjuration', 'specialization', 'manipulation', 'emission'];
        $idx = array_search($nenType, $types, true);
        $controls = [];
        foreach ($types as $i => $t) {
            if ($t === 'specialization' && $nenType !== 'specialization') {
                $controls[$t] = 0;
                continue;
            }
            $dist = min(abs($i - (int)$idx), 6 - abs($i - (int)$idx));
            $controls[$t] = match ($dist) {
                0 => 100,
                1 => 80,
                2 => 60,
                default => 40,
            };
        }

        $affinityRows = [];
        foreach ($types as $i => $t) {
            if ($t === 'specialization' && $nenType !== 'specialization') {
                $affinityRows[] = [
                    'slug' => $t,
                    'label' => game_get_nen_type_label($t),
                    'color' => game_get_nen_type_color($t),
                    'pct' => 0,
                    'maestria' => 0,
                    'unavailable' => true,
                ];
                continue;
            }
            $dist = min(abs($i - (int)$idx), 6 - abs($i - (int)$idx));
            $maestria = match ($dist) {
                0 => 5,
                1 => 4,
                2 => 3,
                default => 2,
            };
            $affinityRows[] = [
                'slug' => $t,
                'label' => game_get_nen_type_label($t),
                'color' => game_get_nen_type_color($t),
                'pct' => $controls[$t],
                'maestria' => $maestria,
                'unavailable' => false,
            ];
        }

        return [
            'character_id' => $charId,
            'nen_type' => $nenType,
            'nen_type_label' => game_get_nen_type_label($nenType),
            'nen_type_color' => game_get_nen_type_color($nenType),
            'aura_color' => $auraColor,
            'control' => $controls,
            'control_labels' => $affinityRows,
            'affinities' => game_get_nen_type_affinities($nenType),
        ];
    }
}

if (!function_exists('game_get_nen_type_short_label')) {
    function game_get_nen_type_short_label(string $slug): string
    {
        $labels = [
            'enhancement' => 'Intens.',
            'transmutation' => 'Transm.',
            'emission' => 'Emisión',
            'conjuration' => 'Mater.',
            'manipulation' => 'Manip.',
            'specialization' => 'Espec.',
        ];
        return $labels[$slug] ?? ucfirst($slug);
    }
}

if (!function_exists('game_render_nen_hex_chart')) {
    /**
     * Diagrama hexagonal de afinidades Nen (maestría por vértice).
     */
    function game_render_nen_hex_chart(array $affinities, string $mainType): string
    {
        $cx = 150.0;
        $cy = 150.0;
        $r = 105.0;
        $points = [];
        $valuePoints = [];

        for ($i = 0; $i < 6; $i++) {
            $angle = (M_PI / 3) * $i - M_PI / 2;
            $x = $cx + $r * cos($angle);
            $y = $cy + $r * sin($angle);
            $points[] = round($x, 1) . ',' . round($y, 1);

            $aff = $affinities[$i] ?? null;
            $maestria = (int)($aff['maestria'] ?? 0);
            $unavailable = !empty($aff['unavailable']);
            $fillR = $unavailable ? 0 : ($r * ($maestria / 5));
            $vx = $cx + $fillR * cos($angle);
            $vy = $cy + $fillR * sin($angle);
            $valuePoints[] = round($vx, 1) . ',' . round($vy, 1);
        }

        $mainColor = game_get_nen_type_color($mainType);
        $svg = '<div class="rpg-nen-hex-wrap">';
        $svg .= '<svg viewBox="0 0 300 300" class="rpg-nen-hex-svg" role="img" aria-label="Diagrama de afinidades Nen">';

        for ($ring = 1; $ring <= 5; $ring++) {
            $ringR = $r * ($ring / 5);
            $ringPts = [];
            for ($i = 0; $i < 6; $i++) {
                $angle = (M_PI / 3) * $i - M_PI / 2;
                $ringPts[] = round($cx + $ringR * cos($angle), 1) . ',' . round($cy + $ringR * sin($angle), 1);
            }
            $svg .= '<polygon points="' . implode(' ', $ringPts) . '" class="rpg-nen-hex-ring" />';
        }

        for ($i = 0; $i < 6; $i++) {
            $angle = (M_PI / 3) * $i - M_PI / 2;
            $x = round($cx + $r * cos($angle), 1);
            $y = round($cy + $r * sin($angle), 1);
            $svg .= '<line x1="' . $cx . '" y1="' . $cy . '" x2="' . $x . '" y2="' . $y . '" class="rpg-nen-hex-spoke" />';
        }

        $svg .= '<polygon points="' . implode(' ', $valuePoints) . '" class="rpg-nen-hex-value" style="--nen-hex-fill:' . htmlspecialchars($mainColor, ENT_QUOTES) . '" />';

        for ($i = 0; $i < 6; $i++) {
            $aff = $affinities[$i] ?? null;
            if (!$aff) {
                continue;
            }
            $angle = (M_PI / 3) * $i - M_PI / 2;
            $labelR = $r + 28;
            $x = round($cx + $labelR * cos($angle), 1);
            $y = round($cy + $labelR * sin($angle), 1);
            $short = game_get_nen_type_short_label((string)$aff['slug']);
            $unavailable = !empty($aff['unavailable']);
            $maestria = (int)($aff['maestria'] ?? 0);
            $maestriaText = $unavailable ? '—' : ('M' . $maestria);
            $isPrimary = !empty($aff['is_primary']);
            $color = htmlspecialchars((string)$aff['color'], ENT_QUOTES);
            $weight = $isPrimary ? '700' : '600';
            $svg .= '<text x="' . $x . '" y="' . ($y - 6) . '" text-anchor="middle" class="rpg-nen-hex-label" fill="' . $color . '" font-weight="' . $weight . '">' . htmlspecialchars($short) . '</text>';
            $svg .= '<text x="' . $x . '" y="' . ($y + 10) . '" text-anchor="middle" class="rpg-nen-hex-maestria' . ($unavailable ? ' rpg-nen-hex-maestria--na' : '') . '" fill="' . ($unavailable ? '#94a3b8' : $color) . '">' . htmlspecialchars($maestriaText) . '</text>';
        }

        $svg .= '</svg></div>';
        return $svg;
    }
}
