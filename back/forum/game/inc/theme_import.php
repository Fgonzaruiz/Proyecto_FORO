<?php
declare(strict_types=1);

/**
 * Importación de tema compartida (CLI: front/import_theme_cli.php).
 */

if (!function_exists('game_theme_import_find_xml')) {
    function game_theme_import_find_xml(): ?string
    {
        $candidates = [
            dirname(__DIR__, 4) . '/front/Default-theme.xml',
            dirname(__DIR__, 3) . '/../front/Default-theme.xml',
        ];
        foreach ($candidates as $path) {
            $real = realpath($path);
            if ($real !== false && is_file($real)) {
                return $real;
            }
        }
        return null;
    }
}

if (!function_exists('game_theme_templateset_ids')) {
    /** @return list<int> */
    function game_theme_templateset_ids(): array
    {
        global $db;
        $sets = [];
        $q = $db->simple_select('themes', 'properties');
        while ($row = $db->fetch_array($q)) {
            $props = my_unserialize($row['properties']);
            if (is_array($props) && !empty($props['templateset'])) {
                $sets[(int)$props['templateset']] = true;
            }
        }
        return array_keys($sets);
    }
}

if (!function_exists('game_sync_manifest_templates')) {
    function game_sync_manifest_templates(int $templatesetId, array $manifest, string $manifestBaseDir): int
    {
        global $db;
        if ($templatesetId <= 0) {
            return 0;
        }
        $updated = 0;
        foreach ($manifest as $title => $rel) {
            $path = $manifestBaseDir . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (!is_file($path)) {
                continue;
            }
            $html = str_replace("\r\n", "\n", (string)file_get_contents($path));
            $titleEsc = $db->escape_string($title);
            $exists = $db->simple_select('templates', 'tid', "sid='{$templatesetId}' AND title='{$titleEsc}'", 1);
            if ($db->fetch_array($exists)) {
                $db->update_query('templates', [
                    'template' => $db->escape_string($html),
                    'dateline' => TIME_NOW,
                ], "sid='{$templatesetId}' AND title='{$titleEsc}'");
            } else {
                $db->insert_query('templates', [
                    'title' => $titleEsc,
                    'template' => $db->escape_string($html),
                    'sid' => $templatesetId,
                    'version' => '1',
                    'dateline' => TIME_NOW,
                ]);
            }
            $updated++;
        }
        return $updated;
    }
}

if (!function_exists('game_propagate_stylesheets')) {
    function game_propagate_stylesheets(int $sourceTid, int $targetTid): int
    {
        global $db;
        if ($sourceTid <= 0 || $targetTid <= 0 || $sourceTid === $targetTid) {
            return 0;
        }
        $copied = 0;
        $q = $db->simple_select('themestylesheets', '*', "tid='{$sourceTid}'");
        while ($sheet = $db->fetch_array($q)) {
            $nameEsc = $db->escape_string($sheet['name']);
            $target = $db->fetch_array($db->simple_select('themestylesheets', 'sid', "tid='{$targetTid}' AND name='{$nameEsc}'", 1));
            if ($target) {
                $db->update_query('themestylesheets', [
                    'stylesheet' => $db->escape_string($sheet['stylesheet']),
                    'lastmodified' => TIME_NOW,
                ], "sid='{$target['sid']}'");
            }
        }
        $q2 = $db->simple_select('themestylesheets', '*', "tid='{$targetTid}'");
        while ($sheet = $db->fetch_array($q2)) {
            if (cache_stylesheet((int)$sheet['tid'], $sheet['name'], $sheet['stylesheet'])) {
                $db->update_query('themestylesheets', [
                    'cachefile' => $db->escape_string($sheet['name']),
                ], "sid='{$sheet['sid']}'");
                $copied++;
            }
        }
        return $copied;
    }
}

if (!function_exists('game_run_theme_import_from_repo')) {
    /**
     * @return array{ok: bool, lines: list<string>}
     */
    function game_run_theme_import_from_repo(): array
    {
        global $db;

        $lines = [];
        $xmlPath = game_theme_import_find_xml();
        if ($xmlPath === null) {
            return ['ok' => false, 'lines' => ['ERROR: No se encuentra Default-theme.xml']];
        }

        $manifestPath = dirname($xmlPath) . '/theme_templates.json';
        $manifest = json_decode((string)file_get_contents($manifestPath), true);
        if (!is_array($manifest)) {
            return ['ok' => false, 'lines' => ['ERROR: theme_templates.json inválido']];
        }

        $contents = file_get_contents($xmlPath);
        if ($contents === false || trim($contents) === '') {
            return ['ok' => false, 'lines' => ['ERROR: XML vacío']];
        }

        $query = $db->simple_select('themes', 'tid, name, properties', "name='Default'", ['limit' => 1]);
        $existing = $db->fetch_array($query);
        $templateset = null;
        if ($existing) {
            $props = my_unserialize($existing['properties']);
            if (is_array($props) && isset($props['templateset'])) {
                $templateset = (int)$props['templateset'];
            }
            $lines[] = "Tema «Default» tid={$existing['tid']}, templateset={$templateset}";
        }

        if ($templateset > 0) {
            foreach (array_keys($manifest) as $title) {
                $db->delete_query('templates', "sid='{$templateset}' AND title='" . $db->escape_string($title) . "'");
            }
            $lines[] = 'Plantillas Default limpiadas (' . count($manifest) . ').';
        }

        $options = ['version_compat' => 1];
        if ($templateset > 0) {
            $options['templateset'] = $templateset;
        }

        $themeId = import_theme_xml($contents, $options);
        if ($themeId <= 0) {
            $lines[] = "ERROR import: código {$themeId}";
            return ['ok' => false, 'lines' => $lines];
        }

        $lines[] = "Import OK — theme_id={$themeId}";

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
        $lines[] = "Stylesheets recacheados (Default): {$recached}";

        foreach (game_theme_templateset_ids() as $sid) {
            if ($templateset !== null && $sid === $templateset) {
                continue;
            }
            $n = game_sync_manifest_templates($sid, $manifest, dirname($xmlPath));
            if ($n > 0) {
                $lines[] = "Plantillas sincronizadas templateset={$sid}: {$n}";
            }
        }

        $active = $db->fetch_array($db->simple_select('themes', 'tid,name', "def='1'", 1));
        if ($active && (int)$active['tid'] !== (int)$themeId) {
            $n = game_propagate_stylesheets((int)$themeId, (int)$active['tid']);
            $lines[] = "CSS propagado a «{$active['name']}» tid={$active['tid']} — hojas: {$n}";
        }

        $lines[] = 'Listo. Hard refresh (Ctrl+Shift+R).';
        return ['ok' => true, 'lines' => $lines];
    }
}
