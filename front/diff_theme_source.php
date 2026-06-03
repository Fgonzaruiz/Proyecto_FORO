<?php
/**
 * Compara plantillas fuente (front/templates/) con Default-theme.xml.
 * SOLO LECTURA — no modifica ningún archivo.
 *
 * Uso:
 *   php diff_theme_source.php          # resumen
 *   php diff_theme_source.php -v       # muestra primeras líneas de diff por template
 *
 * Exit codes:
 *   0 = fuente y XML coinciden en todos los templates del manifiesto
 *   1 = hay diferencias (update_theme.php las sobrescribiría)
 *   2 = error fatal (XML ausente, ilegible, etc.)
 */
declare(strict_types=1);

$root = __DIR__;
$xmlFile = $root . '/Default-theme.xml';
$verbose = in_array('-v', $argv, true) || in_array('--verbose', $argv, true);

$templates = require $root . '/theme_templates.php';

/** Patrones legacy en fuente que no deben volver al XML. */
$legacyInSource = [
    'index' => [
        '#game-calendar-bar' => 'Calendario inline legacy (#game-calendar-bar)',
        '#modal_calendar' => 'Modal calendario legacy (#modal_calendar)',
        'id="calendar-grid"' => 'Grid de 100 días legacy (calendar-grid)',
    ],
];

/** Marcadores deseados: si están en XML pero no en fuente, sync los borraría. */
$mustKeepIfInXml = [
    'index' => [
        'rpg-tablon-container' => 'Tablón premium (rpg-tablon-container)',
        'tablon-fecha-widget' => 'Widget fecha del tablón (tablon-fecha-widget)',
        'roleplay-hero' => 'Banner hero (roleplay-hero)',
    ],
    'header' => [
        "game_rol_header_html" => 'Fecha on-rol del plugin (game_rol_header_html)',
    ],
];

if (!is_file($xmlFile)) {
    fwrite(STDERR, "ERROR: no se encontró {$xmlFile}\n");
    exit(2);
}

$xmlContent = (string)file_get_contents($xmlFile);
if ($xmlContent === '') {
    fwrite(STDERR, "ERROR: {$xmlFile} está vacío\n");
    exit(2);
}

function normalize_template_content(string $content): string
{
    $content = str_replace("\r\n", "\n", $content);
    return rtrim($content, "\n\t ");
}

function extract_template_from_xml(string $xml, string $name): ?string
{
    $pattern = '/<template\s+name="' . preg_quote($name, '/') . '"[^>]*><!\[CDATA\[(.*?)\]\]><\/template>/s';
    if (!preg_match($pattern, $xml, $matches)) {
        return null;
    }
    return normalize_template_content($matches[1]);
}

function read_source_template(string $root, string $relativePath): ?string
{
    $path = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($path)) {
        return null;
    }
    return normalize_template_content((string)file_get_contents($path));
}

function summarize_diff(string $source, string $xml): array
{
    $sourceLines = $source === '' ? [] : explode("\n", $source);
    $xmlLines = $xml === '' ? [] : explode("\n", $xml);
    $max = max(count($sourceLines), count($xmlLines));
    $changed = 0;
    $firstChanges = [];

    for ($i = 0; $i < $max; $i++) {
        $a = $sourceLines[$i] ?? null;
        $b = $xmlLines[$i] ?? null;
        if ($a !== $b) {
            $changed++;
            if (count($firstChanges) < 8) {
                $firstChanges[] = [
                    'line' => $i + 1,
                    'source' => $a,
                    'xml' => $b,
                ];
            }
        }
    }

    return [
        'source_lines' => count($sourceLines),
        'xml_lines' => count($xmlLines),
        'changed_lines' => $changed,
        'first_changes' => $firstChanges,
    ];
}

$inSync = [];
$outOfSync = [];
$missingInXml = [];
$missingSource = [];
$warnings = [];

foreach ($templates as $name => $relativePath) {
    $source = read_source_template($root, $relativePath);
    $xml = extract_template_from_xml($xmlContent, $name);

    if ($source === null) {
        $missingSource[] = ['name' => $name, 'path' => $relativePath];
        continue;
    }
    if ($xml === null) {
        $missingInXml[] = ['name' => $name, 'path' => $relativePath];
        continue;
    }

    if ($source === $xml) {
        $inSync[] = $name;
    } else {
        $outOfSync[] = [
            'name' => $name,
            'path' => $relativePath,
            'diff' => summarize_diff($source, $xml),
        ];
    }

    if (isset($legacyInSource[$name])) {
        foreach ($legacyInSource[$name] as $needle => $label) {
            if (strpos($source, $needle) !== false) {
                $warnings[] = "[LEGACY en fuente] {$name}: {$label}";
            }
        }
    }

    if (isset($mustKeepIfInXml[$name])) {
        foreach ($mustKeepIfInXml[$name] as $needle => $label) {
            if (strpos($xml, $needle) !== false && strpos($source, $needle) === false) {
                $warnings[] = "[PERDIDA al sync] {$name}: XML tiene «{$label}» pero la fuente no — update_theme.php lo borraría";
            }
        }
    }
}

echo "=== diff_theme_source.php (solo lectura) ===\n";
echo "XML: Default-theme.xml\n";
echo "Templates en manifiesto: " . count($templates) . "\n\n";

if ($inSync !== []) {
    echo "OK — en sync (" . count($inSync) . "): " . implode(', ', $inSync) . "\n";
}

if ($missingSource !== []) {
    echo "\nAVISO — fuente ausente (" . count($missingSource) . "):\n";
    foreach ($missingSource as $row) {
        echo "  - {$row['name']} → {$row['path']} (update_theme.php no puede actualizar este bloque)\n";
    }
}

if ($missingInXml !== []) {
    echo "\nAVISO — template no encontrado en XML (" . count($missingInXml) . "):\n";
    foreach ($missingInXml as $row) {
        echo "  - {$row['name']} → {$row['path']}\n";
    }
}

if ($outOfSync !== []) {
    echo "\nDIFERENCIAS — update_theme.php SOBRESCRIBIRÍA el XML (" . count($outOfSync) . "):\n";
    foreach ($outOfSync as $row) {
        $d = $row['diff'];
        echo "  - {$row['name']} ({$row['path']})\n";
        echo "      fuente: {$d['source_lines']} líneas | XML: {$d['xml_lines']} líneas | ~{$d['changed_lines']} líneas distintas\n";

        if ($verbose && $d['first_changes'] !== []) {
            foreach ($d['first_changes'] as $change) {
                $line = $change['line'];
                if ($change['source'] === null) {
                    echo "      L{$line}  (solo en XML): " . truncate_line((string)$change['xml']) . "\n";
                } elseif ($change['xml'] === null) {
                    echo "      L{$line}  (solo en fuente): " . truncate_line((string)$change['source']) . "\n";
                } else {
                    echo "      L{$line}  fuente: " . truncate_line((string)$change['source']) . "\n";
                    echo "      L{$line}  XML:    " . truncate_line((string)$change['xml']) . "\n";
                }
            }
            if ($d['changed_lines'] > count($d['first_changes'])) {
                echo "      … y " . ($d['changed_lines'] - count($d['first_changes'])) . " líneas más\n";
            }
        }
    }
    if (!$verbose) {
        echo "\n  Usa -v para ver las primeras líneas de cada diff.\n";
    }
}

if ($warnings !== []) {
    echo "\nADVERTENCIAS:\n";
    foreach ($warnings as $warning) {
        echo "  ! {$warning}\n";
    }
}

echo "\n";
if ($outOfSync === [] && $missingInXml === [] && $warnings === []) {
    echo "Resultado: SEGURO sincronizar (fuente ≡ XML en manifiesto).\n";
    echo "Siguiente paso: php update_theme.php && php validate_theme_security.php\n";
    exit(0);
}

if ($outOfSync !== [] || $warnings !== []) {
    echo "Resultado: NO sincronizar todavía — revisa fuente vs XML antes de update_theme.php.\n";
    exit(1);
}

echo "Resultado: revisar avisos antes de sincronizar.\n";
exit(1);

function truncate_line(string $line, int $max = 100): string
{
    $line = str_replace(["\t", "\r"], ['  ', ''], $line);
    if (strlen($line) <= $max) {
        return $line;
    }
    return substr($line, 0, $max - 1) . '…';
}
