#!/usr/bin/env python3
"""Sync estadisticas tab from _rpg_system_block.html to posting templates."""
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
src = (ROOT / "front/templates/mybb/posting/_rpg_system_block.html").read_text(encoding="utf-8")

BLOCK_RE = re.compile(
    r'<div id="rpg-tab-estadisticas"[\s\S]*?id="rpg_modifiers"[^>]*>\s*</div>\s*</div>',
    re.MULTILINE,
)
m = BLOCK_RE.search(src)
if not m:
    raise SystemExit("estadisticas block not found in _rpg_system_block.html")
new_block = m.group(0)

targets = [
    "front/templates/mybb/newthread/newthread.html",
    "front/templates/mybb/newreply/newreply.html",
    "front/templates/mybb/showthread/showthread_quickreply.html",
]

for rel in targets:
    p = ROOT / rel
    text = p.read_text(encoding="utf-8")
    text2, n = BLOCK_RE.subn(new_block, text, count=1)
    if not n:
        print(f"SKIP {rel}")
        continue
    p.write_text(text2, encoding="utf-8")
    print(f"OK {rel}")
