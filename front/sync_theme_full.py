#!/usr/bin/env python3
"""Sync front/templates + rpg_custom.css into Default-theme.xml."""
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent
XML = ROOT / "Default-theme.xml"
MANIFEST = ROOT / "theme_templates.json"
RPG_CSS = ROOT.parent / "back" / "forum" / "rpg_custom.css"

xml = XML.read_text(encoding="utf-8")
templates = json.loads(MANIFEST.read_text(encoding="utf-8"))

for name, rel in templates.items():
    path = ROOT / rel.replace("/", "\\")
    if not path.is_file():
        print(f"SKIP {name} (missing {rel})")
        continue
    html = path.read_text(encoding="utf-8").replace("\r\n", "\n")
    pattern = rf'(<template\s+name="{re.escape(name)}"[^>]*><!\[CDATA\[)(.*?)(\]\]></template>)'
    if not re.search(pattern, xml, re.DOTALL):
        print(f"WARN {name} not in XML")
        continue
    xml = re.sub(pattern, lambda m: m.group(1) + html + "\n\t\t" + m.group(3), xml, count=1, flags=re.DOTALL)
    print(f"OK {name}")

if RPG_CSS.is_file():
    css = RPG_CSS.read_text(encoding="utf-8").replace("\r\n", "\n")
    marker = "/* RPG Premium Modern Theme */"
    css_pattern = r'(<stylesheet\s+name="global\.css"[^>]*><!\[CDATA\[)(.*?)(</stylesheet>)'

    def repl_css(m):
        content = m.group(2)
        idx = content.find(marker)
        base = content[:idx] if idx >= 0 else content + "\n"
        base = re.sub(r"\]\]>\s*$", "", base)
        return m.group(1) + base + marker + "\n" + css + "]]>\n\t\t" + m.group(3)

    xml = re.sub(css_pattern, repl_css, xml, count=1, flags=re.DOTALL)
    print("OK global.css")

XML.write_text(xml, encoding="utf-8")
print("Default-theme.xml updated.")
