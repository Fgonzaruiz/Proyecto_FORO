#!/usr/bin/env python3
"""Extract inline <script> blocks from PHP files into jscripts/game/*.js"""
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

EXTRACTS = [
    {
        "php": "back/forum/game/public/zona_staff_peticiones.php",
        "js": "back/forum/jscripts/game/zona_staff_peticiones.js",
        "config_var": "ZONA_STAFF_PETICIONES_CONFIG",
        "config_php": """window.ZONA_STAFF_PETICIONES_CONFIG = {
  bburl: '<?= $b_url ?>',
  staffLevel: <?= $staff_level ?>
};""",
        "strip_vars": ["var bburl = ", "var staffLevel = "],
    },
    {
        "php": "back/forum/game/public/zona_staff_aprobar.php",
        "js": "back/forum/jscripts/game/zona_staff_aprobar.js",
        "config_var": "ZONA_STAFF_APROBAR_CONFIG",
        "config_php": """window.ZONA_STAFF_APROBAR_CONFIG = {
  bburl: '<?= $b_url ?>'
};""",
        "strip_vars": ["var bburl = "],
    },
]


def extract_one(spec: dict) -> None:
    php_path = ROOT / spec["php"]
    js_path = ROOT / spec["js"]
    text = php_path.read_text(encoding="utf-8")
    m = re.search(r"<script>\s*(.*?)\s*</script>", text, re.DOTALL)
    if not m:
        print(f"SKIP no script: {spec['php']}")
        return
    js = m.group(1).strip()
    for prefix in spec.get("strip_vars", []):
        js = re.sub(rf"^{re.escape(prefix)}.*?;\n?", "", js, count=1, flags=re.MULTILINE)

    header = f"""/**
 * Auto-extracted from {spec['php']}
 * Config: window.{spec['config_var']}
 */
(function () {{
  "use strict";
  var cfg = window.{spec['config_var']} || {{}};
  var bburl = cfg.bburl || (window.GAME_BBURL || '');
  var staffLevel = cfg.staffLevel || 0;

"""
    footer = "\n})();\n"
    js_path.parent.mkdir(parents=True, exist_ok=True)
    js_path.write_text(header + js + footer, encoding="utf-8")

    replacement = f"""<script>
{spec['config_php']}
</script>
<script src="<?= rtrim($b_url, '/') ?>/jscripts/game/{js_path.name}?v=1"></script>"""

    new_text = text[: m.start()] + replacement + text[m.end() :]
    php_path.write_text(new_text, encoding="utf-8")
    print(f"OK {spec['php']} -> {spec['js']} ({len(js.splitlines())} lines)")


if __name__ == "__main__":
    for spec in EXTRACTS:
        extract_one(spec)
