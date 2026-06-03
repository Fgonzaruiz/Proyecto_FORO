#!/usr/bin/env python3
"""Extract personaje _scripts.php to personaje_page.js"""
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PHP = ROOT / "back/forum/game/views/personaje/_scripts.php"
JS = ROOT / "back/forum/jscripts/game/personaje_page.js"

text = PHP.read_text(encoding="utf-8")
m = re.search(r"<script>\s*(.*?)\s*</script>", text, re.DOTALL)
if not m:
    raise SystemExit("no script")
js = m.group(1).strip()

replacements = [
    (r"var tagColors = <\?= json_encode\(\$tag_colors \?\? \[\].*?\?\>;", "var tagColors = cfg.tagColors || [];"),
    (r"var catColors = <\?= json_encode\(\$cat_list_display \?\? \[.*?\?\>;", "var catColors = cfg.catColors || {};"),
    (r"relaciones: <\?= json_encode\(isset\(\$char\['cronologia'\]\['relaciones'\]\).*?\?\>,", "relaciones: cfg.cronologia.relaciones || [],"),
    (r"groups: <\?= json_encode\(isset\(\$char\['cronologia'\]\['groups'\]\).*?\?\>,", "groups: cfg.cronologia.groups || [],"),
    (r"connections: <\?= json_encode\(isset\(\$char\['cronologia'\]\['connections'\]\).*?\?\>,", "connections: cfg.cronologia.connections || [],"),
    (r"diario: <\?= json_encode\(isset\(\$char\['cronologia'\]\['diario'\]\).*?\?\>", "diario: cfg.cronologia.diario || []"),
    (r"<\?php if \(\$can_edit\): \?>", "if (cfg.canEdit) {"),
    (r"<\?php endif; \?>", "}"),
    (r"var AJAX_BASE = '<\?= rtrim\(\$bb, '/'\) \?>/game/ajax';", "var AJAX_BASE = (cfg.bburl || '') + '/game/ajax';"),
    (r"window\.__PJ_PROGRESSION = <\?= json_encode\(\$pj_progression.*?\?\>;", "window.__PJ_PROGRESSION = cfg.progression || null;"),
    (r"<\?= \(int\)\(\$char\['id'\] \?\? 0\) \?>", "(cfg.characterId || 0)"),
    (r"<\?= \(int\)\$char\['id'\] \?>", "(cfg.characterId || 0)"),
    (r"character_id=\<\?= \(int\)\$char\['id'\] \?\>", "character_id=' + (cfg.characterId || 0) + '"),
    (r"\?character_id=<\?= \(int\)\$char\['id'\] \?>", "?character_id=' + (cfg.characterId || 0) + '"),
]

for pat, rep in replacements:
    js = re.sub(pat, rep, js, flags=re.DOTALL)

header = """/**
 * Ficha personaje — tabs, deck, cronología, gestión
 * Config: window.PERSONAJE_PAGE_CONFIG
 */
(function () {
  \"use strict\";
  var cfg = window.PERSONAJE_PAGE_CONFIG || {};

"""
footer = "\n})();\n"
JS.write_text(header + js + footer, encoding="utf-8")

new_php = """<script>
window.PERSONAJE_PAGE_CONFIG = <?= json_encode([
  'bburl' => rtrim($bb ?? $mybb->settings['bburl'] ?? '', '/'),
  'canEdit' => !empty($can_edit),
  'characterId' => (int)($char['id'] ?? 0),
  'tagColors' => $tag_colors ?? [],
  'catColors' => $cat_list_display ?? ['Pasado'=>'#8b5cf6','Presente'=>'#10b981','Mision'=>'#f59e0b','Evento'=>'#3b82f6','Trama'=>'#ef4444','Fic'=>'#ec4899','Off_Rol'=>'#6b7280'],
  'cronologia' => [
    'relaciones' => $char['cronologia']['relaciones'] ?? [],
    'groups' => $char['cronologia']['groups'] ?? [],
    'connections' => $char['cronologia']['connections'] ?? [],
    'diario' => $char['cronologia']['diario'] ?? [],
  ],
  'progression' => $pj_progression ?? null,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= rtrim($bb ?? $mybb->settings['bburl'], '/') ?>/jscripts/game/personaje_page.js?v=1"></script>
"""
PHP.write_text(new_php, encoding="utf-8")
print(f"OK personaje_page.js ({len(js.splitlines())} lines)")
