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
                <form id="card-editor-form" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <input type="hidden" id="card_id" value="">

                    <!-- FILA 1: Nombre + Tipo -->
                    <div>
                        <label class="rpg-form-label">Nombre</label>
                        <input type="text" id="c_name" class="textbox" required style="width: 100%;">
                    </div>
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

                    <!-- FILA 2: Activación + Rango -->
                    <div>
                        <label class="rpg-form-label">Activación</label>
                        <select id="c_activation" class="textbox" style="width: 100%;">
                            <option value="activa">Activa</option>
                            <option value="pasiva">Pasiva</option>
                            <option value="reactiva">Reactiva</option>
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

                    <!-- FILA 3: Tags (ancho completo) -->
                    <div style="grid-column: 1 / -1;">
                        <label class="rpg-form-label">Tags</label>
                        <div id="tag-selector">
                            <div id="tag-selected" style="display: flex; flex-wrap: wrap; gap: 4px; min-height: 28px; padding: 4px 0;"></div>
                            <div id="tag-dropdown" style="display: none; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-card); max-height: 320px; overflow-y: auto; margin-top: 8px;"></div>
                            <button type="button" id="tag-toggle-btn" class="rpg-action-btn rpg-btn-secondary" style="margin-top: 6px; padding: 4px 12px; font-size: 13px;">Seleccionar Tags</button>
                            <input type="hidden" id="c_tags" value="">
                        </div>
                    </div>

                    <!-- FILA 4: Descripción (ancho completo) -->
                    <div style="grid-column: 1 / -1;">
                        <label class="rpg-form-label">Descripción</label>
                        <textarea id="c_desc" class="textbox" rows="3" style="width: 100%;"></textarea>
                    </div>

                    <!-- FILA 5: Coste PE + Ejecución -->
                    <div>
                        <label class="rpg-form-label">Coste PE</label>
                        <input type="text" id="c_cost" class="textbox" placeholder="3 PE" style="width: 100%;">
                    </div>
                    <div>
                        <label class="rpg-form-label">Ejecución</label>
                        <select id="c_stat" class="textbox" style="width: 100%;">
                            <option value="">—</option>
                            <option value="FUE">FUE (Fuerza)</option>
                            <option value="AGI">AGI (Agilidad)</option>
                            <option value="DES">DES (Destreza)</option>
                            <option value="INST">INST (Instinto)</option>
                            <option value="ESP">ESP (Espíritu)</option>
                            <option value="INT">INT (Inteligencia)</option>
                        </select>
                    </div>

                    <!-- FILA 6: Dados (ancho completo) -->
                    <div style="grid-column: 1 / -1; border-top: 1px solid var(--border-color); padding-top: 12px;">
                        <label class="rpg-form-label">Dados / Fórmula de daño</label>
                        <div id="dice-builder">
                            <div id="dice-groups"></div>
                            <button type="button" id="dice-add-group" class="rpg-action-btn rpg-btn-secondary" style="padding: 2px 10px; font-size: 12px; margin-top: 4px;">+ Añadir grupo</button>

                            <div style="display: flex; gap: 12px; margin-top: 8px; flex-wrap: wrap;">
                                <div>
                                    <label style="font-size: 0.8em; color: var(--text-secondary); display: block; margin-bottom: 2px;">Bonus fijo</label>
                                    <input type="number" id="dice-fixed" min="0" value="0" class="textbox" style="width: 65px;">
                                </div>
                                <div>
                                    <label style="font-size: 0.8em; color: var(--text-secondary); display: block; margin-bottom: 2px;">Stat</label>
                                    <select id="dice-stat" class="textbox" style="width: 75px;">
                                        <option value="">—</option>
                                        <option value="FUE">FUE</option>
                                        <option value="AGI">AGI</option>
                                        <option value="DES">DES</option>
                                        <option value="INST">INST</option>
                                        <option value="ESP">ESP</option>
                                        <option value="INT">INT</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="font-size: 0.8em; color: var(--text-secondary); display: block; margin-bottom: 2px;">Sufijo</label>
                                    <input type="text" id="dice-suffix" class="textbox" placeholder="[FUEGO]" style="width: 100px;">
                                </div>
                                <div style="display: flex; align-items: flex-end;">
                                    <div style="padding: 5px 10px; background: var(--bg-main); border-radius: var(--radius-md); font-family: monospace; font-size: 0.9em;">
                                        <span style="font-size: 0.8em; color: var(--text-secondary);">→</span>
                                        <span id="dice-preview" style="color: var(--accent-indigo); font-weight: bold; margin-left: 6px;">—</span>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" id="c_dice" value="">
                        </div>
                    </div>

                    <!-- FILA 7: Efectos por Rango (ancho completo) -->
                    <div style="grid-column: 1 / -1; border-top: 1px solid var(--border-color); padding-top: 12px;">
                        <label class="rpg-form-label" style="margin-bottom: 8px; display: block;">Efectos por Rango</label>
                        <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center;">
                            <strong style="color: var(--accent-amber); font-size: 0.9em;">C:</strong> <input type="text" id="eff_c" class="textbox" style="width: 100%;" placeholder="Efecto a nivel C...">
                            <strong style="color: var(--accent-green); font-size: 0.9em;">B:</strong> <input type="text" id="eff_b" class="textbox" style="width: 100%;" placeholder="Efecto a nivel B...">
                            <strong style="color: var(--accent-blue); font-size: 0.9em;">A:</strong> <input type="text" id="eff_a" class="textbox" style="width: 100%;" placeholder="Efecto a nivel A...">
                            <strong style="color: var(--accent-purple); font-size: 0.9em;">S:</strong> <input type="text" id="eff_s" class="textbox" style="width: 100%;" placeholder="Efecto a nivel S...">
                            <strong style="color: var(--accent-rose); font-size: 0.9em;">SS:</strong> <input type="text" id="eff_ss" class="textbox" style="width: 100%;" placeholder="Efecto a nivel SS...">
                        </div>
                    </div>

                    <!-- FILA 8: Requisitos de Rango (ancho completo) -->
                    <div style="grid-column: 1 / -1; border-top: 1px solid var(--border-color); padding-top: 12px;">
                        <label class="rpg-form-label" style="margin-bottom: 8px; display: block;">Requisitos para Subir de Rango</label>
                        <div style="display: grid; grid-template-columns: auto 1fr; gap: 8px; align-items: center;">
                            <strong style="color: var(--accent-green); font-size: 0.9em;">B:</strong> <input type="text" id="upg_b" class="textbox" style="width: 100%;" placeholder="Entrenar 1 mes...">
                            <strong style="color: var(--accent-blue); font-size: 0.9em;">A:</strong> <input type="text" id="upg_a" class="textbox" style="width: 100%;" placeholder="Completar saga de trama...">
                            <strong style="color: var(--accent-purple); font-size: 0.9em;">S:</strong> <input type="text" id="upg_s" class="textbox" style="width: 100%;" placeholder="Vencer a un enemigo poderoso...">
                            <strong style="color: var(--accent-rose); font-size: 0.9em;">SS:</strong> <input type="text" id="upg_ss" class="textbox" style="width: 100%;" placeholder="Despertar...">
                        </div>
                    </div>

                    <!-- FILA 9: Notas + URL Imagen -->
                    <div>
                        <label class="rpg-form-label">Notas</label>
                        <textarea id="c_notes" class="textbox" rows="2" style="width: 100%;"></textarea>
                    </div>
                    <div>
                        <label class="rpg-form-label">URL Imagen</label>
                        <input type="text" id="c_image" class="textbox" placeholder="https://..." style="width: 100%;">
                    </div>

                    <!-- FILA 10: Botones -->
                    <div style="grid-column: 1 / -1; display: flex; gap: 10px; justify-content: flex-end; margin-top: 4px;">
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
                        <div class="rpg-form-group" style="position: relative;">
                            <label class="rpg-form-label">Personaje</label>
                            <div class="character-search" data-target-id="assign_char_id">
                                <input type="text" class="char-search-input textbox" placeholder="Escribe el nombre del personaje..." style="width: 100%;" autocomplete="off">
                                <div class="char-search-results" style="display: none; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-card); max-height: 200px; overflow-y: auto; position: absolute; z-index: 100; width: 100%;"></div>
                                <input type="hidden" class="char-search-value" value="">
                            </div>
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
                        <div class="rpg-form-group" style="position: relative;">
                            <div class="character-search" data-target-id="view_deck_char_id">
                                <input type="text" class="char-search-input textbox" placeholder="Escribe el nombre del personaje..." style="width: 100%;" autocomplete="off">
                                <div class="char-search-results" style="display: none; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-card); max-height: 200px; overflow-y: auto; position: absolute; z-index: 100; width: 100%;"></div>
                                <input type="hidden" class="char-search-value" value="">
                            </div>
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

    // ======= LOAD CATALOG =======
    function loadCatalog() {
        fetch('../ajax/cards_list.php')
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

    // ======= TAG SELECTOR =======
    const TAG_CATEGORIES = [
        { name: 'Activación y temporalidad', tags: ['ACTIVA','PASIVA','REACTIVA','CONTINUA','INSTANTÁNEA','CARGA','CANAL','RETRASADA','ENCADENABLE','UNA VEZ','COOLDOWN X'] },
        { name: 'Alcance y geometría', tags: ['CONTACTO','CUERPO A CUERPO','DISTANCIA CORTA','DISTANCIA MEDIA','DISTANCIA LARGA','AUTOPERSONAL','ALIADOS','ÁREA PEQUEÑA','ÁREA MEDIA','ÁREA GRANDE','LÍNEA','CONO','ANILLO','TRAYECTORIA','TOQUE','GLOBAL'] },
        { name: 'Función de combate', tags: ['OFENSIVA','DEFENSIVA','CONTROL','SOPORTE','MOVILIDAD','CURACIÓN','UTILIDAD','INTERRUPCIÓN','PENETRACIÓN','DESVÍO','ABSORCIÓN','SEÑUELO','ESCUDO'] },
        { name: 'Ejecución', tags: ['EJECUCIÓN: FUE','EJECUCIÓN: AGI','EJECUCIÓN: DES','EJECUCIÓN: INST','EJECUCIÓN: ESP','EJECUCIÓN: INT'] },
        { name: 'Tipo de daño', tags: ['DAÑO FÍSICO','DAÑO CORTANTE','DAÑO CONTUNDENTE','DAÑO PERFORANTE','DAÑO ÍGNEO','DAÑO CRIOGÉNICO','DAÑO ELÉCTRICO','DAÑO TÓXICO','DAÑO EXPLOSIVO','DAÑO INTERNO','DAÑO ESPIRITUAL','DAÑO ESTRUCTURAL','DAÑO OSCURO'] },
        { name: 'Interacción especial', tags: ['ANTI-LOGIA','ANTI-HAKI','KAIROSEKI','IGNORA ARMADURA','DOBLE DAÑO EMPAPADO','VULNERABILIDAD AGUA','ESCALA CON DAÑO RECIBIDO','ESCALA CON PE RESTANTE','ESCALA CON ALIADOS','BONUS VS DERRIBADO','BONUS VS ESTADO','ENCADENADO CON','ROMPE CONCENTRACIÓN'] },
        { name: 'Elemento / naturaleza', tags: ['FUEGO','HIELO','RAYO','VENENO','OSCURIDAD','LUZ','VIENTO','TIERRA','AGUA','HUMO','ARENA','VIBRACIÓN','SONIDO','GRAVEDAD','VACÍO'] },
        { name: 'Akuma no Mi', tags: ['LOGIA','PARAMECIA-PRODUCTOR','PARAMECIA-TRANSFORMADOR','PARAMECIA-MANIPULADOR','ZOAN','ZOAN MÍTICO','ZOAN ANTIGUO','DESPERTAR'] },
        { name: 'Haki', tags: ['HAKI ARMAMENTO','HAKI OBSERVACIÓN','HAKI REY','FLUJO AVANZADO','VISIÓN DE FUTURO','EMISIÓN DE REY'] },
        { name: 'Equipo', tags: ['ARMA','ARMA SECUNDARIA','ARMA ARROJADIZA','ARMADURA','ARMADURA PARCIAL','ACCESORIO','CONSUMIBLE','NAVE','KAIROSEKI INTEGRADO','GRADO MEITO','MODIFICABLE'] },
        { name: 'NPC', tags: ['PIRATA','MARINO','REVOLUCIONARIO','CIVIL','AGENTE CIPHER POL','BOUNTY HUNTER','ALIADO TEMPORAL','OBSTÁCULO','JEFE DE ESCENA'] },
        { name: 'Condición y restricción', tags: ['REQUIERE ARMA','REQUIERE AKUMA NO MI','REQUIERE HAKI','REQUIERE ESTADO PROPIO','REQUIERE ESTADO OBJETIVO','SOLO EN AGUA','SOLO EN TIERRA','SOLO FORMA HÍBRIDA','SOLO FORMA BESTIAL','CONSUMO DOBLE EMPAPADO','AUTO-DAÑO'] }
    ];

    const selectedTags = new Set();
    const tagDropdown = document.getElementById('tag-dropdown');
    const tagSelected = document.getElementById('tag-selected');
    const tagToggleBtn = document.getElementById('tag-toggle-btn');
    const cTagsInput = document.getElementById('c_tags');

    TAG_CATEGORIES.forEach(cat => {
        const group = document.createElement('div');
        group.style = 'border-bottom: 1px solid var(--border-color);';
        const header = document.createElement('div');
        header.textContent = cat.name;
        header.style = 'padding: 8px 12px; font-weight: bold; font-size: 0.85em; background: var(--bg-main); cursor: pointer; user-select: none; display: flex; align-items: center; gap: 6px;';
        header.innerHTML = '<span style="font-size: 0.7em; opacity: 0.5;">▸</span> ' + cat.name;
        header.addEventListener('click', () => {
            const body = header.nextElementSibling;
            const arrow = header.querySelector('span');
            body.style.display = body.style.display === 'none' ? 'flex' : 'none';
            arrow.textContent = body.style.display === 'none' ? '▸' : '▾';
        });
        group.appendChild(header);
        const body = document.createElement('div');
        body.style = 'display: none; flex-wrap: wrap; gap: 3px; padding: 6px 12px 10px;';
        cat.tags.forEach(tag => {
            const label = document.createElement('label');
            label.style = 'display: flex; align-items: center; gap: 3px; padding: 2px 7px; font-size: 0.8em; cursor: pointer; border-radius: 4px; background: var(--bg-input); border: 1px solid var(--border-color);';
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.value = tag;
            cb.addEventListener('change', () => {
                if (cb.checked) selectedTags.add(tag);
                else selectedTags.delete(tag);
                updateTagDisplay();
            });
            label.appendChild(cb);
            label.appendChild(document.createTextNode(tag));
            body.appendChild(label);
        });
        group.appendChild(body);
        tagDropdown.appendChild(group);
    });

    tagToggleBtn.addEventListener('click', () => {
        tagDropdown.style.display = tagDropdown.style.display === 'none' ? 'block' : 'none';
    });

    function updateTagDisplay() {
        tagSelected.innerHTML = '';
        selectedTags.forEach(tag => {
            const pill = document.createElement('span');
            pill.style = 'display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; border-radius: 12px; font-size: 0.8em; background: var(--accent-indigo); color: #fff;';
            pill.textContent = tag;
            const remove = document.createElement('span');
            remove.textContent = '×';
            remove.style = 'cursor: pointer; margin-left: 2px; font-weight: bold; font-size: 1.1em;';
            remove.addEventListener('click', (e) => {
                e.stopPropagation();
                selectedTags.delete(tag);
                const cbs = tagDropdown.querySelectorAll('input[type="checkbox"]');
                cbs.forEach(cb => { if (cb.value === tag) cb.checked = false; });
                updateTagDisplay();
            });
            pill.appendChild(remove);
            tagSelected.appendChild(pill);
        });
        cTagsInput.value = Array.from(selectedTags).join(', ');
    }

    function setTags(tagsArray) {
        selectedTags.clear();
        const cbs = tagDropdown.querySelectorAll('input[type="checkbox"]');
        cbs.forEach(cb => { cb.checked = false; });
        (tagsArray || []).forEach(tag => {
            selectedTags.add(tag);
            cbs.forEach(cb => { if (cb.value === tag) cb.checked = true; });
        });
        updateTagDisplay();
    }

    function resetTags() {
        setTags([]);
    }

    // ======= DICE BUILDER =======
    function buildDiceFormula() {
        const groups = document.querySelectorAll('#dice-groups .dice-group');
        let parts = [];
        groups.forEach(g => {
            const qty = parseInt(g.querySelector('.dice-qty').value) || 1;
            const type = g.querySelector('.dice-type').value;
            if (qty > 0) parts.push(qty + type);
        });
        const fixed = parseInt(document.getElementById('dice-fixed').value) || 0;
        const stat = document.getElementById('dice-stat').value;
        const suffix = document.getElementById('dice-suffix').value.trim();

        let formula = parts.join('+');
        if (fixed > 0) formula += '+' + fixed;
        if (stat) formula += '+' + stat;
        if (suffix) formula += (formula ? ' ' : '') + suffix;

        document.getElementById('dice-preview').textContent = formula || '—';
        document.getElementById('c_dice').value = formula;
    }

    function addDiceGroup(qty, type) {
        const container = document.getElementById('dice-groups');
        const group = document.createElement('div');
        group.className = 'dice-group';
        group.style = 'display: inline-flex; align-items: center; gap: 6px; margin: 4px 8px 4px 0; padding: 6px 10px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md);';

        const qtyInput = document.createElement('input');
        qtyInput.type = 'number';
        qtyInput.className = 'dice-qty';
        qtyInput.min = 1;
        qtyInput.max = 100;
        qtyInput.value = qty || 2;
        qtyInput.style = 'width: 50px;';
        qtyInput.addEventListener('input', buildDiceFormula);

        const typeSelect = document.createElement('select');
        typeSelect.className = 'dice-type';
        typeSelect.style = 'width: 65px;';
        ['d4', 'd6', 'd8', 'd10', 'd12', 'd20', 'd100'].forEach(d => {
            const opt = document.createElement('option');
            opt.value = d;
            opt.textContent = d;
            if (d === (type || 'd20')) opt.selected = true;
            typeSelect.appendChild(opt);
        });
        typeSelect.addEventListener('change', buildDiceFormula);

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.textContent = '×';
        removeBtn.title = 'Quitar grupo';
        removeBtn.style = 'background: none; border: none; color: var(--accent-rose); cursor: pointer; font-size: 16px; padding: 0 2px; line-height: 1;';
        removeBtn.addEventListener('click', () => {
            container.removeChild(group);
            buildDiceFormula();
        });

        group.appendChild(qtyInput);
        group.appendChild(typeSelect);
        group.appendChild(removeBtn);
        container.appendChild(group);
        buildDiceFormula();
    }

    function parseDiceFormula(formula) {
        const container = document.getElementById('dice-groups');
        container.innerHTML = '';
        document.getElementById('dice-fixed').value = '0';
        document.getElementById('dice-stat').value = '';
        document.getElementById('dice-suffix').value = '';

        if (!formula || formula === '—' || !formula.trim()) {
            addDiceGroup(2, 'd20');
            return;
        }

        const parts = formula.split('+');
        let suffixParts = [];

        parts.forEach(part => {
            part = part.trim();
            const diceMatch = part.match(/^(\d+)(d\d+)$/i);
            if (diceMatch) {
                addDiceGroup(parseInt(diceMatch[1]), diceMatch[2]);
                return;
            }
            if (['FUE', 'AGI', 'DES', 'INST', 'ESP', 'INT'].includes(part)) {
                document.getElementById('dice-stat').value = part;
                return;
            }
            if (/^\d+$/.test(part)) {
                document.getElementById('dice-fixed').value = part;
                return;
            }
            suffixParts.push(part);
        });

        if (suffixParts.length > 0) {
            document.getElementById('dice-suffix').value = suffixParts.join(' ');
        }
        buildDiceFormula();
    }

    function resetDiceBuilder() {
        document.getElementById('dice-groups').innerHTML = '';
        addDiceGroup(2, 'd20');
        document.getElementById('dice-fixed').value = '0';
        document.getElementById('dice-stat').value = '';
        document.getElementById('dice-suffix').value = '';
        buildDiceFormula();
    }

    document.getElementById('dice-add-group').addEventListener('click', () => addDiceGroup(1, 'd6'));
    document.getElementById('dice-fixed').addEventListener('input', buildDiceFormula);
    document.getElementById('dice-stat').addEventListener('change', buildDiceFormula);
    document.getElementById('dice-suffix').addEventListener('input', buildDiceFormula);

    // Default dice group
    addDiceGroup(2, 'd20');

    // ======= NEW / RESET CARD =======
    document.getElementById('btn-new-card').addEventListener('click', () => {
        document.getElementById('card-editor-form').reset();
        document.getElementById('card_id').value = '';
        document.getElementById('editor-title').innerHTML = '<i class="fas fa-plus"></i> Crear Nueva Carta';
        resetTags();
        resetDiceBuilder();
        tabs[1].click();
    });

    document.getElementById('btn-cancel-edit').addEventListener('click', () => {
        tabs[0].click();
    });

    // ======= EDIT CARD =======
    function editCard(id) {
        const card = allCards.find(c => c.id == id);
        if(!card) return;

        document.getElementById('card_id').value = card.id;
        document.getElementById('c_name').value = card.name;
        document.getElementById('c_type').value = card.card_type;
        document.getElementById('c_rank').value = card.rank;
        document.getElementById('c_activation').value = card.activation;
        setTags(card.tags || []);
        document.getElementById('c_desc').value = card.description;
        document.getElementById('c_cost').value = card.cost_pe;
        document.getElementById('c_stat').value = card.execution_stat || '';
        parseDiceFormula(card.dice || '');
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

    // ======= DELETE CARD =======
    function deleteCard(id) {
        if(!confirm('¿Seguro que quieres eliminar esta carta? Se quitará de todos los personajes.')) return;
        fetch('../ajax/cards_delete.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({card_id: parseInt(id)})
        }).then(r=>r.json()).then(d=>{
            if(d.ok) loadCatalog();
            else alert('Error: ' + (d.error?.message || 'Desconocido'));
        });
    }

    // ======= SUBMIT =======
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
            fetch('../ajax/cards_update.php', {
                method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload)
            }).then(r=>r.json()).then(d=>{ if(d.ok){ loadCatalog(); tabs[0].click(); } });
        } else {
            fetch('../ajax/cards_create.php', {
                method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload)
            }).then(r=>r.json()).then(d=>{ if(d.ok){ loadCatalog(); tabs[0].click(); } });
        }
    });

    // ======= CHARACTER SEARCH AUTOCOMPLETE =======
    function initCharSearch(container) {
        const input = container.querySelector('.char-search-input');
        const results = container.querySelector('.char-search-results');
        const hidden = container.querySelector('.char-search-value');
        let fetchTimeout = null;

        input.addEventListener('input', () => {
            clearTimeout(fetchTimeout);
            const q = input.value.trim();
            if (q.length < 1) {
                results.style.display = 'none';
                hidden.value = '';
                return;
            }
            fetchTimeout = setTimeout(() => {
                fetch('../ajax/cards_search_characters.php?q=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(d => {
                        results.innerHTML = '';
                        if (d.ok && d.data.length > 0) {
                            results.style.display = 'block';
                            d.data.forEach(ch => {
                                const item = document.createElement('div');
                                item.textContent = ch.name;
                                item.dataset.id = ch.id;
                                item.style = 'padding: 8px 12px; cursor: pointer; font-size: 0.9em; border-bottom: 1px solid var(--border-color);';
                                item.addEventListener('mouseenter', () => { item.style.background = 'var(--bg-main)'; });
                                item.addEventListener('mouseleave', () => { item.style.background = ''; });
                                item.addEventListener('click', () => {
                                    input.value = ch.name;
                                    hidden.value = ch.id;
                                    results.style.display = 'none';
                                });
                                results.appendChild(item);
                            });
                        } else {
                            results.style.display = 'none';
                        }
                    });
            }, 250);
        });

        input.addEventListener('blur', () => {
            setTimeout(() => { results.style.display = 'none'; }, 200);
        });
        input.addEventListener('focus', () => {
            if (results.children.length > 0) results.style.display = 'block';
        });
    }

    document.querySelectorAll('.character-search').forEach(initCharSearch);

    // ======= ASSIGN =======
    document.getElementById('btn-assign').addEventListener('click', () => {
        const charId = document.querySelector('#tab-assign .character-search[data-target-id="assign_char_id"] .char-search-value').value;
        const cardId = document.getElementById('assign_card_id').value;
        const rank = document.getElementById('assign_rank').value;
        if(!charId || !cardId) return alert('Selecciona un personaje y una carta.');
        fetch('../ajax/cards_assign.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({character_id: parseInt(charId), card_id: parseInt(cardId), rank})
        }).then(r=>r.json()).then(d=>{
            if(d.ok) {
                alert('Carta asignada correctamente.');
                const viewInput = document.querySelector('#tab-assign .character-search[data-target-id="view_deck_char_id"] .char-search-input');
                const viewHidden = document.querySelector('#tab-assign .character-search[data-target-id="view_deck_char_id"] .char-search-value');
                viewInput.value = document.querySelector('#tab-assign .character-search[data-target-id="assign_char_id"] .char-search-input').value;
                viewHidden.value = charId;
                loadDeck(parseInt(charId));
            }
        });
    });

    document.getElementById('btn-view-deck').addEventListener('click', () => {
        const charId = document.querySelector('#tab-assign .character-search[data-target-id="view_deck_char_id"] .char-search-value').value;
        if(charId) loadDeck(parseInt(charId));
    });

    function loadDeck(charId) {
        fetch('../ajax/cards_my_deck.php?character_id=' + charId)
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
                            fetch('../ajax/cards_unassign.php', {
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
