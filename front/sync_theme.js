const fs = require('fs');
const path = require('path');

const root = __dirname;
const xmlFile = path.join(root, 'Default-theme.xml');
const templatesJsonPath = path.join(root, 'theme_templates.json');

if (!fs.existsSync(templatesJsonPath)) {
    console.error("ERROR: No se encontró theme_templates.json");
    process.exit(2);
}

const templates = JSON.parse(fs.readFileSync(templatesJsonPath, 'utf8'));

// Legacy patterns in source that shouldn't go to XML
const legacyInSource = {
    'index': {
        '#game-calendar-bar': 'Calendario inline legacy (#game-calendar-bar)',
        '#modal_calendar': 'Modal calendario legacy (#modal_calendar)',
        'id="calendar-grid"': 'Grid de 100 días legacy (calendar-grid)',
    }
};

// Must keep markers: if in XML but not in source, sync would delete them
const mustKeepIfInXml = {
    'index': {
        'rpg-tablon-container': 'Tablón premium (rpg-tablon-container)',
        'tablon-fecha-widget': 'Widget fecha del tablón (tablon-fecha-widget)',
        'roleplay-hero': 'Banner hero (roleplay-hero)',
    },
    'header': {
        "game_rol_header_html": 'Fecha on-rol del plugin (game_rol_header_html)',
    }
};

function checkTemplateSecurity(content) {
    // 1. Database password pattern
    const dbPattern = /\$config\[((['"]database['"])|([^'"].*?))\]\[((['"](database|hostname|password|table_prefix|username)['"])|([^'"].*?))\]/i;
    if (dbPattern.test(content)) {
        return true;
    }

    // 2. System calls via backtick / $ { pattern
    if (/\$\s*\{/.test(content)) {
        return true;
    }

    // 3. Other template variables check (MyBB check_template)
    // Replace allowed patterns
    const allowedPattern = /\{\$[a-zA-Z_][a-zA-Z_0-9]*((?:->|::)\$*[a-zA-Z_][a-zA-Z_0-9]*|\[\s*\$*(['"]?)[a-zA-Z_ 0-9 ]+\2\]\s*)*\}/g;
    const allowedReplaced = content.replace(allowedPattern, '');
    if (/\{\$.+?\}/s.test(allowedReplaced)) {
        return true;
    }

    return false;
}

function normalizeTemplateContent(content) {
    content = content.replace(/\r\n/g, '\n');
    return content.trimEnd();
}

function extractTemplateFromXml(xml, name) {
    const escapedName = name.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
    const regex = new RegExp('<template\\s+name="' + escapedName + '"[^>]*><!\\[CDATA\\[([\\s\\S]*?)\\]\\]><\\/template>', 's');
    const match = regex.exec(xml);
    if (!match) return null;
    return normalizeTemplateContent(match[1]);
}

function readSourceTemplate(name, relativePath) {
    const fullPath = path.join(root, relativePath);
    if (!fs.existsSync(fullPath)) return null;
    return normalizeTemplateContent(fs.readFileSync(fullPath, 'utf8'));
}

function summarizeDiff(source, xml) {
    const sourceLines = source === '' ? [] : source.split('\n');
    const xmlLines = xml === '' ? [] : xml.split('\n');
    const max = Math.max(sourceLines.length, xmlLines.length);
    let changed = 0;
    const firstChanges = [];

    for (let i = 0; i < max; i++) {
        const a = sourceLines[i];
        const b = xmlLines[i];
        if (a !== b) {
            changed++;
            if (firstChanges.length < 8) {
                firstChanges.push({
                    line: i + 1,
                    source: a === undefined ? null : a,
                    xml: b === undefined ? null : b
                });
            }
        }
    }

    return {
        source_lines: sourceLines.length,
        xml_lines: xmlLines.length,
        changed_lines: changed,
        first_changes: firstChanges
    };
}

function truncateLine(line, max = 100) {
    if (!line) return '';
    line = line.replace(/\t/g, '  ').replace(/\r/g, '');
    if (line.length <= max) return line;
    return line.substring(0, max - 1) + '…';
}

const command = process.argv[2] || 'diff';
const verbose = process.argv.includes('-v') || process.argv.includes('--verbose');

if (!fs.existsSync(xmlFile)) {
    console.error("ERROR: No se encontró Default-theme.xml");
    process.exit(2);
}

let xmlContent = fs.readFileSync(xmlFile, 'utf8');

if (command === 'diff') {
    let hasDiff = false;
    let hasWarnings = false;
    const inSync = [];
    const outOfSync = [];
    const missingInXml = [];
    const missingSource = [];
    const warnings = [];

    for (const [name, relativePath] of Object.entries(templates)) {
        const source = readSourceTemplate(name, relativePath);
        const xml = extractTemplateFromXml(xmlContent, name);

        if (source === null) {
            missingSource.push({ name, path: relativePath });
            continue;
        }
        if (xml === null) {
            missingInXml.push({ name, path: relativePath });
            continue;
        }

        if (source === xml) {
            inSync.push(name);
        } else {
            outOfSync.push({
                name,
                path: relativePath,
                diff: summarizeDiff(source, xml)
            });
            hasDiff = true;
        }

        if (legacyInSource[name]) {
            for (const [needle, label] of Object.entries(legacyInSource[name])) {
                if (source.includes(needle)) {
                    warnings.push(`[LEGACY en fuente] ${name}: ${label}`);
                    hasWarnings = true;
                }
            }
        }

        if (mustKeepIfInXml[name]) {
            for (const [needle, label] of Object.entries(mustKeepIfInXml[name])) {
                if (xml.includes(needle) && !source.includes(needle)) {
                    warnings.push(`[PERDIDA al sync] ${name}: XML tiene «${label}» pero la fuente no — sync_theme.js lo borraría`);
                    hasWarnings = true;
                }
            }
        }
    }

    console.log("=== diff_theme_source (Node.js) ===");
    console.log("XML: Default-theme.xml");
    console.log("Templates en manifiesto: " + Object.keys(templates).length + "\n");

    if (inSync.length > 0) {
        console.log("OK — en sync (" + inSync.length + "): " + inSync.join(', ') + "\n");
    }

    if (missingSource.length > 0) {
        console.log("AVISO — fuente ausente (" + missingSource.length + "):");
        missingSource.forEach(row => {
            console.log(`  - ${row.name} → ${row.path} (no se puede actualizar este bloque)`);
        });
    }

    if (missingInXml.length > 0) {
        console.log("AVISO — template no encontrado en XML (" + missingInXml.length + "):");
        missingInXml.forEach(row => {
            console.log(`  - ${row.name} → ${row.path}`);
        });
    }

    if (outOfSync.length > 0) {
        console.log("DIFERENCIAS — sync_theme.js SOBRESCRIBIRÍA el XML (" + outOfSync.length + "):");
        outOfSync.forEach(row => {
            const d = row.diff;
            console.log(`  - ${row.name} (${row.path})`);
            console.log(`      fuente: ${d.source_lines} líneas | XML: ${d.xml_lines} líneas | ~${d.changed_lines} líneas distintas`);

            if (verbose && d.first_changes.length > 0) {
                d.first_changes.forEach(change => {
                    const line = change.line;
                    if (change.source === null) {
                        console.log(`      L${line}  (solo en XML): ` + truncateLine(change.xml));
                    } else if (change.xml === null) {
                        console.log(`      L${line}  (solo en fuente): ` + truncateLine(change.source));
                    } else {
                        console.log(`      L${line}  fuente: ` + truncateLine(change.source));
                        console.log(`      L${line}  XML:    ` + truncateLine(change.xml));
                    }
                });
                if (d.changed_lines > d.first_changes.length) {
                    console.log(`      … y ${d.changed_lines - d.first_changes.length} líneas más`);
                }
            }
        });
        if (!verbose) {
            console.log("\n  Usa -v para ver las primeras líneas de cada diff.\n");
        }
    }

    if (warnings.length > 0) {
        console.log("ADVERTENCIAS:");
        warnings.forEach(w => console.log("  ! " + w));
    }

    console.log("");
    if (!hasDiff && !hasWarnings && missingInXml.length === 0) {
        console.log("Resultado: SEGURO sincronizar (fuente ≡ XML en manifiesto).");
        console.log("Siguiente paso: node front/sync_theme.js sync && node front/sync_theme.js validate");
        process.exit(0);
    } else {
        console.log("Resultado: NO sincronizar todavía — revisa fuente vs XML.");
        process.exit(1);
    }

} else if (command === 'sync') {
    // Sync templates into XML
    for (const [name, relativePath] of Object.entries(templates)) {
        const fullPath = path.join(root, relativePath);
        if (fs.existsSync(fullPath)) {
            let htmlContent = fs.readFileSync(fullPath, 'utf8');
            htmlContent = htmlContent.replace(/\r\n/g, '\n');

            const escapedName = name.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
            const regex = new RegExp('(<template\\s+name="' + escapedName + '"[^>]*><!\\[CDATA\\[)([\\s\\S]*?)(\\]\\]><\\/template>)', 's');
            
            if (regex.test(xmlContent)) {
                xmlContent = xmlContent.replace(regex, (match, prefix, content, suffix) => {
                    return prefix + htmlContent + "\n\t\t" + suffix;
                });
                console.log(`Updated template: ${name}`);
            } else {
                console.warn(`Warning: Template ${name} not found in XML to replace.`);
            }
        } else {
            console.error(`File not found: ${relativePath}`);
        }
    }

    // Update global.css with contents of rpg_custom.css
    const rpgCssPath = path.join(root, '../back/forum/rpg_custom.css');
    if (fs.existsSync(rpgCssPath)) {
        let cssContent = fs.readFileSync(rpgCssPath, 'utf8');
        cssContent = cssContent.replace(/\r\n/g, '\n');

        const cssRegex = /(<stylesheet\s+name="global\.css"[^>]*><\!\[CDATA\[)([\s\S]*?)(\<\/stylesheet\>)/s;
        if (cssRegex.test(xmlContent)) {
            xmlContent = xmlContent.replace(cssRegex, (match, prefix, content, suffix) => {
                const marker = "/* RPG Premium Modern Theme */";
                const markerIndex = content.indexOf(marker);
                let baseCss = content;
                if (markerIndex !== -1) {
                    baseCss = content.substring(0, markerIndex);
                } else {
                    baseCss = content + "\n";
                }
                // Clean trailing CDATA end just in case
                baseCss = baseCss.replace(/\]\]>\s*$/, '');
                
                return prefix + baseCss + marker + "\n" + cssContent + "]]>\n\t\t" + suffix;
            });
            console.log("Updated global.css in XML from rpg_custom.css");
        } else {
            console.warn("Warning: global.css stylesheet tag not found in XML.");
        }
    } else {
        console.warn("rpg_custom.css not found, skipping stylesheet update.");
    }

    fs.writeFileSync(xmlFile, xmlContent, 'utf8');
    console.log("XML updated successfully.");
    process.exit(0);

} else if (command === 'validate') {
    const templatesDir = path.join(root, 'templates/mybb');
    
    function walkDir(dir, fileList = []) {
        const files = fs.readdirSync(dir);
        files.forEach(file => {
            const filePath = path.join(dir, file);
            if (fs.statSync(filePath).isDirectory()) {
                walkDir(filePath, fileList);
            } else if (file.endsWith('.html')) {
                fileList.push(filePath);
            }
        });
        return fileList;
    }

    const htmlFiles = walkDir(templatesDir);
    const failed = [];

    htmlFiles.forEach(filePath => {
        const content = fs.readFileSync(filePath, 'utf8');
        if (checkTemplateSecurity(content)) {
            const relPath = path.relative(root, filePath);
            failed.push(relPath);
        }
    });

    if (failed.length === 0) {
        console.log("OK: Ninguna plantilla fuente dispara el escáner de MyBB.");
        process.exit(0);
    } else {
        console.error("FALLO: Estas plantillas bloquearían la importación del tema:");
        failed.forEach(f => console.error(" - " + f));
        process.exit(1);
    }
} else {
    console.error("ERROR: Comando desconocido: " + command);
    process.exit(2);
}
