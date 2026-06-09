<?php
declare(strict_types=1);

/**
 * Importa Default-theme.xml al MyBB local (actualiza tema "Default" + recache CSS).
 * Uso: php front/import_theme_cli.php
 */
define('IN_MYBB', 1);
require_once __DIR__ . '/../back/forum/global.php';

if (PHP_SAPI !== 'cli') {
    game_require_admin_cp();
}

require_once MYBB_ROOT . $mybb->config['admin_dir'] . '/inc/functions.php';
require_once MYBB_ROOT . $mybb->config['admin_dir'] . '/inc/functions_themes.php';

$xmlPath = __DIR__ . '/Default-theme.xml';
if (!is_file($xmlPath)) {
    fwrite(STDERR, "No se encuentra {$xmlPath}\n");
    exit(1);
}

$contents = file_get_contents($xmlPath);
if ($contents === false || trim($contents) === '') {
    fwrite(STDERR, "XML vacío o ilegible.\n");
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
    echo "Tema existente «Default» tid={$existing['tid']}, templateset={$templateset}\n";
}

if ($templateset > 0) {
    $manifest = json_decode((string)file_get_contents(__DIR__ . '/theme_templates.json'), true);
    if (is_array($manifest)) {
        foreach (array_keys($manifest) as $title) {
            $db->delete_query('templates', "sid='{$templateset}' AND title='" . $db->escape_string($title) . "'");
        }
        echo 'Plantillas del manifiesto limpiadas antes de importar (' . count($manifest) . ").\n";
    }
}

$options = [
    'version_compat' => 1,
];
if ($templateset > 0) {
    $options['templateset'] = $templateset;
}

$themeId = import_theme_xml($contents, $options);

if ($themeId <= 0) {
    $codes = [
        -1 => 'XML inválido o vacío',
        -2 => 'Versión incompatible',
        -3 => 'El tema ya existe (force_name_check)',
        -4 => 'Problema de seguridad en plantillas',
    ];
    fwrite(STDERR, 'Import falló: ' . ($codes[$themeId] ?? "código {$themeId}") . "\n");
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
echo "Listo. Hard refresh (Ctrl+Shift+R) en el navegador.\n";
