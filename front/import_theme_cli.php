<?php
declare(strict_types=1);

/**
 * Importa Default-theme.xml al MyBB local.
 * Actualiza tema Default + propaga plantillas/CSS al tema activo (def=1, p. ej. «RPG»).
 * Uso: php front/import_theme_cli.php
 */
define('IN_MYBB', 1);
require_once __DIR__ . '/../back/forum/global.php';

if (PHP_SAPI !== 'cli') {
    game_require_admin_cp();
}

require_once MYBB_ROOT . $mybb->config['admin_dir'] . '/inc/functions.php';
require_once MYBB_ROOT . $mybb->config['admin_dir'] . '/inc/functions_themes.php';
require_once MYBB_ROOT . 'game/inc/theme_import.php';

$result = game_run_theme_import_from_repo();
foreach ($result['lines'] as $line) {
    echo $line . "\n";
}
exit($result['ok'] ? 0 : 1);
