<?php
declare(strict_types=1);

if (!defined('GAME_NAV_SPEED_FACTOR')) {
    define('GAME_NAV_SPEED_FACTOR', 10);
}
if (!defined('GAME_NAV_MAP_WIDTH')) {
    define('GAME_NAV_MAP_WIDTH', 1000);
}
if (!defined('GAME_NAV_MAP_HEIGHT')) {
    define('GAME_NAV_MAP_HEIGHT', 1000);
}
if (!defined('GAME_NAV_EVENTS_MIN')) {
    define('GAME_NAV_EVENTS_MIN', 0);
}
if (!defined('GAME_NAV_EVENTS_MAX')) {
    define('GAME_NAV_EVENTS_MAX', 8);
}
if (!defined('GAME_NAV_NO_INSTRUMENT_SPEED_PENALTY')) {
    define('GAME_NAV_NO_INSTRUMENT_SPEED_PENALTY', 1.0);
}

/** @return array<string, string> */
function game_nav_sea_zone_labels(): array
{
    return [
        'east_blue' => 'East Blue',
        'west_blue' => 'West Blue',
        'north_blue' => 'North Blue',
        'south_blue' => 'South Blue',
        'grand_line' => 'Grand Line',
        'new_world' => 'New World',
        'calm_belt' => 'Calm Belt',
        'florian_triangle' => 'Triángulo de Florian',
    ];
}
