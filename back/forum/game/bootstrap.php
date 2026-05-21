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

/**
 * Devuelve la fecha global del rol formateada.
 * Ej: "Día 47 de Verano, Año 3"
 */
function game_global_rol_date(): string {
    $epoch = strtotime('2026-05-01');
    $now = time();
    $diff_days = max(0, floor(($now - $epoch) / 86400));
    $rol_days = ($diff_days * 2) + 1;
    $rol_year = floor(($rol_days - 1) / 400) + 1;
    $day_of_year = (($rol_days - 1) % 400) + 1;
    $season_idx = floor(($day_of_year - 1) / 100);
    $rol_day = (($day_of_year - 1) % 100) + 1;
    $seasons_names = ['Primavera', 'Verano', 'Otoño', 'Invierno'];
    $current_season = $seasons_names[$season_idx] ?? 'Desconocida';
    return "Día {$rol_day} de {$current_season}, Año {$rol_year}";
}

function game_require_staff_character(): void {
    global $mybb, $db;
    $uid = (int)($mybb->user['uid'] ?? 0);
    if ($uid === 0) {
        error_no_permission();
    }
    $prefix = TABLE_PREFIX;
    $cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
    $cfg = $db->fetch_array($cfg_q);
    if (!$cfg || !$cfg['active_pj_id']) {
        error_no_permission();
    }
    $pj_q = $db->query("SELECT is_staff FROM {$prefix}game_personajes WHERE id = " . (int)$cfg['active_pj_id'] . " AND user_id = {$uid} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    if (!$pj || !(int)$pj['is_staff']) {
        error_no_permission();
    }
}


