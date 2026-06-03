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

    init: function() {
        const nav = document.getElementById('pj-nav-submenu');
        if (nav && nav.dataset.base) {
            this.config.baseUrl = nav.dataset.base;
        } else {
            // Fallback para encontrar la bburl si no está el nav
            this.config.baseUrl = window.location.origin + (window.location.pathname.split('/')[1] === 'foro' ? '/foro' : ''); // Adaptar según entorno
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

    /**
     * Genera el HTML de una carta (diseño premium)
     */
    renderCard: function(c) {
        const rankColor = this.config.rankColors[c.rank] || this.config.rankColors['C'];
        const isHolo = c.rank === 'SS' ? 'rpg-card--holo' : '';
        const hasImage = c.image_url && c.image_url.trim() !== '';
        
        let tagsHtml = '';
        if (c.tags && c.tags.length > 0) {
            tagsHtml = '<div class="rpg-card-tags">';
            c.tags.forEach(t => {
                const cleanedTag = t.replace(/[\[\]]/g, '').trim().toUpperCase();
                if (cleanedTag) {
                    tagsHtml += `<span class="rpg-card-tag" style="color:${rankColor}; border-color:${rankColor}">${cleanedTag}</span>`;
                }
            });
            tagsHtml += '</div>';
        }

        let rollHtml = '';
        if (c.roll_result && c.roll_result.trim() !== '') {
            let rollLabel = 'Resultado de Tirada';
            let rollIcon = 'fas fa-dice';
            if (c.card_type === 'npc_menor') {
                rollLabel = 'Acción Ejecutada';
                rollIcon = 'fas fa-paw';
            }
            rollHtml = `
                <div style="background: rgba(16, 185, 129, 0.1); border-left: 3px solid #10b981; padding: 10px; margin-top: 10px; border-radius: 4px;">
                    <div style="font-size: 10px; font-weight: bold; color: #10b981; margin-bottom: 3px; text-transform: uppercase;"><i class="${rollIcon}"></i> ${rollLabel}</div>
                    <div style="font-size: 13px; color: var(--text-primary);">${c.roll_result}</div>
                </div>
            `;
        }

        const borderStyle = c.rank === 'SS' ? 'border: 2px solid transparent; background-image: linear-gradient(var(--bg-card), var(--bg-card)), ' + rankColor + '; background-origin: border-box; background-clip: content-box, border-box;' : `border: 2px solid ${rankColor};`;
        
        const durationText = (c.duracion && c.duracion > 0) ? ` • DURACIÓN: ${c.duracion}T` : '';
        const reposoText = (c.reposo && c.reposo > 0) ? ` • REPOSO: ${c.reposo}T` : '';

        // --- 1. RENDER AKUMA NO MI ---
        if (c.card_type === 'akuma_no_mi') {
            const effects = c.effects || {};
            const akumaType = (effects.akuma_type || 'paramecia').toLowerCase();
            const typeLabel = 'AKUMA NO MI: ' + akumaType.toUpperCase();
            
            const efectos = effects.efectos || 'Sin efectos específicos registrados.';
            const limitaciones = effects.limitaciones || 'Sin limitaciones específicas registradas.';
            const debilidades = effects.debilidades || 'Sin debilidades específicas registradas.';
            
            const imageStyle = hasImage ? `background-image: url('${c.image_url}'); aspect-ratio: 1 / 1; height: 216px; background-size: cover; background-position: center; width: 100%; border-bottom: 1px solid var(--border-color);` : '';

            return `
                <div class="rpg-card rpg-card--akuma rpg-card--akuma-${akumaType} ${isHolo}" data-card-id="${c.id}">
                    <div class="rpg-card-header">
                        <div class="rpg-card-title">${c.name}</div>
                        <div class="rpg-card-subtitle akuma-type-label">${typeLabel}</div>
                    </div>
                    ${hasImage ? `<div class="rpg-card-image" style="${imageStyle}"></div>` : ''}
                    <div class="rpg-card-body">
                        <div class="rpg-card-desc">${c.description}</div>
                        
                        <div class="rpg-card-section rpg-card-section--efectos">
                            <span class="rpg-card-section-title"><i class="fas fa-wand-magic-sparkles"></i> EFECTOS</span>
                            <div class="rpg-card-section-text">${efectos}</div>
                        </div>
                        <div class="rpg-card-section rpg-card-section--limitaciones">
                            <span class="rpg-card-section-title"><i class="fas fa-shield-halved"></i> LIMITACIONES</span>
                            <div class="rpg-card-section-text">${limitaciones}</div>
                        </div>
                        <div class="rpg-card-section rpg-card-section--debilidades">
                            <span class="rpg-card-section-title"><i class="fas fa-skull-crossbones"></i> DEBILIDADES</span>
                            <div class="rpg-card-section-text">${debilidades}</div>
                        </div>
                        
                        ${rollHtml}
                    </div>
                </div>
            `;
        }

        // --- 2. RENDER EQUIPO ---
        if (c.card_type === 'equipo') {
            const effects = c.effects || {};
            const eqType = (effects.equipo_type || 'util').toLowerCase();
            const subtype = effects.subtipo || '';
            const eqTypeLabel = 'EQUIPO: ' + eqType.toUpperCase() + (subtype ? ` (${subtype})` : '');
            
            let eqStatsHtml = '';
            if (eqType === 'arma') {
                const dmgDice = effects.damage_dice || c.dice || '—';
                const dmgStat = effects.damage_stat || c.execution_stat || '';
                const dmgFormula = dmgStat ? `${dmgDice} + ${dmgStat}` : dmgDice;
                eqStatsHtml = `
                    <div class="rpg-card-stats-row rpg-card-stats-row--weapon">
                        <div><span><i class="fas fa-sword"></i> DAÑO</span><strong>${dmgFormula}</strong></div>
                    </div>
                `;
            }
            
            return `
                <div class="rpg-card rpg-card--equipo rpg-card--equipo-${eqType} ${isHolo}" data-card-id="${c.id}" style="${borderStyle}">
                    <div class="rpg-card-header">
                        <div class="rpg-card-title">${c.name}</div>
                        <div class="rpg-card-subtitle" style="color: ${c.rank === 'SS' ? '#f59e0b' : rankColor}">
                            [CALIDAD ${c.rank}] ${eqTypeLabel}
                        </div>
                    </div>
                    ${hasImage ? `<div class="rpg-card-image" style="background-image: url('${c.image_url}')"></div>` : ''}
                    <div class="rpg-card-body">
                        ${tagsHtml}
                        ${eqStatsHtml}
                        <div class="rpg-card-desc">${c.description}</div>
                        ${rollHtml}
                    </div>
                </div>
            `;
        }

        // --- 3. RENDER BARCO ---
        if (c.card_type === 'barco') {
            const effects = c.effects || {};
            const bType = effects.barco_type || 'Navío';
            const tier = effects.tier || 1;
            const vida = effects.vida || 100;
            const ataque = effects.ataque || 0;
            const velocidad = effects.velocidad || 0;
            const resistencia = effects.resistencia || 0;
            
            const shipStatsHtml = `
                <div class="rpg-card-ship-grid">
                    <div class="rpg-card-ship-stat"><span><i class="fas fa-anchor"></i> TIER</span><strong>${tier}</strong></div>
                    <div class="rpg-card-ship-stat"><span><i class="fas fa-heart"></i> VIDA</span><strong>${vida}</strong></div>
                    <div class="rpg-card-ship-stat"><span><i class="fas fa-swords"></i> ATK</span><strong>${ataque}</strong></div>
                    <div class="rpg-card-ship-stat"><span><i class="fas fa-wind"></i> VEL</span><strong>${velocidad}</strong></div>
                    <div class="rpg-card-ship-stat" style="grid-column: span 2;"><span><i class="fas fa-shield-halved"></i> RESISTENCIA</span><strong>${resistencia}</strong></div>
                </div>
            `;
            
            return `
                <div class="rpg-card rpg-card--barco ${isHolo}" data-card-id="${c.id}" style="${borderStyle}">
                    <div class="rpg-card-header">
                        <div class="rpg-card-title">${c.name}</div>
                        <div class="rpg-card-subtitle" style="color: ${c.rank === 'SS' ? '#f59e0b' : rankColor}">
                            [TIER ${tier}] BARCO • ${bType.toUpperCase()}
                        </div>
                    </div>
                    ${hasImage ? `<div class="rpg-card-image" style="background-image: url('${c.image_url}')"></div>` : ''}
                    <div class="rpg-card-body">
                        ${shipStatsHtml}
                        <div class="rpg-card-desc">${c.description}</div>
                        ${rollHtml}
                    </div>
                </div>
            `;
        }

        // --- 4. RENDER NPC MENOR / MASCOTA ---
        if (c.card_type === 'npc_menor') {
            const MathRandomId = Math.random().toString(36).substring(2, 9);
            const effects = c.effects || {};
            const npcType = (effects.npc_mascota_type || 'npc').toLowerCase();
            const vida = effects.vida || 50;
            const tier = effects.tier || 1;
            const subLabel = npcType === 'mascota' ? `MASCOTA • TIER ${tier}` : 'NPC MENOR';
            
            let npcStatsHtml = `
                <div class="rpg-card-stats-row">
                    <div><span><i class="fas fa-heart"></i> VIDA</span><strong>${vida} HP</strong></div>
                </div>
            `;
            
            let actionsHtml = '';
            const rawActions = effects.acciones || [];
            const actionsList = typeof rawActions === 'string' ? rawActions.split('\n').map(a => a.trim()).filter(Boolean) : rawActions;
            if (actionsList && actionsList.length > 0) {
                actionsHtml = `<div class="rpg-card-actions-list-container">`;
                actionsHtml += `<span class="rpg-card-section-title"><i class="fas fa-swords"></i> ACCIONES</span>`;
                actionsList.forEach(act => {
                    actionsHtml += `<div class="rpg-card-action-item"><i class="fas fa-paw"></i> ${act}</div>`;
                });
                actionsHtml += `</div>`;
            }
            
            return `
                <div class="rpg-card rpg-card--npc-menor rpg-card--npc-${npcType} ${isHolo}" data-card-id="${c.id}" style="${borderStyle}">
                    <div class="rpg-card-header">
                        <div class="rpg-card-title">${c.name}</div>
                        <div class="rpg-card-subtitle" style="color: ${c.rank === 'SS' ? '#f59e0b' : rankColor}">
                            [RANGO ${c.rank}] ${subLabel}
                        </div>
                    </div>
                    ${hasImage ? `<div class="rpg-card-image" style="background-image: url('${c.image_url}')"></div>` : ''}
                    <div class="rpg-card-body">
                        ${npcStatsHtml}
                        <div class="rpg-card-desc">${c.description}</div>
                        ${actionsHtml}
                        ${rollHtml}
                    </div>
                </div>
            `;
        }

        // --- 5. RENDER HAKI ---
        if (c.card_type === 'haki') {
            const HTMLRandomId = Math.random().toString(36).substring(2, 9);
            const effects = c.effects || {};
            const hakiType = (effects.haki_type || 'busshoku').toLowerCase();
            const hakiLevel = (effects.haki_level || 'basico').toLowerCase();
            
            let hakiTypeName = 'Busshoku (Armamento)';
            if (hakiType === 'kenboshuko') hakiTypeName = 'Kenboshuko (Observación)';
            else if (hakiType === 'haoshoku') hakiTypeName = 'Haoshoku (Conquistador)';
            
            const typeLabel = 'HAKI: ' + hakiTypeName.toUpperCase();
            const levelLabel = hakiLevel.toUpperCase();
            const efectoText = effects.efecto || c.description || 'Sin efecto específico registrado.';

            const hakiClass = `rpg-card--haki rpg-card--haki-${hakiType}`;
            
            return `
                <div class="rpg-card ${hakiClass} ${isHolo}" data-card-id="${c.id}" style="${borderStyle}">
                    <div class="rpg-card-header">
                        <div class="rpg-card-title">${c.name}</div>
                        <div class="rpg-card-subtitle haki-type-label">
                            <span class="haki-level-badge">[${levelLabel}]</span> ${typeLabel}
                        </div>
                    </div>
                    ${hasImage ? `<div class="rpg-card-image" style="background-image: url('${c.image_url}')"></div>` : ''}
                    <div class="rpg-card-body">
                        ${c.description ? `<div class="rpg-card-desc">${c.description}</div>` : ''}
                        <div class="rpg-card-section rpg-card-section--efecto">
                            <span class="rpg-card-section-title"><i class="fas fa-shield-halved"></i> EFECTO</span>
                            <div class="rpg-card-section-text">${efectoText}</div>
                        </div>
                        ${rollHtml}
                    </div>
                </div>
            `;
        }

        // --- 6. RENDER ESTÁNDAR (TÉCNICA) ---
        const typeText = c.card_type.replace('_', ' ').toUpperCase();
        const rankLabel = 'RANGO';
        
        let statsHtml = '';
        if (c.cost_pe !== '—' || c.execution_stat !== '' || c.dice !== '') {
            statsHtml = `<div class="rpg-card-stats-row">`;
            if (c.cost_pe !== '—') statsHtml += `<div><span>COSTE</span><strong>${c.cost_pe}</strong></div>`;
            if (c.execution_stat !== '') statsHtml += `<div><span>STAT</span><strong>${c.execution_stat}</strong></div>`;
            if (c.dice !== '') statsHtml += `<div><span>DADOS</span><strong>${c.dice}</strong></div>`;
            statsHtml += `</div>`;
        }

        return `
            <div class="rpg-card ${isHolo}" data-card-id="${c.id}" style="${borderStyle}">
                <div class="rpg-card-header">
                    <div class="rpg-card-title">${c.name}</div>
                    <div class="rpg-card-subtitle" style="color: ${c.rank === 'SS' ? '#f59e0b' : rankColor}">
                        [${rankLabel} ${c.rank}] ${typeText} • ${c.activation.toUpperCase()}${durationText}${reposoText}
                    </div>
                </div>
                ${hasImage ? `<div class="rpg-card-image" style="background-image: url('${c.image_url}')"></div>` : ''}
                <div class="rpg-card-body">
                    ${tagsHtml}
                    ${statsHtml}
                    <div class="rpg-card-desc">${c.description}</div>
                    ${rollHtml}
                </div>
            </div>
        `;
    }

    /**
     * Carga el deck completo de un personaje y lo agrupa por tipo
     */
    loadCharacterDeck: function(charId, container) {
        console.log("=== loadCharacterDeck start ===", charId);
        const isOwner = container.dataset.isOwner === '1';
        fetch(`${this.config.baseUrl}/game/ajax/cards_my_deck.php?character_id=${charId}`)
            .then(r => {
                console.log("loadCharacterDeck fetch HTTP status:", r.status);
                return r.json();
            })
            .then(d => {
                console.log("loadCharacterDeck data received:", d);
                if (!d.ok) {
                    container.innerHTML = `<div style="text-align:center; padding: 30px; color: var(--accent-rose);">Error al cargar deck: ${d.error ? d.error.message : 'Error desconocido'}</div>`;
                    return;
                }
                
                const cards = d.data;
                if (cards.length === 0) {
                    container.innerHTML = `
                        <div style="text-align:center; padding: 40px; color: var(--text-muted); background: var(--bg-surface); border-radius: var(--radius-md); border: 1px dashed var(--border-color);">
                            <i class="fas fa-layer-group" style="font-size: 40px; opacity: 0.5; margin-bottom:15px;"></i>
                            <h4>Deck Vacío</h4>
                            <p style="font-size:13px;">Este personaje aún no tiene cartas asignadas.</p>
                        </div>
                    `;
                    return;
                }

                const grouped = {};
                cards.forEach(c => {
                    if (!grouped[c.card_type]) grouped[c.card_type] = [];
                    grouped[c.card_type].push(c);
                });

                let html = '';
                const typeNames = {
                    'tecnica': 'Técnicas', 'equipo': 'Equipamiento', 'akuma_no_mi': 'Akuma no Mi', 
                    'haki': 'Haki', 'npc_menor': 'NPCs Menores'
                };

                for (const [type, list] of Object.entries(grouped)) {
                    html += `
                        <h4 style="color: var(--text-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 5px; margin-top: 20px; margin-bottom: 15px; text-transform: uppercase; font-family: var(--font-heading);">
                            ${typeNames[type] || type} <span style="color: var(--text-muted); font-size: 12px;">(${list.length})</span>
                        </h4>
                        <div class="rpg-cards-grid" style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 30px;">
                    `;
                    list.forEach(c => {
                        html += '<div class="rpg-card-wrapper" style="display:flex; flex-direction:column; gap:8px; width:250px;">';
                        html += this.renderCard(c);
                        if (isOwner) {
                            html += `
                                <div class="rpg-card-actions-bar" style="display:flex; gap:8px; padding: 0 4px;">
                                    <button class="rpg-card-action-btn delete-btn" onclick="RpgCards.requestDelete(${charId}, ${c.id}, this)" style="flex:1; background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.2); color:#ef4444; padding:7px 10px; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer; transition:all 0.2s; display:flex; align-items:center; justify-content:center; gap:4px;" onmouseover="this.style.background='rgba(239,68,68,0.18)'" onmouseout="this.style.background='rgba(239,68,68,0.08)'"><i class="fas fa-trash-alt"></i> Solicitar Borrado</button>
                                </div>
                            `;
                        }
                        html += '</div>';
                    });
                    html += `</div>`;
                }

                container.innerHTML = html;
            });
    },

    requestDelete: function(charId, cardId, btn) {
        if (btn.disabled) return;
        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ...';

        fetch(`${this.config.baseUrl}/game/ajax/cards_request_action.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ character_id: charId, card_id: cardId, action: 'delete' })
        })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                btn.innerHTML = '<i class="fas fa-clock"></i> Pendiente';
                btn.style.background = 'rgba(239,68,68,0.04)';
                btn.style.color = 'var(--text-muted)';
                btn.style.borderColor = 'var(--border-color)';
                btn.onmouseover = null;
                btn.onmouseout = null;
            } else {
                btn.disabled = false;
                btn.innerHTML = originalText;
                alert(res.error.message);
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = originalText;
            alert('Error de conexión.');
        });
    },

    /**
     * Busca las zonas de cartas en los posts y carga las cartas jugadas
     */
    loadPostCards: function() {
        const zones = document.querySelectorAll('.rpg-post-cards-zone');
        if (zones.length === 0) return;

        zones.forEach(zone => {
            const postId = zone.dataset.postId;
            fetch(`${this.config.baseUrl}/game/ajax/cards_for_post.php?post_id=${postId}`)
                .then(r => r.json())
                .then(d => {
                    if (d.ok && d.data.length > 0) {
                        zone.style.display = 'block';
                        let html = `<div style="font-family: var(--font-heading); font-size: 12px; font-weight: bold; color: var(--text-muted); text-transform: uppercase; margin-bottom: 10px;"><i class="fas fa-layer-group"></i> Cartas Jugadas en este turno</div>`;
                        html += `<div style="display: flex; gap: 15px; flex-wrap: wrap;">`;
                        
                        let hasDice = false;
                        
                        d.data.forEach(c => {
                            html += this.renderCard(c);
                            if (c.roll_result && c.roll_result.trim() !== '') {
                                hasDice = true;
                            }
                        });
                        html += `</div>`;
                        zone.innerHTML = html;
                        
                        // Ocultar botón de editar si hay dados (UX) - El backend ya lo bloquea de todas formas
                        if (hasDice) {
                            const postWrapper = document.getElementById('post_' + postId);
                            if (postWrapper) {
                                // Buscar enlace de editpost.php
                                const editBtn = postWrapper.querySelector('a[href*="editpost.php"]');
                                if (editBtn) {
                                    // Podríamos ocultarlo, o cambiar el texto
                                    editBtn.style.opacity = '0.3';
                                    editBtn.style.pointerEvents = 'none';
                                    editBtn.title = 'No se puede editar: contiene tiradas de dados.';
                                }
                            }
                        }
                    }
                });
        });
    },

    buildAttachmentsHtml: function(c, weapons, ammo) {
        if (c.card_type === 'npc_menor') {
            const effects = c.effects || {};
            const npcType = (effects.npc_mascota_type || 'npc').toLowerCase();
            if (npcType === 'mascota') {
                const rawActions = effects.acciones || [];
                const actionsList = typeof rawActions === 'string' ? rawActions.split('\n').map(a => a.trim()).filter(Boolean) : rawActions;
                
                if (actionsList && actionsList.length > 0) {
                    let html = `
                        <div class="rpg-attachment-field" style="display:flex; flex-direction:column; gap:4px; text-align: left;">
                            <label style="font-weight: 700; color: var(--text-secondary); font-size: 10px; text-transform: uppercase;">Acción de la Mascota</label>
                            <select class="rpg-attachment-action textbox" style="width:100%; font-size: 11px; padding: 4px 8px; border-radius: 4px; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-primary);">
                                <option value="">-- Selecciona una acción --</option>
                    `;
                    actionsList.forEach(act => {
                        html += `<option value="${act}">${act}</option>`;
                    });
                    html += `
                            </select>
                        </div>
                    `;
                    return html;
                }
            }
        }

        if (!c.dice) return '';
        const armMatches = c.dice.match(/\[ARMA\]/g);
        const armCount = armMatches ? armMatches.length : 0;
        
        const muniMatches = c.dice.match(/\[MUNICION\]/g);
        const muniCount = muniMatches ? muniMatches.length : 0;
        
        if (armCount === 0 && muniCount === 0) return '';
        
        let html = '';
        
        // Weapons
        for (let i = 0; i < armCount; i++) {
            html += `
                <div class="rpg-attachment-field" style="display:flex; flex-direction:column; gap:4px; text-align: left;">
                    <label style="font-weight: 700; color: var(--text-secondary); font-size: 10px; text-transform: uppercase;">Arma #${i+1}</label>
                    <select class="rpg-attachment-weapon textbox" data-index="${i}" style="width:100%; font-size: 11px; padding: 4px 8px; border-radius: 4px; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-primary);">
                        <option value="">-- Ninguna --</option>
            `;
            weapons.forEach(w => {
                html += `<option value="${w.id}">${w.name} (${w.dice || 'Sin dados'})</option>`;
            });
            html += `
                    </select>
                </div>
            `;
        }
        
        // Ammo
        for (let i = 0; i < muniCount; i++) {
            html += `
                <div class="rpg-attachment-field" style="display:flex; flex-direction:column; gap:4px; text-align: left;">
                    <label style="font-weight: 700; color: var(--text-secondary); font-size: 10px; text-transform: uppercase;">Munición #${i+1}</label>
                    <select class="rpg-attachment-ammo textbox" data-index="${i}" style="width:100%; font-size: 11px; padding: 4px 8px; border-radius: 4px; background: var(--bg-card); border: 1px solid var(--border-color); color: var(--text-primary);">
                        <option value="">-- Ninguna --</option>
            `;
            ammo.forEach(a => {
                html += `<option value="${a.id}">${a.name} (${a.dice || 'Sin dados'})</option>`;
            });
            html += `
                    </select>
                </div>
            `;
        }
        
        return html;
    },

    updatePlayedCardsInput: function() {
        const input = document.getElementById('rpg_played_cards');
        if (!input) return;

        const payload = [];
        const selectedEls = document.querySelectorAll('.rpg-selectable-card.selected');
        
        selectedEls.forEach(el => {
            const cid = parseInt(el.dataset.cid);
            const container = el.closest('.rpg-selectable-card-container');
            const attachmentsContainer = container ? container.querySelector('.rpg-card-attachments') : null;
            
            if (attachmentsContainer && attachmentsContainer.style.display !== 'none') {
                const weaponSelects = attachmentsContainer.querySelectorAll('.rpg-attachment-weapon');
                const ammoSelects = attachmentsContainer.querySelectorAll('.rpg-attachment-ammo');
                const actionSelect = attachmentsContainer.querySelector('.rpg-attachment-action');
                
                const weapons = [];
                weaponSelects.forEach(sel => {
                    if (sel.value) weapons.push(parseInt(sel.value));
                });
                
                const ammo = [];
                ammoSelects.forEach(sel => {
                    if (sel.value) ammo.push(parseInt(sel.value));
                });
                
                const item = { card_id: cid };
                let hasExtra = false;
                
                if (weapons.length > 0) {
                    item.weapons = weapons;
                    hasExtra = true;
                }
                if (ammo.length > 0) {
                    item.ammo = ammo;
                    hasExtra = true;
                }
                if (actionSelect && actionSelect.value) {
                    item.selected_action = actionSelect.value;
                    hasExtra = true;
                }
                
                if (hasExtra) {
                    payload.push(item);
                } else {
                    payload.push(cid);
                }
            } else {
                payload.push(cid);
            }
        });

        input.value = JSON.stringify(payload);
    },

    /**
     * Inicializa el selector de cartas en el editor de texto
     */
    initCardSelector: function() {
        const selector = document.getElementById('rpg-card-selector');
        const toggleBtn = document.getElementById('rpg-card-toggle-btn');
        const panel = document.getElementById('rpg-card-deck-panel');
        const input = document.getElementById('rpg_played_cards');
        
        if (!selector || !toggleBtn || !panel || !input) return;

        // Clean template scroll styles programmatically
        panel.style.maxHeight = 'none';
        panel.style.overflowY = 'visible';
        panel.style.display = 'none';
        panel.style.flexDirection = 'column';
        panel.style.gap = '10px';
        panel.style.width = '100%';

        let selectedCards = [];

        // Find thread_id in page
        const tidInput = document.querySelector('input[name="tid"]');
        const tid = tidInput ? parseInt(tidInput.value) : 0;
        
        const url = tid > 0 
            ? `${this.config.baseUrl}/game/ajax/cards_my_deck.php?thread_id=${tid}` 
            : `${this.config.baseUrl}/game/ajax/cards_my_deck.php`;

        // Fetch user's deck
        fetch(url)
            .then(r => r.json())
            .then(d => {
                if (d.ok && d.data.length > 0) {
                    selector.style.display = 'block'; // Show if has cards
                    
                    const meta = d.meta; // thread history meta
                    
                    // Filter weapons and ammo for technique attachments
                    const weapons = d.data.filter(w => {
                        if (w.card_type !== 'equipo') return false;
                        if (!w.tags) return false;
                        return w.tags.some(t => {
                            const clean = t.replace(/[\[\]]/g, '').trim().toUpperCase();
                            return clean.includes('ARMA') || clean.includes('WEAPON');
                        });
                    });

                    const ammo = d.data.filter(a => {
                        if (a.card_type !== 'equipo') return false;
                        if (!a.tags) return false;
                        return a.tags.some(t => {
                            const clean = t.replace(/[\[\]]/g, '').trim().toUpperCase();
                            return clean.includes('MUNICION') || clean.includes('AMMO');
                        });
                    });

                    // Group cards by type
                    const grouped = {};
                    d.data.forEach(c => {
                        if (!grouped[c.card_type]) grouped[c.card_type] = [];
                        grouped[c.card_type].push(c);
                    });

                    const typeNames = {
                        'tecnica': 'Técnicas', 
                        'equipo': 'Equipamiento', 
                        'akuma_no_mi': 'Akuma no Mi', 
                        'haki': 'Haki', 
                        'npc_menor': 'NPCs Menores'
                    };

                    const typeIcons = {
                        'tecnica': '<i class="fas fa-fist-raised" style="color: var(--accent-rose);"></i>', 
                        'equipo': '<i class="fas fa-shield-alt" style="color: var(--accent-blue);"></i>', 
                        'akuma_no_mi': '<i class="fas fa-apple-alt" style="color: var(--accent-purple);"></i>', 
                        'haki': '<i class="fas fa-fire" style="color: var(--accent-amber);"></i>', 
                        'npc_menor': '<i class="fas fa-users" style="color: var(--accent-teal);"></i>'
                    };

                    let html = '';
                    
                    for (const [type, list] of Object.entries(grouped)) {
                        const icon = typeIcons[type] || '<i class="fas fa-layer-group"></i>';
                        const name = typeNames[type] || type.toUpperCase();
                        
                        html += `
                            <div class="rpg-deck-section" style="width: 100%;">
                                <div class="rpg-deck-section-header" onclick="RpgCards.toggleDeckSection('${type}', this)" style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:var(--radius-md); padding:10px 15px; cursor:pointer; transition:all 0.2s; user-select:none;">
                                    <div class="rpg-deck-section-title" style="font-family:var(--font-heading); font-size:13px; font-weight:700; color:var(--text-primary); display:flex; align-items:center; gap:8px; text-transform:uppercase;">
                                        ${icon} ${name} <span style="color:var(--text-secondary); font-size:11px; text-transform:none;">(${list.length})</span>
                                    </div>
                                    <div class="rpg-deck-section-arrow" style="color:var(--text-secondary); transition:transform 0.2s;"><i class="fas fa-chevron-down"></i></div>
                                </div>
                                <div id="rpg-deck-section-content-${type}" class="rpg-deck-section-content" style="display: none; gap: 12px; flex-wrap: wrap; padding: 15px 5px 5px 5px; width: 100%;">
                        `;

                        list.forEach(c => {
                            // Calculate cooldown & duration
                            let cooldown = c.reposo || 0;
                            let duration = c.duracion || 0;

                            let isDisabled = false;
                            let disabledAttr = '';
                            let badgeHtml = '';
                            let overlayHtml = '';

                            if (meta) {
                                const lastPlayed = meta.last_played_turns[c.id] || 0;
                                if (lastPlayed > 0) {
                                    const elapsed = meta.total_posts - lastPlayed;
                                    
                                    if (cooldown > 0 && elapsed < cooldown) {
                                        isDisabled = true;
                                        disabledAttr = 'data-disabled="true"';
                                        const remaining = cooldown - elapsed;
                                        overlayHtml = `
                                            <div class="rpg-card-cooldown-overlay" style="position: absolute; inset: 0; background: rgba(15, 23, 42, 0.75); border-radius: inherit; display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 10; color: #fff; text-align: center; padding: 15px; border: 2px solid var(--accent-rose); pointer-events: none;">
                                                <i class="fas fa-hourglass-half" style="font-size: 24px; color: var(--accent-rose); margin-bottom: 8px; animation: pulse 1.5s infinite;"></i>
                                                <div style="font-family: var(--font-heading); font-weight: 800; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--accent-rose);">En Reposo</div>
                                                <div style="font-size: 10px; opacity: 0.9; margin-top: 4px;">Falta ${remaining} turno${remaining > 1 ? 's' : ''}</div>
                                            </div>
                                        `;
                                    }

                                    if (duration > 0 && elapsed + 1 < duration) {
                                        const activeTurns = duration - (elapsed + 1);
                                        badgeHtml = `
                                            <span class="rpg-card-active-badge" style="position: absolute; top: -6px; right: -6px; background: var(--accent-emerald); color: #fff; padding: 2px 7px; border-radius: 10px; font-size: 8px; font-weight: 800; text-transform: uppercase; z-index: 12; border: 2px solid var(--bg-card); box-shadow: 0 2px 6px rgba(16, 185, 129, 0.4); pointer-events: none;">
                                                <i class="fas fa-circle" style="font-size: 5px; margin-right: 3px; vertical-align: middle;"></i> ACTIVA (${activeTurns})
                                            </span>
                                        `;
                                    }
                                }
                            }

                            const rankColor = this.config.rankColors[c.rank] || this.config.rankColors['C'];
                            const borderStyle = c.rank === 'SS' 
                                ? 'border: 2px solid transparent; background-image: linear-gradient(var(--bg-card), var(--bg-card)), ' + rankColor + '; background-origin: border-box; background-clip: content-box, border-box;' 
                                : `border: 2px solid ${rankColor};`;
                            
                            const opacityStyle = isDisabled ? 'opacity: 0.85; filter: grayscale(10%);' : '';

                            const attachmentsHtml = this.buildAttachmentsHtml(c, weapons, ammo);

                            html += `
                                <div class="rpg-selectable-card-container" style="display:flex; flex-direction:column; gap:8px; width:250px;">
                                    <div class="rpg-selectable-card" data-cid="${c.id}" ${disabledAttr} style="position: relative; cursor: ${isDisabled ? 'not-allowed' : 'pointer'}; transition: transform 0.2s, box-shadow 0.2s; width: 100%; ${opacityStyle}">
                                        ${badgeHtml}
                                        ${overlayHtml}
                                        ${this.renderCard(c)}
                                    </div>
                                    <div class="rpg-card-attachments" style="display: none; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 10px; font-size: 11px; flex-direction: column; gap: 8px;">
                                        ${attachmentsHtml}
                                    </div>
                                </div>
                            `;
                        });

                        html += `
                                </div>
                            </div>
                        `;
                    }

                    panel.innerHTML = html;

                    // Event listener for switch toggle
                    toggleBtn.addEventListener('change', (e) => {
                        if (e.target.checked) {
                            panel.style.display = 'flex';
                        } else {
                            panel.style.display = 'none';
                        }
                    });

                    if (toggleBtn.checked) {
                        panel.style.display = 'flex';
                    }

                    // Selector cards click logic
                    document.querySelectorAll('.rpg-selectable-card').forEach(el => {
                        el.addEventListener('click', (e) => {
                            if (el.dataset.disabled === 'true') {
                                return; // Block selection
                            }

                            const cid = el.dataset.cid;
                            const idx = selectedCards.indexOf(cid);
                            const container = el.closest('.rpg-selectable-card-container');
                            const attachmentsDiv = container ? container.querySelector('.rpg-card-attachments') : null;
                            
                            if (idx === -1) {
                                selectedCards.push(cid);
                                el.classList.add('selected');
                                el.querySelector('.rpg-card').style.boxShadow = '0 0 15px var(--accent-indigo)';
                                el.querySelector('.rpg-card').style.transform = 'translateY(-5px)';
                                if (attachmentsDiv && attachmentsDiv.innerHTML.trim() !== '') {
                                    attachmentsDiv.style.display = 'flex';
                                }
                            } else {
                                selectedCards.splice(idx, 1);
                                el.classList.remove('selected');
                                el.querySelector('.rpg-card').style.boxShadow = 'none';
                                el.querySelector('.rpg-card').style.transform = 'none';
                                if (attachmentsDiv) {
                                    attachmentsDiv.style.display = 'none';
                                }
                            }
                            
                            this.updatePlayedCardsInput();
                        });
                    });

                    // Event listener for select element changes in attachments
                    panel.addEventListener('change', (e) => {
                        if (e.target.classList.contains('rpg-attachment-weapon') || 
                            e.target.classList.contains('rpg-attachment-ammo') || 
                            e.target.classList.contains('rpg-attachment-action')) {
                            this.updatePlayedCardsInput();
                        }
                    });
                }
            });
    },

    toggleDeckSection: function(type, header) {
        const content = document.getElementById(`rpg-deck-section-content-${type}`);
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

document.addEventListener('DOMContentLoaded', () => {
    RpgCards.init();
});
