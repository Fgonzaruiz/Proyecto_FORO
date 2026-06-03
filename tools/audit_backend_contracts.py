#!/usr/bin/env python3
"""Backend contract audit — exit 0 when all ajax endpoints are documented in OpenAPI."""
from __future__ import annotations

import json
import re
import sys
from datetime import date
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
AJAX_DIR = ROOT / "back" / "forum" / "game" / "ajax"
OPENAPI_DIR = ROOT / "packages" / "contracts" / "openapi"
OUT = ROOT / "docs" / "auditoria-backend-metrics.json"

# Legacy 501 stubs — documented but intentionally not implemented (D001 local).
LEGACY_501 = {
    "character_get.php",
    "inventory_get.php",
    "economy_get.php",
    "roll_execute.php",
    "staff_award_xp.php",
}

PATH_RE = re.compile(r"^\s+/game/ajax/([^\s:]+):\s*$", re.M)


def collect_ajax_files() -> set[str]:
    if not AJAX_DIR.is_dir():
        return set()
    return {p.name for p in AJAX_DIR.glob("*.php") if p.is_file()}


def collect_openapi_paths() -> set[str]:
    documented: set[str] = set()
    for yaml_path in sorted(OPENAPI_DIR.glob("*.yaml")):
        text = yaml_path.read_text(encoding="utf-8", errors="replace")
        for m in PATH_RE.finditer(text):
            documented.add(m.group(1))
    return documented


def main() -> int:
    ajax = collect_ajax_files()
    documented = collect_openapi_paths()
    missing = sorted(ajax - documented - LEGACY_501)
    extra = sorted(documented - ajax)

    coverage = 0.0
    if ajax:
        covered = len(ajax - set(missing) - LEGACY_501)
        coverage = round(100.0 * covered / len(ajax), 1)

    metrics = {
        "date": date.today().isoformat(),
        "ajax_count": len(ajax),
        "openapi_paths": len(documented),
        "coverage_percent": coverage,
        "legacy_501": sorted(LEGACY_501),
        "missing_contract": missing,
        "openapi_extra_not_in_ajax": extra,
    }
    OUT.write_text(json.dumps(metrics, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")

    print(f"ajax endpoints: {len(ajax)}")
    print(f"openapi paths:  {len(documented)}")
    print(f"coverage:       {coverage}%")
    if missing:
        print("missing contract:", ", ".join(missing))
        return 1
    if extra:
        print("note: openapi paths without ajax file:", ", ".join(extra[:10]), "..." if len(extra) > 10 else "")
    print("OK")
    return 0


if __name__ == "__main__":
    sys.exit(main())
