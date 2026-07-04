#!/usr/bin/env python3
"""Sync front/templates + rpg_custom.css into Default-theme.xml."""
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent
XML = ROOT / "Default-theme.xml"
MANIFEST = ROOT / "theme_templates.json"
RPG_CSS = ROOT.parent / "back" / "forum" / "rpg_custom.css"
MINIMAL_CSS = ROOT / "templates" / "mybb" / "global" / "mybb-minimal.css"

xml = XML.read_text(encoding="utf-8")
templates = json.loads(MANIFEST.read_text(encoding="utf-8"))

for name, rel in templates.items():
    path = ROOT / rel.replace("/", "\\")
    if not path.is_file():
        print(f"SKIP {name} (missing {rel})")
        continue
    html = path.read_text(encoding="utf-8").replace("\r\n", "\n")
    pattern = rf'(<template\s+name="{re.escape(name)}"[\s\S]*?>\s*<!\[CDATA\[)(.*?)(\]\]>\s*</template>)'
    if not re.search(pattern, xml, re.DOTALL):
        print(f"WARN {name} not in XML")
        continue
    xml = re.sub(pattern, lambda m: m.group(1) + html + "\n\t\t" + m.group(3), xml, count=1, flags=re.DOTALL)
    print(f"OK {name}")

if RPG_CSS.is_file():
    css = RPG_CSS.read_text(encoding='utf-8-sig').replace('\r\n', '\n').lstrip('\ufeff')
    marker = "/* RPG Premium Modern Theme */"
    css_pattern = r'(<stylesheet\s+name="global\.css"[^>]*><!\[CDATA\[)(.*?)(</stylesheet>)'

    def repl_css(m):
        base = MINIMAL_CSS.read_text(encoding="utf-8").replace("\r\n", "\n") if MINIMAL_CSS.is_file() else ""
        if base and not base.endswith("\n"):
            base += "\n"
        return m.group(1) + base + marker + "\n" + css + "]]>\n\t\t" + m.group(3)

    xml = re.sub(css_pattern, repl_css, xml, count=1, flags=re.DOTALL)
    print("OK global.css")

XML.write_text(xml, encoding="utf-8")
print("Default-theme.xml updated.")
