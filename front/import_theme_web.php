<?php
declare(strict_types=1);

/**
 * Importa Default-theme.xml al MyBB (recache CSS global).
 * Uso (admin logueado): http://foro_hxh.test/front/import_theme_web.php
 */
define('IN_MYBB', 1);
require_once __DIR__ . '/../back/forum/global.php';

header('Content-Type: text/plain; charset=utf-8');

if ((int)($mybb->user['uid'] ?? 0) === 0) {
    http_response_code(403);
    echo "Debes iniciar sesión como administrador.\n";
    exit;
}

require_once MYBB_ROOT . $mybb->config['admin_dir'] . '/inc/functions.php';
require_once MYBB_ROOT . $mybb->config['admin_dir'] . '/inc/functions_themes.php';

$isAdmin = false;
if (function_exists('is_super_admin')) {
    $isAdmin = is_super_admin((int)$mybb->user['uid']);
}
if (!$isAdmin && function_exists('game_get_active_staff_level')) {
    $isAdmin = game_get_active_staff_level((int)$mybb->user['uid']) >= 3;
}
if (!$isAdmin) {
    http_response_code(403);
    echo "Solo administradores.\n";
    exit;
}

$xmlPath = __DIR__ . '/Default-theme.xml';
if (!is_file($xmlPath)) {
    echo "ERROR: No se encuentra Default-theme.xml\n";
    exit(1);
}

$contents = file_get_contents($xmlPath);
if ($contents === false || trim($contents) === '') {
    echo "ERROR: XML vacío.\n";
    exit(1);
}

$query = $db->simple_select('themes', 'tid, name, properties', "name='Default'", ['limit' => 1]);
$existing = $db->fetch_array($query);
$templateset = null;
if ($existing) {
    $props = my_unserialize($existing['properties']);
    if (is_array($props) && isset($props['templateset'])) {
        $templateset = (int)$props['templateset'];
    }
    echo "Tema «Default» tid={$existing['tid']}, templateset={$templateset}\n";
}

if ($templateset > 0) {
    $manifest = json_decode((string)file_get_contents(__DIR__ . '/theme_templates.json'), true);
    if (is_array($manifest)) {
        foreach (array_keys($manifest) as $title) {
            $db->delete_query('templates', "sid='{$templateset}' AND title='" . $db->escape_string($title) . "'");
        }
        echo 'Plantillas limpiadas: ' . count($manifest) . "\n";
    }
}

$options = ['version_compat' => 1];
if ($templateset > 0) {
    $options['templateset'] = $templateset;
}

$themeId = import_theme_xml($contents, $options);
if ($themeId <= 0) {
    echo "ERROR import: código {$themeId}\n";
    exit(1);
}

echo "Import OK — theme_id={$themeId}\n";

$recached = 0;
$q = $db->simple_select('themestylesheets', '*', "tid='{$themeId}'");
while ($sheet = $db->fetch_array($q)) {
    if (cache_stylesheet((int)$sheet['tid'], $sheet['name'], $sheet['stylesheet'])) {
        $db->update_query('themestylesheets', [
            'cachefile' => $db->escape_string($sheet['name']),
        ], "sid='{$sheet['sid']}'");
        $recached++;
    }
}

echo "Stylesheets recacheados: {$recached}\n";
echo "Listo. Ctrl+Shift+R en nen.php\n";
