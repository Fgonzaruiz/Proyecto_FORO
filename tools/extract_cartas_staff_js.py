#!/usr/bin/env python3
"""Extract inline JS from cartas_staff.php into jscripts/game/cartas_staff.js"""
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
php = ROOT / "back/forum/game/public/cartas_staff.php"
out = ROOT / "back/forum/jscripts/game/cartas_staff.js"

lines = php.read_text(encoding="utf-8").splitlines()
js = "\n".join(lines[409:1511])
js = js.strip()
if js.startswith("<script>"):
    js = js[8:].strip()
if js.endswith("</script>"):
    js = js[:-9].strip()

js = re.sub(r"^var GAME_AJAX_BASE = .*?;\n", "", js, count=1)
js = re.sub(
    r"^function staffPost\(endpoint, data\) \{.*?\n\}\n",
    "",
    js,
    count=1,
    flags=re.DOTALL,
)

header = """/**
 * Gestión de cartas (staff) — catálogo, editor drawer, asignación.
 * Config: window.CARTAS_STAFF_CONFIG = { ajaxBase: \".../game/ajax\" }
 */
(function () {
  \"use strict\";
  var cfg = window.CARTAS_STAFF_CONFIG || {};
  var GAME_AJAX_BASE = cfg.ajaxBase || (window.GAME_AJAX_BASE || \"\");
  function staffPost(endpoint, data) {
    var url = GAME_AJAX_BASE + \"/\" + String(endpoint).replace(/^\\//, \"\");
    if (window.gamePostJson) {
      return window.gamePostJson(url, data || {});
    }
    var body = data || {};
    if (window.GAME_CSRF) {
      body.my_post_key = window.GAME_CSRF;
    }
    return fetch(url, {
      method: \"POST\",
      headers: { \"Content-Type\": \"application/json\", \"X-Mybb-Post-Key\": window.GAME_CSRF || \"\" },
      credentials: \"same-origin\",
      body: JSON.stringify(body)
    }).then(function (r) { return r.json(); });
  }

"""

footer = "\n})();\n"
out.write_text(header + js + footer, encoding="utf-8")
print(f"Wrote {out} ({len((header + js + footer).splitlines())} lines)")
