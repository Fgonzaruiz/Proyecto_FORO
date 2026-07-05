<?php
declare(strict_types=1);

/**
 * Importa Default-theme.xml (plantillas + CSS) al MyBB activo.
 * Uso (admin logueado): /game/public/sync_theme.php
 */
define('IN_MYBB', 1);
require_once dirname(__DIR__, 2) . '/global.php';

header('Content-Type: text/plain; charset=utf-8');

if ((int)($mybb->user['uid'] ?? 0) === 0) {
    http_response_code(403);
    echo "Debes iniciar sesión como administrador.\n";
    exit;
}

require_once MYBB_ROOT . $mybb->config['admin_dir'] . '/inc/functions.php';
require_once MYBB_ROOT . $mybb->config['admin_dir'] . '/inc/functions_themes.php';
require_once MYBB_ROOT . 'game/inc/theme_import.php';

$result = game_run_theme_import_from_repo();
foreach ($result['lines'] as $line) {
    echo $line . "\n";
}
exit($result['ok'] ? 0 : 1);
