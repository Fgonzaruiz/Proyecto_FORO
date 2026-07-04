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
