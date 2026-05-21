<?php
declare(strict_types=1);

// Mostrar errores PHP en pantalla para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Bootstrap mínimo para páginas /game/* dentro de MyBB.
 */

define('IN_MYBB', 1);

// Intentar cargar MyBB. Si falla, mostrar un mensaje claro.
$bootstrap_path = dirname(__DIR__) . '/global.php';
if (!file_exists($bootstrap_path)) {
    http_response_code(500);
    echo '<h1>Error: No se encuentra global.php</h1>';
    echo '<p>Buscado en: ' . htmlspecialchars($bootstrap_path) . '</p>';
    echo '<p>Asegúrate de que la instalación de MyBB está en el directorio correcto.</p>';
    exit;
}

require_once $bootstrap_path;

// Verificar que $db está disponible
if (!isset($db) || $db === null) {
    http_response_code(500);
    echo '<h1>Error: Conexión a base de datos no disponible</h1>';
    echo '<p>global.php se cargó pero $db no está definido. Revisa la configuración de MyBB.</p>';
    exit;
}

// Autoload local del módulo game.
require_once __DIR__ . '/src/autoload.php';

/**
 * Renderiza una página pública del juego dentro del contenedor HTML de MyBB.
 */
function game_render_page(string $title, string $content): void {
    global $headerinclude, $header, $footer;
    
    echo '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
    ' . ($headerinclude ?? '') . '
</head>
<body class="rpg-game-page">
    ' . ($header ?? '') . '
    <div class="wrapper" style="margin-top: 20px; padding: 0 10px;">
        ' . $content . '
    </div>
    ' . ($footer ?? '') . '
</body>
</html>';
}


