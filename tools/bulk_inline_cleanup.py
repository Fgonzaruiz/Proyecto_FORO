#!/usr/bin/env python3
"""Bulk replace common inline styles with CSS classes in game PHP files."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

REPLACEMENTS = [
    ('class="textbox" style="width: 100%;"', 'class="textbox rpg-input-full"'),
    ('class="textbox" rows="3" style="width: 100%;"', 'class="textbox rpg-input-full" rows="3"'),
    ('class="textbox" rows="2" style="width: 100%;"', 'class="textbox rpg-input-full" rows="2"'),
    ('textarea id="c_desc" class="textbox" rows="3" style="width: 100%;"', 'textarea id="c_desc" class="textbox rpg-input-full" rows="3"'),
    ('placeholder="https://..." style="width: 100%;"', 'placeholder="https://..." class="textbox rpg-input-full"'),
    ('style="grid-column: 1 / -1; display: none; grid-template-columns: 1fr 1fr; gap: 16px; border-top: 1px solid var(--border-color); padding-top: 12px;"',
     'class="rpg-staff-field-section"'),
    ('id="fields-akuma" style="grid-column: 1 / -1; display: none; grid-template-columns: 1fr 1fr; gap: 16px; border-top: 1px solid var(--border-color); padding-top: 12px;"',
     'id="fields-akuma" class="rpg-staff-field-section"'),
    ('id="fields-equipo" style="grid-column: 1 / -1; display: none; grid-template-columns: 1fr 1fr; gap: 16px; border-top: 1px solid var(--border-color); padding-top: 12px;"',
     'id="fields-equipo" class="rpg-staff-field-section"'),
    ('id="fields-barco" style="grid-column: 1 / -1; display: none; grid-template-columns: 1fr 1fr; gap: 16px; border-top: 1px solid var(--border-color); padding-top: 12px;"',
     'id="fields-barco" class="rpg-staff-field-section"'),
    ('id="fields-npc" style="grid-column: 1 / -1; display: none; grid-template-columns: 1fr 1fr; gap: 16px; border-top: 1px solid var(--border-color); padding-top: 12px;"',
     'id="fields-npc" class="rpg-staff-field-section"'),
    ('id="fields-haki" style="grid-column: 1 / -1; display: none; grid-template-columns: 1fr 1fr; gap: 16px; border-top: 1px solid var(--border-color); padding-top: 12px;"',
     'id="fields-haki" class="rpg-staff-field-section"'),
    ('<div style="grid-column: 1 / -1;">', '<div class="rpg-grid-full">'),
    ('id="wrapper-dice" style="grid-column: 1 / -1; border-top: 1px solid var(--border-color); padding-top: 12px;"',
     'id="wrapper-dice" class="rpg-grid-full rpg-section-divider"'),
    ('id="wrapper-turns" style="grid-column: 1 / -1; border-top: 1px solid var(--border-color); padding-top: 12px; display: grid; grid-template-columns: 1fr 1fr; gap: 16px;"',
     'id="wrapper-turns" class="rpg-grid-full rpg-section-divider rpg-grid-2"'),
    ('<div id="tag-selected" style="display: flex; flex-wrap: wrap; gap: 4px; min-height: 28px; padding: 4px 0;"></div>',
     '<div id="tag-selected" class="rpg-staff-tag-selected"></div>'),
    ('<div id="tag-dropdown" style="display: none; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-card); max-height: 320px; overflow-y: auto; margin-top: 8px;"></div>',
     '<div id="tag-dropdown" class="rpg-staff-tag-dropdown"></div>'),
    ('id="tag-toggle-btn" class="rpg-action-btn rpg-btn-secondary" style="margin-top: 6px; padding: 4px 12px; font-size: 13px;"',
     'id="tag-toggle-btn" class="rpg-action-btn rpg-btn-secondary rpg-staff-tag-toggle"'),
    ('<div style="display: flex; gap: 8px; margin-top: 4px;">', '<div class="rpg-dice-toolbar">'),
    ('<div style="display: flex; gap: 12px; margin-top: 8px; flex-wrap: wrap;">', '<div class="rpg-dice-meta-row">'),
    ('style="font-size: 0.8em; color: var(--text-secondary); display: block; margin-bottom: 2px;"', 'class="rpg-dice-label-sm"'),
    ('id="dice-fixed" min="0" value="0" class="textbox" style="width: 70px; padding: 4px 8px !important; height: 28px; font-size: 12px; line-height: 20px;"',
     'id="dice-fixed" min="0" value="0" class="textbox rpg-dice-input-sm"'),
    ('id="dice-stat-mod" class="textbox" placeholder="Ej: 2.5* o /2" style="width: 100px; padding: 4px 8px !important; height: 28px; font-size: 12px; line-height: 20px;"',
     'id="dice-stat-mod" class="textbox rpg-dice-input-md" placeholder="Ej: 2.5* o /2"'),
    ('id="dice-suffix" class="textbox" placeholder="[FUEGO]" style="width: 110px; padding: 4px 8px !important; height: 28px; font-size: 12px; line-height: 20px;"',
     'id="dice-suffix" class="textbox rpg-dice-input-lg" placeholder="[FUEGO]"'),
    ('id="dice-stat" class="textbox" style="width: 90px; padding: 4px 20px 4px 8px !important; height: 28px; font-size: 12px; line-height: 20px; background-position: right 6px top 50% !important; background-size: 8px auto !important;"',
     'id="dice-stat" class="textbox rpg-dice-select-sm"'),
    ('<div style="display: flex; align-items: flex-end;">', '<div class="rpg-dice-meta-row">'),
    ('style="padding: 0 12px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); font-family: monospace; font-size: 0.95em; height: 28px; display: flex; align-items: center; box-shadow: var(--shadow-card);"',
     'class="rpg-dice-preview-box"'),
    ('<span style="font-size: 0.8em; color: var(--text-muted); margin-right: 6px;">→</span>',
     '<span class="rpg-dice-preview-arrow">→</span>'),
    ('<span id="dice-preview" style="color: var(--text-primary); font-weight: bold;">—</span>',
     '<span id="dice-preview" class="rpg-dice-preview-value">—</span>'),
    ('id="dice-add-group" class="rpg-action-btn rpg-btn-secondary" style="padding: 2px 10px; font-size: 12px;"',
     'id="dice-add-group" class="rpg-action-btn rpg-btn-secondary rpg-btn-sm"'),
    ('id="dice-add-arma" class="rpg-action-btn rpg-btn-secondary" style="padding: 2px 10px; font-size: 12px;"',
     'id="dice-add-arma" class="rpg-action-btn rpg-btn-secondary rpg-btn-sm"'),
    ('id="dice-add-municion" class="rpg-action-btn rpg-btn-secondary" style="padding: 2px 10px; font-size: 12px;"',
     'id="dice-add-municion" class="rpg-action-btn rpg-btn-secondary rpg-btn-sm"'),
    ('<div id="npc-actions-container" style="display:flex; flex-direction:column; gap:8px;"></div>',
     '<div id="npc-actions-container" class="rpg-npc-actions"></div>'),
    ('id="btn-npc-add-action" class="rpg-action-btn rpg-btn-secondary" style="padding: 4px 12px; font-size:12px; margin-top:8px;"',
     'id="btn-npc-add-action" class="rpg-action-btn rpg-btn-secondary rpg-staff-tag-toggle"'),
    ('<div style="grid-column: 1 / -1; display: flex; gap: 10px; justify-content: flex-end; margin-top: 4px;">',
     '<div class="rpg-staff-editor-actions">'),
    ('<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start;">',
     '<div class="rpg-staff-assign-grid">'),
    ('<div style="background: var(--bg-card); padding: 20px; border-radius: var(--radius-lg); border: 1px solid var(--border-color);">',
     '<div class="rpg-staff-panel-card">'),
    ('<div class="rpg-form-group" style="position: relative;">', '<div class="rpg-form-group rpg-char-search">'),
    ('class="char-search-input textbox" placeholder="Escribe el nombre del personaje..." style="width: 100%;" autocomplete="off"',
     'class="char-search-input textbox rpg-input-full" placeholder="Escribe el nombre del personaje..." autocomplete="off"'),
    ('class="char-search-results" style="display: none; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-card); max-height: 200px; overflow-y: auto; position: absolute; z-index: 100; width: 100%;"',
     'class="char-search-results rpg-char-search-results"'),
    ('<h4 style="margin-top:0;">Deck del Personaje</h4>', '<h4 class="rpg-staff-panel-title">Deck del Personaje</h4>'),
    ('id="btn-view-deck" class="rpg-action-btn rpg-btn-secondary" style="width: 100%; margin-bottom: 12px;"',
     'id="btn-view-deck" class="rpg-action-btn rpg-btn-secondary rpg-staff-btn-block"'),
    ('<ul id="deck-list" style="list-style: none; padding: 0; margin: 0; max-height: 300px; overflow-y: auto;">',
     '<ul id="deck-list" class="rpg-staff-deck-list">'),
    ('id="equipo_subtipo_select" class="textbox" style="width: 100%; margin-bottom: 8px;"',
     'id="equipo_subtipo_select" class="textbox rpg-input-full rpg-subtipo-select"'),
    ('id="equipo_subtipo" class="textbox" style="width: 100%; display: none;"',
     'id="equipo_subtipo" class="textbox rpg-input-full rpg-subtipo-other"'),
    ('style="padding: 2px 10px; font-size: 12px;"', 'class="rpg-btn-sm"'),
]

CARTAS_SCRIPT_OLD_START = "<script>\nvar GAME_AJAX_BASE"
CARTAS_SCRIPT_NEW = """<script>
window.CARTAS_STAFF_CONFIG = { ajaxBase: '<?= rtrim($b_url, '/') ?>/game/ajax' };
</script>
<script src="<?= rtrim($b_url, '/') ?>/jscripts/game/cartas_staff.js?v=1"></script>"""

def process_file(rel: str, extra: list | None = None):
    path = ROOT / rel
    text = path.read_text(encoding="utf-8")
    reps = REPLACEMENTS + (extra or [])
    for old, new in reps:
        text = text.replace(old, new)
    path.write_text(text, encoding="utf-8")
    count = text.count("style=")
    print(f"{rel}: {count} style= remaining")

# cartas_staff: replace script block
cartas = ROOT / "back/forum/game/public/cartas_staff.php"
ct = cartas.read_text(encoding="utf-8")
si = ct.find("<script>\nvar GAME_AJAX_BASE")
ei = ct.find("</script>", si) + len("</script>")
if si >= 0 and ei > si:
    ct = ct[:si] + CARTAS_SCRIPT_NEW + ct[ei:]
    cartas.write_text(ct, encoding="utf-8")
    print("cartas_staff.php: script extracted")
process_file("back/forum/game/public/cartas_staff.php")

CREAR_EXTRA = [
    ('style="color:var(--text-muted); font-size:13px; margin-bottom:15px;"', 'class="rpg-wizard-text-muted"'),
    ('style="color:var(--text-muted); font-size:13px; margin-bottom:20px;"', 'class="rpg-wizard-text-muted--lg"'),
    ('class="wizard-section" style="margin-bottom:0;"', 'class="wizard-section rpg-wizard-section--flush"'),
    ('class="textbox" style="height:50px; font-size:16px;"', 'class="textbox rpg-wizard-select-lg"'),
    ('style="text-align:center; margin-top: 30px; opacity:0.4;"', 'class="rpg-wizard-center-muted"'),
    ('style="font-size:64px; color:var(--text-muted);"', 'class="rpg-wizard-icon-muted"'),
    ('class="wizard-section" style="margin-top: 30px;"', 'class="wizard-section" style="margin-top:30px;"'),
    ('class="linaje-slots-bar" id="linajeSlotBar" style="display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 14px 20px; margin-bottom: 20px; background: linear-gradient(135deg, rgba(198,40,40,0.05), rgba(74,20,140,0.03)); border-radius: var(--radius-lg); border: 1px solid rgba(198,40,40,0.2);"',
     'class="linaje-slots-bar rpg-wizard-linaje-bar" id="linajeSlotBar"'),
    ('class="linaje-slots-group" style="display: flex; align-items: center; gap: 16px;"', 'class="linaje-slots-group rpg-wizard-linaje-row"'),
    ('class="linaje-slots-label" style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted);"',
     'class="linaje-slots-label rpg-wizard-linaje-label"'),
    ('style="color:var(--accent-indigo);"', 'class="rpg-wizard-icon-accent"'),
    ('class="linaje-slots-dots" id="linajeDots" style="display: flex; gap: 8px;"', 'class="linaje-slots-dots rpg-wizard-linaje-dots" id="linajeDots"'),
    ('class="linaje-slots-count" style="font-size: 22px;"', 'class="linaje-slots-count rpg-wizard-linaje-count"'),
    ('id="linajeSobranteBonus" style="font-size: 12px; font-weight: 700; color: #10b981; text-transform: uppercase; letter-spacing: 0.5px;"',
     'id="linajeSobranteBonus" class="rpg-wizard-linaje-bonus"'),
    ('class="linaje-section-header" style="color:#10b981;"', 'class="linaje-section-header rpg-wizard-linaje-header--green"'),
    ('style="color:#10b981;"', 'class="rpg-wizard-linaje-header--green"'),
    ('class="linaje-section-header" style="color:var(--accent-indigo);"', 'class="linaje-section-header rpg-wizard-linaje-header--primary"'),
    ('class="linaje-section-badge" style="background:rgba(16,185,129,0.1); color:#10b981;"', 'class="linaje-section-badge rpg-wizard-linaje-badge rpg-wizard-linaje-badge--green"'),
    ('class="linaje-section-badge" style="background:rgba(198,40,40,0.1); color:var(--accent-indigo);"', 'class="linaje-section-badge rpg-wizard-linaje-badge rpg-wizard-linaje-badge--primary"'),
    ('class="linaje-section-header" style="color:var(--accent-purple);"', 'class="linaje-section-header rpg-wizard-linaje-header--purple"'),
    ('class="linaje-section-badge" style="background:rgba(74,20,140,0.1); color:var(--accent-purple);"', 'class="linaje-section-badge rpg-wizard-linaje-badge rpg-wizard-linaje-badge--purple"'),
    ('type="button" style="border:none; padding:12px 24px; border-radius: var(--radius-md); cursor:pointer; background: var(--bg-card); color: var(--text-primary); font-family: var(--font-heading); font-weight:700;" onclick="goToStep(1)"',
     'type="button" class="rpg-wizard-btn-back" onclick="goToStep(1)"'),
    ('type="button" style="border:none; padding:12px 32px; border-radius: var(--radius-md); cursor:pointer; background: linear-gradient(135deg, var(--accent-indigo), var(--accent-purple)); color: #fff; font-family: var(--font-heading); font-weight:700;" onclick="goToStep(3)"',
     'type="button" class="rpg-wizard-btn-next" onclick="goToStep(3)"'),
    ('class="wizard-step-content" style="display:none;"', 'class="wizard-step-content rpg-wizard-hidden"'),
    ('class="wizard-section" style="padding: 0; display: flex; overflow:hidden; min-height: 600px;"', 'class="wizard-section rpg-wizard-step-3-layout"'),
    ('<div style="width: 320px; background: var(--bg-main); border-right: 1px solid var(--border-color); display:flex; flex-direction:column; overflow-y:auto;">',
     '<div class="rpg-wizard-preview-sidebar">'),
    ('id="preview_avatar" style="width:100%; height:450px; min-height:450px; background-size:cover; background-position:center; background-image:url(\'https://placehold.co/320x450\');"',
     'id="preview_avatar" class="rpg-wizard-preview-avatar" style="background-image:url(\'https://placehold.co/320x450\');"'),
]
process_file("back/forum/game/public/crear_personaje.php", CREAR_EXTRA)

PETICIONES_EXTRA = [
    ('class="rpg-staff-header" style="background: linear-gradient(135deg, rgba(139,92,246,0.15), rgba(198,40,40,0.1));"',
     'class="rpg-staff-header rpg-staff-header--peticiones"'),
    ('<div style="display:flex; gap:0; border-bottom: 2px solid var(--border-color); margin-top: 20px;">', '<div class="rpg-peticiones-tabs">'),
    ('id="tab-cartas" style="display:block;"', 'id="tab-cartas" class="rpg-peticiones-tab-panel"'),
    ('id="tab-busquedas" style="display:none; margin-top:20px;"', 'id="tab-busquedas" class="rpg-peticiones-tab-panel rpg-is-hidden"'),
    ('class="aprobar-layout" style="display:flex; gap:20px; margin-top:20px;"', 'class="aprobar-layout"'),
    ('class="aprobar-list" id="requests-list" style="width:320px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:var(--radius-lg); flex-shrink:0; display:flex; flex-direction:column; overflow:hidden; box-shadow:var(--shadow-card); min-height:750px;"',
     'class="aprobar-list" id="requests-list"'),
    ('class="aprobar-list-header" style="padding:15px; border-bottom:1px solid var(--border-color); background:var(--bg-surface); font-weight:700; display:flex; justify-content:space-between; align-items:center;"',
     'class="aprobar-list-header"'),
    ('class="aprobar-count" id="requests-count" style="background:var(--accent-indigo); color:#fff; padding:2px 8px; border-radius:10px; font-size:11px;"',
     'class="aprobar-count" id="requests-count"'),
    ('id="requests-list-items" style="flex:1; overflow-y:auto; max-height:680px;"', 'id="requests-list-items" class="aprobar-list-items"'),
    ('class="aprobar-empty" style="padding:20px; text-align:center; color:var(--text-muted);"', 'class="aprobar-empty"'),
    ('class="aprobar-preview" id="request-preview" style="flex:1; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:var(--radius-lg); overflow:hidden; box-shadow:var(--shadow-card); min-height:750px; display:flex; flex-direction:column; justify-content:center; align-items:center; padding:40px 20px; text-align:center; color:var(--text-muted);"',
     'class="aprobar-preview" id="request-preview"'),
    ('id="fields-mod-akuma" style="grid-column: 1 / -1; display: none; grid-template-columns: 1fr 1fr; gap: 16px; border-top: 1px solid var(--border-color); padding-top: 12px;"',
     'id="fields-mod-akuma" class="rpg-staff-field-section"'),
    ('id="fields-mod-equipo" style="grid-column: 1 / -1; display: none; grid-template-columns: 1fr 1fr; gap: 16px; border-top: 1px solid var(--border-color); padding-top: 12px;"',
     'id="fields-mod-equipo" class="rpg-staff-field-section"'),
    ('id="fields-mod-barco" style="grid-column: 1 / -1; display: none; grid-template-columns: 1fr 1fr; gap: 16px; border-top: 1px solid var(--border-color); padding-top: 12px;"',
     'id="fields-mod-barco" class="rpg-staff-field-section"'),
    ('id="fields-mod-npc" style="grid-column: 1 / -1; display: none; grid-template-columns: 1fr 1fr; gap: 16px; border-top: 1px solid var(--border-color); padding-top: 12px;"',
     'id="fields-mod-npc" class="rpg-staff-field-section"'),
    ('id="fields-mod-haki" style="grid-column: 1 / -1; display: none; grid-template-columns: 1fr 1fr; gap: 16px; border-top: 1px solid var(--border-color); padding-top: 12px;"',
     'id="fields-mod-haki" class="rpg-staff-field-section"'),
    ('<div id="mod-npc-actions-container" style="display:flex; flex-direction:column; gap:8px;"></div>',
     '<div id="mod-npc-actions-container" class="rpg-npc-actions"></div>'),
]
process_file("back/forum/game/public/zona_staff_peticiones.php", PETICIONES_EXTRA + REPLACEMENTS)
