<?php
declare(strict_types=1);

/**
 * Autoload mínimo para el módulo `game/`.
 *
 * Convención:
 * - Namespace raíz: `Game\`
 * - Path base: `game/src/`
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'Game\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    $fullPath = __DIR__ . DIRECTORY_SEPARATOR . $relativePath;

    if (is_file($fullPath)) {
        require_once $fullPath;
    }
});

