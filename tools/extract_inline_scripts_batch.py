#!/usr/bin/env python3
"""Extract remaining inline <script> blocks from game/public/*.php into jscripts/game/*.js"""
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

EXTRACTS = [
    {
        "php": "back/forum/game/public/calendario.php",
        "js": "back/forum/jscripts/game/calendario.js",
        "config_var": "CALENDARIO_CONFIG",
        "config_php": "window.CALENDARIO_CONFIG = { bburl: '<?= $bburl ?>' };",
        "bburl_php": "$bburl",
        "replacements": [
            (r"fetch\('<\?= \$bburl \?>/game/ajax/get_calendar\.php'\)", "fetch(bburl + '/game/ajax/get_calendar.php')"),
        ],
        "window_exports": ["showCalendarEvents"],
    },
    {
        "php": "back/forum/game/public/manual.php",
        "js": "back/forum/jscripts/game/manual.js",
        "config_var": "MANUAL_CONFIG",
        "config_php": "window.MANUAL_CONFIG = {};",
        "bburl_php": "$mybb->settings['bburl']",
        "strip_outer_iife": True,
        "window_exports": ["filterManualToc"],
    },
    {
        "php": "back/forum/game/public/notificaciones.php",
        "js": "back/forum/jscripts/game/notificaciones.js",
        "config_var": "NOTIFICACIONES_CONFIG",
        "config_php": "window.NOTIFICACIONES_CONFIG = { bburl: '<?= $bb ?>', ajaxBase: '<?= $bb ?>/game/ajax' };",
        "bburl_php": "$bb",
        "strip_lines": ["const AJAX_BASE = '<?= $bb ?>/game/ajax';"],
        "add_after_cfg": "  var ajaxBase = cfg.ajaxBase || (bburl ? bburl + '/game/ajax' : '');",
        "replacements": [
            (r"AJAX_BASE", "ajaxBase"),
        ],
        "window_exports": [
            "marcarLeida", "marcarTodasLeidas", "toggleDismiss", "deleteNotif",
            "resolverPropuestaTrama",
        ],
    },
    {
        "php": "back/forum/game/public/npc.php",
        "js": "back/forum/jscripts/game/npc.js",
        "config_var": "NPC_CONFIG",
        "config_php": "window.NPC_CONFIG = {};",
        "bburl_php": "$mybb->settings['bburl']",
        "replacements": [
            (r'mb\.style\.backgroundImage="url\("\+d\.crew_banner\+"\)"',
             'mb.setAttribute("data-bg", d.crew_banner); if (window.applyRpgDataAttrs) window.applyRpgDataAttrs(mb)'),
        ],
    },
    {
        "php": "back/forum/game/public/anuncios_staff.php",
        "js": "back/forum/jscripts/game/anuncios_staff.js",
        "config_var": "ANUNCIOS_STAFF_CONFIG",
        "config_php": "window.ANUNCIOS_STAFF_CONFIG = { bburl: '<?= $bburl ?>' };",
        "bburl_php": "$bburl",
        "replacements": [
            (r"fetch\('<\?= \$bburl \?>/game/ajax/announcements_list\.php'\)",
             "fetch(bburl + '/game/ajax/announcements_list.php')"),
            (r"window\.gamePostJson\('<\?= \$bburl \?>/game/ajax/announcements_save\.php'",
             "window.gamePostJson(bburl + '/game/ajax/announcements_save.php'"),
            (r"fetch\('<\?= \$bburl \?>/game/ajax/announcements_save\.php'",
             "fetch(bburl + '/game/ajax/announcements_save.php'"),
        ],
        "window_exports": ["loadAnnouncements", "saveAnnouncement", "deleteAnnouncement"],
        "add_escape_html": True,
    },
    {
        "php": "back/forum/game/public/estilos.php",
        "js": "back/forum/jscripts/game/estilos.js",
        "config_var": "ESTILOS_CONFIG",
        "config_php": "window.ESTILOS_CONFIG = {};",
        "bburl_php": "$mybb->settings['bburl']",
        "content_string": True,
        "replacements": [
            (r"modalBanner\.style\.backgroundImage = `url\\('\\'\$\{img\}'\\'\)`;",
             'modalBanner.setAttribute("data-bg", img); if (window.applyRpgDataAttrs) window.applyRpgDataAttrs(modalBanner);'),
        ],
    },
    {
        "php": "back/forum/game/public/peticiones_general.php",
        "js": "back/forum/jscripts/game/peticiones_general.js",
        "config_var": "PETICIONES_GENERAL_CONFIG",
        "config_php": "window.PETICIONES_GENERAL_CONFIG = { bburl: '<?= $b_url ?>' };",
        "bburl_php": "$b_url",
        "strip_lines": ["var bburl_pet = '<?= $b_url ?>';"],
        "replacements": [
            (r"bburl_pet", "bburl"),
        ],
        "window_exports": ["openBusquedaModal", "closeBusquedaModal", "submitBusqueda"],
    },
    {
        "php": "back/forum/game/public/zona_staff_busquedas.php",
        "js": "back/forum/jscripts/game/zona_staff_busquedas.js",
        "config_var": "ZONA_STAFF_BUSQUEDAS_CONFIG",
        "config_php": "window.ZONA_STAFF_BUSQUEDAS_CONFIG = { bburl: '<?= $b_url ?>' };",
        "bburl_php": "$b_url",
        "strip_lines": ["var bburl = '<?= $b_url ?>';"],
        "window_exports": ["openBusquedaReview", "closeBusquedaReview", "accionBusqueda", "loadBusquedasStaff"],
    },
    {
        "php": "back/forum/game/public/akuma_no_mi.php",
        "js": "back/forum/jscripts/game/akuma_no_mi.js",
        "config_var": "AKUMA_NO_MI_CONFIG",
        "config_php": "window.AKUMA_NO_MI_CONFIG = {};",
        "bburl_php": "$mybb->settings['bburl']",
        "content_string": True,
        "replacements": [
            (r"modalBanner\.style\.backgroundImage = `url\\('\\'\$\{img\}'\\'\)`;",
             'modalBanner.setAttribute("data-bg", img); if (window.applyRpgDataAttrs) window.applyRpgDataAttrs(modalBanner);'),
        ],
    },
    {
        "php": "back/forum/game/public/historia.php",
        "js": "back/forum/jscripts/game/historia.js",
        "config_var": "HISTORIA_CONFIG",
        "config_php": "window.HISTORIA_CONFIG = {};",
        "bburl_php": "$mybb->settings['bburl']",
        "content_string": True,
        "replacements": [
            (r'modalBanner\.style\.backgroundImage = "url\(" \+ img \+ "\)";',
             'modalBanner.setAttribute("data-bg", img); if (window.applyRpgDataAttrs) window.applyRpgDataAttrs(modalBanner);'),
        ],
    },
    {
        "php": "back/forum/game/public/mis_personajes.php",
        "js": "back/forum/jscripts/game/mis_personajes.js",
        "config_var": "MIS_PERSONAJES_CONFIG",
        "config_php": "window.MIS_PERSONAJES_CONFIG = { bburl: '<?= $bb ?>' };",
        "bburl_php": "$bb",
        "replacements": [
            (r"var url = '<\?= \$bb \?>/game/ajax/set_active_pj\.php';",
             "var url = bburl + '/game/ajax/set_active_pj.php';"),
        ],
        "window_exports": ["switchPJ"],
    },
    {
        "php": "back/forum/game/public/biblioteca_personajes.php",
        "js": "back/forum/jscripts/game/biblioteca_personajes.js",
        "config_var": "BIBLIOTECA_PERSONAJES_CONFIG",
        "config_php": "window.BIBLIOTECA_PERSONAJES_CONFIG = {};",
        "bburl_php": "$mybb->settings['bburl']",
        "replacements": [
            (r'mb\.style\.backgroundImage="url\(\'"\+i\+"\'\)"',
             'mb.setAttribute("data-bg", i); if (window.applyRpgDataAttrs) window.applyRpgDataAttrs(mb)'),
        ],
    },
    {
        "php": "back/forum/game/public/objetos.php",
        "js": "back/forum/jscripts/game/objetos.js",
        "config_var": "OBJETOS_CONFIG",
        "config_php": "window.OBJETOS_CONFIG = {};",
        "bburl_php": "$mybb->settings['bburl']",
        "content_string": True,
        "replacements": [
            (r"modalBanner\.style\.backgroundImage = `url\\('\\'\$\{img\}'\\'\)`;",
             'modalBanner.setAttribute("data-bg", img); if (window.applyRpgDataAttrs) window.applyRpgDataAttrs(modalBanner);'),
        ],
    },
]


def escape_html_fn() -> str:
    return """
function escapeHtml(text) {
  if (!text) return '';
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
"""


def extract_script(text: str) -> tuple[int, int, str] | None:
    m = re.search(
        r'<script(?:\s+type="text/javascript")?\s*>\s*(.*?)\s*</script>',
        text,
        re.DOTALL,
    )
    if not m:
        return None
    return m.start(), m.end(), m.group(1).strip()


def build_js(spec: dict, js_body: str) -> str:
    if spec.get("strip_outer_iife"):
        js_body = re.sub(r"^\(function\(\)\{\s*", "", js_body)
        js_body = re.sub(r"\s*\}\)\(\);\s*$", "", js_body)

    for line in spec.get("strip_lines", []):
        js_body = js_body.replace(line + "\n", "").replace(line, "")

    for old, new in spec.get("replacements", []):
        js_body = re.sub(old, new, js_body)

    header = f"""/**
 * Auto-extracted from {spec['php']}
 * Config: window.{spec['config_var']}
 */
(function () {{
  "use strict";
  var cfg = window.{spec['config_var']} || {{}};
  var bburl = cfg.bburl || (window.GAME_BBURL || '');
"""
    if spec.get("add_after_cfg"):
        header += spec["add_after_cfg"] + "\n"

    footer = ""
    if spec.get("add_escape_html"):
        footer += escape_html_fn()
        # Fix anuncios template literals to use escapeHtml
        footer = footer  # escapeHtml added before exports

    exports = spec.get("window_exports", [])
    if exports:
        footer += "\n"
        for name in exports:
            footer += f"  window.{name} = {name};\n"

    footer += "\n})();\n"

    if spec.get("add_escape_html") and "escapeHtml" not in js_body:
        # Insert escapeHtml before first function
        js_body = escape_html_fn().strip() + "\n\n" + js_body
        # Patch anuncios innerHTML template - replace ${a.date} etc with escapeHtml calls
        js_body = js_body.replace("${a.date}", "${escapeHtml(a.date)}")
        js_body = js_body.replace("${a.title}", "${escapeHtml(a.title)}")
        js_body = js_body.replace("${a.content}", "${escapeHtml(a.content)}")

    return header + js_body + footer


def php_replacement(spec: dict, js_name: str) -> str:
    bburl_php = spec["bburl_php"]
    if bburl_php.startswith("$"):
        src_expr = f"<?= rtrim({bburl_php}, '/') ?>"
    else:
        src_expr = f"<?= rtrim({bburl_php}, '/') ?>"
    return f"""<script>
{spec['config_php']}
</script>
<script src="{src_expr}/jscripts/game/{js_name}?v=1"></script>"""


def patch_content_string_php(text: str, spec: dict, replacement: str) -> str:
    """Replace inline script inside $content = '...' string."""
    # Find script block inside the content string
    m = re.search(
        r"(<script(?:\s+type=\"text/javascript\")?\s*>\s*.*?\s*</script>\s*\n';)",
        text,
        re.DOTALL,
    )
    if not m:
        raise ValueError(f"content_string script not found in {spec['php']}")

    inner = m.group(1)
    # Close content string before scripts, append scripts via PHP concat
    new_block = "';\n\n$content .= '\n" + replacement + "\n';"
    return text.replace(inner, new_block, 1)


def extract_one(spec: dict) -> None:
    php_path = ROOT / spec["php"]
    js_path = ROOT / spec["js"]
    text = php_path.read_text(encoding="utf-8")

    found = extract_script(text)
    if not found:
        print(f"SKIP no script: {spec['php']}")
        return

    start, end, js_body = found
    js_content = build_js(spec, js_body)
    js_path.parent.mkdir(parents=True, exist_ok=True)
    js_path.write_text(js_content, encoding="utf-8")

    replacement = php_replacement(spec, js_path.name)

    if spec.get("content_string"):
        new_text = patch_content_string_php(text, spec, replacement)
    else:
        new_text = text[:start] + replacement + text[end:]

    php_path.write_text(new_text, encoding="utf-8")
    print(f"OK {spec['php']} -> {spec['js']} ({len(js_body.splitlines())} lines JS)")


if __name__ == "__main__":
    for spec in EXTRACTS:
        extract_one(spec)
