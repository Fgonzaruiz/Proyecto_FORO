/**
 * Gestión de cartas (staff) — catálogo, editor drawer, asignación.
 * Config: window.CARTAS_STAFF_CONFIG = { ajaxBase: ".../game/ajax" }
 */
(function () {
  "use strict";
  var cfg = window.CARTAS_STAFF_CONFIG || {};
  var GAME_AJAX_BASE = cfg.ajaxBase || (window.GAME_AJAX_BASE || "");
  function staffPost(endpoint, data) {
    var url = GAME_AJAX_BASE + "/" + String(endpoint).replace(/^\//, "");
    if (window.gamePostJson) {
      return window.gamePostJson(url, data || {});
    }
    var body = data || {};
    if (window.GAME_CSRF) {
      body.my_post_key = window.GAME_CSRF;
    }
    return fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json", "X-Mybb-Post-Key": window.GAME_CSRF || "" },
      credentials: "same-origin",
      body: JSON.stringify(body)
    }).then(function (r) { return r.json(); });
  }


  function init() {
    const CARD_TYPE_LABELS = {
        tecnica: 'Técnicas',
        equipo: 'Equipo',
        npc_menor: 'NPC Menor'
    };
    const CARD_TYPE_ORDER = ['tecnica', 'equipo', 'npc_menor'];

    const tabs = document.querySelectorAll('.rpg-staff-tabs .rpg-tab-btn');
    const contents = document.querySelectorAll('.rpg-tab-content');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.classList.add('rpg-is-hidden'));
            tab.classList.add('active');
            document.getElementById(tab.dataset.target).classList.remove('rpg-is-hidden');
        });
    });

    const editorModal = document.getElementById('card-editor-modal');
    const editorStepType = document.getElementById('card-editor-step-type');
    const editorForm = document.getElementById('card-editor-form');
    const sectionEconomia = document.getElementById('section-economia');
    const sectionCombate = document.getElementById('section-combate');
    const TRADEABLE_TYPES = ['equipo', 'npc_menor'];

    function openEditorModal(showTypeStep) {
        if (!editorModal) return;
        if (showTypeStep) {
            editorStepType.classList.remove('rpg-is-hidden');
            editorForm.classList.add('rpg-is-hidden');
        } else {
            editorStepType.classList.add('rpg-is-hidden');
            editorForm.classList.remove('rpg-is-hidden');
        }
        if (window.RpgModal) {
            RpgModal.open('card-editor-modal');
        }
    }

    function closeEditorModal() {
        if (window.RpgModal) {
            RpgModal.close('card-editor-modal');
        }
    }

    if (window.RpgModal) {
        RpgModal.bind('card-editor-modal');
        RpgModal.bind('tecnica-guide-modal');
    }

    document.querySelectorAll('.rpg-type-picker-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const type = btn.dataset.cardType;
            document.getElementById('c_type').value = type;
            editorStepType.classList.add('rpg-is-hidden');
            editorForm.classList.remove('rpg-is-hidden');
            updateFieldVisibility();
            updateEconomyVisibility();
        });
    });

    function updateEconomyVisibility() {
        const type = document.getElementById('c_type').value;
        const show = TRADEABLE_TYPES.indexOf(type) !== -1;
        if (sectionEconomia) sectionEconomia.classList.toggle('rpg-is-hidden', !show);
    }

    function updateSectionCombateVisibility() {
        const type = document.getElementById('c_type').value;
        const hideAll = ['npc_menor'].indexOf(type) !== -1;
        if (sectionCombate) sectionCombate.classList.toggle('rpg-is-hidden', hideAll);
    }

    let allCards = [];
    let catalogSearchQuery = '';

    function isConsumibleCard(c) {
        if (c.card_type !== 'equipo') return false;
        const eff = c.effects || {};
        if (eff.equipo_type === 'util') return true;
        const tags = (c.tags || []).map(t => String(t).toUpperCase());
        return tags.some(t => t === 'CONSUMIBLE' || t === 'MUNICION' || t === 'AMMO');
    }

    function filterCatalogCards(cards) {
        const q = catalogSearchQuery.trim().toLowerCase();
        if (!q) return cards;
        return cards.filter(c => (c.name || '').toLowerCase().includes(q));
    }

    function toggleCatalogSection(type, headerEl) {
        const content = document.getElementById('catalog-section-' + type);
        if (!content) return;
        const isOpen = content.classList.toggle('is-open');
        headerEl.classList.toggle('is-open', isOpen);
    }
    window.toggleCatalogSection = toggleCatalogSection;

    function renderCatalogCard(c) {
        const isEquipo = c.card_type === 'equipo';
        const rankLabel = isEquipo ? 'Rareza' : 'Rango';
        const durText = c.duracion > 0 ? ' • Duración: ' + c.duracion + 't' : '';
        const repText = c.reposo > 0 ? ' • Reposo: ' + c.reposo + 't' : '';
        const el = document.createElement('div');
        el.className = 'rpg-staff-catalog-card';
        el.innerHTML =
            '<div class="rpg-staff-catalog-card__head">' +
                '<strong class="rpg-staff-catalog-card__name">' + escapeHtml(c.name) + '</strong>' +
                '<span class="rpg-staff-catalog-card__rank">' + rankLabel + ' ' + escapeHtml(String(c.rank)) + '</span>' +
            '</div>' +
            '<div class="rpg-staff-catalog-card__meta">' + escapeHtml(c.card_type.toUpperCase()) + durText + repText + '</div>' +
            '<div class="rpg-staff-catalog-card__desc">' + escapeHtml(c.description || '') + '</div>' +
            '<div class="rpg-staff-catalog-card__actions">' +
                '<button type="button" class="rpg-system-tab-btn edit-card" data-id="' + c.id + '">Editar</button>' +
                '<button type="button" class="rpg-system-tab-btn rpg-staff-btn-danger del-card" data-id="' + c.id + '">Eliminar</button>' +
            '</div>';
        return el;
    }

    function escapeHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function renderCatalog(cards) {
        const list = document.getElementById('catalog-list');
        list.innerHTML = '';
        const filtered = filterCatalogCards(cards);
        if (filtered.length === 0) {
            list.innerHTML = '<div class="rpg-staff-catalog-empty">' +
                (catalogSearchQuery.trim() ? 'Sin resultados para "' + escapeHtml(catalogSearchQuery.trim()) + '".' : 'No hay cartas creadas.') +
            '</div>';
            return;
        }
        const grouped = {};
        filtered.forEach(c => {
            const t = c.card_type || 'otro';
            if (!grouped[t]) grouped[t] = [];
            grouped[t].push(c);
        });
        const types = CARD_TYPE_ORDER.filter(t => grouped[t] && grouped[t].length);
        Object.keys(grouped).forEach(t => {
            if (types.indexOf(t) === -1) types.push(t);
        });
        types.forEach(type => {
            const listType = grouped[type];
            if (!listType || !listType.length) return;
            const label = CARD_TYPE_LABELS[type] || type;
            const section = document.createElement('div');
            section.className = 'rpg-deck-section rpg-staff-catalog-section';
            section.innerHTML =
                '<div class="rpg-deck-section-header is-open" onclick="toggleCatalogSection(\'' + type + '\', this)">' +
                    '<div class="rpg-deck-section-title">' + label + ' <span class="rpg-deck-section-count">(' + listType.length + ')</span></div>' +
                    '<div class="rpg-deck-section-arrow"><i class="fas fa-chevron-down"></i></div>' +
                '</div>' +
                '<div id="catalog-section-' + type + '" class="rpg-deck-section-content is-open rpg-staff-catalog-grid"></div>';
            list.appendChild(section);
            const grid = section.querySelector('.rpg-staff-catalog-grid');
            listType.forEach(c => grid.appendChild(renderCatalogCard(c)));
        });
        list.querySelectorAll('.edit-card').forEach(btn => btn.addEventListener('click', e => editCard(e.currentTarget.dataset.id)));
        list.querySelectorAll('.del-card').forEach(btn => btn.addEventListener('click', e => deleteCard(e.currentTarget.dataset.id)));
    }

    let catalogSearchTimeout = null;
    document.getElementById('catalog-search').addEventListener('input', (e) => {
        clearTimeout(catalogSearchTimeout);
        catalogSearchTimeout = setTimeout(() => {
            catalogSearchQuery = e.target.value;
            renderCatalog(allCards);
        }, 200);
    });

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

    function populateCardSelect(cards) {
        const sel = document.getElementById('assign_card_id');
        sel.innerHTML = '<option value="">Selecciona una carta...</option>';
        cards.forEach(c => {
            sel.innerHTML += `<option value="${c.id}">${c.name} (${c.card_type})</option>`;
        });
        updateAssignQtyVisibility();
    }

    function updateAssignQtyVisibility() {
        const cardId = document.getElementById('assign_card_id').value;
        const qtyGroup = document.getElementById('assign-qty-group');
        if (!cardId) {
            qtyGroup.classList.add('rpg-is-hidden');
            return;
        }
        const card = allCards.find(c => String(c.id) === String(cardId));
        if (card && isConsumibleCard(card)) {
            qtyGroup.classList.remove('rpg-is-hidden');
        } else {
            qtyGroup.classList.add('rpg-is-hidden');
        }
    }
    document.getElementById('assign_card_id').addEventListener('change', updateAssignQtyVisibility);

    // ======= TAG SELECTOR =======
    const TAG_CATEGORIES = [
        { name: 'Activación y temporalidad', tags: ['ACTIVA','PASIVA','REACTIVA','CONTINUA','INSTANTÁNEA','CARGA','CANAL','RETRASADA','ENCADENABLE','UNA VEZ','COOLDOWN X'] },
        { name: 'Alcance y geometría', tags: ['CONTACTO','CUERPO A CUERPO','DISTANCIA CORTA','DISTANCIA MEDIA','DISTANCIA LARGA','AUTOPERSONAL','ALIADOS','ÁREA PEQUEÑA','ÁREA MEDIA','ÁREA GRANDE','LÍNEA','CONO','ANILLO','TRAYECTORIA','TOQUE','GLOBAL'] },
        { name: 'Función de combate', tags: ['OFENSIVA','DEFENSIVA','CONTROL','SOPORTE','MOVILIDAD','CURACIÓN','UTILIDAD','INTERRUPCIÓN','PENETRACIÓN','DESVÍO','ABSORCIÓN','SEÑUELO','ESCUDO'] },
        { name: 'Ejecución', tags: ['EJECUCIÓN: FUE','EJECUCIÓN: AGI','EJECUCIÓN: DES','EJECUCIÓN: INST','EJECUCIÓN: ESP','EJECUCIÓN: INT'] },
        { name: 'Tipo de daño', tags: ['DAÑO FÍSICO','DAÑO CORTANTE','DAÑO CONTUNDENTE','DAÑO PERFORANTE','DAÑO ÍGNEO','DAÑO CRIOGÉNICO','DAÑO ELÉCTRICO','DAÑO TÓXICO','DAÑO EXPLOSIVO','DAÑO INTERNO','DAÑO ESPIRITUAL','DAÑO ESTRUCTURAL','DAÑO OSCURO'] },
        { name: 'Interacción especial', tags: ['ANTI-LOGIA','ANTI-HAKI','KAIROSEKI','IGNORA ARMADURA','DOBLE DAÑO EMPAPADO','VULNERABILIDAD AGUA','ESCALA CON DAÑO RECIBIDO','ESCALA CON PE RESTANTE','ESCALA CON ALIADOS','BONUS VS DERRIBADO','BONUS VS ESTADO','ENCADENADO CON','ROMPE CONCENTRACIÓN'] },
        { name: 'Elemento / naturaleza', tags: ['FUEGO','HIELO','RAYO','VENENO','OSCURIDAD','LUZ','VIENTO','TIERRA','AGUA','HUMO','ARENA','VIBRACIÓN','SONIDO','GRAVEDAD','VACÍO'] },
        { name: 'Akuma no Mi', tags: ['LOGIA','PARAMECIA','ZOAN'] },
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
        group.className = 'rpg-staff-tag-group';
        const header = document.createElement('div');
        header.className = 'rpg-staff-tag-group-header';
        header.innerHTML = '<span class="rpg-staff-tag-group-arrow">▸</span> ' + cat.name;
        header.addEventListener('click', () => {
            const body = header.nextElementSibling;
            const arrow = header.querySelector('.rpg-staff-tag-group-arrow');
            body.classList.toggle('is-open');
            arrow.textContent = body.classList.contains('is-open') ? '▾' : '▸';
        });
        group.appendChild(header);
        const body = document.createElement('div');
        body.className = 'rpg-staff-tag-group-body';
        cat.tags.forEach(tag => {
            const label = document.createElement('label');
            label.className = 'rpg-staff-tag-option';
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
        tagDropdown.classList.toggle('is-open');
    });

    function updateTagDisplay() {
        tagSelected.innerHTML = '';
        selectedTags.forEach(tag => {
            const pill = document.createElement('span');
            pill.className = 'rpg-staff-tag-pill';
            pill.textContent = tag;
            const remove = document.createElement('span');
            remove.textContent = '×';
            remove.className = 'rpg-staff-tag-pill-remove';
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
        const groups = document.querySelectorAll('#dice-groups > div');
        let parts = [];
        groups.forEach(g => {
            if (g.classList.contains('dice-group')) {
                const qty = parseInt(g.querySelector('.dice-qty').value) || 1;
                const type = g.querySelector('.dice-type').value;
                if (qty > 0) parts.push(qty + type);
            } else if (g.classList.contains('dice-placeholder')) {
                const type = g.querySelector('.placeholder-type').value;
                parts.push(type);
            }
        });
        const fixed = parseInt(document.getElementById('dice-fixed').value) || 0;
        const stat = document.getElementById('dice-stat').value;
        const statMod = document.getElementById('dice-stat-mod').value.trim();
        const suffix = document.getElementById('dice-suffix').value.trim();

        let formula = parts.join('+');
        if (fixed > 0) formula += (formula ? '+' : '') + fixed;
        if (stat) {
            let statPart = stat;
            if (statMod) {
                if (statMod.includes('/')) {
                    const divisor = statMod.replace('/', '').trim();
                    statPart = stat + '/' + divisor;
                } else if (statMod.includes('*')) {
                    const mult = statMod.replace('*', '').trim();
                    statPart = mult + '*' + stat;
                } else {
                    if (!isNaN(parseFloat(statMod))) {
                        statPart = statMod + '*' + stat;
                    } else {
                        statPart = statMod + stat;
                    }
                }
            }
            formula += (formula ? '+' : '') + statPart;
        }
        if (suffix) formula += (formula ? ' ' : '') + suffix;

        document.getElementById('dice-preview').textContent = formula || '—';
        document.getElementById('c_dice').value = formula;
    }

    function addDiceGroup(qty, type) {
        const container = document.getElementById('dice-groups');
        const group = document.createElement('div');
        group.className = 'dice-group rpg-dice-group-chip';

        const qtyInput = document.createElement('input');
        qtyInput.type = 'number';
        qtyInput.className = 'dice-qty textbox rpg-dice-qty-input';
        qtyInput.min = 1;
        qtyInput.max = 100;
        qtyInput.value = qty || 2;
        qtyInput.addEventListener('input', buildDiceFormula);

        const typeSelect = document.createElement('select');
        typeSelect.className = 'dice-type textbox rpg-dice-type-select';
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
        removeBtn.className = 'rpg-dice-remove-btn';
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

    function addPlaceholderGroup(type) {
        const container = document.getElementById('dice-groups');
        const group = document.createElement('div');
        group.className = 'dice-placeholder rpg-dice-group-chip rpg-dice-group-chip--placeholder';

        const textSpan = document.createElement('span');
        textSpan.textContent = type;

        const typeInput = document.createElement('input');
        typeInput.type = 'hidden';
        typeInput.className = 'placeholder-type';
        typeInput.value = type;

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.textContent = '×';
        removeBtn.title = 'Quitar';
        removeBtn.className = 'rpg-dice-remove-btn';
        removeBtn.addEventListener('click', () => {
            container.removeChild(group);
            buildDiceFormula();
        });

        group.appendChild(textSpan);
        group.appendChild(typeInput);
        group.appendChild(removeBtn);
        container.appendChild(group);
        buildDiceFormula();
    }

    function parseDiceFormula(formula) {
        const container = document.getElementById('dice-groups');
        container.innerHTML = '';
        document.getElementById('dice-fixed').value = '0';
        document.getElementById('dice-stat').value = '';
        document.getElementById('dice-stat-mod').value = '';
        document.getElementById('dice-suffix').value = '';

        if (!formula || formula === '—' || !formula.trim()) {
            addDiceGroup(2, 'd20');
            return;
        }

        // Extract bracketed suffix at the end (e.g. [FUEGO], [AGUA], etc.)
        let suffix = '';
        let formulaNoSuffix = formula.trim();
        const suffixMatch = formula.match(/\[([^\]]+)\]$/);
        if (suffixMatch) {
            suffix = suffixMatch[0]; // e.g. "[FUEGO]"
            formulaNoSuffix = formula.substring(0, formula.length - suffix.length).trim();
        }

        const parts = formulaNoSuffix.split('+');
        let suffixParts = [];

        parts.forEach(part => {
            part = part.trim();
            if (!part) return;
            const diceMatch = part.match(/^(\d+)(d\d+)$/i);
            if (diceMatch) {
                addDiceGroup(parseInt(diceMatch[1]), diceMatch[2]);
                return;
            }
            if (part === '[ARMA]' || part === '[MUNICION]') {
                addPlaceholderGroup(part);
                return;
            }

            // Stat with multiplier or divisor
            const multMatch = part.match(/^([\d.]+)\*(FUE|AGI|DES|INST|ESP|INT)$/i);
            if (multMatch) {
                document.getElementById('dice-stat').value = multMatch[2].toUpperCase();
                document.getElementById('dice-stat-mod').value = multMatch[1] + '*';
                return;
            }
            const divMatch = part.match(/^(FUE|AGI|DES|INST|ESP|INT)\/([\d.]+)$/i);
            if (divMatch) {
                document.getElementById('dice-stat').value = divMatch[1].toUpperCase();
                document.getElementById('dice-stat-mod').value = '/' + divMatch[2];
                return;
            }
            const reverseMultMatch = part.match(/^(FUE|AGI|DES|INST|ESP|INT)\*([\d.]+)$/i);
            if (reverseMultMatch) {
                document.getElementById('dice-stat').value = reverseMultMatch[1].toUpperCase();
                document.getElementById('dice-stat-mod').value = reverseMultMatch[2] + '*';
                return;
            }

            if (['FUE', 'AGI', 'DES', 'INST', 'ESP', 'INT'].includes(part.toUpperCase())) {
                document.getElementById('dice-stat').value = part.toUpperCase();
                return;
            }
            if (/^\d+$/.test(part)) {
                document.getElementById('dice-fixed').value = part;
                return;
            }
            suffixParts.push(part);
        });

        // Add back the extracted suffix tag to suffixParts
        if (suffix) {
            suffixParts.push(suffix);
        }

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
        document.getElementById('dice-stat-mod').value = '';
        document.getElementById('dice-suffix').value = '';
        buildDiceFormula();
    }

    document.getElementById('dice-add-group').addEventListener('click', () => addDiceGroup(1, 'd6'));
    document.getElementById('dice-add-arma').addEventListener('click', () => addPlaceholderGroup('[ARMA]'));
    document.getElementById('dice-add-municion').addEventListener('click', () => addPlaceholderGroup('[MUNICION]'));
    document.getElementById('dice-fixed').addEventListener('input', buildDiceFormula);
    document.getElementById('dice-stat').addEventListener('change', buildDiceFormula);
    document.getElementById('dice-stat-mod').addEventListener('input', buildDiceFormula);
    document.getElementById('dice-suffix').addEventListener('input', buildDiceFormula);

    // Default dice group
    addDiceGroup(2, 'd20');

    // ======= NPC ACTIONS DYNAMIC LIST =======
    const npcActionsContainer = document.getElementById('npc-actions-container');
    
    const DICE_OPTIONS = ['1d4','1d6','1d8','1d10','1d12','2d4','2d6','2d8','2d10','3d6','4d6'];
    const STAT_OPTIONS = ['','FUE','AGI','DES','INST','ESP','INT'];

    function addNpcActionRow(action = '') {
        if (!npcActionsContainer) return;
        let name = '';
        let dice = '';
        let stat = '';
        if (typeof action === 'string') {
            name = action.replace(/\s*\([^)]*\)\s*$/, '').trim();
            const m = action.match(/(\d+d\d+(?:\s*[+\-]\s*\w+)?)/i);
            dice = m ? m[1].replace(/\s+/g, '') : '';
        } else if (action && typeof action === 'object') {
            name = action.name || '';
            dice = action.dice || '';
            stat = action.stat || '';
        }
        const div = document.createElement('div');
        div.className = 'rpg-npc-action-row npc-action-row';
        let diceOpts = DICE_OPTIONS.map(d => `<option value="${d}"${d === dice ? ' selected' : ''}>${d}</option>`).join('');
        diceOpts += `<option value=""${!dice ? ' selected' : ''}>Sin dado</option>`;
        let statOpts = STAT_OPTIONS.map(s => `<option value="${s}"${s === stat ? ' selected' : ''}>${s || '— Stat —'}</option>`).join('');
        div.innerHTML = `
            <input type="text" class="textbox rpg-npc-action-name npc-action-name" value="${name.replace(/"/g, '&quot;')}" placeholder="Nombre (ej: Picotazo Rápido)">
            <select class="textbox rpg-dice-select-sm npc-action-dice">${diceOpts}</select>
            <select class="textbox rpg-dice-select-sm npc-action-stat">${statOpts}</select>
            <button type="button" class="rpg-btn-remove-sm remove-npc-action">Eliminar</button>
        `;
        div.querySelector('.remove-npc-action').addEventListener('click', () => {
            div.remove();
            if (npcActionsContainer.children.length === 0) {
                addNpcActionRow('');
            }
        });
        npcActionsContainer.appendChild(div);
    }

    document.getElementById('btn-npc-add-action').addEventListener('click', () => addNpcActionRow(''));

    function getNpcActions() {
        const rows = document.querySelectorAll('#npc-actions-container .npc-action-row');
        return Array.from(rows).map(row => {
            const name = row.querySelector('.npc-action-name')?.value.trim() || '';
            const dice = row.querySelector('.npc-action-dice')?.value.trim() || '';
            const stat = row.querySelector('.npc-action-stat')?.value.trim() || '';
            if (!name) return null;
            const out = { name };
            if (dice) out.dice = dice;
            if (stat) out.stat = stat;
            return out;
        }).filter(Boolean);
    }

    function setNpcActions(actions) {
        if (!npcActionsContainer) return;
        npcActionsContainer.innerHTML = '';
        let list = [];
        if (Array.isArray(actions)) {
            list = actions;
        } else if (typeof actions === 'string') {
            list = actions.split('\n');
        }
        if (list.length === 0) {
            addNpcActionRow('');
        } else {
            list.forEach(act => addNpcActionRow(act));
        }
    }

    // ======= DYNAMIC SUBTIPO OPTIONS =======
    const subOptions = {
        arma: ['Espada', 'Lanza', 'Arco', 'Ballesta', 'Pistola', 'Rifle', 'Hacha', 'Maza', 'Otros'],
        util: ['Botiquín', 'Comida', 'Brújula', 'Munición', 'Kairooseki', 'Herramienta', 'Otros'],
        armadura: ['Peto', 'Escudo', 'Casco', 'Grebas', 'Guanteletes', 'Otros']
    };

    function updateSubtipoOptions(currentVal = '') {
        const eqType = document.getElementById('equipo_type').value;
        const sel = document.getElementById('equipo_subtipo_select');
        const input = document.getElementById('equipo_subtipo');
        if (!sel || !input) return;
        
        const list = subOptions[eqType] || ['Otros'];
        
        sel.innerHTML = '';
        list.forEach(opt => {
            sel.innerHTML += `<option value="${opt.toLowerCase()}">${opt}</option>`;
        });
        
        const lowerList = list.map(x => x.toLowerCase());
        const searchVal = (currentVal || input.value || '').trim().toLowerCase();
        
        if (searchVal && lowerList.includes(searchVal)) {
            sel.value = searchVal;
            input.value = searchVal;
            input.classList.add('rpg-subtipo-other');
            input.classList.remove('is-visible');
        } else if (searchVal) {
            sel.value = 'otros';
            input.value = currentVal || input.value;
            input.classList.add('is-visible');
            input.classList.remove('rpg-subtipo-other');
        } else {
            sel.value = lowerList[0];
            input.value = lowerList[0];
            input.classList.add('rpg-subtipo-other');
            input.classList.remove('is-visible');
        }
    }

    document.getElementById('equipo_subtipo_select').addEventListener('change', (e) => {
        const input = document.getElementById('equipo_subtipo');
        if (e.target.value === 'otros') {
            input.classList.add('is-visible');
            input.classList.remove('rpg-subtipo-other');
            input.value = '';
            input.focus();
        } else {
            input.classList.add('rpg-subtipo-other');
            input.classList.remove('is-visible');
            input.value = e.target.value;
        }
    });

    function resetEditorForm() {
        document.getElementById('card-editor-form').reset();
        document.getElementById('card_id').value = '';
        document.getElementById('editor-title').innerHTML = '<i class="fas fa-plus"></i> Crear Nueva Carta';
        resetTags();
        resetDiceBuilder();
        document.getElementById('c_execution_cost').value = 0;
        document.getElementById('equipo_subtipo').value = '';
        document.getElementById('equipo_peso').value = 1;
        updateSubtipoOptions('');
        document.getElementById('npc_vida').value = 50;
        document.getElementById('npc_tier').value = 1;
        setNpcActions([]);
        
        document.getElementById('c_cost_berries').value = 1;
        
        updateFieldVisibility();
        updateEconomyVisibility();
        updateSectionCombateVisibility();
    }

    document.getElementById('btn-new-card').addEventListener('click', () => {
        resetEditorForm();
        openEditorModal(true);
    });

    document.getElementById('btn-cancel-edit').addEventListener('click', closeEditorModal);

    const btnTecnicaGuide = document.getElementById('btn-tecnica-guide');
    if (btnTecnicaGuide) {
        btnTecnicaGuide.addEventListener('click', () => {
            if (window.RpgModal) {
                RpgModal.open('tecnica-guide-modal');
            }
        });
    }

    // Visibilidad dinámica de campos RPG
    const typeSelect = document.getElementById('c_type');
    const eqTypeSelect = document.getElementById('equipo_type');
    const npcTypeSelect = document.getElementById('npc_mascota_type');

    function isUtilConsumibleDice() {
        const eqType = eqTypeSelect.value;
        if (eqType !== 'util') return false;
        const sub = (document.getElementById('equipo_subtipo').value || '').toLowerCase();
        const tags = (document.getElementById('c_tags').value || '').toUpperCase();
        if (sub.includes('municion') || sub.includes('munición')) return true;
        if (tags.includes('MUNICION') || tags.includes('AMMO') || tags.includes('CONSUMIBLE')) return true;
        return true;
    }

    function updateFieldVisibility() {
        const type = typeSelect.value;

        const btnTecnicaGuide = document.getElementById('btn-tecnica-guide');
        if (btnTecnicaGuide) {
            if (type === 'tecnica') {
                btnTecnicaGuide.classList.remove('rpg-is-hidden');
            } else {
                btnTecnicaGuide.classList.add('rpg-is-hidden');
            }
        }
        
        // Default wrappers
        const wActivation = document.getElementById('wrapper-activation');
        const wRank = document.getElementById('wrapper-rank');
        const wCost = document.getElementById('wrapper-cost');
        const wStat = document.getElementById('wrapper-stat');
        const wDice = document.getElementById('wrapper-dice');
        const wTurns = document.getElementById('wrapper-turns');
        
        // Custom wrappers
        const fEquipo = document.getElementById('fields-equipo');
        const fNpc = document.getElementById('fields-npc');
        
        // Reset defaults
        wActivation.style.display = 'block';
        wRank.style.display = (type === 'tecnica' || type === 'equipo') ? 'block' : 'none';
        wCost.style.display = 'block';
        wStat.style.display = 'block';
        wDice.style.display = 'block';
        wTurns.style.display = 'grid';
        
        // Hide all custom
        fEquipo.style.display = 'none';
        fNpc.style.display = 'none';
        
        if (type === 'equipo') {
            wActivation.style.display = 'none';
            wCost.style.display = 'none';
            wTurns.style.display = 'none';
            
            fEquipo.style.display = 'grid';
            
            const eqType = eqTypeSelect.value;
            updateSubtipoOptions();
            const wStack = document.getElementById('wrapper-equipo-stack');
            if (eqType === 'arma') {
                wDice.style.display = 'block';
                wStat.style.display = 'block';
                if (wStack) wStack.classList.add('rpg-wizard-hidden');
            } else if (eqType === 'util') {
                if (isUtilConsumibleDice()) {
                    wDice.style.display = 'block';
                    document.getElementById('dice-hint-util')?.classList.remove('rpg-is-hidden');
                } else {
                    wDice.style.display = 'none';
                }
                wStat.style.display = 'none';
                if (wStack) wStack.classList.remove('rpg-wizard-hidden');
            } else {
                wDice.style.display = 'none';
                wStat.style.display = 'none';
                if (wStack) wStack.classList.add('rpg-wizard-hidden');
            }
        } else if (type === 'npc_menor') {
            wActivation.style.display = 'none';
            wCost.style.display = 'none';
            wStat.style.display = 'none';
            wDice.style.display = 'none';
            wTurns.style.display = 'none';
            
            fNpc.style.display = 'grid';
            
            // Mascot tier
            const npcType = npcTypeSelect.value;
            const wNpcTier = document.getElementById('wrapper-npc-tier');
            if (npcType === 'mascota') {
                wNpcTier.style.display = 'block';
            } else {
                wNpcTier.style.display = 'none';
            }
        }
        updateSectionCombateVisibility();
        updateEconomyVisibility();
    }

    typeSelect.addEventListener('change', () => {
        updateFieldVisibility();
    });
    eqTypeSelect.addEventListener('change', () => {
        updateSubtipoOptions();
        updateFieldVisibility();
    });
    npcTypeSelect.addEventListener('change', updateFieldVisibility);
    
    // Init state
    updateFieldVisibility();

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
        document.getElementById('c_execution_cost').value = card.execution_cost || 0;
        document.getElementById('c_stat').value = card.execution_stat || '';
        
        // Migrate legacy weapon fields dynamically into the dice formula
        const effects = card.effects || {};
        let diceFormula = card.dice || '';
        if (card.card_type === 'equipo' && !diceFormula && effects.damage_dice) {
            diceFormula = effects.damage_dice + (effects.damage_stat ? `+${effects.damage_stat}` : '');
        }
        parseDiceFormula(diceFormula);

        document.getElementById('c_reposo').value = card.reposo || 0;
        document.getElementById('c_duracion').value = card.duracion || 0;
        document.getElementById('c_notes').value = card.notes;
        document.getElementById('c_image').value = card.image_url;

        // Cargar efectos estructurados dinámicos
        document.getElementById('equipo_type').value = effects.equipo_type || 'util';
        updateSubtipoOptions(effects.subtipo || '');
        const stackEl = document.getElementById('equipo_stack_qty');
        if (stackEl) stackEl.value = effects.default_cantidad || 1;
        const pesoEl = document.getElementById('equipo_peso');
        if (pesoEl) pesoEl.value = card.peso || 1;

        document.getElementById('npc_mascota_type').value = effects.npc_mascota_type || 'npc';
        document.getElementById('npc_vida').value = effects.vida || 50;
        document.getElementById('npc_tier').value = effects.tier || 1;
        setNpcActions(effects.acciones || []);

        document.getElementById('c_cost_berries').value = card.cost_berries > 0 ? card.cost_berries : 1;

        updateFieldVisibility();
        updateEconomyVisibility();

        document.getElementById('editor-title').innerHTML = '<i class="fas fa-edit"></i> Editar Carta';
        openEditorModal(false);
    }

    // ======= DELETE CARD =======
    function deleteCard(id) {
        if(!confirm('¿Seguro que quieres eliminar esta carta? Se quitará de todos los personajes.')) return;
        staffPost('cards_delete.php', { card_id: parseInt(id, 10) }).then(d => {
            if(d.ok) loadCatalog();
            else alert('Error: ' + ((d.error && d.error.message) ? d.error.message : 'Desconocido'));
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
            execution_cost: parseInt(document.getElementById('c_execution_cost').value) || 0,
            execution_stat: document.getElementById('c_stat').value,
            dice: document.getElementById('c_dice').value,
            notes: document.getElementById('c_notes').value,
            image_url: document.getElementById('c_image').value,
            cost_jenny: parseInt(document.getElementById('c_cost_berries').value, 10) || 0,
        };
        
        const type = document.getElementById('c_type').value;
        if (TRADEABLE_TYPES.indexOf(type) !== -1 && payload.cost_jenny < 1) {
            alert('Las cartas de equipo y NPC menor deben tener un valor en Jenny mayor que 0.');
            return;
        }
        payload.effects = {};
        
        if (type === 'equipo') {
            const eqType = document.getElementById('equipo_type').value;
            payload.effects = {
                equipo_type: eqType,
                subtipo: document.getElementById('equipo_subtipo').value,
                damage_dice: '',
                damage_stat: ''
            };
            if (eqType === 'util') {
                payload.effects.default_cantidad = parseInt(document.getElementById('equipo_stack_qty')?.value, 10) || 1;
                if (!payload.dice || payload.dice === '2d20') {
                    payload.dice = document.getElementById('c_dice').value || '';
                }
            } else if (eqType === 'arma') {
                payload.effects.damage_dice = payload.dice;
                payload.effects.damage_stat = document.getElementById('c_stat').value;
            }
            payload.peso = parseInt(document.getElementById('equipo_peso').value, 10) || 1;
        } else if (type === 'npc_menor') {
            const subType = document.getElementById('npc_mascota_type').value;
            const actionsList = getNpcActions();
            payload.effects = {
                npc_mascota_type: subType,
                vida: parseInt(document.getElementById('npc_vida').value) || 0,
                tier: subType === 'mascota' ? (parseInt(document.getElementById('npc_tier').value) || 1) : 1,
                acciones: actionsList
            };
        }

        payload.reposo = parseInt(document.getElementById('c_reposo').value) || 0;
        payload.duracion = parseInt(document.getElementById('c_duracion').value) || 0;

        if (id) {
            payload.card_id = parseInt(id);
            staffPost('cards_update.php', payload)
            .then(d => {
                if (!d || d.ok === false) throw new Error((d && d.error && d.error.message) ? d.error.message : 'Error');
                return d;
            })
            .then(d => {
                if (d.ok) {
                    loadCatalog();
                    closeEditorModal();
                } else {
                    alert('Error al actualizar la carta: ' + ((d.error && d.error.message) ? d.error.message : 'Error desconocido'));
                }
            })
            .catch(err => {
                alert('Error de conexión o de servidor: ' + err.message + '\n(Asegúrate de haber corrido las migraciones de base de datos)');
            });
        } else {
            staffPost('cards_create.php', payload)
            .then(d => {
                if (!d || d.ok === false) throw new Error((d && d.error && d.error.message) ? d.error.message : 'Error');
                return d;
            })
            .then(d => {
                if (d.ok) {
                    loadCatalog();
                    closeEditorModal();
                } else {
                    alert('Error al crear la carta: ' + ((d.error && d.error.message) ? d.error.message : 'Error desconocido'));
                }
            })
            .catch(err => {
                alert('Error de conexión o de servidor: ' + err.message + '\n(Asegúrate de haber corrido las migraciones de base de datos)');
            });
        }
    });

    // ======= CHARACTER SEARCH AUTOCOMPLETE =======
    if (window.GameCharSearch) {
        document.querySelectorAll('.character-search').forEach(function (container) {
            GameCharSearch.init(container, {
                fetchUrl: '../ajax/cards_search_characters.php',
                onSelect: function (id, name, el) {
                    if (el.dataset.targetId === 'view_deck_char_id') {
                        loadDeck(parseInt(id, 10));
                    }
                }
            });
        });
    }

    function assignCharContainer() {
        return document.querySelector('#tab-assign .character-search[data-target-id="assign_char_id"]');
    }

    function viewDeckCharContainer() {
        return document.querySelector('#tab-assign .character-search[data-target-id="view_deck_char_id"]');
    }

    function resolveCharId(container) {
        if (window.GameCharSearch && container) {
            return GameCharSearch.resolve(container);
        }
        var hidden = container && container.querySelector('.char-search-value');
        return Promise.resolve(hidden ? String(hidden.value || '').trim() : '');
    }

    // ======= ASSIGN =======
    document.getElementById('btn-assign').addEventListener('click', function () {
        var charContainer = assignCharContainer();
        resolveCharId(charContainer).then(function (charId) {
            var cardId = document.getElementById('assign_card_id').value;
            var rank = document.getElementById('assign_rank').value;
            if (!charId && !cardId) return alert('Selecciona un personaje y una carta.');
            if (!charId) return alert('Selecciona un personaje de la lista o pulsa Enter con el nombre exacto.');
            if (!cardId) return alert('Selecciona una carta del listado.');
            var card = allCards.find(function (c) { return String(c.id) === String(cardId); });
            var payload = {
                character_id: parseInt(charId, 10),
                card_id: parseInt(cardId, 10),
                rank: rank
            };
            if (card && isConsumibleCard(card)) {
                payload.cantidad = parseInt(document.getElementById('assign_cantidad').value, 10) || 1;
            }
            staffPost('cards_assign.php', payload).then(function (d) {
                if (d.ok) {
                    alert('Carta asignada correctamente.');
                    var viewContainer = viewDeckCharContainer();
                    var assignInput = charContainer.querySelector('.char-search-input');
                    var viewInput = viewContainer.querySelector('.char-search-input');
                    var viewHidden = viewContainer.querySelector('.char-search-value');
                    viewInput.value = assignInput.value;
                    viewHidden.value = charId;
                    loadDeck(parseInt(charId, 10));
                }
            });
        });
    });

    document.getElementById('btn-view-deck').addEventListener('click', function () {
        var container = viewDeckCharContainer();
        resolveCharId(container).then(function (charId) {
            if (charId) {
                loadDeck(parseInt(charId, 10));
            } else {
                alert('Selecciona un personaje de la lista o pulsa Enter con el nombre exacto.');
            }
        });
    });

    function loadDeck(charId) {
        fetch('../ajax/cards_my_deck.php?character_id=' + charId + '&staff=1')
            .then(r=>r.json()).then(d=>{
                const list = document.getElementById('deck-list');
                list.innerHTML = '';
                if(d.ok && d.data.length > 0) {
                    d.data.forEach(c => {
                        const li = document.createElement('li');
                        li.className = 'rpg-deck-list-item';
                        li.innerHTML = `
                            <div>
                                <strong>${c.name}</strong> <span class="rpg-deck-list-rank">[${c.rank}]</span>
                            </div>
                            <button class="rpg-system-tab-btn rpg-system-tab-btn--compact unassign-btn" data-cid="${c.id}">Quitar</button>
                        `;
                        list.appendChild(li);
                    });
                    document.querySelectorAll('.unassign-btn').forEach(btn => {
                        btn.addEventListener('click', (e) => {
                            const cardId = e.target.dataset.cid;
                            staffPost('cards_unassign.php', {
                                character_id: parseInt(charId, 10),
                                card_id: parseInt(cardId, 10)
                            }).then(res => {
                                if(res.ok) loadDeck(charId);
                            });
                        });
                    });
                } else {
                    list.innerHTML = '<li class="rpg-deck-list-empty">Sin cartas.</li>';
                }
            });
    }

    loadCatalog();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
