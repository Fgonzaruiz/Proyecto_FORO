<?php
declare(strict_types=1);

/** Días on-rol absolutos desde el epoch del calendario (1 = primer día). */
function game_rol_days_at(?int $timestamp = null): int
{
    $epoch = strtotime('2026-05-01');
    $now = $timestamp ?? time();
    $diffSeconds = max(0, $now - $epoch);
    $diffDaysFloat = $diffSeconds / 86400;

    return (int)floor($diffDaysFloat * 1.5) + 1;
}

/** Etiqueta legible: "Día 47 de Verano, Año 3". */
function game_rol_date_label(int $rolDays): string
{
    $rolDays = max(1, $rolDays);
    $daysPerSeason = 65;
    $daysPerYear = $daysPerSeason * 4;

    $rolYear = (int)floor(($rolDays - 1) / $daysPerYear) + 1;
    $dayOfYear = (($rolDays - 1) % $daysPerYear) + 1;
    $seasonIdx = (int)floor(($dayOfYear - 1) / $daysPerSeason);
    $rolDay = (($dayOfYear - 1) % $daysPerSeason) + 1;

    $seasons = ['Primavera', 'Verano', 'Otoño', 'Invierno'];
    $season = $seasons[$seasonIdx] ?? 'Desconocida';

    return "Día {$rolDay} de {$season}, Año {$rolYear}";
}

/** @return array{season_name: string, season_key: string, icon_class: string} */
function game_rol_season_meta(int $rolDays): array
{
    $rolDays = max(1, $rolDays);
    $daysPerSeason = 65;
    $daysPerYear = $daysPerSeason * 4;
    $dayOfYear = (($rolDays - 1) % $daysPerYear) + 1;
    $seasonIdx = (int)floor(($dayOfYear - 1) / $daysPerSeason);
    $seasons = ['Primavera', 'Verano', 'Otoño', 'Invierno'];
    $keys = ['primavera', 'verano', 'otono', 'invierno'];
    $icons = ['fa-seedling', 'fa-sun', 'fa-leaf', 'fa-snowflake'];
    $idx = min(3, max(0, $seasonIdx));
    return [
        'season_name' => $seasons[$idx],
        'season_key' => $keys[$idx],
        'icon_class' => $icons[$idx],
    ];
}
