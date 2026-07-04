<?php
declare(strict_types=1);

// Errores: solo verbose si GAME_DEBUG está definido (p. ej. en inc/config.php del servidor).
if (defined('GAME_DEBUG') && GAME_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
}

/**
 * Bootstrap mínimo para páginas /game/* dentro de MyBB.
 */

define('IN_MYBB', 1);

// Evitar que un BOM/aviso PHP rompa el DOCTYPE (quirks mode → estilos no aplican).
if (ob_get_level() === 0) {
    ob_start();
}

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
require_once __DIR__ . '/inc/inventory_helpers.php';
require_once __DIR__ . '/inc/stat_helpers.php';
require_once __DIR__ . '/inc/oracle_helpers.php';
require_once __DIR__ . '/inc/grado_helpers.php';
require_once __DIR__ . '/inc/oficios_helpers.php';
require_once __DIR__ . '/inc/disciplinas_helpers.php';
require_once __DIR__ . '/inc/estilos_canonicos_helpers.php';
require_once __DIR__ . '/inc/universe_helpers.php';
require_once __DIR__ . '/inc/nen_helpers.php';
require_once __DIR__ . '/inc/rol_calendar_helpers.php';
require_once __DIR__ . '/inc/pd_helpers.php';
require_once __DIR__ . '/inc/mission_helpers.php';
require_once __DIR__ . '/inc/post_rpg_debug.php';

/**
 * Renderiza una página pública del juego dentro del contenedor HTML de MyBB.
 */
function game_render_page(string $title, string $content): void {
    global $headerinclude, $header, $footer;

    // Descartar salida capturada antes del DOCTYPE (avisos PHP, BOM, hooks).
    while (ob_get_level() > 1) {
        ob_end_clean();
    }
    if (ob_get_level() > 0) {
        ob_clean();
    }

    // global.php ya evalúa headerinclude/header/footer con {$stylesheets} expandido.
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
    ' . $content . '
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
    return "Día {$rol_day} de {$current_season}, Año {$rol_year}";
}

/**
 * Bloquea herramientas de mantenimiento en producción (404).
 * Permitido si GAME_DEBUG o GAME_ALLOW_MAINTENANCE están definidos.
 */
function game_deny_public_maintenance(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    if (defined('GAME_ALLOW_MAINTENANCE') && GAME_ALLOW_MAINTENANCE) {
        return;
    }
    if (defined('GAME_DEBUG') && GAME_DEBUG) {
        return;
    }
    global $mybb;
    if ((int)($mybb->user['uid'] ?? 0) > 0 && (int)($mybb->usergroup['cancp'] ?? 0) === 1) {
        return;
    }
    http_response_code(404);
    exit;
}

/**
 * Scripts de mantenimiento: solo administrador MyBB (cancp).
 */
function game_require_admin_cp(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    global $mybb;
    if ((int)($mybb->user['uid'] ?? 0) === 0 || (int)($mybb->usergroup['cancp'] ?? 0) !== 1) {
        error_no_permission();
    }
}

/**
 * Log estructurado de acciones mutadoras (una línea JSON en error_log).
 *
 * @param array<string, mixed> $context
 */
function game_log_action(string $action, array $context = []): void
{
    $payload = array_merge(['action' => $action, 'ts' => date('c')], $context);
    error_log('[game] ' . json_encode($payload, JSON_UNESCAPED_UNICODE));
}

function game_get_active_pj_id(int $userId): int
{
    global $db;
    if ($userId <= 0) {
        return 0;
    }
    $prefix = TABLE_PREFIX;
    $cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$userId} LIMIT 1");
    $cfg = $db->fetch_array($cfg_q);
    $activePjId = $cfg ? (int)$cfg['active_pj_id'] : 0;
    if ($activePjId <= 0) {
        return 0;
    }
    $pj_q = $db->query(
        "SELECT id FROM {$prefix}game_personajes WHERE id = {$activePjId} AND user_id = {$userId} LIMIT 1"
    );
    return $db->fetch_array($pj_q) ? $activePjId : 0;
}

function game_get_active_staff_level(int $userId): int
{
    global $db;
    if ($userId <= 0) {
        return 0;
    }
    $prefix = TABLE_PREFIX;
    $cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$userId} LIMIT 1");
    $cfg = $db->fetch_array($cfg_q);
    $activePjId = $cfg ? (int)$cfg['active_pj_id'] : 0;
    if ($activePjId <= 0) {
        return 0;
    }
    $pj_q = $db->query(
        "SELECT staff_level, is_staff FROM {$prefix}game_personajes
         WHERE id = {$activePjId} AND user_id = {$userId} LIMIT 1"
    );
    $pj = $db->fetch_array($pj_q);
    if (!$pj || !(int)$pj['is_staff']) {
        return 0;
    }
    return (int)$pj['staff_level'];
}

function game_require_staff_character(): void {
    if (PHP_SAPI === 'cli') {
        return;
    }
    if (game_get_active_staff_level((int)($GLOBALS['mybb']->user['uid'] ?? 0)) === 0) {
        error_no_permission();
    }
}

function game_require_staff_level(int $minLevel): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    game_require_staff_character();
    $level = game_get_active_staff_level((int)($GLOBALS['mybb']->user['uid'] ?? 0));
    if ($level < $minLevel) {
        error_no_permission();
    }
}


