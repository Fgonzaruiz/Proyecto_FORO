<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

$uid = (int)($mybb->user['uid'] ?? 0);
if (!$uid) {
    header('Location: ../../member.php?action=login');
    exit;
}

$prefix = TABLE_PREFIX;
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;

if ($active_pj_id > 0) {
    $pj_q = $db->query("SELECT staff_level, name FROM {$prefix}game_personajes WHERE id = {$active_pj_id} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    $staff_level = $pj ? (int)$pj['staff_level'] : 0;
    $pj_name = $pj ? $pj['name'] : '';
}

if ($staff_level < 3) {
    header('Location: ../index.php');
    exit;
}

$b_url = $mybb->settings['bburl'];

ob_start();
?>
<div class="rpg-staff-zone">
    <div class="rpg-staff-header" style="background: linear-gradient(135deg, var(--accent-amber), var(--accent-rose));">
        <div class="rpg-staff-header-content">
            <a href="zona_staff.php" style="color: #fff; text-decoration: none; font-size: 0.9em; opacity: 0.8;"><i class="fas fa-arrow-left"></i> Volver a Zona Staff</a>
            <h1 style="margin-top: 10px;"><i class="fas fa-layer-group"></i> Gestión de Cartas</h1>
            <p>Sistema de creación, edición y asignación de cartas.</p>
        </div>
    </div>

    <div class="rpg-staff-grid" style="grid-template-columns: 1fr;">
        <div class="rpg-staff-section">
            <div style="display: flex; gap: 20px; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
                <button class="rpg-tab-btn active" data-target="tab-catalog" style="background: transparent; border: none; color: var(--text-primary); font-family: var(--font-heading); font-size: 16px; cursor: pointer; border-bottom: 2px solid var(--accent-indigo);">Catálogo</button>
                <button class="rpg-tab-btn" data-target="tab-editor" style="background: transparent; border: none; color: var(--text-muted); font-family: var(--font-heading); font-size: 16px; cursor: pointer;">Editor de Cartas</button>
                <button class="rpg-tab-btn" data-target="tab-assign" style="background: transparent; border: none; color: var(--text-muted); font-family: var(--font-heading); font-size: 16px; cursor: pointer;">Asignación</button>
            </div>

            <!-- TAB: CATÁLOGO -->
            <div id="tab-catalog" class="rpg-tab-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3><i class="fas fa-list"></i> Catálogo de Cartas</h3>
                    <button id="btn-new-card" class="rpg-action-btn rpg-btn-primary" style="padding: 8px 16px; font-size: 14px;"><i class="fas fa-plus"></i> Nueva Carta</button>
                </div>
                <div id="catalog-list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                    <div style="text-align: center; padding: 40px; color: var(--text-muted); grid-column: 1 / -1;">Cargando catálogo...</div>
                </div>
            </div>

            <!-- TAB: EDITOR -->
            <div id="tab-editor" class="rpg-tab-content" style="display: none;">
                <h3 id="editor-title"><i class="fas fa-edit"></i> Crear Nueva Carta</h3>
                <form id="card-editor-form" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <input type="hidden" id="card_id" value="">
                    
                    <div class="rpg-form-group">
                        <label class="rpg-form-label">Nombre</label>
                        <input type="text" id="c_name" class="textbox" required style="width: 100%;">
                    </div>
                    
                    <div class="rpg-form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <label class="rpg-form-label">Tipo</label>
                            <select id="c_type" class="textbox" style="width: 100%;">
                                <option value="tecnica">Técnica</option>
                                <option value="equipo">Equipo</option>
                                <option value="akuma_no_mi">Akuma no Mi</option>
                                <option value="haki">Haki</option>
                                <option value="npc_menor">NPC Menor</option>
                            </select>
                        </div>
                        <div>
                            <label class="rpg-form-label">Rango</label>
                            <select id="c_rank" class="textbox" style="width: 100%;">
                                <option value="C">C (Común)</option>
                                <option value="B">B (Poco común)</option>
                                <option value="A">A (Raro)</option>
                                <option value="S">S (Épico)</option>
                                <option value="SS">SS (Legendario)</option>
                            </select>
                        </div>
                    </div>

                    <div class="rpg-form-group">
                        <label class="rpg-form-label">Activación</label>
                        <select id="c_activation" class="textbox" style="width: 100%;">
                            <option value="activa">Activa</option>
                            <option value="pasiva">Pasiva</option>
                            <option value="reactiva">Reactiva</option>
                        </select>
                    </div>

                    <div class="rpg-form-group">
                        <label class="rpg-form-label">Tags (separados por coma)</label>
                        <input type="text" id="c_tags" class="textbox" placeholder="OFENSIVA, FUEGO, AREA" style="width: 100%;">
                    </div>

                    <div class="rpg-form-group" style="grid-column: 1 / -1;">
                        <label class="rpg-form-label">Descripción</label>
                        <textarea id="c_desc" class="textbox" rows="3" style="width: 100%;"></textarea>
                    </div>

                    <div class="rpg-form-group" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; grid-column: 1 / -1;">
                        <div>
                            <label class="rpg-form-label">Coste PE</label>
                            <input type="text" id="c_cost" class="textbox" placeholder="3 PE" style="width: 100%;">
                        </div>
                        <div>
                            <label class="rpg-form-label">Ejecución</label>
                            <input type="text" id="c_stat" class="textbox" placeholder="AGI" style="width: 100%;">
                        </div>
                        <div>
                            <label class="rpg-form-label">Dados</label>
                            <input type="text" id="c_dice" class="textbox" placeholder="2d6+FUE" style="width: 100%;">
                        </div>
                    </div>

                    <div class="rpg-form-group" style="grid-column: 1 / -1;">
                        <label class="rpg-form-label" style="border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">Efectos por Rango</label>
                        <div style="display: grid; grid-template-columns: auto 1fr; gap: 10px; align-items: center; margin-top: 10px;">
                            <strong>C:</strong> <input type="text" id="eff_c" class="textbox" style="width: 100%;" placeholder="Efecto a nivel C...">
                            <strong>B:</strong> <input type="text" id="eff_b" class="textbox" style="width: 100%;" placeholder="Efecto a nivel B...">
                            <strong>A:</strong> <input type="text" id="eff_a" class="textbox" style="width: 100%;" placeholder="Efecto a nivel A...">
                            <strong>S:</strong> <input type="text" id="eff_s" class="textbox" style="width: 100%;" placeholder="Efecto a nivel S...">
                            <strong>SS:</strong> <input type="text" id="eff_ss" class="textbox" style="width: 100%;" placeholder="Efecto a nivel SS...">
                        </div>
                    </div>

                    <div class="rpg-form-group" style="grid-column: 1 / -1;">
                        <label class="rpg-form-label" style="border-bottom: 1px solid var(--border-color); padding-bottom: 5px;">Requisitos para Alcanzar Rango</label>
                        <div style="display: grid; grid-template-columns: auto 1fr; gap: 10px; align-items: center; margin-top: 10px;">
                            <strong>B:</strong> <input type="text" id="upg_b" class="textbox" style="width: 100%;" placeholder="Ej: Entrenar 1 mes...">
                            <strong>A:</strong> <input type="text" id="upg_a" class="textbox" style="width: 100%;" placeholder="Ej: Completar saga de trama...">
                            <strong>S:</strong> <input type="text" id="upg_s" class="textbox" style="width: 100%;" placeholder="Ej: Vencer a un enemigo poderoso...">
                            <strong>SS:</strong> <input type="text" id="upg_ss" class="textbox" style="width: 100%;" placeholder="Ej: Despertar...">
                        </div>
                    </div>

                    <div class="rpg-form-group">
                        <label class="rpg-form-label">Notas (Opcional)</label>
                        <textarea id="c_notes" class="textbox" rows="2" style="width: 100%;"></textarea>
                    </div>

                    <div class="rpg-form-group">
                        <label class="rpg-form-label">URL Imagen (Opcional)</label>
                        <input type="text" id="c_image" class="textbox" placeholder="https://..." style="width: 100%;">
                    </div>

                    <div style="grid-column: 1 / -1; display: flex; gap: 10px; justify-content: flex-end; margin-top: 10px;">
                        <button type="button" id="btn-cancel-edit" class="rpg-action-btn rpg-btn-secondary">Cancelar</button>
                        <button type="submit" class="rpg-action-btn rpg-btn-primary">Guardar Carta</button>
                    </div>
                </form>
            </div>

            <!-- TAB: ASIGNACIÓN -->
            <div id="tab-assign" class="rpg-tab-content" style="display: none;">
                <h3><i class="fas fa-hand-holding-magic"></i> Asignar Carta a Personaje</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start;">
                    
                    <div style="background: var(--bg-card); padding: 20px; border-radius: var(--radius-lg); border: 1px solid var(--border-color);">
                        <div class="rpg-form-group">
                            <label class="rpg-form-label">Personaje (ID)</label>
                            <input type="number" id="assign_char_id" class="textbox" style="width: 100%;">
                        </div>
                        <div class="rpg-form-group">
                            <label class="rpg-form-label">Carta</label>
                            <select id="assign_card_id" class="textbox" style="width: 100%;">
                                <option value="">Cargando cartas...</option>
                            </select>
                        </div>
                        <div class="rpg-form-group">
                            <label class="rpg-form-label">Rango a Asignar</label>
                            <select id="assign_rank" class="textbox" style="width: 100%;">
                                <option value="C">C</option>
                                <option value="B">B</option>
                                <option value="A">A</option>
                                <option value="S">S</option>
                                <option value="SS">SS</option>
                            </select>
                        </div>
                        <button id="btn-assign" class="rpg-action-btn rpg-btn-primary" style="width: 100%;">Asignar Carta</button>
                    </div>

                    <div style="background: var(--bg-card); padding: 20px; border-radius: var(--radius-lg); border: 1px solid var(--border-color);">
                        <h4 style="margin-top:0;">Deck del Personaje</h4>
                        <div class="rpg-form-group">
                            <input type="number" id="view_deck_char_id" class="textbox" placeholder="ID del Personaje" style="width: calc(100% - 90px); display: inline-block;">
                            <button id="btn-view-deck" class="rpg-action-btn rpg-btn-secondary" style="padding: 8px 12px;">Ver</button>
                        </div>
                        <ul id="deck-list" style="list-style: none; padding: 0; margin: 0; max-height: 300px; overflow-y: auto;">
                            <!-- Lista de cartas asignadas -->
                        </ul>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Tab switching
    const tabs = document.querySelectorAll('.rpg-tab-btn');
    const contents = document.querySelectorAll('.rpg-tab-content');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => { t.classList.remove('active'); t.style.borderBottom = 'none'; t.style.color = 'var(--text-muted)'; });
            contents.forEach(c => c.style.display = 'none');
            tab.classList.add('active');
            tab.style.borderBottom = '2px solid var(--accent-indigo)';
            tab.style.color = 'var(--text-primary)';
            document.getElementById(tab.dataset.target).style.display = 'block';
        });
    });

    let allCards = [];

    // Load Catalog
    function loadCatalog() {
        fetch('ajax/cards_list.php')
            .then(r => r.json())
            .then(d => {
                if(d.ok) {
                    allCards = d.data;
                    renderCatalog(d.data);
                    populateCardSelect(d.data);
                }
            });
    }

    function renderCatalog(cards) {
        const list = document.getElementById('catalog-list');
        list.innerHTML = '';
        if(cards.length === 0) {
            list.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: var(--text-muted);">No hay cartas creadas.</div>';
            return;
        }
        
        cards.forEach(c => {
            const el = document.createElement('div');
            el.style = 'background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 15px; display: flex; flex-direction: column; gap: 10px;';
            el.innerHTML = `
                <div style="display:flex; justify-content: space-between; align-items:flex-start;">
                    <strong style="color: var(--accent-indigo); font-size: 1.1em;">${c.name}</strong>
                    <span style="background: var(--bg-main); padding: 2px 6px; border-radius: 4px; font-size: 0.8em; font-weight: bold;">Rango ${c.rank}</span>
                </div>
                <div style="font-size: 0.85em; color: var(--text-secondary);">${c.card_type.toUpperCase()}</div>
                <div style="font-size: 0.9em; color: var(--text-primary); flex: 1;">${c.description}</div>
                <div style="display: flex; gap: 5px; margin-top: auto; border-top: 1px solid var(--border-color); padding-top: 10px;">
                    <button class="rpg-action-btn rpg-btn-secondary edit-card" data-id="${c.id}" style="padding: 5px 10px; font-size: 12px; flex:1;">Editar</button>
                    <button class="rpg-action-btn rpg-btn-secondary del-card" data-id="${c.id}" style="padding: 5px 10px; font-size: 12px; flex:1; background: rgba(239,68,68,0.1); color: var(--accent-rose); border-color: transparent;">Eliminar</button>
                </div>
            `;
            list.appendChild(el);
        });

        document.querySelectorAll('.edit-card').forEach(btn => btn.addEventListener('click', e => editCard(e.target.dataset.id)));
        document.querySelectorAll('.del-card').forEach(btn => btn.addEventListener('click', e => deleteCard(e.target.dataset.id)));
    }

    function populateCardSelect(cards) {
        const sel = document.getElementById('assign_card_id');
        sel.innerHTML = '<option value="">Selecciona una carta...</option>';
        cards.forEach(c => {
            sel.innerHTML += `<option value="${c.id}">${c.name} (${c.card_type})</option>`;
        });
    }

    document.getElementById('btn-new-card').addEventListener('click', () => {
        document.getElementById('card-editor-form').reset();
        document.getElementById('card_id').value = '';
        document.getElementById('editor-title').innerHTML = '<i class="fas fa-plus"></i> Crear Nueva Carta';
        tabs[1].click();
    });

    document.getElementById('btn-cancel-edit').addEventListener('click', () => {
        tabs[0].click();
    });

    function editCard(id) {
        const card = allCards.find(c => c.id == id);
        if(!card) return;
        
        document.getElementById('card_id').value = card.id;
        document.getElementById('c_name').value = card.name;
        document.getElementById('c_type').value = card.card_type;
        document.getElementById('c_rank').value = card.rank;
        document.getElementById('c_activation').value = card.activation;
        document.getElementById('c_tags').value = (card.tags || []).join(', ');
        document.getElementById('c_desc').value = card.description;
        document.getElementById('c_cost').value = card.cost_pe;
        document.getElementById('c_stat').value = card.execution_stat;
        document.getElementById('c_dice').value = card.dice;
        document.getElementById('eff_c').value = (card.effects && card.effects.C) ? card.effects.C : '';
        document.getElementById('eff_b').value = (card.effects && card.effects.B) ? card.effects.B : '';
        document.getElementById('eff_a').value = (card.effects && card.effects.A) ? card.effects.A : '';
        document.getElementById('eff_s').value = (card.effects && card.effects.S) ? card.effects.S : '';
        document.getElementById('eff_ss').value = (card.effects && card.effects.SS) ? card.effects.SS : '';
        
        document.getElementById('upg_b').value = (card.upgrade && card.upgrade.B) ? card.upgrade.B : '';
        document.getElementById('upg_a').value = (card.upgrade && card.upgrade.A) ? card.upgrade.A : '';
        document.getElementById('upg_s').value = (card.upgrade && card.upgrade.S) ? card.upgrade.S : '';
        document.getElementById('upg_ss').value = (card.upgrade && card.upgrade.SS) ? card.upgrade.SS : '';
        document.getElementById('c_notes').value = card.notes;
        document.getElementById('c_image').value = card.image_url;
        
        document.getElementById('editor-title').innerHTML = '<i class="fas fa-edit"></i> Editar Carta';
        tabs[1].click();
    }

    function deleteCard(id) {
        if(!confirm('¿Seguro que quieres eliminar esta carta? Se quitará de todos los personajes.')) return;
        fetch('ajax/cards_delete.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({card_id: parseInt(id)})
        }).then(r=>r.json()).then(d=>{
            if(d.ok) loadCatalog();
            else alert('Error: ' + (d.error?.message || 'Desconocido'));
        });
    }

    document.getElementById('card-editor-form').addEventListener('submit', (e) => {
        e.preventDefault();
        const id = document.getElementById('card_id').value;
        const payload = {
            name: document.getElementById('c_name').value,
            card_type: document.getElementById('c_type').value,
            rank: document.getElementById('c_rank').value,
            activation: document.getElementById('c_activation').value,
            tags: document.getElementById('c_tags').value.split(',').map(t=>t.trim()).filter(t=>t),
            description: document.getElementById('c_desc').value,
            cost_pe: document.getElementById('c_cost').value,
            execution_stat: document.getElementById('c_stat').value,
            dice: document.getElementById('c_dice').value,
            notes: document.getElementById('c_notes').value,
            image_url: document.getElementById('c_image').value,
        };
        const effects = {};
        if (document.getElementById('eff_c').value.trim() !== '') effects.C = document.getElementById('eff_c').value.trim();
        if (document.getElementById('eff_b').value.trim() !== '') effects.B = document.getElementById('eff_b').value.trim();
        if (document.getElementById('eff_a').value.trim() !== '') effects.A = document.getElementById('eff_a').value.trim();
        if (document.getElementById('eff_s').value.trim() !== '') effects.S = document.getElementById('eff_s').value.trim();
        if (document.getElementById('eff_ss').value.trim() !== '') effects.SS = document.getElementById('eff_ss').value.trim();
        payload.effects = effects;

        const upgrade = {};
        if (document.getElementById('upg_b').value.trim() !== '') upgrade.B = document.getElementById('upg_b').value.trim();
        if (document.getElementById('upg_a').value.trim() !== '') upgrade.A = document.getElementById('upg_a').value.trim();
        if (document.getElementById('upg_s').value.trim() !== '') upgrade.S = document.getElementById('upg_s').value.trim();
        if (document.getElementById('upg_ss').value.trim() !== '') upgrade.SS = document.getElementById('upg_ss').value.trim();
        payload.upgrade = upgrade;

        if (id) {
            payload.card_id = parseInt(id);
            fetch('ajax/cards_update.php', {
                method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload)
            }).then(r=>r.json()).then(d=>{ if(d.ok){ loadCatalog(); tabs[0].click(); } });
        } else {
            fetch('ajax/cards_create.php', {
                method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload)
            }).then(r=>r.json()).then(d=>{ if(d.ok){ loadCatalog(); tabs[0].click(); } });
        }
    });

    document.getElementById('btn-assign').addEventListener('click', () => {
        const charId = document.getElementById('assign_char_id').value;
        const cardId = document.getElementById('assign_card_id').value;
        const rank = document.getElementById('assign_rank').value;
        
        if(!charId || !cardId) return alert('Faltan datos');
        
        fetch('ajax/cards_assign.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({character_id: parseInt(charId), card_id: parseInt(cardId), rank})
        }).then(r=>r.json()).then(d=>{
            if(d.ok) {
                alert('Carta asignada correctamente.');
                document.getElementById('view_deck_char_id').value = charId;
                loadDeck(charId);
            }
        });
    });

    document.getElementById('btn-view-deck').addEventListener('click', () => {
        const charId = document.getElementById('view_deck_char_id').value;
        if(charId) loadDeck(charId);
    });

    function loadDeck(charId) {
        fetch('ajax/cards_my_deck.php?character_id=' + charId)
            .then(r=>r.json()).then(d=>{
                const list = document.getElementById('deck-list');
                list.innerHTML = '';
                if(d.ok && d.data.length > 0) {
                    d.data.forEach(c => {
                        const li = document.createElement('li');
                        li.style = 'padding: 10px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;';
                        li.innerHTML = `
                            <div>
                                <strong>${c.name}</strong> <span style="font-size: 0.8em; color: var(--accent-indigo);">[${c.rank}]</span>
                            </div>
                            <button class="rpg-action-btn rpg-btn-secondary unassign-btn" data-cid="${c.id}" style="padding: 4px 8px; font-size: 11px;">Quitar</button>
                        `;
                        list.appendChild(li);
                    });
                    document.querySelectorAll('.unassign-btn').forEach(btn => {
                        btn.addEventListener('click', (e) => {
                            const cardId = e.target.dataset.cid;
                            fetch('ajax/cards_unassign.php', {
                                method: 'POST', headers: {'Content-Type':'application/json'},
                                body: JSON.stringify({character_id: parseInt(charId), card_id: parseInt(cardId)})
                            }).then(r=>r.json()).then(res=>{
                                if(res.ok) loadDeck(charId);
                            });
                        });
                    });
                } else {
                    list.innerHTML = '<li style="padding: 10px; color: var(--text-muted);">Sin cartas.</li>';
                }
            });
    }

    loadCatalog();
});
</script>
<?php
$content = ob_get_clean();
game_render_page("Gestión de Cartas", $content);
