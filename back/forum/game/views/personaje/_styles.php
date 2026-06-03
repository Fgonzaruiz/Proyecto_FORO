<style>
/* Pestañas para la ficha */
.pj-preview-tabs { display: flex; border-bottom: 2px solid var(--border-color); margin-bottom: 24px; }
.pj-preview-tab {
    padding: 10px 20px; font-family: var(--font-heading); font-weight: 700; font-size: 14px;
    color: var(--text-muted); cursor: pointer; border-bottom: 3px solid transparent;
    margin-bottom: -2px; transition: all 0.2s ease;
}
.pj-preview-tab:hover { color: var(--text-primary); }
.pj-preview-tab.active { color: var(--accent-indigo); border-bottom-color: var(--accent-indigo); }
.pj-preview-tab-content { display: none; }
.pj-preview-tab-content.active { display: block; }

/* Barras de stats (copiadas del creador) */
.rpg-preview-stat-bar { background: var(--bg-card); border-radius: 10px; height: 8px; width: 100%; overflow: hidden; margin-top: 4px; }
.rpg-preview-stat-fill { height: 100%; background: linear-gradient(90deg, var(--accent-indigo), var(--accent-purple)); border-radius: 10px; transition: width 0.5s ease; }
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
.gene-card.perk-racial { border-left: 3px solid var(--accent-indigo); }
.gene-card.perk-general { border-left: 3px solid var(--accent-purple); }

/* Custom Scrollbars for boxes */
.pj-scroll-box {
    background: var(--bg-surface); border: 1px solid var(--border-color);
    border-radius: var(--radius-md); padding: 20px; height: 280px;
    overflow-y: auto; margin-bottom: 30px; font-size: 14px; line-height: 1.7; color: var(--text-secondary);
}
.pj-scroll-box::-webkit-scrollbar { width: 6px; }
.pj-scroll-box::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); border-radius: 4px; }
.pj-scroll-box::-webkit-scrollbar-thumb { background: var(--accent-indigo); border-radius: 4px; }

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
.pj-relation-img { width: 75px; height: 75px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent-indigo); margin: 0 auto 12px auto; display: block; padding: 3px; background: rgba(255,255,255,0.05); }
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
    background: linear-gradient(90deg, var(--accent-indigo), var(--accent-purple));
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
    border-color: var(--accent-indigo) !important;
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
.pj-btn-add {
    background: var(--btn-primary-bg) !important;
    color: white !important;
    border: 1px solid rgba(184, 151, 66, 0.4) !important;
    padding: 10px 20px !important;
    border-radius: var(--radius-md) !important;
    font-size: 12px !important;
    font-weight: 800 !important;
    text-transform: uppercase !important;
    cursor: pointer !important;
    transition: transform 0.2s, box-shadow 0.2s !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 8px !important;
    box-shadow: var(--btn-primary-shadow) !important;
}
.pj-btn-add:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 20px rgba(198, 40, 40, 0.4) !important;
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
</style>
