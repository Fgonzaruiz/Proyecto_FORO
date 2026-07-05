#!/usr/bin/env python3
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
xml = (ROOT / "front" / "Default-theme.xml").read_text(encoding="utf-8")
match = re.search(
    r'<stylesheet name="global\.css"[^>]*><!\[CDATA\[(.*?)\]\]>\s*</stylesheet>',
    xml,
    re.DOTALL,
)
css = match.group(1)
for theme_dir in (ROOT / "back" / "forum" / "cache" / "themes").glob("theme*"):
    for name in ("global.css", "global.min.css"):
        (theme_dir / name).write_text(css, encoding="utf-8")
print("cache ok")
