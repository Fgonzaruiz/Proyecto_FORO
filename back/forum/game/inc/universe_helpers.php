<?php
declare(strict_types=1);

function game_universe_config(string $key) {
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../src/Config/universe.php';
    }
    return $config[$key] ?? null;
}
