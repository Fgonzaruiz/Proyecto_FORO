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

    _cardRankAttr: function(c) {
        return ' data-rank="' + (c.rank || 'C') + '"';
    },

    isConsumibleCard: function(c) {
        if (!c) return false;
        if (c.is_consumible === true) return true;
        if (c.is_consumible === false) return false;
        if (c.card_type !== 'equipo') return false;
        var ef = c.effects || {};
        if (String(ef.equipo_type || '').toLowerCase() === 'util') return true;
        var tags = c.tags || [];
        if (!Array.isArray(tags)) return false;
        return tags.some(function(t) {
            var u = String(t).toUpperCase();
            return u === 'CONSUMIBLE' || u === 'MUNICION' || u === 'AMMO';
        });
    },

    _qtyBadgeHtml: function(c) {
        if (!this.isConsumibleCard(c)) return '';
        if (c.cantidad === undefined || c.cantidad === null) return '';
        var qty = parseInt(c.cantidad, 10);
        if (isNaN(qty)) return '';
        var qtyClass = qty <= 2 ? 'rpg-card-qty-badge--low' : 'rpg-card-qty-badge--ok';
        return '<span class="rpg-card-qty-badge ' + qtyClass + '">×' + qty + '</span>';
    },

    _consumibleDisabledState: function(c) {
        if (!this.isConsumibleCard(c)) return null;
        var qty = parseInt(c.cantidad, 10);
        if (isNaN(qty) || qty > 0) return null;
        return {
            isDisabled: true,
            disabledAttr: 'data-disabled="true" data-exhausted-disabled="true"',
            overlayHtml:
                '<div class="rpg-card-empty-overlay">' +
                    '<i class="fas fa-box-open"></i>' +
                    '<div class="rpg-card-empty-overlay__title">Agotado</div>' +
                '</div>'
        };
    },

    _cardImageHtml: function(c) {
        if (!c.image_url || !c.image_url.trim()) return '';
        var url = String(c.image_url).replace(/'/g, '%27');
        return '<div class="rpg-card-image rpg-card-image--has-img" style="--card-img:url(\'' + url + '\')"></div>';
    },

    hideCardTextModal: function() {
        var modal = document.getElementById('rpg-text-modal');
        if (modal) modal.classList.remove('is-open');
    },

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
            '... <span class="rpg-ver-mas-link" onclick="RpgCards.showCardTextModal(\'' + uid + '\')">[Ver más]</span>';
    },

    showCardTextModal: function(uid) {
        var data = this._cardDataCache[uid];
        if (!data) return;
        var modal = document.getElementById('rpg-text-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'rpg-text-modal';
            modal.className = 'rpg-text-modal';
            modal.innerHTML =
                '<div class="rpg-text-modal__dialog">' +
                    '<div class="rpg-text-modal__header">' +
                        '<div id="rpg-text-modal-title" class="rpg-text-modal__title"></div>' +
                        '<button type="button" class="rpg-text-modal__close" onclick="RpgCards.hideCardTextModal()">&times;</button>' +
                    '</div>' +
                    '<div id="rpg-text-modal-body" class="rpg-text-modal__body"></div>' +
                '</div>';
            document.body.appendChild(modal);
            modal.addEventListener('click', function(e) { if (e.target === modal) RpgCards.hideCardTextModal(); });
        }
        document.getElementById('rpg-text-modal-title').textContent = data.name;
        document.getElementById('rpg-text-modal-body').textContent = data.text;
        modal.classList.add('is-open');
    },

    // ──────────────────────────────────────────────────────────────────────────
    // PANEL DE MODIFICADORES
    // ──────────────────────────────────────────────────────────────────────────

    addModifier: function() {
        /* legacy no-op: use tab Estadísticas steppers */
    },

    removeModifier: function(stat) {
        if (this._modifiers) delete this._modifiers[stat];
        if (typeof RpgStats !== 'undefined') RpgStats.syncSteppers();
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
            var modClass = val > 0 ? 'rpg-modifier-chip--buff' : 'rpg-modifier-chip--debuff';
            var sign = val > 0 ? '+' : '';
            list.innerHTML += '<span class="rpg-modifier-chip ' + modClass + '" onclick="RpgCards.removeModifier(\'' + stat + '\')" title="Click para eliminar">' +
                stat.toUpperCase() + ' ' + sign + val + ' <i class="fas fa-times"></i></span>';
        }
    },

    _updateModifiersInput: function() {
        var input = document.getElementById('rpg_modifiers');
        if (!input) return;
        input.value = JSON.stringify(this._modifiers || {});
    },

    adjustStatMod: function(stat, delta) {
        if (!stat || !delta) return;
        if (!this._modifiers) this._modifiers = {};
        this._modifiers[stat] = (this._modifiers[stat] || 0) + delta;
        if (this._modifiers[stat] === 0) delete this._modifiers[stat];
        if (typeof RpgStats !== 'undefined') RpgStats.syncSteppers();
        this._renderModifierList();
        this._updateModifiersInput();
    },

    _injectModifierPanel: function(container) {
        /* deprecated: modifiers moved to tab Estadísticas (RpgStats) */
    },

    // ──────────────────────────────────────────────────────────────────────────
    // RENDERIZADO DE CARTAS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Genera el HTML de una carta (diseño premium)
     */
    renderCard: function(c) {
        var isHolo   = c.rank === 'SS' ? 'rpg-card--holo' : '';
        var rankAttr = this._cardRankAttr(c);
        var self     = this;

        var tagsHtml = '';
        if (c.tags && c.tags.length > 0) {
            tagsHtml = '<div class="rpg-card-tags">';
            c.tags.forEach(function(t) {
                var cleanedTag = t.replace(/[\[\]]/g, '').trim().toUpperCase();
                if (cleanedTag) {
                    tagsHtml += '<span class="rpg-card-tag">' + cleanedTag + '</span>';
                }
            });
            tagsHtml += '</div>';
        }

        var rollHtml = '';
        if (c.roll_result && c.roll_result.trim() !== '') {
            var rollLabel = c.card_type === 'npc_menor' ? 'Acción Ejecutada' : 'Resultado de Tirada';
            var rollIcon  = c.card_type === 'npc_menor' ? 'fas fa-paw' : 'fas fa-dice';
            rollHtml = '<div class="rpg-card-roll-result">' +
                '<div class="rpg-card-roll-result__label"><i class="' + rollIcon + '"></i> ' + rollLabel + '</div>' +
                '<div class="rpg-card-roll-result__text">' + c.roll_result.replace(/\n/g, '<br>') + '</div>' +
                '</div>';
        }

        var durationText = (c.duracion && c.duracion > 0) ? ' • DURACIÓN: ' + c.duracion + 'T' : '';
        var reposoText   = (c.reposo   && c.reposo   > 0) ? ' • REPOSO: '   + c.reposo   + 'T' : '';

        // ── AKUMA NO MI ──────────────────────────────────────────────────────
        if (c.card_type === 'akuma_no_mi') {
            var effects   = c.effects || {};
            var akumaType = (effects.akuma_type || 'paramecia').toLowerCase();
            var typeLabel = 'AKUMA NO MI: ' + akumaType.toUpperCase();

            var efectos     = effects.efectos     || 'Sin efectos específicos registrados.';
            var limitaciones= effects.limitaciones|| 'Sin limitaciones específicas registradas.';
            var debilidades = effects.debilidades || 'Sin debilidades específicas registradas.';

            return '<div class="rpg-card rpg-card--akuma rpg-card--akuma-' + akumaType + ' ' + isHolo + '" data-card-id="' + c.id + '"' + rankAttr + '>' +
                '<div class="rpg-card-header">' +
                    '<div class="rpg-card-title">' + c.name + '</div>' +
                    '<div class="rpg-card-subtitle akuma-type-label">' + typeLabel + '</div>' +
                '</div>' +
                self._cardImageHtml(c) +
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

            return '<div class="rpg-card rpg-card--equipo rpg-card--equipo-' + eqType + ' ' + isHolo + '" data-card-id="' + c.id + '"' + rankAttr + '>' +
                '<div class="rpg-card-header">' +
                    '<div class="rpg-card-title">' + c.name + '</div>' +
                    '<div class="rpg-card-subtitle">[CALIDAD ' + c.rank + '] ' + eqTypeLabel + '</div>' +
                '</div>' +
                self._cardImageHtml(c) +
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
                    '<div class="rpg-card-ship-stat rpg-card-ship-stat--wide"><span><i class="fas fa-shield-halved"></i> RESISTENCIA</span><strong>' + resistencia + '</strong></div>' +
                '</div>';

            return '<div class="rpg-card rpg-card--barco ' + isHolo + '" data-card-id="' + c.id + '"' + rankAttr + '>' +
                '<div class="rpg-card-header">' +
                    '<div class="rpg-card-title">' + c.name + '</div>' +
                    '<div class="rpg-card-subtitle">[TIER ' + tier + '] BARCO • ' + bType.toUpperCase() + '</div>' +
                '</div>' +
                self._cardImageHtml(c) +
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

            return '<div class="rpg-card rpg-card--npc-menor rpg-card--npc-' + npcType + ' ' + isHolo + '" data-card-id="' + c.id + '"' + rankAttr + '>' +
                '<div class="rpg-card-header">' +
                    '<div class="rpg-card-title">' + c.name + '</div>' +
                    '<div class="rpg-card-subtitle">' + subLabel + '</div>' +
                '</div>' +
                self._cardImageHtml(c) +
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

            return '<div class="rpg-card rpg-card--haki rpg-card--haki-' + hakiType + ' ' + isHolo + '" data-card-id="' + c.id + '"' + rankAttr + '>' +
                '<div class="rpg-card-header">' +
                    '<div class="rpg-card-title">' + c.name + '</div>' +
                    '<div class="rpg-card-subtitle haki-type-label">' +
                        '<span class="haki-level-badge">[' + levelLabel + ']</span> ' + typeLabel +
                    '</div>' +
                '</div>' +
                self._cardImageHtml(c) +
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
        var execCost = parseInt(c.execution_cost || 0);
        if (c.cost_pe !== '—' || c.execution_stat !== '' || c.dice !== '' || execCost > 0) {
            statsHtml = '<div class="rpg-card-stats-row">';
            if (c.cost_pe !== '—') statsHtml += '<div><span>COSTE</span><strong>' + c.cost_pe + '</strong></div>';
            if (execCost > 0) statsHtml += '<div><span>P.A</span><strong>' + execCost + '</strong></div>';
            if (c.execution_stat !== '') statsHtml += '<div><span>STAT</span><strong>' + c.execution_stat + '</strong></div>';
            if (c.dice !== '') statsHtml += '<div><span>DADOS</span><strong>' + c.dice + '</strong></div>';
            statsHtml += '</div>';
        }

        return '<div class="rpg-card ' + isHolo + '" data-card-id="' + c.id + '"' + rankAttr + '>' +
            '<div class="rpg-card-header">' +
                '<div class="rpg-card-title">' + c.name + '</div>' +
                '<div class="rpg-card-subtitle">[' + rankLabel + ' ' + c.rank + '] ' + typeText + ' • ' + (c.activation || '').toUpperCase() + durationText + reposoText + '</div>' +
            '</div>' +
            self._cardImageHtml(c) +
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
                    container.innerHTML = '<div class="rpg-deck-error">Error al cargar deck: ' + (d.error ? d.error.message : 'Error desconocido') + '</div>';
                    return;
                }

                var cards = d.data;
                if (cards.length === 0) {
                    container.innerHTML =
                        '<div class="rpg-deck-empty">' +
                            '<i class="fas fa-layer-group rpg-deck-empty__icon"></i>' +
                            '<h4>Deck Vacío</h4>' +
                            '<p class="rpg-deck-empty__text">Este personaje aún no tiene cartas asignadas.</p>' +
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
                    'tecnica':    '<i class="fas fa-fist-raised rpg-deck-icon--tecnica"></i>',
                    'equipo':     '<i class="fas fa-shield-alt rpg-deck-icon--equipo"></i>',
                    'akuma_no_mi':'<i class="fas fa-apple-alt rpg-deck-icon--akuma_no_mi"></i>',
                    'haki':       '<i class="fas fa-fire rpg-deck-icon--haki"></i>',
                    'npc_menor':  '<i class="fas fa-users rpg-deck-icon--npc_menor"></i>',
                    'barco':      '<i class="fas fa-ship rpg-deck-icon--barco"></i>'
                };

                var html = '';
                for (var type in grouped) {
                    if (!Object.prototype.hasOwnProperty.call(grouped, type)) continue;
                    var list     = grouped[type];
                    var icon     = typeIcons[type] || '<i class="fas fa-layer-group"></i>';
                    var name     = typeNames[type]  || type.toUpperCase();
                    var secId    = 'rpg-deck-view-' + type;

                    html += '<div class="rpg-deck-section">' +
                        '<div class="rpg-deck-section-header" onclick="RpgCards.toggleDeckViewSection(\'' + secId + '\',this)">' +
                            '<div class="rpg-deck-section-title">' +
                                icon + ' ' + name + ' <span class="rpg-deck-section-count">(' + list.length + ')</span>' +
                            '</div>' +
                            '<div class="rpg-deck-section-arrow"><i class="fas fa-chevron-down"></i></div>' +
                        '</div>' +
                        '<div id="' + secId + '" class="rpg-deck-section-content">';

                    list.forEach(function(c) {
                        html += '<div class="rpg-card-wrapper">' +
                            self._qtyBadgeHtml(c) +
                            self.renderCard(c) +
                        '</div>';
                    });

                    html += '</div></div>';
                }

                container.innerHTML = html;
            })
            .catch(function() {
                container.innerHTML = '<div class="rpg-deck-error"><i class="fas fa-exclamation-triangle"></i> Error de conexión al cargar el deck.</div>';
            });
    },

    toggleDeckViewSection: function(sectionId, header) {
        var content = document.getElementById(sectionId);
        if (!content) return;
        var isOpen = content.classList.toggle('is-open');
        header.classList.toggle('is-open', isOpen);
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
                btn.classList.add('is-pending');
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
            self.renderSinglePostZone(zone);
        });
    },

    reloadPostCards: function(postId) {
        var zone = document.querySelector('.rpg-post-cards-zone[data-post-id="' + postId + '"]');
        if (zone) {
            this.renderSinglePostZone(zone);
        }
    },

    revealHiddenAction: function(postId, index, btn) {
        if (btn.disabled) return;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ...';

        var self = this;
        var csrfToken = window.GAME_CSRF || '';
        fetch(this.config.baseUrl + '/game/ajax/reveal_hidden_action.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-Mybb-Post-Key': csrfToken
            },
            body: JSON.stringify({ post_id: postId, index: index, my_post_key: csrfToken })
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.ok) {
                self.reloadPostCards(postId);
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-eye"></i> Mostrar Oculto ' + index;
                alert(res.error.message);
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-eye"></i> Mostrar Oculto ' + index;
            alert('Error de conexión.');
        });
    },

    renderSinglePostZone: function(zone) {
        var self = this;
        var postId = zone.dataset.postId;
        fetch(self.config.baseUrl + '/game/ajax/cards_for_post.php?post_id=' + postId)
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.ok) return;

                var mods = d.modifications;
                var hasMods = false;
                if (mods) {
                    if (mods.pv_change !== 0 || mods.pe_change !== 0) {
                        hasMods = true;
                    }
                    if (mods.stat_mods) {
                        for (var key in mods.stat_mods) {
                            if (mods.stat_mods[key] !== 0) {
                                hasMods = true;
                                break;
                            }
                        }
                    }
                }

                var hasNormalCards = d.data && d.data.length > 0;
                var hasHiddenActions = d.hidden_actions && d.hidden_actions.length > 0;

                if (!hasNormalCards && !hasMods && !hasHiddenActions) {
                    zone.classList.remove('is-visible');
                    zone.innerHTML = '';
                    return;
                }

                zone.classList.add('is-visible');
                var html = '';
                var hasDice = false;

                // 1. Renderizar Modificaciones
                if (hasMods) {
                    html += '<div class="rpg-post-mods-container">';
                    html += '<span class="rpg-post-mods-title"><i class="fas fa-sliders-h"></i> Modificaciones:</span>';
                    
                    if (mods.pv_change > 0) {
                        html += '<span class="rpg-post-mod-chip rpg-post-mod-chip--hp-heal"><i class="fas fa-heart"></i> +' + mods.pv_change + ' PV</span>';
                    } else if (mods.pv_change < 0) {
                        html += '<span class="rpg-post-mod-chip rpg-post-mod-chip--hp-damage"><i class="fas fa-heart-broken"></i> ' + mods.pv_change + ' PV</span>';
                    }
                    
                    if (mods.pe_change > 0) {
                        html += '<span class="rpg-post-mod-chip rpg-post-mod-chip--pe-gain"><i class="fas fa-bolt"></i> +' + mods.pe_change + ' PE</span>';
                    } else if (mods.pe_change < 0) {
                        html += '<span class="rpg-post-mod-chip rpg-post-mod-chip--pe-spend"><i class="fas fa-bolt"></i> ' + mods.pe_change + ' PE</span>';
                    }

                    if (mods.stat_mods) {
                        var statLabels = {
                            'fue': 'FUE',
                            'agi': 'AGI',
                            'des': 'DES',
                            'int': 'INT',
                            'esp': 'ESP',
                            'inst': 'INST'
                        };
                        for (var statKey in mods.stat_mods) {
                            var val = parseInt(mods.stat_mods[statKey]);
                            if (val !== 0) {
                                var statLabel = statLabels[statKey] || statKey.toUpperCase();
                                if (val > 0) {
                                    html += '<span class="rpg-post-mod-chip rpg-post-mod-chip--stat-buff"><i class="fas fa-arrow-up"></i> ' + statLabel + ' +' + val + '</span>';
                                } else {
                                    html += '<span class="rpg-post-mod-chip rpg-post-mod-chip--stat-debuff"><i class="fas fa-arrow-down"></i> ' + statLabel + ' ' + val + '</span>';
                                }
                            }
                        }
                    }
                    html += '</div>';
                }

                // 2. Renderizar Cartas normales
                if (hasNormalCards) {
                    var bodyId   = 'rpg-pc-body-' + postId;
                    var arrowId  = 'rpg-pc-arrow-' + postId;
                    var toggleId = 'rpg-pc-toggle-' + postId;

                    html +=
                        '<div id="' + toggleId + '" class="rpg-post-cards-toggle" onclick="RpgCards.togglePostCards(\'' + bodyId + '\',\'' + arrowId + '\',\'' + toggleId + '\')">' +
                            '<span class="rpg-post-cards-toggle__label"><i class="fas fa-layer-group"></i> Cartas Jugadas (' + d.data.length + ')</span>' +
                            '<span id="' + arrowId + '" class="rpg-post-cards-toggle__arrow"><i class="fas fa-chevron-right"></i></span>' +
                        '</div>' +
                        '<div id="' + bodyId + '" class="rpg-post-cards-body">';

                    d.data.forEach(function(c) {
                        html += self.renderCard(c);
                        if (c.roll_result && c.roll_result.trim()) hasDice = true;
                    });
                    html += '</div>';
                }

                // 3. Renderizar Acciones Ocultas
                if (hasHiddenActions) {
                    d.hidden_actions.forEach(function(act) {
                        var idx = act.index;
                        var haBodyId   = 'rpg-ha-body-' + postId + '-' + idx;
                        var haArrowId  = 'rpg-ha-arrow-' + postId + '-' + idx;
                        var haToggleId = 'rpg-ha-toggle-' + postId + '-' + idx;
                        
                        var statusLabel = act.is_revealed 
                            ? ' <span class="rpg-hidden-badge-inline rpg-hidden-badge-inline--revealed"><i class="fas fa-eye"></i> Revelado</span>'
                            : ' <span class="rpg-hidden-badge-inline rpg-hidden-badge-inline--hidden"><i class="fas fa-eye-slash"></i> Oculto</span>';
                            
                        html +=
                            '<div id="' + haToggleId + '" class="rpg-post-cards-toggle" onclick="RpgCards.togglePostCards(\'' + haBodyId + '\',\'' + haArrowId + '\',\'' + haToggleId + '\')">' +
                                '<span class="rpg-post-cards-toggle__label"><i class="fas fa-eye-slash"></i> Acción Oculta #' + idx + statusLabel + '</span>' +
                                '<span id="' + haArrowId + '" class="rpg-post-cards-toggle__arrow"><i class="fas fa-chevron-right"></i></span>' +
                            '</div>' +
                            '<div id="' + haBodyId + '" class="rpg-post-hidden-action-body">';
                            
                        if (act.description) {
                            html += '<div class="rpg-hidden-desc">' + act.description.replace(/\n/g, '<br>') + '</div>';
                        }
                        
                        if (act.cards && act.cards.length > 0) {
                            html += '<div class="rpg-post-hidden-cards-grid">';
                            act.cards.forEach(function(c) {
                                html += self.renderCard(c);
                                if (c.roll_result && c.roll_result.trim()) hasDice = true;
                            });
                            html += '</div>';
                        }
                        
                        if (act.can_reveal && !act.is_revealed) {
                            html += '<div class="rpg-hidden-action-reveal-btn-wrap">' +
                                        '<button class="rpg-btn-reveal-hidden" onclick="RpgCards.revealHiddenAction(' + postId + ', ' + idx + ', this)">' +
                                            '<i class="fas fa-eye"></i> Mostrar Oculto ' + idx + 
                                        '</button>' +
                                    '</div>';
                        }
                        
                        html += '</div>';
                    });
                }

                zone.innerHTML = html;

                if (hasDice) {
                    var postWrapper = document.getElementById('post_' + postId);
                    if (postWrapper) {
                        var editBtn = postWrapper.querySelector('a[href*="editpost.php"]');
                        if (editBtn) {
                            editBtn.classList.add('is-edit-locked');
                            editBtn.title = 'No se puede editar: contiene tiradas de dados.';
                        }
                    }
                }
            });
    },

    togglePostCards: function(bodyId, arrowId, toggleId) {
        var body  = document.getElementById(bodyId);
        var arrow = document.getElementById(arrowId);
        var toggle = toggleId ? document.getElementById(toggleId) : null;
        if (!body) return;
        var isOpen = body.classList.toggle('is-open');
        if (arrow) arrow.classList.toggle('is-open', isOpen);
        if (toggle) toggle.classList.toggle('is-open', isOpen);
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
                    var html = '<div class="rpg-attachment-field">' +
                        '<label class="rpg-attachment-label">Acción de la Mascota</label>' +
                        '<select class="rpg-attachment-action textbox rpg-attachment-select">' +
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
            html += '<div class="rpg-attachment-field">' +
                '<label class="rpg-attachment-label">Arma #' + (i + 1) + '</label>' +
                '<select class="rpg-attachment-weapon textbox rpg-attachment-select" data-index="' + i + '">' +
                    '<option value="">-- Ninguna --</option>';
            weapons.forEach(function(w) {
                html += '<option value="' + w.id + '">' + w.name + ' (' + (w.dice || 'Sin dados') + ')</option>';
            });
            html += '</select></div>';
        }

        for (var j = 0; j < muniCount; j++) {
            html += '<div class="rpg-attachment-field">' +
                '<label class="rpg-attachment-label">Munición #' + (j + 1) + '</label>' +
                '<select class="rpg-attachment-ammo textbox rpg-attachment-select" data-index="' + j + '">' +
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

            if (attachmentsCt && attachmentsCt.classList.contains('is-visible')) {
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

    getCardExecutionCost: function(cid) {
        if (!RpgCards.deckData) return 0;
        var card = RpgCards.deckData.find(function(c) { return c.id === parseInt(cid); });
        return card ? parseInt(card.execution_cost || 0) : 0;
    },

    updatePaUsage: function() {
        var maxPa = RpgStats._maxPa || 10;
        
        // Sum normal cards
        var normalPa = 0;
        document.querySelectorAll('#rpg-card-deck-panel .rpg-selectable-card.selected').forEach(function(el) {
            var cid = el.dataset.cid;
            normalPa += RpgCards.getCardExecutionCost(cid);
        });
        
        // Sum hidden actions cards
        var hiddenPa = 0;
        document.querySelectorAll('.rpg-hidden-selectable-card.selected').forEach(function(el) {
            var cid = el.dataset.cid;
            hiddenPa += RpgCards.getCardExecutionCost(cid);
        });
        
        var totalSpent = normalPa + hiddenPa;
        var remainingPa = maxPa - totalSpent;
        
        // Update display
        var displayEl = document.getElementById('rpg-stat-pa-display');
        var inputEl = document.getElementById('rpg-stat-pa-input');
        if (displayEl) displayEl.textContent = remainingPa;
        if (inputEl) inputEl.value = remainingPa;
        
        // Update unified panel indicators (Jugar Cartas and Acciones Ocultas)
        document.querySelectorAll('.rpg-pa-current-val').forEach(function(el) {
            el.textContent = remainingPa;
        });
        document.querySelectorAll('.rpg-pa-max-val').forEach(function(el) {
            el.textContent = maxPa;
        });
        
        // Disable cards that exceed remaining PA
        // Normal cards
        document.querySelectorAll('#rpg-card-deck-panel .rpg-selectable-card').forEach(function(el) {
            var cid = el.dataset.cid;
            var isSelected = el.classList.contains('selected');
            var cost = RpgCards.getCardExecutionCost(cid);
            var originDisabled = el.dataset.cooldownDisabled === 'true' || el.dataset.exhaustedDisabled === 'true';
            
            if (originDisabled) return;
            
            if (!isSelected && cost > remainingPa) {
                el.classList.add('is-disabled-pa');
                el.dataset.disabledPa = 'true';
            } else {
                el.classList.remove('is-disabled-pa');
                el.dataset.disabledPa = 'false';
            }
        });
        
        // Hidden cards
        document.querySelectorAll('.rpg-hidden-selectable-card').forEach(function(el) {
            var cid = el.dataset.cid;
            var isSelected = el.classList.contains('selected');
            var cost = RpgCards.getCardExecutionCost(cid);
            var originDisabled = el.dataset.cooldownDisabled === 'true' || el.dataset.exhaustedDisabled === 'true';
            
            if (originDisabled) return;
            
            if (!isSelected && cost > remainingPa) {
                el.classList.add('is-disabled-pa');
                el.dataset.disabledPa = 'true';
            } else {
                el.classList.remove('is-disabled-pa');
                el.dataset.disabledPa = 'false';
            }
        });
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
                RpgCards.deckData = d.data; // Cache deck
                self._metaData = d.meta; // Cache meta
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
                
                RpgCards.weapons = weapons;
                RpgCards.ammo = ammo;

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
                    'tecnica':    '<i class="fas fa-fist-raised rpg-deck-icon--tecnica"></i>',
                    'equipo':     '<i class="fas fa-shield-alt rpg-deck-icon--equipo"></i>',
                    'akuma_no_mi':'<i class="fas fa-apple-alt rpg-deck-icon--akuma_no_mi"></i>',
                    'haki':       '<i class="fas fa-fire rpg-deck-icon--haki"></i>',
                    'npc_menor':  '<i class="fas fa-users rpg-deck-icon--npc_menor"></i>',
                    'barco':      '<i class="fas fa-ship rpg-deck-icon--barco"></i>'
                };

                var html = '';

                for (var type in grouped) {
                    if (!Object.prototype.hasOwnProperty.call(grouped, type)) continue;
                    var list = grouped[type];
                    var icon = typeIcons[type] || '<i class="fas fa-layer-group"></i>';
                    var name = typeNames[type] || type.toUpperCase();

                    html +=
                        '<div class="rpg-deck-section">' +
                            '<div class="rpg-deck-section-header" onclick="RpgCards.toggleDeckSection(\'' + type + '\',this)">' +
                                '<div class="rpg-deck-section-title">' +
                                    icon + ' ' + name + ' <span class="rpg-deck-section-count">(' + list.length + ')</span>' +
                                '</div>' +
                                '<div class="rpg-deck-section-arrow"><i class="fas fa-chevron-down"></i></div>' +
                            '</div>' +
                            '<div id="rpg-deck-section-content-' + type + '" class="rpg-deck-section-content">';

                    list.forEach(function(c) {
                        var cooldown = c.reposo   || 0;
                        var duration = c.duracion || 0;

                        var isDisabled  = false;
                        var disabledAttr= '';
                        var badgeHtml   = '';
                        var overlayHtml = '';

                        var qtyBadge = self._qtyBadgeHtml(c);
                        var consumibleState = self._consumibleDisabledState(c);
                        if (consumibleState) {
                            isDisabled   = consumibleState.isDisabled;
                            disabledAttr = consumibleState.disabledAttr;
                            overlayHtml  = consumibleState.overlayHtml;
                        }

                        if (meta) {
                            var lastPlayed = meta.last_played_turns[c.id] || 0;
                            if (lastPlayed > 0) {
                                var elapsed = meta.total_posts - lastPlayed;

                                if (cooldown > 0 && elapsed < cooldown) {
                                    isDisabled   = true;
                                    disabledAttr = 'data-disabled="true" data-cooldown-disabled="true"';
                                    var remaining = cooldown - elapsed;
                                    overlayHtml =
                                        '<div class="rpg-card-cooldown-overlay">' +
                                            '<i class="fas fa-hourglass-half"></i>' +
                                            '<div class="rpg-card-cooldown-overlay__title">En Reposo</div>' +
                                            '<div class="rpg-card-cooldown-overlay__sub">Falta ' + remaining + ' turno' + (remaining > 1 ? 's' : '') + '</div>' +
                                        '</div>';
                                }

                                if (duration > 0 && elapsed + 1 < duration) {
                                    var activeTurns = duration - (elapsed + 1);
                                    badgeHtml =
                                        '<span class="rpg-card-active-badge">' +
                                            '<i class="fas fa-circle"></i> ACTIVA (' + activeTurns + ')' +
                                        '</span>';
                                }
                            }
                        }

                        var disabledClass = isDisabled ? ' is-disabled' : '';
                        var attachmentsHtml = self.buildAttachmentsHtml(c, weapons, ammo);

                        html +=
                            '<div class="rpg-selectable-card-container">' +
                                '<div class="rpg-selectable-card' + disabledClass + '" data-cid="' + c.id + '" ' + disabledAttr + '>' +
                                    badgeHtml +
                                    qtyBadge +
                                    overlayHtml +
                                    self.renderCard(c) +
                                '</div>' +
                                '<div class="rpg-card-attachments">' +
                                    attachmentsHtml +
                                '</div>' +
                            '</div>';
                    });

                    html += '</div></div>';
                }

                panel.innerHTML = html;

                // Toggle del panel de cartas
                toggleBtn.addEventListener('change', function(e) {
                    panel.classList[e.target.checked ? 'add' : 'remove']('is-visible');
                });
                if (toggleBtn.checked) panel.classList.add('is-visible');

                // Click en carta seleccionable
                document.querySelectorAll('.rpg-selectable-card').forEach(function(el) {
                    el.addEventListener('click', function() {
                        if (el.dataset.disabled === 'true' || el.dataset.disabledPa === 'true') return;

                        var cid     = el.dataset.cid;
                        var idx     = selectedCards.indexOf(cid);
                        var ct      = el.closest('.rpg-selectable-card-container');
                        var attDiv  = ct ? ct.querySelector('.rpg-card-attachments') : null;

                        if (idx === -1) {
                            selectedCards.push(cid);
                            el.classList.add('selected');
                            if (attDiv && attDiv.innerHTML.trim() !== '') attDiv.classList.add('is-visible');
                        } else {
                            selectedCards.splice(idx, 1);
                            el.classList.remove('selected');
                            if (attDiv) attDiv.classList.remove('is-visible');
                        }
                        self.updatePlayedCardsInput();
                        self.updatePaUsage();
                        if (typeof RpgHiddenActions !== 'undefined') {
                            RpgHiddenActions.syncCardAvailability();
                        }
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
                self.updatePaUsage();
            });
    },

    // ──────────────────────────────────────────────────────────────────────────
    // TOGGLE SECCIONES DEL SELECTOR
    // ──────────────────────────────────────────────────────────────────────────

    toggleDeckSection: function(type, header) {
        var content = document.getElementById('rpg-deck-section-content-' + type);
        if (!content) return;
        var isOpen = content.classList.toggle('is-open');
        header.classList.toggle('is-open', isOpen);
    }
};

const RpgStats = {
    _maxPv: 0,
    _maxPe: 0,
    _maxPa: 10,
    _baseUrl: '',

    _clampVital: function(kind, val) {
        var max = kind === 'pe' ? this._maxPe : this._maxPv;
        var ceiling = max > 0 ? max : 9999;
        return Math.max(0, Math.min(ceiling, val));
    },

    _parseFormula: function(base, formula) {
        var cleaned = String(formula || '').replace(/\s/g, '');
        if (!cleaned) return base;
        var parts = cleaned.match(/[+-]\d+/g);
        if (!parts) return base;
        var val = base;
        for (var i = 0; i < parts.length; i++) {
            val += parseInt(parts[i], 10);
        }
        return val;
    },

    _updateVitalDisplay: function(kind) {
        var input = document.getElementById(kind === 'pe' ? 'rpg-stat-pe-input' : 'rpg-stat-pv-input');
        var display = document.getElementById(kind === 'pe' ? 'rpg-stat-pe-display' : 'rpg-stat-pv-display');
        if (!input || !display) return;
        display.textContent = input.value;
    },

    _applyFormula: function(kind) {
        var formulaEl = document.getElementById(kind === 'pe' ? 'rpg-stat-pe-formula' : 'rpg-stat-pv-formula');
        var input = document.getElementById(kind === 'pe' ? 'rpg-stat-pe-input' : 'rpg-stat-pv-input');
        if (!input) return;
        var current = parseInt(input.value, 10);
        if (isNaN(current)) current = kind === 'pe' ? this._maxPe : this._maxPv;
        var next = this._clampVital(kind, this._parseFormula(current, formulaEl ? formulaEl.value : ''));
        input.value = next;
        if (formulaEl) formulaEl.value = '';
        this._updateVitalDisplay(kind);
        this.updateHiddenVitals();
    },

    init: function() {
        var panel = document.getElementById('rpg-stats-panel');
        if (!panel) return;

        var self = this;
        if (RpgCards && RpgCards.config && RpgCards.config.baseUrl) {
            this._baseUrl = RpgCards.config.baseUrl;
        } else {
            this._baseUrl = window.location.origin;
        }

        ['pv', 'pe'].forEach(function(kind) {
            var formulaEl = document.getElementById(kind === 'pe' ? 'rpg-stat-pe-formula' : 'rpg-stat-pv-formula');
            if (!formulaEl) return;
            formulaEl.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    self._applyFormula(kind);
                }
            });
            formulaEl.addEventListener('blur', function() {
                if (formulaEl.value.trim()) self._applyFormula(kind);
            });
        });

        panel.querySelectorAll('.rpg-stat-stepper').forEach(function(row) {
            row.querySelectorAll('.rpg-stat-stepper__btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    RpgCards.adjustStatMod(row.dataset.stat, parseInt(btn.dataset.delta, 10));
                });
            });
        });

        this.loadState();
    },

    loadState: function() {
        var tidInput = document.querySelector('input[name="tid"]');
        var tid = tidInput ? parseInt(tidInput.value, 10) : 0;
        var url = this._baseUrl + '/game/ajax/thread_pj_state.php?thread_id=' + (tid || 0);

        fetch(url, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.ok || !d.data) return;
                var data = d.data;
                RpgStats._maxPv = data.max_pv || 0;
                RpgStats._maxPe = data.max_pe || 0;
                RpgStats._maxPa = data.max_pa || 10;

                var pvMaxEl = document.getElementById('rpg-stat-pv-max');
                var peMaxEl = document.getElementById('rpg-stat-pe-max');
                var paMaxEl = document.getElementById('rpg-stat-pa-max');
                if (pvMaxEl) pvMaxEl.textContent = RpgStats._maxPv;
                if (peMaxEl) peMaxEl.textContent = RpgStats._maxPe;
                if (paMaxEl) paMaxEl.textContent = RpgStats._maxPa;

                var pvInput = document.getElementById('rpg-stat-pv-input');
                var peInput = document.getElementById('rpg-stat-pe-input');
                if (pvInput) pvInput.value = data.current_pv;
                if (peInput) peInput.value = data.current_pe;

                RpgStats._updateVitalDisplay('pv');
                RpgStats._updateVitalDisplay('pe');

                RpgCards._modifiers = (data.stat_mods && !Array.isArray(data.stat_mods)) ? data.stat_mods : {};
                RpgStats.syncSteppers();
                RpgCards._renderModifierList();
                RpgCards._updateModifiersInput();
                RpgStats.updateHiddenVitals();
                RpgCards.updatePaUsage();
            })
            .catch(function() {});
    },

    updateHiddenVitals: function() {
        var pvInput = document.getElementById('rpg-stat-pv-input');
        var peInput = document.getElementById('rpg-stat-pe-input');
        var pvHidden = document.getElementById('rpg_thread_pv');
        var peHidden = document.getElementById('rpg_thread_pe');
        if (pvHidden && pvInput) pvHidden.value = pvInput.value;
        if (peHidden && peInput) peHidden.value = peInput.value;
    },

    syncSteppers: function() {
        document.querySelectorAll('.rpg-stat-stepper').forEach(function(row) {
            var stat = row.dataset.stat;
            var valEl = row.querySelector('.rpg-stat-stepper__val');
            if (!valEl || !stat) return;
            var val = (RpgCards._modifiers && RpgCards._modifiers[stat]) ? RpgCards._modifiers[stat] : 0;
            valEl.textContent = val > 0 ? '+' + val : String(val);
        });
    }
};

const RpgHiddenActions = {
    actions: [],
    
    init: function() {
        var listContainer = document.getElementById('rpg-hidden-actions-list');
        if (!listContainer) return;
        listContainer.innerHTML = '';
        this.actions = [];
        this.serialize();
    },
    
    addAction: function() {
        var listContainer = document.getElementById('rpg-hidden-actions-list');
        if (!listContainer) return;
        
        var idx = this.actions.length + 1;
        this.actions.push({
            description: '',
            cards: []
        });
        
        var itemEl = document.createElement('div');
        itemEl.className = 'rpg-hidden-action-item';
        itemEl.dataset.idx = idx;
        
        itemEl.innerHTML = 
            '<div class="rpg-hidden-action-item-header">' +
                '<span class="rpg-hidden-action-title">Acción Oculta #' + idx + '</span>' +
                '<button type="button" class="rpg-btn-remove-hidden" onclick="RpgHiddenActions.removeAction(' + idx + ')">' +
                    '<i class="fas fa-trash-alt"></i>' +
                '</button>' +
            '</div>' +
            '<div class="rpg-hidden-action-body">' +
                '<textarea class="rpg-hidden-action-desc textbox" placeholder="Describe aquí la acción oculta o tirada secreta..." oninput="RpgHiddenActions.serialize()"></textarea>' +
                '<div class="rpg-hidden-action-cards-toggle-wrap">' +
                    '<button type="button" class="rpg-btn-toggle-cards" onclick="RpgHiddenActions.toggleCardsPanel(' + idx + ', this)">' +
                        '<i class="fas fa-layer-group"></i> Jugar Cartas en este Oculto (<span class="rpg-action-card-count">0</span>)' +
                    '</button>' +
                '</div>' +
                '<div class="rpg-hidden-action-deck-panel collapsed" id="rpg-hidden-deck-' + idx + '">' +
                '</div>' +
                '<div class="rpg-hidden-action-summary-chips"></div>' +
            '</div>';
            
        listContainer.appendChild(itemEl);
        
        // Render the deck accordion for this action
        var deckPanel = itemEl.querySelector('#rpg-hidden-deck-' + idx);
        this.renderDeckForAction(deckPanel, idx);
        
        this.serialize();
        this.syncCardAvailability();
    },
    
    removeAction: function(idxToRemove) {
        var listContainer = document.getElementById('rpg-hidden-actions-list');
        if (!listContainer) return;
        
        // Remove item from DOM
        var itemEl = listContainer.querySelector('.rpg-hidden-action-item[data-idx="' + idxToRemove + '"]');
        if (itemEl) itemEl.remove();
        
        // Re-index remaining action elements in DOM
        var items = listContainer.querySelectorAll('.rpg-hidden-action-item');
        var newActions = [];
        var self = this;
        
        items.forEach(function(item, index) {
            var newIdx = index + 1;
            item.dataset.idx = newIdx;
            
            // Update title text
            var titleEl = item.querySelector('.rpg-hidden-action-title');
            if (titleEl) titleEl.textContent = 'Acción Oculta #' + newIdx;
            
            // Update buttons and handlers
            var removeBtn = item.querySelector('.rpg-btn-remove-hidden');
            if (removeBtn) {
                removeBtn.setAttribute('onclick', 'RpgHiddenActions.removeAction(' + newIdx + ')');
            }
            
            var toggleBtn = item.querySelector('.rpg-btn-toggle-cards');
            if (toggleBtn) {
                toggleBtn.setAttribute('onclick', 'RpgHiddenActions.toggleCardsPanel(' + newIdx + ', this)');
            }
            
            var deckPanel = item.querySelector('.rpg-hidden-action-deck-panel');
            if (deckPanel) {
                deckPanel.id = 'rpg-hidden-deck-' + newIdx;
                // Update dataset on selectable cards inside this deck
                deckPanel.querySelectorAll('.rpg-hidden-selectable-card').forEach(function(card) {
                    card.dataset.actionIdx = newIdx;
                });
                
                // Update accordion section content IDs
                deckPanel.querySelectorAll('.rpg-deck-section-header').forEach(function(header) {
                    var oldOnclick = header.getAttribute('onclick');
                    var typeMatch = oldOnclick.match(/'([^']+)'/);
                    if (typeMatch) {
                        var type = typeMatch[1];
                        header.setAttribute('onclick', 'RpgHiddenActions.toggleDeckSection(' + newIdx + ', \'' + type + '\', this)');
                    }
                });
                
                deckPanel.querySelectorAll('.rpg-deck-section-content').forEach(function(content) {
                    var oldId = content.id;
                    var type = oldId.split('-').pop();
                    content.id = 'rpg-deck-section-content-hidden-' + newIdx + '-' + type;
                });
            }
            
            newActions.push({
                description: item.querySelector('.rpg-hidden-action-desc').value,
                cards: [] // Will be populated by serialize()
            });
        });
        
        this.actions = newActions;
        
        this.serialize();
        this.syncCardAvailability();
        RpgCards.updatePaUsage();
    },
    
    toggleCardsPanel: function(actionIdx, btn) {
        var panel = document.getElementById('rpg-hidden-deck-' + actionIdx);
        if (!panel) return;
        var isCollapsed = panel.classList.toggle('collapsed');
        btn.classList.toggle('active', !isCollapsed);
    },
    
    toggleDeckSection: function(actionIdx, type, header) {
        var content = document.getElementById('rpg-deck-section-content-hidden-' + actionIdx + '-' + type);
        if (!content) return;
        var isOpen = content.classList.toggle('is-open');
        header.classList.toggle('is-open', isOpen);
    },
    
    renderDeckForAction: function(container, actionIdx) {
        if (!RpgCards.deckData || !RpgCards.deckData.length) {
            container.innerHTML = '<div class="rpg-no-cards-msg">No hay cartas disponibles.</div>';
            return;
        }
        
        var self = this;
        var grouped = {};
        RpgCards.deckData.forEach(function(c) {
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
            'tecnica':    '<i class="fas fa-fist-raised rpg-deck-icon--tecnica"></i>',
            'equipo':     '<i class="fas fa-shield-alt rpg-deck-icon--equipo"></i>',
            'akuma_no_mi':'<i class="fas fa-apple-alt rpg-deck-icon--akuma_no_mi"></i>',
            'haki':       '<i class="fas fa-fire rpg-deck-icon--haki"></i>',
            'npc_menor':  '<i class="fas fa-users rpg-deck-icon--npc_menor"></i>',
            'barco':      '<i class="fas fa-ship rpg-deck-icon--barco"></i>'
        };
        
        var html = '';
        for (var type in grouped) {
            if (!Object.prototype.hasOwnProperty.call(grouped, type)) continue;
            var list = grouped[type];
            var icon = typeIcons[type] || '<i class="fas fa-layer-group"></i>';
            var name = typeNames[type] || type.toUpperCase();
            var secId = 'rpg-deck-section-content-hidden-' + actionIdx + '-' + type;
            
            html +=
                '<div class="rpg-deck-section">' +
                    '<div class="rpg-deck-section-header" onclick="RpgHiddenActions.toggleDeckSection(' + actionIdx + ', \'' + type + '\', this)">' +
                        '<div class="rpg-deck-section-title">' +
                            icon + ' ' + name + ' <span class="rpg-deck-section-count">(' + list.length + ')</span>' +
                        '</div>' +
                        '<div class="rpg-deck-section-arrow"><i class="fas fa-chevron-down"></i></div>' +
                    '</div>' +
                    '<div id="' + secId + '" class="rpg-deck-section-content">';
                    
            list.forEach(function(c) {
                var cooldown = c.reposo   || 0;
                var duration = c.duracion || 0;
                
                var isDisabled  = false;
                var disabledAttr= '';
                var badgeHtml   = '';
                var overlayHtml = '';
                
                var qtyBadge = RpgCards._qtyBadgeHtml(c);
                var consumibleState = RpgCards._consumibleDisabledState(c);
                if (consumibleState) {
                    isDisabled   = consumibleState.isDisabled;
                    disabledAttr = 'data-disabled="true" data-exhausted-disabled="true"';
                    overlayHtml  = consumibleState.overlayHtml;
                }
                
                var meta = RpgCards._metaData;
                if (meta) {
                    var lastPlayed = meta.last_played_turns[c.id] || 0;
                    if (lastPlayed > 0) {
                        var elapsed = meta.total_posts - lastPlayed;
                        if (cooldown > 0 && elapsed < cooldown) {
                            isDisabled   = true;
                            disabledAttr = 'data-disabled="true" data-cooldown-disabled="true"';
                            var remaining = cooldown - elapsed;
                            overlayHtml =
                                '<div class="rpg-card-cooldown-overlay">' +
                                    '<i class="fas fa-hourglass-half"></i>' +
                                    '<div class="rpg-card-cooldown-overlay__title">En Reposo</div>' +
                                    '<div class="rpg-card-cooldown-overlay__sub">Falta ' + remaining + ' T</div>' +
                                '</div>';
                        }
                        if (duration > 0 && elapsed + 1 < duration) {
                            var activeTurns = duration - (elapsed + 1);
                            badgeHtml =
                                '<span class="rpg-card-active-badge">' +
                                    '<i class="fas fa-circle"></i> ACTIVA (' + activeTurns + ')' +
                                '</span>';
                        }
                    }
                }
                
                var disabledClass = isDisabled ? ' is-disabled' : '';
                var attachmentsHtml = RpgCards.buildAttachmentsHtml(c, RpgCards.weapons || [], RpgCards.ammo || []);
                
                html +=
                    '<div class="rpg-selectable-card-container">' +
                        '<div class="rpg-hidden-selectable-card' + disabledClass + '" data-cid="' + c.id + '" data-action-idx="' + actionIdx + '" ' + disabledAttr + '>' +
                            badgeHtml +
                            qtyBadge +
                            overlayHtml +
                            RpgCards.renderCard(c) +
                        '</div>' +
                        '<div class="rpg-card-attachments">' +
                            attachmentsHtml +
                        '</div>' +
                    '</div>';
            });
            
            html += '</div></div>';
        }
        
        container.innerHTML = html;
        
        // Add click event listeners
        container.querySelectorAll('.rpg-hidden-selectable-card').forEach(function(cardEl) {
            cardEl.addEventListener('click', function() {
                if (cardEl.dataset.disabled === 'true' || cardEl.dataset.disabledPa === 'true') return;
                
                var cid = cardEl.dataset.cid;
                var ct = cardEl.closest('.rpg-selectable-card-container');
                var attDiv = ct ? ct.querySelector('.rpg-card-attachments') : null;
                
                if (cardEl.classList.contains('selected')) {
                    cardEl.classList.remove('selected');
                    if (attDiv) attDiv.classList.remove('is-visible');
                } else {
                    cardEl.classList.add('selected');
                    if (attDiv && attDiv.innerHTML.trim() !== '') attDiv.classList.add('is-visible');
                }
                self.updateSummaryChips(actionIdx);
                self.serialize();
                self.syncCardAvailability();
                RpgCards.updatePaUsage();
            });
        });
        
        // Attachments change listener
        container.addEventListener('change', function(e) {
            if (e.target.classList.contains('rpg-attachment-weapon') ||
                e.target.classList.contains('rpg-attachment-ammo')   ||
                e.target.classList.contains('rpg-attachment-action')) {
                self.serialize();
            }
        });
    },
    
    updateSummaryChips: function(actionIdx) {
        var itemEl = document.querySelector('.rpg-hidden-action-item[data-idx="' + actionIdx + '"]');
        if (!itemEl) return;
        var summaryCt = itemEl.querySelector('.rpg-hidden-action-summary-chips');
        var cardCountSpan = itemEl.querySelector('.rpg-action-card-count');
        if (!summaryCt) return;
        
        summaryCt.innerHTML = '';
        var selectedCards = itemEl.querySelectorAll('.rpg-hidden-selectable-card.selected');
        cardCountSpan.textContent = selectedCards.length;
        
        selectedCards.forEach(function(cardEl) {
            var cid = parseInt(cardEl.dataset.cid);
            var cardObj = RpgCards.deckData.find(function(c) { return c.id === cid; });
            if (cardObj) {
                var chip = document.createElement('span');
                chip.className = 'rpg-card-summary-chip';
                chip.innerHTML = '<i class="fas fa-info-circle"></i> ' + cardObj.name + ' <span class="rpg-card-summary-chip-rank">' + cardObj.rank + '</span>';
                summaryCt.appendChild(chip);
            }
        });
    },
    
    serialize: function() {
        var input = document.getElementById('rpg_hidden_actions');
        if (!input) return;
        
        var payload = [];
        var items = document.querySelectorAll('.rpg-hidden-action-item');
        
        items.forEach(function(itemEl) {
            var idx = parseInt(itemEl.dataset.idx);
            var descText = itemEl.querySelector('.rpg-hidden-action-desc').value;
            
            var actionCards = [];
            var selectedCards = itemEl.querySelectorAll('.rpg-hidden-selectable-card.selected');
            
            selectedCards.forEach(function(cardEl) {
                var cid = parseInt(cardEl.dataset.cid);
                var container = cardEl.closest('.rpg-selectable-card-container');
                var attachmentsCt = container ? container.querySelector('.rpg-card-attachments') : null;
                
                if (attachmentsCt && attachmentsCt.classList.contains('is-visible')) {
                    var weaponSelects = attachmentsCt.querySelectorAll('.rpg-attachment-weapon');
                    var ammoSelects = attachmentsCt.querySelectorAll('.rpg-attachment-ammo');
                    var actionSelect = attachmentsCt.querySelector('.rpg-attachment-action');
                    
                    var weapons = [];
                    weaponSelects.forEach(function(sel) { if (sel.value) weapons.push(parseInt(sel.value)); });
                    
                    var ammo = [];
                    ammoSelects.forEach(function(sel) { if (sel.value) ammo.push(parseInt(sel.value)); });
                    
                    var cardItem = { card_id: cid };
                    var hasExtra = false;
                    
                    if (weapons.length > 0) { cardItem.weapons = weapons; hasExtra = true; }
                    if (ammo.length > 0) { cardItem.ammo = ammo; hasExtra = true; }
                    if (actionSelect && actionSelect.value) { cardItem.selected_action = actionSelect.value; hasExtra = true; }
                    
                    actionCards.push(hasExtra ? cardItem : cid);
                } else {
                    actionCards.push(cid);
                }
            });
            
            payload.push({
                index: idx,
                description: descText,
                cards: actionCards
            });
        });
        
        input.value = JSON.stringify(payload);
    },
    
    syncCardAvailability: function() {
        var normalSelected = [];
        document.querySelectorAll('#rpg-card-deck-panel .rpg-selectable-card.selected').forEach(function(el) {
            normalSelected.push(parseInt(el.dataset.cid));
        });
        
        var hiddenSelectedByAction = {};
        var allHiddenSelected = [];
        
        document.querySelectorAll('.rpg-hidden-selectable-card.selected').forEach(function(el) {
            var actionIdx = parseInt(el.dataset.actionIdx);
            var cid = parseInt(el.dataset.cid);
            if (!hiddenSelectedByAction[actionIdx]) hiddenSelectedByAction[actionIdx] = [];
            hiddenSelectedByAction[actionIdx].push(cid);
            allHiddenSelected.push(cid);
        });
        
        document.querySelectorAll('#rpg-card-deck-panel .rpg-selectable-card').forEach(function(el) {
            var cid = parseInt(el.dataset.cid);
            var originDisabled = el.dataset.cooldownDisabled === 'true' || el.dataset.exhaustedDisabled === 'true';
            
            if (originDisabled) return;
            
            if (allHiddenSelected.indexOf(cid) !== -1) {
                el.classList.add('is-disabled');
                el.dataset.disabled = 'true';
            } else {
                el.classList.remove('is-disabled');
                el.dataset.disabled = 'false';
            }
        });
        
        document.querySelectorAll('.rpg-hidden-selectable-card').forEach(function(el) {
            var cid = parseInt(el.dataset.cid);
            var actionIdx = parseInt(el.dataset.actionIdx);
            var originDisabled = el.dataset.cooldownDisabled === 'true' || el.dataset.exhaustedDisabled === 'true';
            
            if (originDisabled) return;
            
            var selectedInNormal = normalSelected.indexOf(cid) !== -1;
            var selectedInOtherHidden = false;
            
            for (var aIdx in hiddenSelectedByAction) {
                if (parseInt(aIdx) !== actionIdx) {
                    if (hiddenSelectedByAction[aIdx].indexOf(cid) !== -1) {
                        selectedInOtherHidden = true;
                        break;
                    }
                }
            }
            
            if (selectedInNormal || selectedInOtherHidden) {
                el.classList.add('is-disabled');
                el.dataset.disabled = 'true';
            } else {
                el.classList.remove('is-disabled');
                el.dataset.disabled = 'false';
            }
        });
    }
};

document.addEventListener('DOMContentLoaded', function() {
    RpgCards.init();
    RpgStats.init();
    if (typeof RpgHiddenActions !== 'undefined') {
        RpgHiddenActions.init();
    }
});
