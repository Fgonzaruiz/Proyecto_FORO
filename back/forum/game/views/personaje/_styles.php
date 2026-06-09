<style>
.rpg-char-page { max-width: 1200px; margin: 0 auto; }
.pj-page-shell { display: flex; background: var(--bg-main); border: 1px solid var(--border-color); border-radius: var(--radius-lg); overflow: hidden; min-height: 700px; margin-top: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
.pj-page-content { flex: 1; padding: 40px; overflow-y: auto; }
.pj-bio-meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 30px; background: var(--bg-surface); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--border-color); }
.pj-bio-meta-item { font-size: 14px; }
.pj-tab-section-heading { font-family: var(--font-heading); font-size: 18px; color: var(--text-primary); margin-bottom: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px; }
.pj-scroll-box--bio { height: 200px; }
/* Ficha personaje — complementa rpg_custom.css (tokens v2 Carta Náutica) */
.pj-preview-tabs { display: flex; border-bottom: 2px solid var(--border-color); margin-bottom: 24px; }
.pj-preview-tab {
    padding: 10px 20px; font-family: var(--font-heading); font-weight: 700; font-size: 14px;
    color: var(--text-muted); cursor: pointer; border-bottom: 3px solid transparent;
    margin-bottom: -2px; transition: all 0.2s ease;
}
.pj-preview-tab:hover { color: var(--text-primary); }
.pj-preview-tab.active { color: var(--accent-primary); border-bottom-color: var(--accent-primary); }
.pj-preview-tab-content { display: none; min-width: 0; max-width: 100%; box-sizing: border-box; }
.pj-preview-tab-content.active { display: block; }

/* Barras de stats (copiadas del creador) */
.rpg-preview-stat-bar { background: var(--bg-card); border-radius: 10px; height: 8px; width: 100%; overflow: hidden; margin-top: 4px; }
.rpg-preview-stat-fill { height: 100%; background: linear-gradient(90deg, var(--accent-primary), var(--accent-purple)); border-radius: 10px; transition: width 0.5s ease; }
.rpg-preview-stat-row { margin-bottom: 12px; text-align: left; }

/* Gene cards */
.gene-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}
.gene-card {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    padding: 16px;
    background: var(--bg-main);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
    text-align: left;
}
.gene-card:hover { border-color: var(--border-hover); transform: translateX(3px); }
.gene-card-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}
.gene-card-info { flex: 1; display: flex; flex-direction: column; }
.gene-card-name { font-weight: 800; font-size: 13px; color: var(--text-primary); margin-bottom: 2px; font-family: var(--font-heading); text-transform: uppercase; letter-spacing: 0.5px; }
.gene-card-desc { font-size: 11px; color: var(--text-muted); line-height: 1.4; margin-top: 4px; margin-bottom: 6px; }
.gene-card-badge {
    align-self: flex-start;
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 2px 8px;
    border-radius: 10px;
    flex-shrink: 0;
}
.gene-card.passive-primary { border-left: 3px solid #10b981; }
.gene-card.passive-secondary { border-left: 3px solid #f59e0b; }
.gene-card.perk-racial { border-left: 3px solid var(--accent-primary); }
.gene-card.perk-general { border-left: 3px solid var(--accent-purple); }

/* Custom Scrollbars for boxes */
.pj-scroll-box {
    background: var(--bg-surface); border: 1px solid var(--border-color);
    border-radius: var(--radius-md); padding: 20px; height: 280px;
    overflow-y: auto; margin-bottom: 30px; font-size: 14px; line-height: 1.7; color: var(--text-secondary);
}
.pj-scroll-box::-webkit-scrollbar { width: 6px; }
.pj-scroll-box::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); border-radius: 4px; }
.pj-scroll-box::-webkit-scrollbar-thumb { background: var(--accent-primary); border-radius: 4px; }

/* Timeline (Diario estilo libreta) */
.pj-timeline { position: relative; margin-top: 20px; }
.pj-timeline-item { background: #fdfbf7; color: #333; padding: 20px 25px; border-radius: 4px; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.15); position: relative; border-left: 5px solid #d4c5b0; font-family: 'Georgia', serif; }
.pj-timeline-item::before { content: ''; position: absolute; left: 8px; right: 8px; top: 8px; bottom: 8px; border: 1px dashed rgba(0,0,0,0.08); pointer-events: none; }
.pj-timeline-date { font-family: var(--font-heading); font-size: 13px; font-weight: 700; color: #8c7b66; border-bottom: 2px solid rgba(212,197,176,0.3); padding-bottom: 5px; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px; }
.pj-timeline-desc { font-size: 15px; color: #4a4a4a; line-height: 1.8; margin-bottom: 15px; font-style: italic; }
.pj-timeline-link { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: bold; text-transform: uppercase; color: #8c7b66; background: #f0e9df; padding: 6px 14px; border-radius: 20px; text-decoration: none; transition: all 0.2s; border: 1px solid #d4c5b0; font-family: var(--font-main); font-style: normal; }
.pj-timeline-link:hover { background: #d4c5b0; color: #fff; }

/* Relations Grid */
.pj-relations-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; margin-top: 20px; }
.pj-relation-card { background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 15px; text-align: center; transition: transform 0.2s, border-color 0.2s; }
.pj-relation-card:hover { transform: translateY(-5px); border-color: var(--accent-purple); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
.pj-relation-img { width: 75px; height: 75px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent-primary); margin: 0 auto 12px auto; display: block; padding: 3px; background: rgba(255,255,255,0.05); }
.pj-relation-name { font-family: var(--font-heading); font-size: 15px; font-weight: 700; color: var(--text-primary); margin-bottom: 5px; }
.pj-relation-tag-wrap { display: flex; flex-wrap: wrap; gap: 4px; justify-content: center; margin-top: 4px; }
.pj-relation-tag { font-size: 10px; font-weight: 700; text-transform: uppercase; display: inline-block; padding: 2px 8px; border-radius: 10px; letter-spacing: 0.3px; }

/* Tag selector */
.pj-tag-selector { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 5px; }
.pj-tag-option { font-size: 11px; font-weight: 600; padding: 4px 12px; border-radius: 14px; cursor: pointer; border: 2px solid transparent; transition: all 0.15s; opacity: 0.5; user-select: none; }
.pj-tag-option.selected { opacity: 1; border-color: currentColor; box-shadow: 0 0 8px rgba(0,0,0,0.15); }
.pj-tag-option:hover { opacity: 0.8; }
.pj-tag-picker { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 5px; }
.pj-tag { font-size: 11px; font-weight: 600; padding: 4px 12px; border-radius: 14px; cursor: pointer; border: 2px solid transparent; background: rgba(0,0,0,0.1); transition: all 0.15s; opacity: 0.5; user-select: none; }
.pj-tag.active { opacity: 1; background: currentColor !important; color: #fff !important; box-shadow: 0 0 8px rgba(0,0,0,0.15); }
.pj-tag:hover { opacity: 0.8; }


/* In-situ Modals (Beautified & Made Theme-Independent) */
.pj-modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.6) !important;
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    backdrop-filter: blur(4px);
}
.pj-modal {
    background: var(--bg-surface, #1a1c2e) !important;
    color: var(--text-primary, #e2e8f0) !important;
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08)) !important;
    border-radius: 16px !important;
    width: 560px;
    max-width: 94vw;
    padding: 30px !important;
    box-shadow: var(--shadow-main, 0 25px 50px rgba(0, 0, 0, 0.7)) !important;
    position: relative;
    overflow: visible;
    box-sizing: border-box !important;
}
.pj-modal::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 5px;
    background: linear-gradient(90deg, var(--accent-primary), var(--accent-purple));
    border-top-left-radius: 16px;
    border-top-right-radius: 16px;
}
.pj-modal-title {
    font-family: var(--font-heading, inherit);
    font-size: 18px !important;
    color: var(--text-primary) !important;
    margin-bottom: 20px !important;
    text-align: center;
    font-weight: 800 !important;
}
.pj-modal .form-group {
    margin-bottom: 16px !important;
}
.pj-modal .textbox {
    background: var(--bg-main, rgba(0, 0, 0, 0.3)) !important;
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.1)) !important;
    color: var(--text-primary, #ffffff) !important;
    border-radius: 8px !important;
    padding: 12px 14px !important;
    transition: all 0.3s !important;
    width: 100% !important;
    box-sizing: border-box !important;
    font-size: 13px !important;
}
.pj-modal .textbox:focus {
    background: var(--bg-card) !important;
    border-color: var(--accent-primary) !important;
    box-shadow: 0 0 0 3px rgba(211, 47, 47, 0.2) !important;
    outline: none !important;
}
.pj-modal label {
    font-size: 11px !important;
    text-transform: uppercase !important;
    letter-spacing: 1px !important;
    color: var(--text-secondary, rgba(255, 255, 255, 0.6)) !important;
    font-weight: 700 !important;
    margin-bottom: 6px !important;
    display: block !important;
}

.pj-modal-actions {
    text-align: right !important;
    margin-top: 20px !important;
    display: flex !important;
    justify-content: flex-end !important;
    gap: 12px !important;
}

.pj-edit-list {
    max-height: 380px !important;
    overflow-y: auto !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 10px !important;
    padding-right: 5px !important;
}
/* Custom Premium Scrollbar for lists */
.pj-edit-list::-webkit-scrollbar {
    width: 6px !important;
}
.pj-edit-list::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.02) !important;
    border-radius: 4px !important;
}
.pj-edit-list::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15) !important;
    border-radius: 4px !important;
    transition: background 0.2s !important;
}
.pj-edit-list::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3) !important;
}

.pj-edit-item {
    background: var(--bg-card, rgba(255, 255, 255, 0.03)) !important;
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08)) !important;
    border-radius: 12px !important;
    padding: 12px 16px !important;
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 15px !important;
    width: 100% !important;
    box-sizing: border-box !important;
    transition: all 0.2s ease-in-out !important;
}
.pj-edit-item:hover {
    background: var(--bg-card-hover, rgba(255, 255, 255, 0.06)) !important;
    border-color: var(--border-hover, rgba(255, 255, 255, 0.15)) !important;
    transform: translateY(-1px) !important;
}
.pj-edit-item-body {
    flex: 1 1 auto !important;
    min-width: 0 !important;
}
.pj-edit-item-actions {
    display: flex !important;
    gap: 8px !important;
    flex-shrink: 0 !important;
    margin-left: auto !important; /* Pushes action buttons to the far right */
    align-items: center !important;
}
.pj-edit-btn {
    width: 32px !important;
    height: 32px !important;
    border-radius: 8px !important;
    border: none !important;
    cursor: pointer !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 13px !important;
    transition: all 0.2s ease !important;
    box-sizing: border-box !important;
    padding: 0 !important; /* Resets any button padding */
}
.pj-edit-btn:hover {
    transform: scale(1.08) translateY(-1px) !important;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3) !important;
}
.pj-edit-btn-edit {
    background: rgba(59, 130, 246, 0.25) !important;
    color: #93c5fd !important;
    border: 1px solid rgba(59, 130, 246, 0.4) !important;
}
.pj-edit-btn-edit:hover {
    background: #3b82f6 !important;
    color: #ffffff !important;
    border-color: #3b82f6 !important;
}
.pj-edit-btn-del {
    background: rgba(239, 68, 68, 0.25) !important;
    color: #fca5a5 !important;
    border: 1px solid rgba(239, 68, 68, 0.4) !important;
}
.pj-edit-btn-del:hover {
    background: #ef4444 !important;
    color: #ffffff !important;
    border-color: #ef4444 !important;
}

/* Tabs inside Modals */
.pj-modal-tab-btn {
    background: var(--btn-primary-bg) !important;
    color: #ffffff !important;
    border: 1px solid rgba(184, 151, 66, 0.4) !important;
    padding: 8px 16px !important;
    font-family: var(--font-heading, inherit) !important;
    font-size: 12px !important;
    font-weight: 800 !important;
    text-transform: uppercase !important;
    cursor: pointer !important;
    transition: transform 0.2s, box-shadow 0.2s !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    box-shadow: var(--btn-primary-shadow) !important;
    border-radius: var(--radius-md) !important;
    opacity: 0.6 !important;
}
.pj-modal-tab-btn:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 20px rgba(198, 40, 40, 0.4) !important;
    opacity: 1 !important;
}
.pj-modal-tab-btn.active {
    opacity: 1 !important;
    box-shadow: var(--btn-primary-shadow) !important;
}
.pj-cat-counter { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
.pj-cat-chip { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px 4px 6px; border-radius: 6px; font-size: 11px; font-weight: 700; line-height: 1; }
.pj-cat-chip .num { font-size: 14px; font-weight: 800; }
.pj-cat-picker { cursor:pointer; border-radius:8px; padding:6px 16px; font-weight:700; font-size:12px; transition:all 0.15s; opacity:0.6; user-select:none; }
.pj-cat-picker:hover { opacity:0.9; }
.pj-cat-picker.active { opacity:1; box-shadow: 0 0 10px rgba(0,0,0,0.3); }

/* Sidebar ficha */
.pj-sidebar {
    width: 290px;
    min-width: 290px;
    background: var(--bg-surface);
    border-right: 1px solid var(--border-color);
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
}
.pj-sidebar-avatar {
    width: 290px;
    height: 450px;
    min-width: 290px;
    min-height: 450px;
    max-width: 290px;
    max-height: 450px;
    flex-shrink: 0;
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center top;
    background-color: var(--bg-main);
    border-bottom: 2px solid var(--accent-primary);
}
.pj-sidebar-avatar img {
    width: 290px;
    height: 450px;
    object-fit: contain;
    object-position: center top;
    display: block;
}
.pj-sidebar-body { padding: 20px; }
.pj-sidebar-name {
    font-family: var(--font-heading);
    font-size: 24px;
    color: var(--text-primary);
    margin-bottom: 10px;
    text-align: center;
}
.pj-sidebar-name--pirata { color: var(--color-faccion-pirata); }
.pj-sidebar-name--marine { color: var(--color-faccion-marine); }
.pj-sidebar-name--cazador { color: var(--color-faccion-cazador); }
.pj-sidebar-name--civil { color: var(--color-faccion-civil); }
.pj-sidebar-name--revolucionario { color: var(--color-faccion-revolucionario); }
.pj-sidebar-name--gobierno { color: var(--color-faccion-gobierno); }
.pj-sidebar-name--staff { color: var(--color-faccion-staff); }
.pj-sidebar-badges {
    display: flex;
    justify-content: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 15px;
}
.pj-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 4px rgba(43, 34, 26, 0.04);
    border: 1px solid transparent;
    transition: all 0.2s ease;
}
.pj-badge:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(43, 34, 26, 0.08);
}
.pj-badge--ok { background: rgba(16, 185, 129, 0.08); color: #10b981; border-color: rgba(16, 185, 129, 0.25); }
.pj-badge--warn { background: rgba(245, 158, 11, 0.08); color: #d97706; border-color: rgba(245, 158, 11, 0.25); }
.pj-badge--err { background: rgba(239, 68, 68, 0.08); color: #ef4444; border-color: rgba(239, 68, 68, 0.25); }
.pj-badge--faction { background: rgba(198, 40, 40, 0.08); color: var(--accent-primary); border-color: rgba(198, 40, 40, 0.25); }
.pj-badge--rank { background: rgba(124, 58, 237, 0.08); color: #7c3aed; border-color: rgba(124, 58, 237, 0.25); }
.pj-badge--staff { background: var(--accent-primary); color: #fff; border-color: var(--accent-primary); }
.pj-badge--level { background: rgba(184, 151, 66, 0.08); color: var(--color-laton); border-color: rgba(184, 151, 66, 0.25); }
.pj-sidebar-info {
    background: var(--bg-card);
    border-radius: var(--radius-md);
    padding: 15px;
    border: 1px solid var(--border-color);
    margin-bottom: 20px;
}
.pj-info-row {
    display: flex;
    align-items: center;
    gap: 10px;
}
.pj-info-row + .pj-info-row { margin-top: 10px; }
.pj-info-row--border { margin-bottom: 10px; border-bottom: 1px solid var(--border-color); padding-bottom: 5px; }
.pj-info-icon { color: var(--text-secondary); font-size: 20px; }
.pj-info-label {
    font-size: 10px;
    color: var(--text-muted);
    text-transform: uppercase;
    font-weight: bold;
}
.pj-info-value {
    font-weight: 700;
    color: var(--text-primary);
    font-size: 14px;
}
.pj-vitals-row { display: flex; gap: 10px; margin-bottom: 20px; }
.pj-vital {
    flex: 1;
    border-radius: var(--radius-md);
    padding: 10px;
    text-align: center;
}
.pj-vital--pv { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); }
.pj-vital--pe { background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); }
.pj-vital__label {
    font-size: 10px;
    text-transform: uppercase;
    font-weight: bold;
    letter-spacing: 0.5px;
}
.pj-vital--pv .pj-vital__label { color: #f87171; }
.pj-vital--pe .pj-vital__label { color: #60a5fa; }
.pj-vital__value { font-size: 20px; font-weight: 800; margin-top: 4px; }
.pj-vital--pv .pj-vital__value { color: #ef4444; }
.pj-vital--pe .pj-vital__value { color: #3b82f6; }
.pj-stats-heading {
    font-size: 12px;
    font-family: var(--font-heading);
    color: var(--text-muted);
    text-transform: uppercase;
    margin-bottom: 10px;
}
.rpg-preview-stat-label {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    font-weight: bold;
}
.rpg-preview-stat-fill { width: var(--stat-pct, 0%); }
.rpg-preview-stat-fill--fue { background: linear-gradient(90deg, #C62828, #4f46e5); }
.rpg-preview-stat-fill--agi { background: linear-gradient(90deg, #10b981, #059669); }
.rpg-preview-stat-fill--des { background: linear-gradient(90deg, #3b82f6, #2563eb); }
.rpg-preview-stat-fill--inst { background: linear-gradient(90deg, #06b6d4, #0891b2); }
.rpg-preview-stat-fill--esp { background: linear-gradient(90deg, #ec4899, #db2777); }
.rpg-preview-stat-fill--int { background: linear-gradient(90deg, #f59e0b, #d97706); }

/* Tab cronología / secciones */
.pj-tab-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 10px;
    margin-bottom: 20px;
}
.pj-tab-section-header--spaced { margin-top: 40px; }
.pj-tab-section-title {
    font-family: var(--font-heading);
    font-size: 18px;
    color: var(--text-primary);
    margin: 0;
}
.pj-tab-section-actions { display: flex; gap: 8px; }
.pj-empty-msg {
    color: var(--text-muted);
    font-size: 14px;
    text-align: center;
    margin-bottom: 40px;
}
.pj-scroll-box--tall { height: 350px; }
.pj-timeline-item-wrapper {
    margin-bottom: 15px;
    border: 1px dashed var(--border-color);
    padding: 15px;
    border-radius: 6px;
    background: var(--bg-surface);
}
.pj-timeline-item--cat {
    border-left: 4px solid var(--cat-color, var(--accent-primary));
    padding-left: 15px;
}
.pj-timeline-date-row {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--cat-color, var(--accent-primary));
}
.pj-timeline-cat-label {
    text-transform: uppercase;
    letter-spacing: 1px;
    font-size: 11px;
    font-weight: 800;
}
.pj-timeline-date-sub { font-size: 12px; font-weight: 600; opacity: 0.7; }
.pj-timeline-thread-name {
    margin-top: 10px;
    font-size: 14px;
    font-weight: 700;
    color: var(--accent-primary);
}
.pj-timeline-desc--view {
    margin-top: 8px;
    font-size: 14px;
    line-height: 1.6;
    color: var(--text-primary);
    font-style: italic;
    white-space: pre-wrap;
}
.pj-timeline-participants { margin-top: 12px; display: flex; flex-wrap: wrap; gap: 6px; }
.pj-participant-chip {
    font-size: 11px;
    font-weight: 600;
    color: var(--text-primary);
    background: var(--bg-main);
    padding: 4px 10px;
    border-radius: 12px;
    border: 1px solid var(--border-color);
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.pj-participant-chip i { color: var(--text-muted); }
.pj-cat-chip { color: var(--cat-color); background: color-mix(in srgb, var(--cat-color) 13%, transparent); }

/* Edit lists (_scripts.php) */
.pj-empty-list-msg {
    color: var(--text-muted);
    font-size: 13px;
    text-align: center;
    padding: 20px 0;
}
.pj-grp-member-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    padding: 8px;
    border-radius: 6px;
    transition: background 0.2s;
}
.pj-grp-member-label:hover { background: rgba(255, 255, 255, 0.05); }
.pj-grp-member-check { width: 16px; height: 16px; }
.pj-grp-member-avatar { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; }
.pj-grp-member-name {
    font-size: 13px;
    color: var(--text-primary);
    text-transform: none;
    letter-spacing: normal;
    font-weight: normal;
}
.pj-grp-empty-hint {
    font-size: 12px;
    color: var(--text-muted);
    text-align: center;
    padding-top: 20px;
}
.pj-edit-item--cat {
    border-left: 4px solid var(--cat-color, var(--accent-primary));
    background: linear-gradient(to right, color-mix(in srgb, var(--cat-color) 8%, transparent), transparent);
    margin-bottom: 10px;
}
.pj-edit-item-body--pad { padding-right: 15px; }
.pj-edit-item-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--cat-color, var(--accent-primary));
}
.pj-edit-item-cat {
    text-transform: uppercase;
    letter-spacing: 1px;
    font-size: 11px;
    font-weight: 800;
}
.pj-edit-item-date { color: var(--text-muted); font-size: 12px; font-weight: 600; }
.pj-edit-item-thread {
    margin-top: 2px;
    font-size: 12px;
    font-weight: 700;
    color: var(--accent-primary);
}
.pj-edit-item-desc {
    margin-top: 6px;
    font-size: 13px;
    line-height: 1.4;
    color: var(--text-primary);
}
.pj-edit-item-participants { margin-top: 4px; display: flex; flex-wrap: wrap; gap: 3px; }
.pj-edit-participant-chip {
    font-size: 10px;
    font-weight: 600;
    color: var(--text-secondary);
    background: rgba(255, 255, 255, 0.05);
    padding: 1px 6px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
}
.pj-edit-item--spaced { margin-bottom: 10px; }
.pj-edit-item--grp { border-left: 4px solid var(--grp-color, var(--accent-purple)); }
.pj-rel-row {
    display: flex;
    align-items: center;
    gap: 15px;
    flex: 1;
    min-width: 0;
}
.pj-rel-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255, 255, 255, 0.1);
    flex-shrink: 0;
}
.pj-rel-info { min-width: 0; }
.pj-rel-name {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 8px;
}
.pj-rel-tag { color: var(--tag-color, var(--accent-primary)); margin-right: 10px; font-weight: 600; }
.pj-rel-tags { font-size: 11px; margin-top: 4px; display: flex; gap: 6px; flex-wrap: wrap; }
.pj-npc-badge {
    font-size: 9px;
    background: #f59e0b;
    color: #000;
    padding: 1px 5px;
    border-radius: 4px;
    font-weight: 800;
    text-transform: uppercase;
}
.pj-grp-row { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
.pj-grp-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--grp-color);
    box-shadow: 0 0 8px var(--grp-color);
    flex-shrink: 0;
}
.pj-grp-name {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-primary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pj-grp-count {
    font-size: 11px;
    color: var(--text-muted);
    font-weight: 600;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    padding: 3px 8px;
    flex-shrink: 0;
    margin-right: 5px;
}
.pj-conn-row {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 0;
    font-size: 13px;
}
.pj-conn-label {
    font-weight: 700;
    color: var(--conn-color);
    background: color-mix(in srgb, var(--conn-color) 15%, transparent);
    border: 1px solid color-mix(in srgb, var(--conn-color) 33%, transparent);
    padding: 2px 8px;
    border-radius: 6px;
    flex-shrink: 0;
}
.pj-conn-path {
    color: var(--text-muted);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pj-conn-path i { margin: 0 6px; opacity: 0.5; }
.pj-conn-path i:first-child { margin-right: 6px; margin-left: 0; }
.pj-debug-error {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 999999;
    padding: 20px;
    white-space: pre-wrap;
    font-family: monospace;
    color: #fff;
}
.pj-debug-error--fatal { background: red; }
.pj-debug-error--warn { background: orange; }

/* Solicitudes / gestión (_scripts.php) */
.pj-req-empty {
    padding: 40px 20px;
    color: var(--text-muted);
    text-align: center;
}
.pj-req-empty i {
    font-size: 24px;
    color: var(--accent-emerald);
    display: block;
    margin-bottom: 8px;
    opacity: 0.6;
}
.pj-req-list-item { /* uses existing classes */ }
.pj-req-preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 10px;
    margin-bottom: 15px;
}
.pj-req-preview-title {
    margin: 0;
    font-size: 15px;
    color: var(--text-primary);
    font-family: var(--font-heading);
    font-weight: 800;
}
.pj-req-status-pill {
    font-size: 10px;
    font-weight: 800;
    background: rgba(255, 255, 255, 0.05);
    padding: 3px 10px;
    border-radius: 12px;
    color: var(--text-muted);
}
.pj-req-preview-body { display: flex; gap: 15px; flex-wrap: wrap; flex: 1; min-height: 0; }
.pj-req-chat-col { flex: 1; display: flex; flex-direction: column; gap: 10px; min-width: 250px; }
.pj-req-card-col { display: flex; flex-direction: column; align-items: center; gap: 8px; flex-shrink: 0; }
.pj-req-card-label {
    font-size: 10px;
    font-weight: 800;
    color: var(--accent-primary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.pj-req-conforme-row .rpg-action-btn { flex: 1; justify-content: center; }
.pj-req-conforme-row { margin-top: 10px; display: flex; gap: 10px; }
.pj-req-list-row { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.pj-req-list-name { font-size: 12px; color: var(--text-primary); text-overflow: ellipsis; overflow: hidden; white-space: nowrap; max-width: 140px; }
.pj-req-list-status { font-size: 9px; font-weight: 800; color: var(--status-color); flex-shrink: 0; }
.pj-req-list-meta { font-size: 10px; color: var(--text-muted); margin-top: 4px; }
.pj-empty-list-msg { padding: 20px; color: var(--text-muted); text-align: center; }
.rpg-chat-sender--player { color: var(--accent-indigo); font-weight: 700; }
.rpg-chat-sender--staff { color: var(--accent-purple); font-weight: 700; }
.rpg-chat-time { margin-left: 10px; }
.rpg-chat-text { white-space: pre-wrap; }
.rpg-card-tag-pill { display: inline-block; font-size: 8px; font-weight: 700; padding: 1px 6px; border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-muted); text-transform: uppercase; }
.rpg-card-stat-row { display: flex; gap: 8px; margin: 10px 0; background: var(--bg-main); padding: 6px 10px; border-radius: 6px; border: 1px solid var(--border-color); font-size: 10px; }
.rpg-card-stat-label { display: block; font-size: 8px; color: var(--text-muted); font-weight: 700; }
.rpg-card-stat-val { color: var(--text-primary); }
.rpg-card-preview-img { width: 100%; height: 90px; background-image: var(--card-img); background-size: cover; background-position: center; border-radius: 4px; margin-bottom: 8px; }
.rpg-card-preview-head { padding: 8px 12px; background: var(--bg-surface); border-bottom: 1px solid var(--border-color); font-family: var(--font-heading); }
.rpg-card-preview-name { font-weight: 900; color: var(--text-primary); font-size: 12px; }
.rpg-card-preview-rank { font-size: 9px; color: var(--text-muted); text-transform: uppercase; margin-top: 2px; }
.rpg-card-preview-body { padding: 10px; }
.rpg-card-preview-tags { display: flex; gap: 3px; flex-wrap: wrap; margin-bottom: 8px; }
.rpg-card-preview-desc { font-size: 10px; color: var(--text-secondary); line-height: 1.4; height: 100px; overflow-y: auto; padding-right: 3px; white-space: pre-wrap; }

/* Tab gestión (_tab_gestion.php) */
.rpg-pp-display { background: linear-gradient(135deg, rgba(198,40,40,0.1), rgba(74,20,140,0.06)); border: 1px solid rgba(198,40,40,0.2); border-radius: 10px; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
.rpg-pp-display h3 { margin: 0; font-size: 14px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
.rpg-pp-val { font-size: 24px; font-weight: 900; color: var(--accent-indigo); text-shadow: 0 0 10px rgba(198,40,40,0.3); font-family: var(--font-heading); }
.rpg-attr-buy-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; }
.rpg-attr-buy-card { background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 10px; padding: 15px 18px; display: flex; flex-direction: column; gap: 12px; transition: border-color 0.2s; position: relative; }
.rpg-attr-buy-card:hover { border-color: rgba(198,40,40,0.3); }
.rpg-attr-buy-header { display: flex; align-items: center; gap: 10px; }
.rpg-attr-buy-icon { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; background: var(--icon-bg, transparent); color: var(--icon-color, inherit); }
.rpg-attr-buy-name { font-weight: 800; font-size: 12px; color: var(--text-primary); text-transform: uppercase; letter-spacing: 0.5px; font-family: var(--font-heading); }
.rpg-attr-buy-value { font-size: 15px; font-weight: 900; color: var(--text-primary); margin-left: auto; }
.rpg-attr-buy-actions { display: flex; align-items: center; justify-content: space-between; gap: 10px; border-top: 1px solid var(--border-color); padding-top: 10px; }
.rpg-attr-buy-cost { font-size: 11px; color: var(--text-muted); font-weight: 700; }
.rpg-attr-buy-cost span { color: var(--accent-indigo); }
.rpg-attr-buy-btn { background: linear-gradient(135deg, var(--accent-indigo), var(--accent-purple)); border: none; border-radius: 6px; color: #fff; padding: 8px 15px; font-weight: 800; font-size: 11px; text-transform: uppercase; cursor: pointer; transition: opacity 0.2s; display: inline-flex; align-items: center; gap: 6px; }
.rpg-attr-buy-btn:hover { opacity: 0.9; }
.rpg-chat-container { display: flex; flex-direction: column; height: 350px; background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; }
.rpg-chat-messages { flex: 1; overflow-y: auto; padding: 15px; display: flex; flex-direction: column; gap: 10px; }
.rpg-chat-bubble { padding: 10px 14px; border-radius: 8px; max-width: 85%; font-size: 13px; line-height: 1.5; word-break: break-word; position: relative; }
.rpg-chat-bubble.player { background: rgba(198,40,40,0.08); border: 1px solid rgba(198,40,40,0.15); align-self: flex-end; color: var(--text-primary); }
.rpg-chat-bubble.staff { background: rgba(74,20,140,0.08); border: 1px solid rgba(74,20,140,0.15); align-self: flex-start; color: var(--text-primary); }
.rpg-chat-bubble-meta { font-size: 9px; color: var(--text-muted); margin-bottom: 4px; display: flex; justify-content: space-between; font-weight: 700; }
.rpg-chat-input-bar { display: flex; border-top: 1px solid var(--border-color); background: var(--bg-surface); }
.rpg-chat-input { flex: 1; border: none; background: transparent; color: var(--text-primary); padding: 12px 15px; font-size: 13px; outline: none; }
.rpg-chat-send { background: var(--accent-indigo); color: #fff; border: none; padding: 0 20px; font-weight: 800; font-size: 13px; cursor: pointer; }
.rpg-req-split { display: flex; gap: 20px; min-height: 480px; }
.rpg-req-list { width: 260px; background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 8px; overflow-y: auto; max-height: 480px; flex-shrink: 0; }
.rpg-req-item { padding: 12px 15px; border-bottom: 1px solid var(--border-color); cursor: pointer; transition: background 0.2s; }
.rpg-req-item:hover { background: rgba(255,255,255,0.02); }
.rpg-req-item.active { background: rgba(198,40,40,0.08); border-left: 3px solid var(--accent-indigo); }
.rpg-req-detail { flex: 1; display: flex; flex-direction: column; gap: 15px; background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 8px; padding: 20px; }
.rpg-card-preview-mini { width: 220px; background: var(--bg-card); border: 2px solid var(--border-color); border-radius: 8px; overflow: hidden; box-shadow: var(--shadow-card); font-size: 12px; flex-shrink: 0; }
.rpg-gestion-panel { background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 25px; box-sizing: border-box; max-width: 100%; overflow-x: hidden; }
.rpg-gestion-dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; margin-top: 15px; }
.rpg-gestion-card { background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 12px; padding: 24px; display: flex; flex-direction: column; gap: 15px; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; box-shadow: var(--shadow-card); text-decoration: none !important; }
.rpg-gestion-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(135deg, rgba(198,40,40,0.03), rgba(74,20,140,0.03)); opacity: 0; transition: opacity 0.3s; }
.rpg-gestion-card:hover { transform: translateY(-4px); border-color: var(--accent-indigo); box-shadow: 0 8px 25px rgba(198,40,40,0.12); }
.rpg-gestion-card:hover::before { opacity: 1; }
.rpg-gestion-card-icon { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; color: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: transform 0.3s; }
.rpg-gestion-card:hover .rpg-gestion-card-icon { transform: scale(1.1); }
.rpg-gestion-card-icon--attr { background: linear-gradient(135deg, var(--accent-indigo), var(--accent-blue)); }
.rpg-gestion-card-icon--deck { background: linear-gradient(135deg, var(--accent-purple), var(--accent-pink)); }
.rpg-gestion-card-icon--catalog { background: linear-gradient(135deg, var(--accent-blue), var(--accent-teal)); }
.rpg-gestion-card-icon--requests { background: linear-gradient(135deg, var(--accent-rose), var(--accent-orange)); }
.rpg-gestion-card-icon--red { background: linear-gradient(135deg, var(--accent-rose), #dc2626); }
.rpg-gestion-card-icon--purple { background: linear-gradient(135deg, var(--accent-purple), var(--accent-indigo)); }
.rpg-gestion-card-body { display: flex; flex-direction: column; gap: 6px; }
.rpg-gestion-card-body h3 { margin: 0; font-size: 15px; font-weight: 800; color: var(--text-primary); font-family: var(--font-heading); letter-spacing: 0.5px; }
.rpg-gestion-card-body p { margin: 0; font-size: 12px; color: var(--text-muted); line-height: 1.5; }
.rpg-gestion-card-footer { margin-top: auto; display: flex; justify-content: space-between; align-items: center; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; border-top: 1px solid var(--border-color); padding-top: 12px; }
.rpg-gestion-card-tag { color: var(--accent-indigo); }
.rpg-gestion-card-badge { background: var(--accent-rose); color: #fff; padding: 2px 8px; border-radius: 20px; font-size: 9px; font-weight: 800; }
.rpg-gestion-chevron { color: var(--text-muted); }
.rpg-back-btn { background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color); color: var(--text-secondary); padding: 8px 16px; border-radius: 8px; font-family: var(--font-heading); font-weight: 800; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; margin-bottom: 20px; }
.rpg-back-btn:hover { background: rgba(198, 40, 40, 0.06); border-color: var(--accent-indigo); color: var(--text-primary); }
.rpg-back-btn--flat { margin-bottom: 0; }
.gestion-subtab-content { display: none; }
#gestion_dashboard { display: block; }
.rpg-is-hidden { display: none !important; }
.rpg-pp-display--wrap { flex-wrap: wrap; gap: 16px; }
.rpg-pp-col { flex: 1; min-width: 200px; }
.rpg-pp-col--wide { flex: 1; min-width: 220px; }
.rpg-pp-stats-row { display: flex; flex-wrap: wrap; gap: 20px; align-items: center; }
.rpg-pp-val--sm { font-size: 18px; }
.rpg-pp-desc { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
.rpg-pp-desc--spaced { font-size: 12px; color: var(--text-muted); margin-top: 4px; line-height: 1.5; }
.rpg-warning-text { color: #f59e0b; font-weight: 700; }
.rpg-muted-soft { opacity: 0.85; }
.rpg-level-pending-box { margin-top: 12px; }
.rpg-level-pending-msg { font-size: 12px; color: var(--accent-amber, #f59e0b); font-weight: 700; }
.rpg-level-cooldown { font-size: 11px; color: var(--text-muted); margin-top: 6px; }
.rpg-attr-claim-btn { margin-top: 8px; }
.rpg-locked-panel { padding: 40px; text-align: center; color: var(--text-muted); background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 8px; }
.rpg-locked-icon { font-size: 28px; color: var(--accent-amber); margin-bottom: 12px; display: block; }
.rpg-form-panel { max-width: 650px; margin: 0 auto; background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 12px; padding: 30px; display: flex; flex-direction: column; gap: 20px; box-shadow: var(--shadow-card); }
.rpg-form-panel--wide { max-width: none; width: 100%; margin: 0; }
.rpg-form-heading { margin: 0; font-size: 16px; color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 12px; display: flex; align-items: center; gap: 10px; font-family: var(--font-heading); font-weight: 800; }
.rpg-form-heading-icon--purple { color: var(--accent-purple); font-size: 18px; }
.rpg-form-heading-icon--indigo { color: var(--accent-indigo); font-size: 18px; }
.rpg-form-label { font-size: 11px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; margin-bottom: 6px; display: block; }
.rpg-form-input { width: 100%; box-sizing: border-box; padding: 10px; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); }
.rpg-form-input--resize { resize: vertical; }
.rpg-form-input--spaced { margin-bottom: 8px; }
.rpg-form-stack { display: flex; flex-direction: column; gap: 20px; }
.rpg-form-stack--wide { display: flex; flex-direction: column; gap: 20px; width: 100%; }
.rpg-form-help { font-size: 12px; color: var(--text-muted); margin: 0; line-height: 1.6; }
.rpg-form-section-spaced { margin-top: 15px; }
.rpg-form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.rpg-form-row-flex { display: flex; gap: 12px; }
.rpg-form-group--flex1 { flex: 1; }
.rpg-form-group--span2 { grid-column: span 2; }
.rpg-gestion-deck-modes { display: flex; gap: 10px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 5px; }
.rpg-req-fields { display: none; flex-direction: column; gap: 12px; margin-top: 5px; }
.rpg-req-fields.is-visible { display: flex; }
.rpg-npc-actions { display: flex; flex-direction: column; gap: 8px; }
.rpg-btn-add-dashed { width: 100%; margin-top: 8px; background: var(--bg-surface); border: 1px dashed var(--border-color); border-radius: 6px; color: var(--text-secondary); padding: 8px; cursor: pointer; font-weight: 700; }
.req-npc-action-row { display: flex; gap: 8px; align-items: center; margin-bottom: 4px; }
.rpg-req-submit-row { margin-top: 15px; }
.rpg-req-submit-row .rpg-action-btn,
.rpg-req-submit-row .rpg-system-tab-btn { width: 100%; justify-content: center; margin-top: 5px; }
.rpg-req-loading { padding: 20px; text-align: center; color: var(--text-muted); }
.rpg-req-detail-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-muted); text-align: center; }
.rpg-req-detail-empty i { font-size: 40px; color: var(--text-muted); opacity: 0.3; margin-bottom: 15px; }
.pj-network-wrap { position: relative; }
.pj-view-toggles {
    position: absolute;
    top: 15px;
    right: 15px;
    z-index: 10;
    display: flex;
    gap: 15px;
}
.pj-view-toggle {
    background: none;
    border: none;
    color: var(--text-primary);
    font-size: 22px;
    cursor: pointer;
    opacity: 0.4;
    transition: opacity 0.2s;
}
.pj-view-toggle.is-active { opacity: 1; }
.pj-network-container {
    width: 100%;
    height: 500px;
    background: radial-gradient(circle, var(--bg-surface) 0%, var(--bg-main) 100%);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    overflow: hidden;
    position: relative;
}
.pj-view-list {
    display: none;
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    padding-top: 40px;
}
.pj-view-list.is-visible { display: block; }
.pj-view-graph.is-hidden { display: none; }
.pj-scroll-box--network { height: 460px; border: none; background: transparent; }
.pj-relation-card-link { text-decoration: none; color: inherit; }
.pj-relation-npc-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #f59e0b;
    color: #000;
    font-size: 9px;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.5);
    z-index: 2;
}
.pj-relation-desc { font-size: 11px; color: var(--text-muted); margin-top: 8px; line-height: 1.4; }
.pj-relation-tag { background: color-mix(in srgb, var(--tag-color, var(--accent-primary)) 13%, transparent); }

/* Modales ficha (_modals.php) */
.pj-modal-row { display: flex; gap: 8px; }
.pj-modal-input-flex { flex: 1; }
.pj-modal-row .rpg-system-tab-btn { flex-shrink: 0; }
.pj-form-hint { font-size: 11px; color: var(--text-muted); margin-top: 4px; }
.pj-detect-box { background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 8px; padding: 15px; margin-bottom: 16px; }
.pj-detect-header { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.pj-detect-icon-ok { color: #10b981; }
.pj-detect-title { font-size: 13px; font-weight: 700; color: var(--text-primary); }
.pj-detect-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 12px; }
.pj-detect-label { color: var(--text-muted); }
.pj-detect-val { color: var(--text-primary); font-weight: 600; }
.pj-detect-val--bold { font-weight: 700; }
.pj-label-inline { display: flex; align-items: center; gap: 8px; cursor: pointer; }
.pj-label-inline--bold { display: flex; align-items: center; gap: 8px; font-weight: 700; cursor: pointer; }
.pj-label-hint { color: var(--text-muted); font-weight: 400; text-transform: none; }
.pj-pj-results { margin-top: 6px; display: flex; flex-wrap: wrap; gap: 6px; }
.pj-tag { border-color: var(--tag-color); color: var(--tag-color); }
.pj-modal-divider { border: 0; border-top: 1px solid var(--border-color); margin: 20px 0; }
.pj-conn-panel { padding: 15px; background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 15px; }
.pj-conn-help { font-size: 12px; color: var(--text-secondary); margin-top: 0; }
.pj-color-swatches { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 8px; }
.pj-color-swatch { width: 28px; height: 28px; border-radius: 50%; background: var(--swatch-color); cursor: pointer; border: 2px solid transparent; transition: transform 0.15s; }
.pj-modal-footer-right { text-align: right; margin-top: 20px; }
.pj-modal-footer-right--lg { text-align: right; margin-top: 30px; }
.pj-modal-footer-right .rpg-system-tab-btn { margin-right: 10px; }
.pj-modal--md { width: 520px; max-width: 95vw; }
.pj-modal--lg { width: 540px; max-width: 95vw; padding: 25px; }
.pj-modal--sm { width: 500px; }
.pj-modal-toolbar { margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
.pj-modal-toolbar-text { font-size: 12px; color: var(--text-muted); }
.pj-modal-toolbar .rpg-system-tab-btn { padding: 6px 12px; font-size: 12px; }
.pj-edit-list--modal { height: 320px; overflow-y: auto; }
.pj-modal-footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 20px; }
.pj-modal-tabs { display: flex; border-bottom: 1px solid var(--border-color); margin-bottom: 20px; gap: 5px; justify-content: center; }
.pj-modal-title--spaced { margin-bottom: 15px; }
.pj-tab-content.is-hidden { display: none; }
.pj-scroll-box--grp { height: 180px; padding: 10px; background: var(--bg-main); border: 1px solid var(--border-color); margin-bottom: 0; }

/* Tab Linaje — perks y slots */
.pj-linaje-perk-card { position: relative; }
.pj-linaje-perk-cost {
    position: absolute; top: 12px; right: 80px;
    font-family: var(--font-heading); font-size: 10px; font-weight: 800;
    background: rgba(198, 40, 40, 0.1); color: var(--accent-indigo);
    padding: 2px 6px; border-radius: 4px;
}
.pj-linaje-perk-icon { background: var(--icon-bg, rgba(198,40,40,0.1)); border: 2px solid rgba(198,40,40,0.3); }
.pj-linaje-perk-icon i { color: var(--icon-color, #C62828); }
.pj-linaje-perk-badge { background: color-mix(in srgb, var(--badge-color, #C62828) 13%, transparent); color: var(--badge-color, #C62828); }
.linaje-slots-bar {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 10px; padding: 14px 20px; margin-bottom: 20px;
    background: linear-gradient(135deg, rgba(198,40,40,0.05), rgba(74,20,140,0.03));
    border-radius: var(--radius-lg); border: 1px solid rgba(198,40,40,0.2);
}
.linaje-slots-group { display: flex; align-items: center; gap: 12px; }
.linaje-slots-label { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); }
.linaje-slots-label i { color: var(--accent-indigo); }
.linaje-slots-dots { display: flex; gap: 6px; }
.linaje-slot-dot {
    width: 12px; height: 12px; border-radius: 50%;
    border: 2px solid var(--border-color); background: var(--bg-main);
}
.linaje-slot-dot.filled { background: var(--accent-indigo); box-shadow: 0 0 8px rgba(198,40,40,0.5); }
.linaje-slots-count { font-family: var(--font-heading); font-weight: 900; font-size: 22px; color: var(--accent-purple); }
#linajeSobranteBonus { font-size: 12px; font-weight: 700; color: #10b981; text-transform: uppercase; letter-spacing: 0.5px; }
.pj-linaje-section-title { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
.pj-linaje-section-title--green { color: #10b981; }
.pj-linaje-section-title--indigo { color: var(--accent-indigo); margin-top: 20px; }
.pj-linaje-section-title--purple { color: var(--accent-purple); margin-top: 20px; }
.pj-linaje-empty { padding: 30px; text-align: center; background: var(--bg-surface); border-radius: var(--radius-md); border: 1px dashed var(--border-color); }
.pj-linaje-empty i { font-size: 40px; opacity: 0.5; margin-bottom: 15px; display: block; }
.pj-linaje-empty i.pj-linaje-empty__icon--indigo { color: var(--accent-indigo); }
.pj-linaje-empty i.pj-linaje-empty__icon--purple { color: var(--accent-purple); }
.pj-linaje-empty h4 { color: var(--text-primary); margin-bottom: 5px; }
.pj-linaje-empty p { color: var(--text-muted); font-size: 13px; margin: 0; }
.pj-linaje-legacy-notice {
    padding: 12px 16px; background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.3);
    border-radius: var(--radius-md); margin-bottom: 20px; display: flex; align-items: center; gap: 12px;
}
.pj-linaje-legacy-notice i { color: #f59e0b; font-size: 18px; }
.pj-linaje-legacy-notice__title { font-weight: 800; font-size: 12px; color: #f59e0b; text-transform: uppercase; letter-spacing: 0.5px; }
.pj-linaje-legacy-notice__text { font-size: 12px; color: var(--text-muted); }
</style>
