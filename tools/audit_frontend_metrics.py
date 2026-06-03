#!/usr/bin/env python3
"""Frontend audit gates — exit 0 when all pass. Writes docs/auditoria-metrics.json."""
from __future__ import annotations

import json
import re
import sys
from datetime import date
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "docs" / "auditoria-metrics.json"
MARKER = "/* RPG Premium Modern Theme */"
ACCENT_ALIAS = re.compile(r"^\s*--accent-indigo:\s*var\(--accent-primary\)", re.M)


def count_style(path: Path) -> int:
    if not path.exists():
        return 0
    if path.is_file():
        return path.read_text(encoding="utf-8", errors="replace").count("style=")
    total = 0
    for f in path.rglob("*"):
        if f.is_file() and f.suffix in {".php", ".html", ".js", ".xml"}:
            total += f.read_text(encoding="utf-8", errors="replace").count("style=")
    return total


def count_inline_scripts(_base: Path) -> int:
    """Script blocks in public/views excluding config-only and external src."""
    total = 0
    for sub in ("back/forum/game/public", "back/forum/game/views"):
        d = ROOT / sub
        if not d.is_dir():
            continue
        for f in d.rglob("*.php"):
            text = f.read_text(encoding="utf-8", errors="replace")
            for m in re.finditer(r"<script\b([^>]*)>(.*?)</script>", text, re.I | re.S):
                attrs = m.group(1)
                if re.search(r"\bsrc\s*=", attrs, re.I):
                    continue
                body = m.group(2).strip()
                if not body:
                    continue
                if re.match(r"window\.\w+_CONFIG\s*=", body):
                    continue
                total += 1
    return total


def accent_indigo_gate(css: str) -> tuple[bool, int]:
    uses = len(re.findall(r"--accent-indigo", css))
    alias_lines = len(ACCENT_ALIAS.findall(css))
    # exactly one alias definition, no other references
    ok = uses == 1 and alias_lines == 1
    return ok, uses


def form_group_gate(css: str) -> tuple[bool, int]:
    blocks = re.findall(r"\.rpg-form-group\s*\{", css)
    return len(blocks) == 1, len(blocks)


def legacy_lines_gate() -> tuple[bool, int]:
    minimal = ROOT / "front" / "templates" / "mybb" / "global" / "mybb-minimal.css"
    if minimal.is_file():
        text = minimal.read_text(encoding="utf-8", errors="replace")
        lines = text.count("\n") + (1 if text.strip() else 0)
        return lines <= 200, lines
    xml = ROOT / "front" / "Default-theme.xml"
    if not xml.is_file():
        return False, -1
    text = xml.read_text(encoding="utf-8", errors="replace")
    m = re.search(
        r'<stylesheet\s+name="global\.css"[^>]*><!\[CDATA\[(.*?)\]\]>\s*</stylesheet>',
        text,
        re.S,
    )
    if not m:
        return False, -1
    content = m.group(1)
    idx = content.find(MARKER)
    if idx < 0:
        return False, content.count("\n") + 1
    legacy = content[:idx]
    lines = legacy.count("\n") + (1 if legacy.strip() else 0)
    return lines <= 200, lines


def contrast_ratio(fg: tuple[int, int, int], bg: tuple[int, int, int]) -> float:
    def lum(c):
        r, g, b = [x / 255 for x in c]
        r = r / 12.92 if r <= 0.03928 else ((r + 0.055) / 1.055) ** 2.4
        g = g / 12.92 if g <= 0.03928 else ((g + 0.055) / 1.055) ** 2.4
        b = b / 12.92 if b <= 0.03928 else ((b + 0.055) / 1.055) ** 2.4
        return 0.2126 * r + 0.7152 * g + 0.0722 * b

    l1, l2 = lum(fg), lum(bg)
    hi, lo = max(l1, l2), min(l1, l2)
    return (hi + 0.05) / (lo + 0.05)


def hex_rgb(h: str) -> tuple[int, int, int]:
    h = h.lstrip("#")
    if len(h) == 3:
        h = "".join(c * 2 for c in h)
    return int(h[0:2], 16), int(h[2:4], 16), int(h[4:6], 16)


def badge_contrast_gate() -> tuple[bool, list]:
    pairs = [
        ("rpg-staff-badge", "#ffffff", "#4A148C"),
        ("aprobar-count", "#ffffff", "#C62828"),
        ("pill-amber", "#92400e", "#F4EBD0"),
    ]
    results = []
    all_ok = True
    for name, fg, bg in pairs:
        ratio = round(contrast_ratio(hex_rgb(fg), hex_rgb(bg)), 2)
        ok = ratio >= 4.5
        if not ok:
            all_ok = False
        results.append({"badge": name, "fg": fg, "bg": bg, "ratio": ratio, "pass": ok})
    return all_ok, results


def audit_html_gate() -> tuple[bool, dict]:
    path = ROOT / "docs" / "auditoria-frontend-foro.html"
    if not path.is_file():
        return False, {"error": "missing"}
    text = path.read_text(encoding="utf-8", errors="replace")
    scores = re.findall(r'<span class="score-num">([\d.]+)</span>', text)
    min_score = min(float(s) for s in scores) if scores else 0
    s13_closed = "Auditoría cerrada" in text or "lista vacía de pendientes" in text.lower()
    return min_score >= 8.8 and s13_closed, {"min_score": min_score, "section13_closed": s13_closed}


def main() -> int:
    css_path = ROOT / "back" / "forum" / "rpg_custom.css"
    css = css_path.read_text(encoding="utf-8", errors="replace") if css_path.is_file() else ""

    gates = {}

    for name, path in [
        ("style_templates", ROOT / "front" / "templates" / "mybb"),
        ("style_game_public", ROOT / "back" / "forum" / "game" / "public"),
        ("style_game_views", ROOT / "back" / "forum" / "game" / "views"),
    ]:
        n = count_style(path)
        gates[name] = {"count": n, "max": 0, "pass": n == 0}

    inline = count_inline_scripts(ROOT)
    gates["inline_scripts_public_views"] = {
        "count": inline,
        "max": 0,
        "pass": inline == 0,
    }

    ok_indigo, indigo_n = accent_indigo_gate(css)
    gates["accent_indigo_alias_only"] = {"count": indigo_n, "pass": ok_indigo}

    ok_fg, fg_n = form_group_gate(css)
    gates["rpg_form_group_single"] = {"count": fg_n, "pass": ok_fg}

    ok_legacy, legacy_n = legacy_lines_gate()
    gates["global_css_legacy_lines"] = {"count": legacy_n, "max": 200, "pass": ok_legacy}

    ok_badges, badge_detail = badge_contrast_gate()
    gates["badge_contrast"] = {"pass": ok_badges, "pairs": badge_detail}

    ok_html, html_detail = audit_html_gate()
    gates["audit_html_scores"] = {"pass": ok_html, **html_detail}

    all_pass = all(g.get("pass") for g in gates.values())

    report = {
        "date": date.today().isoformat(),
        "pass": all_pass,
        "gates": gates,
    }
    OUT.parent.mkdir(parents=True, exist_ok=True)
    OUT.write_text(json.dumps(report, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")

    print(json.dumps(report, indent=2, ensure_ascii=False))
    if not all_pass:
        failed = [k for k, v in gates.items() if not v.get("pass")]
        print(f"\nFAILED gates: {', '.join(failed)}", file=sys.stderr)
        return 1
    print("\nAll gates passed.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
