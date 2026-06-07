<?php
/**
 * apply_templates.php
 * Aplica cambios de templates y CSS directamente a la BD de MyBB.
 * Uso: php tools/apply_templates.php
 * No requiere importar XML.
 */

declare(strict_types=1);

define('IN_MYBB', 1);
$root = dirname(__DIR__) . '/back/forum';
require_once $root . '/global.php';

global $db;

$prefix = TABLE_PREFIX;
// Auto-detect RPG theme and templateset by name
$ts_q = $db->query("SELECT sid FROM {$prefix}templatesets WHERE title = 'RPG Templates' LIMIT 1");
$ts = $db->fetch_array($ts_q);
$theme_sid = $ts ? (int)$ts['sid'] : 0;

$th_q = $db->query("SELECT tid FROM {$prefix}themes WHERE name = 'RPG' LIMIT 1");
$th = $db->fetch_array($th_q);
$theme_tid = $th ? (int)$th['tid'] : 0;

if (!$theme_sid || !$theme_tid) {
    echo "[ERROR] No se pudo auto-detectar RPG Templates (sid={$theme_sid}) o Theme RPG (tid={$theme_tid}). Ejecuta check_sets.php.\n";
    exit(1);
}
$templates_dir = dirname(__DIR__) . '/front/templates/mybb';
$manifest_file = dirname(__DIR__) . '/front/theme_templates.json';

$errors = [];
$updated = 0;
$skipped = 0;

echo "=== Apply Templates & CSS ===\n\n";

// 1. Check templateset exists
$ts_q = $db->query("SELECT sid FROM {$prefix}templatesets WHERE sid = {$theme_sid}");
$ts = $db->fetch_array($ts_q);
if (!$ts) {
    echo "[ERROR] Templateset ID {$theme_sid} no encontrado.\n";
    exit(1);
}
echo "[OK] Templateset {$theme_sid} encontrado.\n";

// 2. Check theme exists
$th_q = $db->query("SELECT tid, name FROM {$prefix}themes WHERE tid = {$theme_tid}");
$th = $db->fetch_array($th_q);
if (!$th) {
    echo "[ERROR] Theme ID {$theme_tid} no encontrado.\n";
    exit(1);
}
echo "[OK] Theme '{$th['name']}' (tid={$theme_tid}) encontrado.\n\n";

// 3. Load manifest
$manifest = json_decode(file_get_contents($manifest_file), true);
if (!$manifest) {
    echo "[ERROR] No se pudo leer {$manifest_file}\n";
    exit(1);
}
echo "Manifest: " . count($manifest) . " templates listados.\n";

// 4. Process each template
foreach ($manifest as $name => $rel_path) {
    $file_path = $templates_dir . '/' . str_replace('templates/mybb/', '', $rel_path);
    $file_path = str_replace('/', DIRECTORY_SEPARATOR, $file_path);
    
    if (!file_exists($file_path)) {
        $errors[] = "No encontrado: {$rel_path}";
        continue;
    }
    
    $html = file_get_contents($file_path);
    $html = str_replace("\r\n", "\n", $html);
    
    // Check if template exists in DB
    $q = $db->query("SELECT tid FROM {$prefix}templates WHERE title = '{$db->escape_string($name)}' AND sid = {$theme_sid} LIMIT 1");
    $existing = $db->fetch_array($q);
    
    $now = time();
    
    if ($existing) {
        $db->write_query("UPDATE {$prefix}templates SET template = '{$db->escape_string($html)}', version = '', dateline = {$now} WHERE tid = {$existing['tid']}");
        echo "  [UPDATE] {$name}\n";
    } else {
        $db->write_query("INSERT INTO {$prefix}templates (title, template, sid, version, dateline) VALUES ('{$db->escape_string($name)}', '{$db->escape_string($html)}', {$theme_sid}, '', {$now})");
        echo "  [INSERT] {$name}\n";
    }
    $updated++;
}

// 5. Update global.css from rpg_custom.css
$rpg_css_file = $root . '/rpg_custom.css';
$minimal_css_file = dirname(__DIR__) . '/front/templates/mybb/global/mybb-minimal.css';

if (file_exists($rpg_css_file)) {
    $rpg_css = file_get_contents($rpg_css_file);
    $rpg_css = str_replace("\r\n", "\n", $rpg_css);
    $marker = "/* RPG Premium Modern Theme */";
    
    // Base CSS
    $base_css = '';
    if (file_exists($minimal_css_file)) {
        $base_css = file_get_contents($minimal_css_file);
        $base_css = str_replace("\r\n", "\n", $base_css);
        if (!str_ends_with($base_css, "\n")) $base_css .= "\n";
    }
    
    $new_css = $base_css . $marker . "\n" . $rpg_css;
    
    // Check if stylesheet exists
    $css_q = $db->query("SELECT sid FROM {$prefix}themestylesheets WHERE name = 'global.css' AND tid = {$theme_tid} LIMIT 1");
    $css_existing = $db->fetch_array($css_q);
    
    if ($css_existing) {
        $now = time();
        $db->write_query("UPDATE {$prefix}themestylesheets SET stylesheet = '{$db->escape_string($new_css)}', lastmodified = {$now} WHERE sid = {$css_existing['sid']}");
        echo "\n[UPDATE] global.css (stylesheets)\n";
    } else {
        $db->write_query("INSERT INTO {$prefix}themestylesheets (name, tid, stylesheet, cachefile, lastmodified) VALUES ('global.css', {$theme_tid}, '{$db->escape_string($new_css)}', '', " . time() . ")");
        echo "\n[INSERT] global.css (stylesheets)\n";
    }
} else {
    echo "\n[SKIP] rpg_custom.css no encontrado.\n";
}

// 6. Clear cache
$db->write_query("DELETE FROM {$prefix}datacache WHERE title = 'templates'");
$db->write_query("DELETE FROM {$prefix}datacache WHERE title = 'theme'");

echo "\n=== Resumen ===\n";
echo "Templates procesados: {$updated}\n";
echo "Errores: " . count($errors) . "\n";
if (!empty($errors)) {
    echo "Detalle de errores:\n";
    foreach ($errors as $e) {
        echo "  - {$e}\n";
    }
}
echo "\n[OK] Cambios aplicados directamente a la BD.\n";
echo "NO necesitas importar XML. Refresca cualquier página del foro.\n";
