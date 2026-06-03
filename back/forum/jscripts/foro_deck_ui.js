/**
 * Motor del Sistema de Cartas RPG
 * Maneja el renderizado de cartas, inventario de personaje, selector en posts y visualización.
 */

const RpgCards = {
    // Configuración base
    config: {
        baseUrl: '',
        rankColors: {
            'C': '#94a3b8',
            'B': '#10b981',
            'A': '#3b82f6',
            'S': '#8b5cf6',
            'SS': 'linear-gradient(135deg, #f59e0b, #ef4444)'
        }
    },

    // Cache para texto completo de cartas (truncación)
    _cardDataCache: {},
    // Modificadores activos para el turno actual
    _modifiers: {},

    init: function() {
        const nav = document.getElementById('pj-nav-submenu');
        if (nav && nav.dataset.base) {
            this.config.baseUrl = nav.dataset.base;
        } else {
            const gameIdx = window.location.pathname.toLowerCase().indexOf('/game/');
            if (gameIdx !== -1) {
                this.config.baseUrl = window.location.origin + window.location.pathname.substring(0, gameIdx);
            } else {
                const firstFolder = window.location.pathname.split('/')[1] || '';
                if (firstFolder.toLowerCase() === 'foro') {
                    this.config.baseUrl = window.location.origin + '/' + firstFolder;
                } else {
                    this.config.baseUrl = window.location.origin;
                }
            }
        }

        // 1. Mostrar cartas en los posts
        this.loadPostCards();

        // 2. Inicializar selector en editor de texto (Quick Reply / New Reply)
        this.initCardSelector();

        // 3. Cargar deck en perfil de personaje si estamos en esa página
        const deckContainer = document.getElementById('rpg-character-deck-container');
        if (deckContainer && deckContainer.dataset.charId) {
            this.loadCharacterDeck(deckContainer.dataset.charId, deckContainer);
        }
    },

    // ──────────────────────────────────────────────────────────────────────────
    // TRUNCACIÓN DE TEXTO
    // ──────────────────────────────────────────────────────────────────────────

    truncateDesc: function(text, cacheKey, cardName, limit) {
        limit = limit || 150;
        if (!text || text.length <= limit) return text;
        var uid = 'td_' + cacheKey + '_' + Math.random().toString(36).slice(2, 6);
        this._cardDataCache[uid] = { name: cardName, text: text };
        return text.substring(0, limit).trim() +
            '... <span class="rpg-ver-mas-link" onclick="RpgCards.showCardTextModal(\'' + uid + '\')" style="color:var(--accent-indigo,#6366f1);cursor:pointer;font-size:11px;font-weight:700;text-decoration:underline;">[Ver más]</span>';
    },

    showCardTextModal: function(uid) {
        var data = this._cardDataCache[uid];
        if (!data) return;
        var modal = document.getElementById('rpg-text-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'rpg-text-modal';
            modal.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.75);display:none;align-items:center;justify-content:center;padding:20px;';
            modal.innerHTML = '<div style="background:var(--bg-card,#1e293b);border:1px solid var(--border-color,#334155);border-radius:12px;max-width:480px;width:100%;padding:24px;max-height:80vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.5);">' +
                '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">' +
                '<div id="rpg-text-modal-title" style="font-family:var(--font-heading);font-weight:800;font-size:16px;color:var(--text-primary,#f1f5f9);"></div>' +
                '<button onclick="document.getElementById(\'rpg-text-modal\').style.display=\'none\'" style="background:none;border:none;cursor:pointer;color:var(--text-secondary,#94a3b8);font-size:22px;line-height:1;">&times;</button>' +
                '</div>' +
                '<div id="rpg-text-modal-body" style="color:var(--text-primary,#f1f5f9);font-size:14px;line-height:1.7;white-space:pre-wrap;"></div>' +
                '</div>';
            document.body.appendChild(modal);
            modal.addEventListener('click', function(e) { if (e.target === modal) modal.style.display = 'none'; });
        }
        document.getElementById('rpg-text-modal-title').textContent = data.name;
        document.getElementById('rpg-text-modal-body').textContent = data.text;
        modal.style.display = 'flex';
    },

    // ──────────────────────────────────────────────────────────────────────────
    // PANEL DE MODIFICADORES
    // ──────────────────────────────────────────────────────────────────────────

    addModifier: function() {
        var statEl = document.getElementById('rpg-mod-stat');
        var valEl  = document.getElementById('rpg-mod-value');
        if (!statEl || !valEl) return;
        var stat = statEl.value;
        var val  = parseInt(valEl.value);
        if (!stat || isNaN(val) || val === 0) return;
        if (!this._modifiers) this._modifiers = {};
        this._modifiers[stat] = (this._modifiers[stat] || 0) + val;
        valEl.value = '';
        this._renderModifierList();
        this._updateModifiersInput();
    },

    removeModifier: function(stat) {
        if (this._modifiers) delete this._modifiers[stat];
        this._renderModifierList();
        this._updateModifiersInput();
    },

    _renderModifierList: function() {
        var list = document.getElementById('rpg-modifier-list');
        if (!list) return;
        list.innerHTML = '';
        for (var stat in (this._modifiers || {})) {
            if (!Object.prototype.hasOwnProperty.call(this._modifiers, stat)) continue;
            var val = this._modifiers[stat];
            if (val === 0) continue;
            var color = val > 0 ? '#10b981' : '#ef4444';
            var sign  = val > 0 ? '+' : '';
            var rgb   = val > 0 ? '16,185,129' : '239,68,68';
            list.innerHTML += '<span style="display:inline-flex;align-items:center;gap:4px;background:rgba(' + rgb + ',0.12);border:1px solid ' + color + ';color:' + color + ';padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;cursor:pointer;" onclick="RpgCards.removeModifier(\'' + stat + '\')" title="Click para eliminar">' +
                stat.toUpperCase() + ' ' + sign + val + ' <i class="fas fa-times" style="font-size:9px;"></i></span>';
        }
    },

    _updateModifiersInput: function() {
        var input = document.getElementById('rpg_modifiers');
        if (!input) {
            input = document.createElement('input');
            input.type  = 'hidden';
            input.name  = 'rpg_modifiers';
            input.id    = 'rpg_modifiers';
            var played = document.getElementById('rpg_played_cards');
            if (played && played.parentNode) {
                played.parentNode.insertBefore(input, played.nextSibling);
            }
        }
        input.value = JSON.stringify(this._modifiers || {});
    },

    _injectModifierPanel: function(container) {
        this._updateModifiersInput();
        if (document.getElementById('rpg-modifier-panel')) return;

        var modPanel = document.createElement('div');
        modPanel.id = 'rpg-modifier-panel';
        modPanel.style.cssText = 'margin-top:12px;padding:12px 14px;background:var(--bg-surface);border:1px solid var(--border-color);border-radius:var(--radius-md);';
        modPanel.innerHTML =
            '<div style="font-family:var(--font-heading);font-size:11px;font-weight:800;text-transform:uppercase;color:var(--text-muted);margin-bottom:10px;display:flex;align-items:center;gap:6px;letter-spacing:0.5px;">' +
                '<i class="fas fa-sliders"></i> Modificadores Activos ' +
                '<span style="font-size:10px;color:var(--text-muted);font-weight:500;text-transform:none;font-family:var(--font-body);">(buffs / debuffs de este turno)</span>' +
            '</div>' +
            '<div style="display:flex;gap:6px;margin-bottom:8px;align-items:center;">' +
                '<select id="rpg-mod-stat" class="textbox" style="flex:1;font-size:12px;padding:5px 8px;border-radius:4px;background:var(--bg-card);border:1px solid var(--border-color);color:var(--text-primary);">' +
                    '<option value="fue">FUE (Fuerza)</option>' +
                    '<option value="agi">AGI (Agilidad)</option>' +
                    '<option value="des">DES (Destreza)</option>' +
                    '<option value="int">INT (Inteligencia)</option>' +
                    '<option value="esp">ESP (Espíritu)</option>' +
                    '<option value="inst">INST (Instinto)</option>' +
                '</select>' +
                '<input type="number" id="rpg-mod-value" class="textbox" placeholder="ej: +5" style="width:75px;font-size:12px;padding:5px 8px;border-radius:4px;background:var(--bg-card);border:1px solid var(--border-color);color:var(--text-primary);" />' +
                '<button type="button" onclick="RpgCards.addModifier()" style="background:var(--accent-indigo,#6366f1);color:#fff;border:none;padding:5px 13px;border-radius:4px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;transition:opacity 0.2s;" onmouseover="this.style.opacity=\'0.85\'" onmouseout="this.style.opacity=\'1\'"><i class="fas fa-plus"></i> Añadir</button>' +
            '</div>' +
            '<div id="rpg-modifier-list" style="display:flex;flex-wrap:wrap;gap:6px;min-height:24px;"></div>';
        container.appendChild(modPanel);
    },

    // ──────────────────────────────────────────────────────────────────────────
    // RENDERIZADO DE CARTAS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Genera el HTML de una carta (diseño premium)
     */
    renderCard: function(c) {
        var rankColor = this.config.rankColors[c.rank] || this.config.rankColors['C'];
        var isHolo   = c.rank === 'SS' ? 'rpg-card--holo' : '';
        var hasImage = c.image_url && c.image_url.trim() !== '';

        var tagsHtml = '';
        if (c.tags && c.tags.length > 0) {
            tagsHtml = '<div class="rpg-card-tags">';
            c.tags.forEach(function(t) {
                var cleanedTag = t.replace(/[\[\]]/g, '').trim().toUpperCase();
                if (cleanedTag) {
                    tagsHtml += '<span class="rpg-card-tag" style="color:' + rankColor + '; border-color:' + rankColor + '">' + cleanedTag + '</span>';
                }
            });
            tagsHtml += '</div>';
        }

        var rollHtml = '';
        if (c.roll_result && c.roll_result.trim() !== '') {
            var rollLabel = c.card_type === 'npc_menor' ? 'Acción Ejecutada' : 'Resultado de Tirada';
            var rollIcon  = c.card_type === 'npc_menor' ? 'fas fa-paw' : 'fas fa-dice';
            rollHtml = '<div style="background:rgba(16,185,129,0.1);border-left:3px solid #10b981;padding:10px;margin-top:10px;border-radius:4px;">' +
                '<div style="font-size:10px;font-weight:bold;color:#10b981;margin-bottom:3px;text-transform:uppercase;"><i class="' + rollIcon + '"></i> ' + rollLabel + '</div>' +
                '<div style="font-size:13px;color:var(--text-primary);">' + c.roll_result.replace(/\n/g, '<br>') + '</div>' +
                '</div>';
        }

        var borderStyle = c.rank === 'SS'
            ? 'border: 2px solid transparent; background-image: linear-gradient(var(--bg-card), var(--bg-card)), ' + rankColor + '; background-origin: border-box; background-clip: content-box, border-box;'
            : 'border: 2px solid ' + rankColor + ';';

        var durationText = (c.duracion && c.duracion > 0) ? ' • DURACIÓN: ' + c.duracion + 'T' : '';
        var reposoText   = (c.reposo   && c.reposo   > 0) ? ' • REPOSO: '   + c.reposo   + 'T' : '';

        var self = this;

        // ── AKUMA NO MI ──────────────────────────────────────────────────────
        if (c.card_type === 'akuma_no_mi') {
            var effects   = c.effects || {};
            var akumaType = (effects.akuma_type || 'paramecia').toLowerCase();
            var typeLabel = 'AKUMA NO MI: ' + akumaType.toUpperCase();

            var efectos     = effects.efectos     || 'Sin efectos específicos registrados.';
            var limitaciones= effects.limitaciones|| 'Sin limitaciones específicas registradas.';
            var debilidades = effects.debilidades || 'Sin debilidades específicas registradas.';

            var imageStyle = hasImage
                ? 'background-image:url(\'' + c.image_url + '\');aspect-ratio:1/1;height:216px;background-size:cover;background-position:center;width:100%;border-bottom:1px solid var(--border-color);'
                : '';

            return '<div class="rpg-card rpg-card--akuma rpg-card--akuma-' + akumaType + ' ' + isHolo + '" data-card-id="' + c.id + '">' +
                '<div class="rpg-card-header">' +
                    '<div class="rpg-card-title">' + c.name + '</div>' +
                    '<div class="rpg-card-subtitle akuma-type-label">' + typeLabel + '</div>' +
                '</div>' +
                (hasImage ? '<div class="rpg-card-image" style="' + imageStyle + '"></div>' : '') +
                '<div class="rpg-card-body">' +
                    '<div class="rpg-card-desc">' + self.truncateDesc(c.description, c.id, c.name) + '</div>' +
                    '<div class="rpg-card-section rpg-card-section--efectos">' +
                        '<span class="rpg-card-section-title"><i class="fas fa-wand-magic-sparkles"></i> EFECTOS</span>' +
                        '<div class="rpg-card-section-text">' + self.truncateDesc(efectos, c.id + '_ef', c.name + ' — Efectos') + '</div>' +
                    '</div>' +
                    '<div class="rpg-card-section rpg-card-section--limitaciones">' +
                        '<span class="rpg-card-section-title"><i class="fas fa-shield-halved"></i> LIMITACIONES</span>' +
                        '<div class="rpg-card-section-text">' + self.truncateDesc(limitaciones, c.id + '_lim', c.name + ' — Limitaciones') + '</div>' +
                    '</div>' +
                    '<div class="rpg-card-section rpg-card-section--debilidades">' +
                        '<span class="rpg-card-section-title"><i class="fas fa-skull-crossbones"></i> DEBILIDADES</span>' +
                        '<div class="rpg-card-section-text">' + self.truncateDesc(debilidades, c.id + '_deb', c.name + ' — Debilidades') + '</div>' +
                    '</div>' +
                    rollHtml +
                '</div>' +
            '</div>';
        }

        // ── EQUIPO ───────────────────────────────────────────────────────────
        if (c.card_type === 'equipo') {
            var effects  = c.effects || {};
            var eqType   = (effects.equipo_type || 'util').toLowerCase();
            var subtype  = effects.subtipo || '';
            var eqTypeLabel = 'EQUIPO: ' + eqType.toUpperCase() + (subtype ? ' (' + subtype + ')' : '');

            var eqStatsHtml = '';
            if (eqType === 'arma') {
                var dmgDice = effects.damage_dice || c.dice || '—';
                var dmgStat = (effects.damage_stat || c.execution_stat || '').toUpperCase();
                var dmgFormula = dmgStat ? dmgDice + ' + ' + dmgStat : dmgDice;
                eqStatsHtml = '<div class="rpg-card-stats-row rpg-card-stats-row--weapon">' +
                    '<div><span><i class="fas fa-sword"></i> DAÑO</span><strong>' + dmgFormula + '</strong></div>' +
                    '</div>';
            } else if (eqType !== 'armadura' && c.dice && c.dice !== '—' && c.dice.trim() !== '') {
                // Útiles / munición: mostrar dado aplicado
                eqStatsHtml = '<div class="rpg-card-stats-row">' +
                    '<div><span><i class="fas fa-dice-d6"></i> DADO</span><strong>' + c.dice + '</strong></div>' +
                    '</div>';
            }

            return '<div class="rpg-card rpg-card--equipo rpg-card--equipo-' + eqType + ' ' + isHolo + '" data-card-id="' + c.id + '" style="' + borderStyle + '">' +
                '<div class="rpg-card-header">' +
                    '<div class="rpg-card-title">' + c.name + '</div>' +
                    '<div class="rpg-card-subtitle" style="color:' + (c.rank === 'SS' ? '#f59e0b' : rankColor) + '">[CALIDAD ' + c.rank + '] ' + eqTypeLabel + '</div>' +
                '</div>' +
                (hasImage ? '<div class="rpg-card-image" style="background-image:url(\'' + c.image_url + '\')"></div>' : '') +
                '<div class="rpg-card-body">' +
                    tagsHtml +
                    eqStatsHtml +
                    '<div class="rpg-card-desc">' + self.truncateDesc(c.description, c.id, c.name) + '</div>' +
                    rollHtml +
                '</div>' +
            '</div>';
        }

        // ── BARCO ────────────────────────────────────────────────────────────
        if (c.card_type === 'barco') {
            var effects     = c.effects || {};
            var bType       = effects.barco_type || 'Navío';
            var tier        = effects.tier        || 1;
            var vida        = effects.vida        || 100;
            var ataque      = effects.ataque      || 0;
            var velocidad   = effects.velocidad   || 0;
            var resistencia = effects.resistencia || 0;

            var shipStatsHtml =
                '<div class="rpg-card-ship-grid">' +
                    '<div class="rpg-card-ship-stat"><span><i class="fas fa-anchor"></i> TIER</span><strong>' + tier + '</strong></div>' +
                    '<div class="rpg-card-ship-stat"><span><i class="fas fa-heart"></i> VIDA</span><strong>' + vida + '</strong></div>' +
                    '<div class="rpg-card-ship-stat"><span><i class="fas fa-swords"></i> ATK</span><strong>' + ataque + '</strong></div>' +
                    '<div class="rpg-card-ship-stat"><span><i class="fas fa-wind"></i> VEL</span><strong>' + velocidad + '</strong></div>' +
                    '<div class="rpg-card-ship-stat" style="grid-column:span 2;"><span><i class="fas fa-shield-halved"></i> RESISTENCIA</span><strong>' + resistencia + '</strong></div>' +
                '</div>';

            return '<div class="rpg-card rpg-card--barco ' + isHolo + '" data-card-id="' + c.id + '" style="' + borderStyle + '">' +
                '<div class="rpg-card-header">' +
                    '<div class="rpg-card-title">' + c.name + '</div>' +
                    '<div class="rpg-card-subtitle" style="color:' + (c.rank === 'SS' ? '#f59e0b' : rankColor) + '">[TIER ' + tier + '] BARCO • ' + bType.toUpperCase() + '</div>' +
                '</div>' +
                (hasImage ? '<div class="rpg-card-image" style="background-image:url(\'' + c.image_url + '\')"></div>' : '') +
                '<div class="rpg-card-body">' +
                    shipStatsHtml +
                    '<div class="rpg-card-desc">' + self.truncateDesc(c.description, c.id, c.name) + '</div>' +
                    rollHtml +
                '</div>' +
            '</div>';
        }

        // ── NPC MENOR / MASCOTA ──────────────────────────────────────────────
        if (c.card_type === 'npc_menor') {
            var effects  = c.effects || {};
            var npcType  = (effects.npc_mascota_type || 'npc').toLowerCase();
            var vida     = effects.vida || 50;
            var tier     = effects.tier || 1;
            var subLabel = npcType === 'mascota' ? 'MASCOTA • TIER ' + tier : 'NPC MENOR';

            var npcStatsHtml =
                '<div class="rpg-card-stats-row">' +
                    '<div><span><i class="fas fa-heart"></i> VIDA</span><strong>' + vida + ' HP</strong></div>' +
                '</div>';

            var actionsHtml = '';
            var rawActions  = effects.acciones || [];
            var actionsList = typeof rawActions === 'string'
                ? rawActions.split('\n').map(function(a){ return a.trim(); }).filter(Boolean)
                : rawActions;
            if (actionsList && actionsList.length > 0) {
                actionsHtml = '<div class="rpg-card-actions-list-container"><span class="rpg-card-section-title"><i class="fas fa-swords"></i> ACCIONES</span>';
                actionsList.forEach(function(act) {
                    actionsHtml += '<div class="rpg-card-action-item"><i class="fas fa-paw"></i> ' + act + '</div>';
                });
                actionsHtml += '</div>';
            }

            return '<div class="rpg-card rpg-card--npc-menor rpg-card--npc-' + npcType + ' ' + isHolo + '" data-card-id="' + c.id + '" style="' + borderStyle + '">' +
                '<div class="rpg-card-header">' +
                    '<div class="rpg-card-title">' + c.name + '</div>' +
                    '<div class="rpg-card-subtitle" style="color:' + (c.rank === 'SS' ? '#f59e0b' : rankColor) + '">' + subLabel + '</div>' +
                '</div>' +
                (hasImage ? '<div class="rpg-card-image" style="background-image:url(\'' + c.image_url + '\')"></div>' : '') +
                '<div class="rpg-card-body">' +
                    npcStatsHtml +
                    '<div class="rpg-card-desc">' + self.truncateDesc(c.description, c.id, c.name) + '</div>' +
                    actionsHtml +
                    rollHtml +
                '</div>' +
            '</div>';
        }

        // ── HAKI ─────────────────────────────────────────────────────────────
        if (c.card_type === 'haki') {
            var effects  = c.effects || {};
            var hakiType = (effects.haki_type || 'busoshoku').toLowerCase();
            if (hakiType === 'busshoku')   hakiType = 'busoshoku';
            if (hakiType === 'kenboshuko') hakiType = 'kenbunshoku';

            var hakiLevel = (effects.haki_level || 'basico').toLowerCase();

            var hakiTypeName = 'Busoshoku (Armamento)';
            if (hakiType === 'kenbunshoku') hakiTypeName = 'Kenbunshoku (Observación)';
            else if (hakiType === 'haoshoku') hakiTypeName = 'Haoshoku (Conquistador)';

            var typeLabel   = 'HAKI: ' + hakiTypeName.toUpperCase();
            var levelLabel  = hakiLevel.toUpperCase();
            var efectoText  = effects.efecto || c.description || 'Sin efecto específico registrado.';

            return '<div class="rpg-card rpg-card--haki rpg-card--haki-' + hakiType + ' ' + isHolo + '" data-card-id="' + c.id + '" style="' + borderStyle + '">' +
                '<div class="rpg-card-header">' +
                    '<div class="rpg-card-title">' + c.name + '</div>' +
                    '<div class="rpg-card-subtitle haki-type-label">' +
                        '<span class="haki-level-badge">[' + levelLabel + ']</span> ' + typeLabel +
                    '</div>' +
                '</div>' +
                (hasImage ? '<div class="rpg-card-image" style="background-image:url(\'' + c.image_url + '\')"></div>' : '') +
                '<div class="rpg-card-body">' +
                    (c.description ? '<div class="rpg-card-desc">' + self.truncateDesc(c.description, c.id, c.name) + '</div>' : '') +
                    '<div class="rpg-card-section rpg-card-section--efecto">' +
                        '<span class="rpg-card-section-title"><i class="fas fa-shield-halved"></i> EFECTO</span>' +
                        '<div class="rpg-card-section-text">' + self.truncateDesc(efectoText, c.id + '_ef', c.name + ' — Efecto') + '</div>' +
                    '</div>' +
                    rollHtml +
                '</div>' +
            '</div>';
        }

        // ── TÉCNICA (estándar) ───────────────────────────────────────────────
        var typeText  = c.card_type.replace('_', ' ').toUpperCase();
        var rankLabel = 'RANGO';

        var statsHtml = '';
        if (c.cost_pe !== '—' || c.execution_stat !== '' || c.dice !== '') {
            statsHtml = '<div class="rpg-card-stats-row">';
            if (c.cost_pe !== '—') statsHtml += '<div><span>COSTE</span><strong>' + c.cost_pe + '</strong></div>';
            if (c.execution_stat !== '') statsHtml += '<div><span>STAT</span><strong>' + c.execution_stat + '</strong></div>';
            if (c.dice !== '') statsHtml += '<div><span>DADOS</span><strong>' + c.dice + '</strong></div>';
            statsHtml += '</div>';
        }

        return '<div class="rpg-card ' + isHolo + '" data-card-id="' + c.id + '" style="' + borderStyle + '">' +
            '<div class="rpg-card-header">' +
                '<div class="rpg-card-title">' + c.name + '</div>' +
                '<div class="rpg-card-subtitle" style="color:' + (c.rank === 'SS' ? '#f59e0b' : rankColor) + '">[' + rankLabel + ' ' + c.rank + '] ' + typeText + ' • ' + (c.activation || '').toUpperCase() + durationText + reposoText + '</div>' +
            '</div>' +
            (hasImage ? '<div class="rpg-card-image" style="background-image:url(\'' + c.image_url + '\')"></div>' : '') +
            '<div class="rpg-card-body">' +
                tagsHtml +
                statsHtml +
                '<div class="rpg-card-desc">' + self.truncateDesc(c.description, c.id, c.name) + '</div>' +
                rollHtml +
            '</div>' +
        '</div>';
    },

    // ──────────────────────────────────────────────────────────────────────────
    // DECK DE PERSONAJE (TAB PERFIL — colapsable por tipo)
    // ──────────────────────────────────────────────────────────────────────────

    loadCharacterDeck: function(charId, container) {
        var self = this;
        fetch(this.config.baseUrl + '/game/ajax/cards_my_deck.php?character_id=' + charId)
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.ok) {
                    container.innerHTML = '<div style="text-align:center;padding:30px;color:var(--accent-rose);">Error al cargar deck: ' + (d.error ? d.error.message : 'Error desconocido') + '</div>';
                    return;
                }

                var cards = d.data;
                if (cards.length === 0) {
                    container.innerHTML =
                        '<div style="text-align:center;padding:40px;color:var(--text-muted);background:var(--bg-surface);border-radius:var(--radius-md);border:1px dashed var(--border-color);">' +
                            '<i class="fas fa-layer-group" style="font-size:40px;opacity:0.5;margin-bottom:15px;"></i>' +
                            '<h4>Deck Vacío</h4>' +
                            '<p style="font-size:13px;">Este personaje aún no tiene cartas asignadas.</p>' +
                        '</div>';
                    return;
                }

                var grouped = {};
                cards.forEach(function(c) {
                    if (!grouped[c.card_type]) grouped[c.card_type] = [];
                    grouped[c.card_type].push(c);
                });

                var typeNames = {
                    'tecnica': 'Técnicas', 'equipo': 'Equipamiento', 'akuma_no_mi': 'Akuma no Mi',
                    'haki': 'Haki', 'npc_menor': 'NPCs Menores', 'barco': 'Barcos'
                };
                var typeIcons = {
                    'tecnica':    '<i class="fas fa-fist-raised"  style="color:var(--accent-rose);"></i>',
                    'equipo':     '<i class="fas fa-shield-alt"   style="color:var(--accent-blue);"></i>',
                    'akuma_no_mi':'<i class="fas fa-apple-alt"    style="color:var(--accent-purple);"></i>',
                    'haki':       '<i class="fas fa-fire"         style="color:var(--accent-amber);"></i>',
                    'npc_menor':  '<i class="fas fa-users"        style="color:var(--accent-teal);"></i>',
                    'barco':      '<i class="fas fa-ship"         style="color:var(--accent-blue);"></i>'
                };

                var html = '';
                for (var type in grouped) {
                    if (!Object.prototype.hasOwnProperty.call(grouped, type)) continue;
                    var list     = grouped[type];
                    var icon     = typeIcons[type] || '<i class="fas fa-layer-group"></i>';
                    var name     = typeNames[type]  || type.toUpperCase();
                    var secId    = 'rpg-deck-view-' + type;

                    html += '<div class="rpg-deck-section" style="width:100%;margin-bottom:8px;">' +
                        '<div class="rpg-deck-section-header" onclick="RpgCards.toggleDeckViewSection(\'' + secId + '\',this)" style="display:flex;justify-content:space-between;align-items:center;background:var(--bg-surface);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:10px 15px;cursor:pointer;transition:all 0.2s;user-select:none;">' +
                            '<div style="font-family:var(--font-heading);font-size:13px;font-weight:700;color:var(--text-primary);display:flex;align-items:center;gap:8px;text-transform:uppercase;">' +
                                icon + ' ' + name + ' <span style="color:var(--text-secondary);font-size:11px;text-transform:none;">(' + list.length + ')</span>' +
                            '</div>' +
                            '<div class="rpg-deck-section-arrow" style="color:var(--text-secondary);transition:transform 0.2s;"><i class="fas fa-chevron-down"></i></div>' +
                        '</div>' +
                        '<div id="' + secId + '" style="display:none;gap:15px;flex-wrap:wrap;padding:15px 5px 5px 5px;width:100%;">';

                    list.forEach(function(c) {
                        var qtyBadge = '';
                        if (c.cantidad !== undefined && c.cantidad !== null) {
                            var qty = parseInt(c.cantidad);
                            if (!isNaN(qty)) {
                                var qc = qty <= 0 ? '#ef4444' : (qty <= 2 ? '#f59e0b' : '#10b981');
                                qtyBadge = '<span style="position:absolute;top:-6px;left:-6px;background:' + qc + ';color:#fff;padding:2px 7px;border-radius:10px;font-size:8px;font-weight:800;z-index:12;border:2px solid var(--bg-card);box-shadow:0 2px 6px rgba(0,0,0,0.3);">×' + qty + '</span>';
                            }
                        }
                        html += '<div class="rpg-card-wrapper" style="position:relative;display:flex;flex-direction:column;gap:8px;width:250px;">' +
                            qtyBadge +
                            self.renderCard(c) +
                        '</div>';
                    });

                    html += '</div></div>';
                }

                container.innerHTML = html;
            })
            .catch(function() {
                container.innerHTML = '<div style="text-align:center;padding:30px;color:var(--accent-rose);"><i class="fas fa-exclamation-triangle"></i> Error de conexión al cargar el deck.</div>';
            });
    },

    toggleDeckViewSection: function(sectionId, header) {
        var content = document.getElementById(sectionId);
        if (!content) return;
        if (content.style.display === 'none') {
            content.style.display = 'flex';
            header.style.borderColor = 'var(--accent-indigo)';
            header.querySelector('.rpg-deck-section-arrow').style.transform = 'rotate(180deg)';
        } else {
            content.style.display = 'none';
            header.style.borderColor = 'var(--border-color)';
            header.querySelector('.rpg-deck-section-arrow').style.transform = 'none';
        }
    },

    requestDelete: function(charId, cardId, btn) {
        if (btn.disabled) return;
        btn.disabled = true;
        var originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ...';

        var self = this;
        fetch(this.config.baseUrl + '/game/ajax/cards_request_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ character_id: charId, card_id: cardId, action: 'delete' })
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.ok) {
                btn.innerHTML = '<i class="fas fa-clock"></i> Pendiente';
                btn.style.background     = 'rgba(239,68,68,0.04)';
                btn.style.color          = 'var(--text-muted)';
                btn.style.borderColor    = 'var(--border-color)';
                btn.onmouseover = null;
                btn.onmouseout  = null;
            } else {
                btn.disabled = false;
                btn.innerHTML = originalText;
                alert(res.error.message);
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.innerHTML = originalText;
            alert('Error de conexión.');
        });
    },

    // ──────────────────────────────────────────────────────────────────────────
    // CARTAS JUGADAS EN POST (colapsadas por defecto)
    // ──────────────────────────────────────────────────────────────────────────

    loadPostCards: function() {
        var self  = this;
        var zones = document.querySelectorAll('.rpg-post-cards-zone');
        if (zones.length === 0) return;

        zones.forEach(function(zone) {
            var postId = zone.dataset.postId;
            fetch(self.config.baseUrl + '/game/ajax/cards_for_post.php?post_id=' + postId)
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (!d.ok || !d.data.length) return;

                    zone.style.display = 'block';
                    var bodyId   = 'rpg-pc-body-' + postId;
                    var arrowId  = 'rpg-pc-arrow-' + postId;
                    var hasDice  = false;

                    var html =
                        '<div onclick="RpgCards.togglePostCards(\'' + bodyId + '\',\'' + arrowId + '\')" style="display:flex;align-items:center;gap:8px;cursor:pointer;user-select:none;padding:4px 0 6px;">' +
                            '<span style="font-family:var(--font-heading);font-size:11px;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;"><i class="fas fa-layer-group"></i> Cartas Jugadas (' + d.data.length + ')</span>' +
                            '<span id="' + arrowId + '" style="color:var(--text-muted);font-size:10px;transition:transform 0.2s;"><i class="fas fa-chevron-right"></i></span>' +
                        '</div>' +
                        '<div id="' + bodyId + '" style="display:none;gap:15px;flex-wrap:wrap;padding-top:10px;border-top:1px solid var(--border-color);">';

                    d.data.forEach(function(c) {
                        html += self.renderCard(c);
                        if (c.roll_result && c.roll_result.trim()) hasDice = true;
                    });
                    html += '</div>';
                    zone.innerHTML = html;

                    if (hasDice) {
                        var postWrapper = document.getElementById('post_' + postId);
                        if (postWrapper) {
                            var editBtn = postWrapper.querySelector('a[href*="editpost.php"]');
                            if (editBtn) {
                                editBtn.style.opacity = '0.3';
                                editBtn.style.pointerEvents = 'none';
                                editBtn.title = 'No se puede editar: contiene tiradas de dados.';
                            }
                        }
                    }
                });
        });
    },

    togglePostCards: function(bodyId, arrowId) {
        var body  = document.getElementById(bodyId);
        var arrow = document.getElementById(arrowId);
        if (!body) return;
        if (body.style.display === 'none') {
            body.style.display = 'flex';
            if (arrow) arrow.style.transform = 'rotate(90deg)';
        } else {
            body.style.display = 'none';
            if (arrow) arrow.style.transform = 'none';
        }
    },

    // ──────────────────────────────────────────────────────────────────────────
    // ATTACHMENTS (armas / munición / acción mascota)
    // ──────────────────────────────────────────────────────────────────────────

    buildAttachmentsHtml: function(c, weapons, ammo) {
        if (c.card_type === 'npc_menor') {
            var effects  = c.effects || {};
            var npcType  = (effects.npc_mascota_type || 'npc').toLowerCase();
            if (npcType === 'mascota') {
                var rawActions  = effects.acciones || [];
                var actionsList = typeof rawActions === 'string'
                    ? rawActions.split('\n').map(function(a){ return a.trim(); }).filter(Boolean)
                    : rawActions;

                if (actionsList && actionsList.length > 0) {
                    var html = '<div class="rpg-attachment-field" style="display:flex;flex-direction:column;gap:4px;text-align:left;">' +
                        '<label style="font-weight:700;color:var(--text-secondary);font-size:10px;text-transform:uppercase;">Acción de la Mascota</label>' +
                        '<select class="rpg-attachment-action textbox" style="width:100%;font-size:11px;padding:4px 8px;border-radius:4px;background:var(--bg-card);border:1px solid var(--border-color);color:var(--text-primary);">' +
                            '<option value="">-- Selecciona una acción --</option>';
                    actionsList.forEach(function(act) {
                        html += '<option value="' + act + '">' + act + '</option>';
                    });
                    html += '</select></div>';
                    return html;
                }
            }
        }

        if (!c.dice) return '';
        var armMatches  = c.dice.match(/\[ARMA\]/g);
        var armCount    = armMatches  ? armMatches.length  : 0;
        var muniMatches = c.dice.match(/\[MUNICION\]/g);
        var muniCount   = muniMatches ? muniMatches.length : 0;

        if (armCount === 0 && muniCount === 0) return '';

        var html = '';

        for (var i = 0; i < armCount; i++) {
            html += '<div class="rpg-attachment-field" style="display:flex;flex-direction:column;gap:4px;text-align:left;">' +
                '<label style="font-weight:700;color:var(--text-secondary);font-size:10px;text-transform:uppercase;">Arma #' + (i + 1) + '</label>' +
                '<select class="rpg-attachment-weapon textbox" data-index="' + i + '" style="width:100%;font-size:11px;padding:4px 8px;border-radius:4px;background:var(--bg-card);border:1px solid var(--border-color);color:var(--text-primary);">' +
                    '<option value="">-- Ninguna --</option>';
            weapons.forEach(function(w) {
                html += '<option value="' + w.id + '">' + w.name + ' (' + (w.dice || 'Sin dados') + ')</option>';
            });
            html += '</select></div>';
        }

        for (var j = 0; j < muniCount; j++) {
            html += '<div class="rpg-attachment-field" style="display:flex;flex-direction:column;gap:4px;text-align:left;">' +
                '<label style="font-weight:700;color:var(--text-secondary);font-size:10px;text-transform:uppercase;">Munición #' + (j + 1) + '</label>' +
                '<select class="rpg-attachment-ammo textbox" data-index="' + j + '" style="width:100%;font-size:11px;padding:4px 8px;border-radius:4px;background:var(--bg-card);border:1px solid var(--border-color);color:var(--text-primary);">' +
                    '<option value="">-- Ninguna --</option>';
            ammo.forEach(function(a) {
                html += '<option value="' + a.id + '">' + a.name + ' (' + (a.dice || 'Sin dados') + ')</option>';
            });
            html += '</select></div>';
        }

        return html;
    },

    // ──────────────────────────────────────────────────────────────────────────
    // PAYLOAD (cartas seleccionadas)
    // ──────────────────────────────────────────────────────────────────────────

    updatePlayedCardsInput: function() {
        var input = document.getElementById('rpg_played_cards');
        if (!input) return;

        var payload     = [];
        var selectedEls = document.querySelectorAll('.rpg-selectable-card.selected');

        selectedEls.forEach(function(el) {
            var cid           = parseInt(el.dataset.cid);
            var container     = el.closest('.rpg-selectable-card-container');
            var attachmentsCt = container ? container.querySelector('.rpg-card-attachments') : null;

            if (attachmentsCt && attachmentsCt.style.display !== 'none') {
                var weaponSelects = attachmentsCt.querySelectorAll('.rpg-attachment-weapon');
                var ammoSelects   = attachmentsCt.querySelectorAll('.rpg-attachment-ammo');
                var actionSelect  = attachmentsCt.querySelector('.rpg-attachment-action');

                var weapons = [];
                weaponSelects.forEach(function(sel) { if (sel.value) weapons.push(parseInt(sel.value)); });

                var ammo = [];
                ammoSelects.forEach(function(sel) { if (sel.value) ammo.push(parseInt(sel.value)); });

                var item     = { card_id: cid };
                var hasExtra = false;

                if (weapons.length > 0) { item.weapons = weapons; hasExtra = true; }
                if (ammo.length    > 0) { item.ammo    = ammo;    hasExtra = true; }
                if (actionSelect && actionSelect.value) { item.selected_action = actionSelect.value; hasExtra = true; }

                payload.push(hasExtra ? item : cid);
            } else {
                payload.push(cid);
            }
        });

        input.value = JSON.stringify(payload);
    },

    // ──────────────────────────────────────────────────────────────────────────
    // SELECTOR EN EDITOR (Quick Reply / New Reply)
    // ──────────────────────────────────────────────────────────────────────────

    initCardSelector: function() {
        var selector  = document.getElementById('rpg-card-selector');
        var toggleBtn = document.getElementById('rpg-card-toggle-btn');
        var panel     = document.getElementById('rpg-card-deck-panel');
        var input     = document.getElementById('rpg_played_cards');

        if (!selector || !toggleBtn || !panel || !input) return;

        var self          = this;
        var selectedCards = [];

        var tidInput = document.querySelector('input[name="tid"]');
        var tid      = tidInput ? parseInt(tidInput.value) : 0;

        var url = tid > 0
            ? this.config.baseUrl + '/game/ajax/cards_my_deck.php?thread_id=' + tid
            : this.config.baseUrl + '/game/ajax/cards_my_deck.php';

        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.ok || !d.data.length) return;
                selector.classList.remove('is-hidden');

                var meta = d.meta;

                // Detectar armas y munición por effects (más preciso que por tags)
                var weapons = d.data.filter(function(w) {
                    if (w.card_type !== 'equipo') return false;
                    var eff = w.effects || {};
                    return (eff.equipo_type || '').toLowerCase() === 'arma';
                });
                var ammo = d.data.filter(function(a) {
                    if (a.card_type !== 'equipo') return false;
                    if (!a.tags) return false;
                    return a.tags.some(function(t) {
                        var clean = t.replace(/[\[\]]/g, '').trim().toUpperCase();
                        return clean.includes('MUNICION') || clean.includes('AMMO');
                    });
                });

                var grouped = {};
                d.data.forEach(function(c) {
                    if (!grouped[c.card_type]) grouped[c.card_type] = [];
                    grouped[c.card_type].push(c);
                });

                var typeNames = {
                    'tecnica':    'Técnicas',
                    'equipo':     'Equipamiento',
                    'akuma_no_mi':'Akuma no Mi',
                    'haki':       'Haki',
                    'npc_menor':  'NPCs Menores',
                    'barco':      'Barcos'
                };
                var typeIcons = {
                    'tecnica':    '<i class="fas fa-fist-raised"  style="color:var(--accent-rose);"></i>',
                    'equipo':     '<i class="fas fa-shield-alt"   style="color:var(--accent-blue);"></i>',
                    'akuma_no_mi':'<i class="fas fa-apple-alt"    style="color:var(--accent-purple);"></i>',
                    'haki':       '<i class="fas fa-fire"         style="color:var(--accent-amber);"></i>',
                    'npc_menor':  '<i class="fas fa-users"        style="color:var(--accent-teal);"></i>',
                    'barco':      '<i class="fas fa-ship"         style="color:var(--accent-blue);"></i>'
                };

                var html = '';

                for (var type in grouped) {
                    if (!Object.prototype.hasOwnProperty.call(grouped, type)) continue;
                    var list = grouped[type];
                    var icon = typeIcons[type] || '<i class="fas fa-layer-group"></i>';
                    var name = typeNames[type] || type.toUpperCase();

                    html +=
                        '<div class="rpg-deck-section" style="width:100%;">' +
                            '<div class="rpg-deck-section-header" onclick="RpgCards.toggleDeckSection(\'' + type + '\',this)" style="display:flex;justify-content:space-between;align-items:center;background:var(--bg-surface);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:10px 15px;cursor:pointer;transition:all 0.2s;user-select:none;">' +
                                '<div class="rpg-deck-section-title" style="font-family:var(--font-heading);font-size:13px;font-weight:700;color:var(--text-primary);display:flex;align-items:center;gap:8px;text-transform:uppercase;">' +
                                    icon + ' ' + name + ' <span style="color:var(--text-secondary);font-size:11px;text-transform:none;">(' + list.length + ')</span>' +
                                '</div>' +
                                '<div class="rpg-deck-section-arrow" style="color:var(--text-secondary);transition:transform 0.2s;"><i class="fas fa-chevron-down"></i></div>' +
                            '</div>' +
                            '<div id="rpg-deck-section-content-' + type + '" class="rpg-deck-section-content" style="display:none;gap:12px;flex-wrap:wrap;padding:15px 5px 5px 5px;width:100%;">';

                    list.forEach(function(c) {
                        var cooldown = c.reposo   || 0;
                        var duration = c.duracion || 0;

                        var isDisabled  = false;
                        var disabledAttr= '';
                        var badgeHtml   = '';
                        var overlayHtml = '';

                        // Badge cantidad
                        var qtyBadge = '';
                        if (c.cantidad !== undefined && c.cantidad !== null) {
                            var qty = parseInt(c.cantidad);
                            if (!isNaN(qty)) {
                                if (qty <= 0) {
                                    isDisabled   = true;
                                    disabledAttr = 'data-disabled="true"';
                                    overlayHtml  =
                                        '<div style="position:absolute;inset:0;background:rgba(15,23,42,0.82);border-radius:inherit;display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:10;color:#fff;text-align:center;padding:15px;border:2px solid var(--accent-rose);pointer-events:none;">' +
                                            '<i class="fas fa-box-open" style="font-size:24px;color:var(--accent-rose);margin-bottom:8px;"></i>' +
                                            '<div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--accent-rose);">Agotado</div>' +
                                        '</div>';
                                } else {
                                    var qc = qty <= 2 ? '#f59e0b' : '#10b981';
                                    qtyBadge = '<span style="position:absolute;top:-6px;left:-6px;background:' + qc + ';color:#fff;padding:2px 7px;border-radius:10px;font-size:8px;font-weight:800;z-index:12;border:2px solid var(--bg-card);box-shadow:0 2px 6px rgba(0,0,0,0.3);">×' + qty + '</span>';
                                }
                            }
                        }

                        if (meta) {
                            var lastPlayed = meta.last_played_turns[c.id] || 0;
                            if (lastPlayed > 0) {
                                var elapsed = meta.total_posts - lastPlayed;

                                if (cooldown > 0 && elapsed < cooldown) {
                                    isDisabled   = true;
                                    disabledAttr = 'data-disabled="true"';
                                    var remaining = cooldown - elapsed;
                                    overlayHtml =
                                        '<div class="rpg-card-cooldown-overlay" style="position:absolute;inset:0;background:rgba(15,23,42,0.75);border-radius:inherit;display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:10;color:#fff;text-align:center;padding:15px;border:2px solid var(--accent-rose);pointer-events:none;">' +
                                            '<i class="fas fa-hourglass-half" style="font-size:24px;color:var(--accent-rose);margin-bottom:8px;animation:pulse 1.5s infinite;"></i>' +
                                            '<div style="font-family:var(--font-heading);font-weight:800;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:var(--accent-rose);">En Reposo</div>' +
                                            '<div style="font-size:10px;opacity:0.9;margin-top:4px;">Falta ' + remaining + ' turno' + (remaining > 1 ? 's' : '') + '</div>' +
                                        '</div>';
                                }

                                if (duration > 0 && elapsed + 1 < duration) {
                                    var activeTurns = duration - (elapsed + 1);
                                    badgeHtml =
                                        '<span class="rpg-card-active-badge" style="position:absolute;top:-6px;right:-6px;background:var(--accent-emerald);color:#fff;padding:2px 7px;border-radius:10px;font-size:8px;font-weight:800;text-transform:uppercase;z-index:12;border:2px solid var(--bg-card);box-shadow:0 2px 6px rgba(16,185,129,0.4);pointer-events:none;">' +
                                            '<i class="fas fa-circle" style="font-size:5px;margin-right:3px;vertical-align:middle;"></i> ACTIVA (' + activeTurns + ')' +
                                        '</span>';
                                }
                            }
                        }

                        var rankColor2  = self.config.rankColors[c.rank] || self.config.rankColors['C'];
                        var borderStyle2 = c.rank === 'SS'
                            ? 'border:2px solid transparent;background-image:linear-gradient(var(--bg-card),var(--bg-card)),' + rankColor2 + ';background-origin:border-box;background-clip:content-box,border-box;'
                            : 'border:2px solid ' + rankColor2 + ';';

                        var opacityStyle    = isDisabled ? 'opacity:0.85;filter:grayscale(10%);' : '';
                        var attachmentsHtml = self.buildAttachmentsHtml(c, weapons, ammo);

                        html +=
                            '<div class="rpg-selectable-card-container" style="display:flex;flex-direction:column;gap:8px;width:250px;">' +
                                '<div class="rpg-selectable-card" data-cid="' + c.id + '" ' + disabledAttr + ' style="position:relative;cursor:' + (isDisabled ? 'not-allowed' : 'pointer') + ';transition:transform 0.2s,box-shadow 0.2s;width:100%;' + opacityStyle + '">' +
                                    badgeHtml +
                                    qtyBadge +
                                    overlayHtml +
                                    self.renderCard(c) +
                                '</div>' +
                                '<div class="rpg-card-attachments" style="display:none;background:var(--bg-surface);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:10px;font-size:11px;flex-direction:column;gap:8px;">' +
                                    attachmentsHtml +
                                '</div>' +
                            '</div>';
                    });

                    html += '</div></div>';
                }

                panel.innerHTML = html;

                // Panel de modificadores
                self._injectModifierPanel(selector);

                // Toggle del panel de cartas
                toggleBtn.addEventListener('change', function(e) {
                    panel.classList[e.target.checked ? 'add' : 'remove']('is-visible');
                });
                if (toggleBtn.checked) panel.classList.add('is-visible');

                // Click en carta seleccionable
                document.querySelectorAll('.rpg-selectable-card').forEach(function(el) {
                    el.addEventListener('click', function() {
                        if (el.dataset.disabled === 'true') return;

                        var cid     = el.dataset.cid;
                        var idx     = selectedCards.indexOf(cid);
                        var ct      = el.closest('.rpg-selectable-card-container');
                        var attDiv  = ct ? ct.querySelector('.rpg-card-attachments') : null;

                        if (idx === -1) {
                            selectedCards.push(cid);
                            el.classList.add('selected');
                            if (attDiv && attDiv.innerHTML.trim() !== '') attDiv.style.display = 'flex';
                        } else {
                            selectedCards.splice(idx, 1);
                            el.classList.remove('selected');
                            if (attDiv) attDiv.style.display = 'none';
                        }
                        self.updatePlayedCardsInput();
                    });
                });

                // Cambios en selects de attachments
                panel.addEventListener('change', function(e) {
                    if (e.target.classList.contains('rpg-attachment-weapon') ||
                        e.target.classList.contains('rpg-attachment-ammo')   ||
                        e.target.classList.contains('rpg-attachment-action')) {
                        self.updatePlayedCardsInput();
                    }
                });
            });
    },

    // ──────────────────────────────────────────────────────────────────────────
    // TOGGLE SECCIONES DEL SELECTOR
    // ──────────────────────────────────────────────────────────────────────────

    toggleDeckSection: function(type, header) {
        var content = document.getElementById('rpg-deck-section-content-' + type);
        if (!content) return;

        if (content.style.display === 'none') {
            content.style.display = 'flex';
            header.style.borderColor = 'var(--accent-indigo)';
            header.querySelector('.rpg-deck-section-arrow').style.transform = 'rotate(180deg)';
        } else {
            content.style.display = 'none';
            header.style.borderColor = 'var(--border-color)';
            header.querySelector('.rpg-deck-section-arrow').style.transform = 'none';
        }
    }
};

document.addEventListener('DOMContentLoaded', function() {
    RpgCards.init();
});
