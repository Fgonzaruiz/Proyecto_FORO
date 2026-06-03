#!/usr/bin/env python3
"""Bulk refactor style= attributes to CSS classes in PHP/JS files."""
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

# Order matters — longer patterns first
JS_HTML_REPLACEMENTS = [
    ('style="padding:20px; color:var(--accent-rose); text-align:center;"', 'class="rpg-error-box"'),
    ('style="padding:40px 20px; color:var(--text-muted); text-align:center;"', 'class="rpg-empty-state"'),
    ('style="padding:40px 20px; color:var(--text-muted); text-align:center;"><i class="fas fa-check-circle" style="font-size:32px; color:var(--accent-emerald); display:block; margin-bottom:10px; opacity:0.7;"></i>',
     'class="rpg-empty-state"><i class="fas fa-check-circle"></i>'),
    ('style="display:flex; gap:12px; padding:15px; border-bottom:1px solid var(--border-color); cursor:pointer; transition:background 0.2s;"',
     'class="rpg-request-row request-item"'),
    ('style="width:45px; height:45px; border-radius:50%; background-image:url(\'', 'class="rpg-request-avatar" data-bg="'),
    ("style=\"width:45px; height:45px; border-radius:50%; background-image:url('", 'class="rpg-request-avatar" data-bg="'),
    ("'); background-size:cover; background-position:center; flex-shrink:0; border:2px solid var(--border-color);\"",
     '">'),
    ('style="flex:1; min-width:0;"', 'class="rpg-request-body"'),
    ('style="font-weight:700; color:var(--text-primary); font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"',
     'class="rpg-request-name"'),
    ('style="font-size:12px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px;"',
     'class="rpg-request-card-name"'),
    ('style="text-align:center; padding:40px;"', 'class="rpg-preview-loading"'),
    ('style="text-align:center; padding:40px;"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i>',
     'class="rpg-preview-loading"><i class="fas fa-spinner fa-spin"></i>'),
    ('style="text-align:center; color:var(--text-muted); padding:40px 20px;"', 'class="rpg-success-empty"'),
    ('style="text-align:center; color:var(--text-muted); padding:40px 20px;"><i class="fas fa-check-circle" style="font-size:48px; display:block; margin-bottom:15px; opacity:0.5; color:var(--accent-emerald);"></i>',
     'class="rpg-success-empty"><i class="fas fa-check-circle"></i>'),
    ('style="padding: 10px; color: var(--text-muted);"', 'class="rpg-deck-list-empty"'),
    ('style="padding: 10px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;"',
     'class="rpg-deck-list-item"'),
    ('style="font-size: 0.8em; color: var(--accent-primary);"', 'class="rpg-deck-list-rank"'),
    ('style="padding: 8px 12px; cursor: pointer; font-size: 0.9em; border-bottom: 1px solid var(--border-color);"',
     'class="rpg-char-search-item"'),
    ('style="font-size:11px; font-weight:700; color:var(--text-muted); display:block; margin-bottom:4px;"', 'class="rpg-form-label-sm"'),
    ('style="width: 100%;"', 'class="rpg-input-full"'),
    ('style="display:none;"', 'class="rpg-is-hidden"'),
    ('style="display: none;"', 'class="rpg-is-hidden"'),
    ('style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.75); z-index:9999; align-items:center; justify-content:center; backdrop-filter:blur(4px);"',
     'class="rpg-modal-overlay" id="busqueda-review-modal" data-modal'),
]

PHP_HTML_REPLACEMENTS = [
    ('<i class="fas fa-clipboard-list" style="font-size:48px; display:block; margin-bottom:15px; opacity:0.3; color:var(--accent-purple);"></i>',
     '<i class="fas fa-clipboard-list rpg-preview-empty-icon"></i>'),
    ('<div style="text-align:center; padding:40px; color:var(--text-muted);">', '<div class="rpg-peticiones-loading">'),
    ('id="busqueda-review-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.75); z-index:9999; align-items:center; justify-content:center; backdrop-filter:blur(4px);"',
     'id="busqueda-review-modal" class="rpg-modal-overlay"'),
    ('style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; width: 90%; max-width: 650px; max-height: 90vh; overflow-y: auto; box-shadow: var(--shadow-main);"',
     'class="rpg-modal-panel"'),
    ('style="padding: 25px 30px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;"',
     'class="rpg-modal-header"'),
    ('id="brm-titulo" style="margin:0; font-size: 20px; color: var(--text-primary); display:flex; align-items:center; gap:10px;"',
     'id="brm-titulo" class="rpg-modal-title"'),
    ('style="color:var(--accent-rose);"', 'class="rpg-modal-title-icon"'),
    ('onclick="closeBusquedaReview()" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:20px;"',
     'onclick="closeBusquedaReview()" class="rpg-modal-close"'),
    ('style="padding: 25px 30px;"', 'class="rpg-modal-body"'),
    ('id="brm-img" src="" style="width:100%; height:220px; object-fit:cover; border-radius:8px; margin-bottom:20px; display:none;"',
     'id="brm-img" src="" class="rpg-modal-img rpg-is-hidden"'),
    ('style="display:flex; align-items:center; gap:12px; margin-bottom:20px;"', 'class="rpg-modal-author"'),
    ('id="brm-avatar" src="" style="width:50px; height:50px; border-radius:50%; object-fit:cover; border:2px solid var(--accent-rose);"',
     'id="brm-avatar" src="" class="rpg-modal-avatar"'),
    ('id="brm-pj" style="font-weight: 700; color: var(--text-primary);"', 'id="brm-pj" class="rpg-modal-pj"'),
    ('id="brm-date" style="font-size: 12px; color: var(--text-muted);"', 'id="brm-date" class="rpg-modal-date"'),
    ('id="brm-desc" style="font-size: 14px; color: var(--text-secondary); line-height: 1.7; margin-bottom: 20px; background: var(--bg-main); padding: 15px; border-radius: 8px; white-space: pre-wrap;"',
     'id="brm-desc" class="rpg-modal-desc"'),
    ('style="font-size: 13px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px; display: block;"',
     'class="rpg-modal-label"'),
    ('rows="3" style="width:100%; background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; color: var(--text-primary); font-size: 13px; resize: vertical; box-sizing: border-box;"',
     'rows="3" class="rpg-staff-textarea"'),
    ('style="display: flex; gap: 10px; margin-top: 20px;"', 'class="rpg-modal-actions"'),
    ('onclick="accionBusqueda(\'aprobar\')" style="flex:1; background: linear-gradient(135deg,#10b981,#059669); color: #fff; border: none; border-radius: 6px; padding: 12px; font-weight: 700; cursor: pointer; font-size: 15px; display:flex; align-items:center; justify-content:center; gap:8px;"',
     'onclick="accionBusqueda(\'aprobar\')" class="rpg-btn-approve-lg"'),
    ('onclick="accionBusqueda(\'denegar\')" style="flex:1; background: linear-gradient(135deg,#ef4444,#dc2626); color: #fff; border: none; border-radius: 6px; padding: 12px; font-weight: 700; cursor: pointer; font-size: 15px; display:flex; align-items:center; justify-content:center; gap:8px;"',
     'onclick="accionBusqueda(\'denegar\')" class="rpg-btn-reject-lg"'),
    ("<pre style='font-family: monospace; background: #0a0c16; color: #e2e8f0; padding: 20px; border-radius: 12px;'>",
     "<pre class='rpg-admin-pre'>"),
]

GLOBS = [
    "back/forum/game/public/**/*.php",
    "back/forum/game/views/**/*.php",
    "back/forum/jscripts/game/**/*.js",
    "back/forum/game/sql/migrate_*.php",
]


def apply_replacements(text: str, reps: list) -> str:
    for old, new in reps:
        text = text.replace(old, new)
    return text


def process_file(path: Path) -> int:
    text = path.read_text(encoding="utf-8")
    orig = text.count("style=")
    text = apply_replacements(text, PHP_HTML_REPLACEMENTS)
    text = apply_replacements(text, JS_HTML_REPLACEMENTS)
    path.write_text(text, encoding="utf-8")
    new = text.count("style=")
    if orig != new:
        print(f"{path.relative_to(ROOT)}: {orig} -> {new}")
    return orig - new


def main():
    total = 0
    for pattern in GLOBS:
        for path in ROOT.glob(pattern):
            if path.is_file():
                total += process_file(path)
    print(f"Removed {total} style= occurrences")


if __name__ == "__main__":
    main()
