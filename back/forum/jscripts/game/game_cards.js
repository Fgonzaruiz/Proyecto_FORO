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

        let statsHtml = '';
        if (c.cost_pe !== '—' || c.execution_stat !== '' || c.dice !== '') {
            statsHtml = `<div class="rpg-card-stats-row">`;
            if (c.cost_pe !== '—') statsHtml += `<div><span>COSTE</span><strong>${c.cost_pe}</strong></div>`;
            if (c.execution_stat !== '') statsHtml += `<div><span>STAT</span><strong>${c.execution_stat}</strong></div>`;
            if (c.dice !== '') statsHtml += `<div><span>DADOS</span><strong>${c.dice}</strong></div>`;
            statsHtml += `</div>`;
        }

        let rollHtml = '';
        if (c.roll_result && c.roll_result.trim() !== '') {
            rollHtml = `
                <div style="background: rgba(16, 185, 129, 0.1); border-left: 3px solid #10b981; padding: 10px; margin-top: 10px; border-radius: 4px;">
                    <div style="font-size: 10px; font-weight: bold; color: #10b981; margin-bottom: 3px; text-transform: uppercase;">Resultado de Tirada</div>
                    <div style="font-family: monospace; font-size: 13px; color: var(--text-primary);">${c.roll_result}</div>
                </div>
            `;
        }

        const borderStyle = c.rank === 'SS' ? 'border: 2px solid transparent; background-image: linear-gradient(var(--bg-card), var(--bg-card)), ' + rankColor + '; background-origin: border-box; background-clip: content-box, border-box;' : `border: 2px solid ${rankColor};`;

        return `
            <div class="rpg-card ${isHolo}" data-card-id="${c.id}" style="${borderStyle}">
                <div class="rpg-card-header">
                    <div class="rpg-card-title">${c.name}</div>
                    <div class="rpg-card-subtitle" style="color: ${c.rank === 'SS' ? '#f59e0b' : rankColor}">
                        [${c.rank}] ${c.card_type.toUpperCase()} • ${c.activation.toUpperCase()}
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
    },

    /**
     * Carga el deck completo de un personaje y lo agrupa por tipo
     */
    loadCharacterDeck: function(charId, container) {
        fetch(`${this.config.baseUrl}/game/ajax/cards_my_deck.php?character_id=${charId}`)
            .then(r => r.json())
            .then(d => {
                if (!d.ok) {
                    container.innerHTML = `<div style="text-align:center; padding: 30px; color: var(--accent-rose);">Error al cargar deck.</div>`;
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
                        html += this.renderCard(c);
                    });
                    html += `</div>`;
                }

                container.innerHTML = html;
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

    /**
     * Inicializa el selector de cartas en el editor de texto
     */
    initCardSelector: function() {
        const selector = document.getElementById('rpg-card-selector');
        const toggleBtn = document.getElementById('rpg-card-toggle-btn');
        const panel = document.getElementById('rpg-card-deck-panel');
        const input = document.getElementById('rpg_played_cards');
        
        if (!selector || !toggleBtn || !panel || !input) return;

        let selectedCards = [];

        // Fetch user's deck
        fetch(`${this.config.baseUrl}/game/ajax/cards_my_deck.php`)
            .then(r => r.json())
            .then(d => {
                if (d.ok && d.data.length > 0) {
                    selector.style.display = 'block'; // Mostrar botón porque tiene cartas
                    
                    let html = '';
                    d.data.forEach(c => {
                        html += `
                            <div class="rpg-selectable-card" data-cid="${c.id}" style="cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;">
                                ${this.renderCard(c)}
                            </div>
                        `;
                    });
                    panel.innerHTML = html;

                    // Event listeners
                    toggleBtn.addEventListener('click', () => {
                        if (panel.style.display === 'none') {
                            panel.style.display = 'flex';
                            toggleBtn.classList.replace('rpg-btn-secondary', 'rpg-btn-primary');
                        } else {
                            panel.style.display = 'none';
                            toggleBtn.classList.replace('rpg-btn-primary', 'rpg-btn-secondary');
                        }
                    });

                    document.querySelectorAll('.rpg-selectable-card').forEach(el => {
                        el.addEventListener('click', (e) => {
                            const cid = el.dataset.cid;
                            const idx = selectedCards.indexOf(cid);
                            
                            if (idx === -1) {
                                selectedCards.push(cid);
                                el.classList.add('selected');
                                el.querySelector('.rpg-card').style.boxShadow = '0 0 15px var(--accent-indigo)';
                                el.querySelector('.rpg-card').style.transform = 'translateY(-5px)';
                            } else {
                                selectedCards.splice(idx, 1);
                                el.classList.remove('selected');
                                el.querySelector('.rpg-card').style.boxShadow = 'none';
                                el.querySelector('.rpg-card').style.transform = 'none';
                            }
                            
                            input.value = JSON.stringify(selectedCards);
                        });
                    });
                }
            });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    RpgCards.init();
});
