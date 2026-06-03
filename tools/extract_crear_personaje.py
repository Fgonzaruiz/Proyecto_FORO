#!/usr/bin/env python3
"""Extract crear_personaje.php script + inline style block."""
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PHP = ROOT / "back/forum/game/public/crear_personaje.php"
JS = ROOT / "back/forum/jscripts/game/crear_personaje.js"
CSS = ROOT / "back/forum/rpg_custom.css"

text = PHP.read_text(encoding="utf-8")

# Move <style> block to rpg_custom.css
sm = re.search(r"<style>\s*(.*?)\s*</style>", text, re.DOTALL)
if sm:
    css_block = sm.group(1).strip()
    marker = "/* ========== CREAR PERSONAJE WIZARD (from crear_personaje.php) ========== */"
    css_text = CSS.read_text(encoding="utf-8")
    if marker not in css_text:
        insert_at = css_text.find("@supports not (backdrop-filter")
        css_text = css_text[:insert_at] + marker + "\n" + css_block + "\n\n" + css_text[insert_at:]
        CSS.write_text(css_text, encoding="utf-8")
        print("OK moved wizard CSS to rpg_custom.css")
    text = text[: sm.start()] + text[sm.end() :]

# Extract script
m = re.search(r"<script>\s*(.*?)\s*</script>", text, re.DOTALL)
if not m:
    raise SystemExit("no script found")
js = m.group(1).strip()
js = js.replace("var LINAJE_DATA = <?php echo $catalog_json; ?>;", "var LINAJE_DATA = cfg.catalog || {};")
js = js.replace("pj_id: <?= (int)$edit_pj_id ?>,", "pj_id: cfg.editPjId || 0,")
js = js.replace("var saveUrl = '<?= rtrim($bb, '/') ?>/game/ajax/save_personaje.php';", "var saveUrl = (cfg.bburl || '') + '/game/ajax/save_personaje.php';")
js = js.replace("var editData = <?= $edit_data ?: 'null' ?>;", "var editData = cfg.editData || null;")

header = """/**
 * Wizard crear/editar personaje
 * Config: window.CREAR_PERSONAJE_CONFIG
 */
(function () {
  \"use strict\";
  var cfg = window.CREAR_PERSONAJE_CONFIG || {};

"""
footer = "\n})();\n"
JS.write_text(header + js + footer, encoding="utf-8")

replacement = """<script>
window.CREAR_PERSONAJE_CONFIG = <?= json_encode([
  'bburl' => rtrim($bb, '/'),
  'editPjId' => (int)($edit_pj_id ?? 0),
  'editData' => $edit_data ? json_decode($edit_data, true) : null,
  'catalog' => json_decode($catalog_json ?: '{}', true),
], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= rtrim($bb, '/') ?>/jscripts/game/crear_personaje.js?v=1"></script>"""

text = text[: m.start()] + replacement + text[m.end() :]
PHP.write_text(text, encoding="utf-8")
print(f"OK crear_personaje.js ({len(js.splitlines())} lines)")
