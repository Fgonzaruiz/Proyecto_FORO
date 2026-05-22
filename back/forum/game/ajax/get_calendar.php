<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

// Obtener la fecha on-rol actual
$epoch = strtotime('2026-05-01');
$now = time();
$diff_seconds = max(0, $now - $epoch);
$diff_days_float = $diff_seconds / 86400;
$rol_days = floor($diff_days_float * 1.5) + 1;

$days_per_season = 65;
$days_per_year = $days_per_season * 4; // 260

$rol_year = floor(($rol_days - 1) / $days_per_year) + 1;
$day_of_year = (($rol_days - 1) % $days_per_year) + 1;
$season_idx = floor(($day_of_year - 1) / $days_per_season);
$rol_day = (($day_of_year - 1) % $days_per_season) + 1;

$seasons_names = ['Primavera', 'Verano', 'Otoño', 'Invierno'];
$current_season = $seasons_names[$season_idx] ?? 'Desconocida';

$data = [
    'current' => [
        'day' => $rol_day,
        'season' => $season_idx,
        'season_name' => $current_season,
        'year' => $rol_year,
        'formatted' => "Día {$rol_day} de {$current_season}, Año {$rol_year}"
    ],
    'events' => []
];

// Leer eventos del JSON
$json_path = __DIR__ . '/../data/calendar.json';
$events = [];
if (file_exists($json_path)) {
    $content = file_get_contents($json_path);
    if ($content) {
        $parsed = json_decode($content, true);
        if (is_array($parsed) && !empty($parsed['events'])) {
            $events = $parsed['events'];
        }
    }
}

// Filtrar eventos solo para el año y estación actual
// Opcionalmente podemos enviar todos los de la estación actual o año actual.
// Enviemos todos los de la ESTACIÓN y AÑO actual para llenar el calendario de 100 días.
$filtered_events = [];
foreach ($events as $ev) {
    if (($ev['season'] ?? -1) == $season_idx && ($ev['year'] ?? -1) == $rol_year) {
        $filtered_events[] = $ev;
    }
}

$data['events'] = $filtered_events;

echo json_encode(['ok' => true, 'data' => $data, 'error' => null]);
exit;
