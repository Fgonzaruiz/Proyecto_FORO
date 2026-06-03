/* 
   RPG Custom Interactive Functions
   Theme Switcher, dynamic icons, and more.
*/

document.addEventListener("DOMContentLoaded", function() {
    function getCleanBase() {
        var el = document.getElementById('pj-nav-submenu');
        var val = el ? (el.getAttribute('data-base') || '') : '';
        return val.replace(/^https?:/, '');
    }

    // --- 2. ROTATING HERO BANNER BACKGROUND ---
    const heroImages = [
        'https://images.unsplash.com/photo-1519074069444-1ba4e5663476?q=80&w=1920&auto=format&fit=crop', 
        'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?q=80&w=1920&auto=format&fit=crop', 
        'https://images.unsplash.com/photo-1509198397868-475647b2a1e5?q=80&w=1920&auto=format&fit=crop', 
        'https://images.unsplash.com/photo-1534447677768-be436bb09401?q=80&w=1920&auto=format&fit=crop', 
        'https://images.unsplash.com/photo-1578632767115-351597cf2477?q=80&w=1920&auto=format&fit=crop'
    ];
    const randomHeroImg = heroImages[Math.floor(Math.random() * heroImages.length)];
    const heroSection = document.querySelector('.roleplay-hero');
    if (heroSection) {
        heroSection.style.backgroundImage = `url('${randomHeroImg}')`;
    }

    // Scroll-to-top button
    var scrollBtn = document.querySelector('.scroll-top');
    if (scrollBtn) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 400) scrollBtn.classList.add('is-visible');
            else scrollBtn.classList.remove('is-visible');
        });
        scrollBtn.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // --- 3. DYNAMIC FORUM CARD STYLE & ICON MAP ---
    const themeConfig = {
        'reglamento':     { icon: 'fa-bullhorn',       color: '#8b5cf6', shadow: 'rgba(139, 92, 246, 0.4)' },
        'anuncios':       { icon: 'fa-bell',           color: '#C62828', shadow: 'rgba(198, 40, 40, 0.4)' },
        'noticias':       { icon: 'fa-file-alt',       color: '#3b82f6', shadow: 'rgba(59, 130, 246, 0.4)' },
        'eventos':        { icon: 'fa-calendar-alt',   color: '#14b8a6', shadow: 'rgba(20, 184, 166, 0.4)' },
        'presentaciones': { icon: 'fa-user-astronaut', color: '#10b981', shadow: 'rgba(16, 185, 129, 0.4)' },
        'zona de usuarios':{ icon: 'fa-users',          color: '#06b6d4', shadow: 'rgba(6, 182, 212, 0.4)' },
        'cumpleaños':     { icon: 'fa-gift',           color: '#f59e0b', shadow: 'rgba(245, 158, 11, 0.4)' },
        'despedidas':     { icon: 'fa-hand-paper',     color: '#f97316', shadow: 'rgba(249, 115, 22, 0.4)' },
        'guías de rol':   { icon: 'fa-book-open',      color: '#ec4899', shadow: 'rgba(236, 72, 153, 0.4)' },
        'búsqueda de rol': { icon: 'fa-search',         color: '#f43f5e', shadow: 'rgba(244, 63, 94, 0.4)' },
        'ambientación':   { icon: 'fa-globe-americas', color: '#f97316', shadow: 'rgba(249, 115, 22, 0.4)' },
        'off-topic':      { icon: 'fa-smile',          color: '#8b5cf6', shadow: 'rgba(139, 92, 246, 0.4)' },
        'default':        { icon: 'fa-compass',        color: '#3b82f6', shadow: 'rgba(59, 130, 246, 0.3)' }
    };

    const forumCards = document.querySelectorAll('.rpg-forum-card');
    forumCards.forEach(card => {
        const titleLink = card.querySelector('.rpg-forum-name a');
        if (!titleLink) return;
        const titleText = titleLink.textContent.trim().toLowerCase();
        let selectedStyle = themeConfig['default'];
        
        for (const keyword in themeConfig) {
            if (titleText.includes(keyword)) {
                selectedStyle = themeConfig[keyword];
                break;
            }
        }
        
        const iconDiv = card.querySelector('.rpg-forum-icon');
        if (iconDiv) {
            iconDiv.innerHTML = `<i class="fas ${selectedStyle.icon}"></i>`;
            iconDiv.style.borderColor = selectedStyle.color;
            // The background color handles the opacity in CSS natively, but we can override it slightly
            iconDiv.style.color = selectedStyle.color; // Mantiene el icono colorido
            iconDiv.style.boxShadow = `0 0 15px ${selectedStyle.shadow}`;
        }
    });

    // --- 4. ANTI-FLICKER / FADE IN (Opcional) ---
    document.body.style.opacity = '1';

    // --- 5. CHARACTER SWITCHER (PERSONAJE NAV DROPDOWN) ---
    var pjMenu = document.getElementById('pj-nav-submenu');
    if (pjMenu) {
        var bb = getCleanBase();
        (function(menu, base) {
            fetch(base + '/game/ajax/my_personajes.php')
                .then(function(r){ return r.json() })
                .then(function(d){
                    if (!d.ok || !d.data) {
                        menu.innerHTML = '<li><a href="' + base + '/game/public/mis_personajes.php"><i class="fas fa-cog"></i> Gestionar Personajes</a></li>'
                        + '<li><a href="' + base + '/game/public/peticiones_general.php"><i class="fas fa-envelope-open-text"></i> Solicitudes</a></li>';
                        return;
                    }
                    var activeChar = null;
                    if (d.data.chars && d.data.chars.length > 0) {
                        d.data.chars.forEach(function(c){
                            if (c.is_active) activeChar = c;
                        });
                    }
                    var html = '<li><a href="' + base + '/game/public/mis_personajes.php"><i class="fas fa-cog"></i> Gestionar Personajes</a></li>'
                        + '<li><a href="' + base + '/game/public/peticiones_general.php"><i class="fas fa-clipboard-list"></i> Trámites</a></li>';
                    menu.innerHTML = html;

                    // Replace welcomeblock username with character name
                    if (activeChar) {
                        var wb = document.querySelector('.nav-welcome-text');
                        if (wb) {
                            // Keep the chevron, replace the username text
                            var text = wb.childNodes[0];
                            if (text) text.textContent = ' ' + activeChar.name + ' ';
                        }
                        // Show/hide admin/mod elements based on character's is_staff
                        if (activeChar.is_staff) {
                            document.body.classList.add('rpg-staff');
                        } else {
                            document.body.classList.remove('rpg-staff');
                        }
                        // Staff nav item: show/hide based on staff_level
                        var staffItem = document.getElementById('staff-nav-item');
                        var staffText = document.getElementById('staff-nav-text');
                        if (staffItem && staffText) {
                            var level = activeChar.staff_level || 0;
                            if (level > 0) {
                                staffItem.classList.remove('is-hidden');
                                var labels = {1: 'PANEL', 2: 'PANEL', 3: 'PANEL'};
                                var linkLabels = {1: 'Zona Colaborador', 2: 'Zona Moderador', 3: 'Zona Administrador'};
                                staffText.textContent = labels[level] || 'PANEL';
                                var staffLink = document.getElementById('staff-nav-link');
                                if (staffLink) {
                                    var linkText = staffLink.childNodes[0];
                                    if (linkText) linkText.textContent = linkLabels[level] || 'ZONA';
                                }
                            } else {
                                staffItem.classList.add('is-hidden');
                            }
                        }
                    }
                })
                .catch(function(){
                    menu.innerHTML = '<li><a href="' + base + '/game/public/mis_personajes.php"><i class="fas fa-cog"></i> Gestionar Personajes</a></li>'
                        + '<li><a href="' + base + '/game/public/peticiones_general.php"><i class="fas fa-clipboard-list"></i> Trámites</a></li>';
                });
        })(pjMenu, bb);
    }

    // --- 6. POSTBIT: Replace with Character Info ---
    var postCards = document.querySelectorAll('.rpg-post-pjcard');
    if (postCards.length > 0) {
        var bb = getCleanBase();
        postCards.forEach(function(card) {
            var uid = card.getAttribute('data-uid');
            var postId = card.getAttribute('data-post-id');
            if (!uid) return;
            var url = bb + '/game/ajax/get_active_pj_for_user.php?uid=' + uid;
            if (postId) url += '&post_id=' + postId;
            fetch(url)
                .then(function(r){ return r.json() })
                .then(function(d){
                    if (d.ok && d.data) {
                        var c = d.data;
                        var img = card.querySelector('img');
                        if (img) {
                            img.src = c.avatar || bb + '/images/game/personaje_banner.png';
                            img.style.display = 'block';
                        }
                        var nameEl = card.querySelector('.rpg-post-pj-character-name');
                        var rankEl = card.querySelector('.rpg-post-pj-character-rank');
                        var crewEl = card.querySelector('.rpg-post-pj-character-crew');
                        if (nameEl) {
                            var link = nameEl.querySelector('a');
                            if (link) {
                                link.href = bb + '/game/public/personaje.php?pj=' + c.id;
                                var span = link.querySelector('span');
                                if (span) {
                                    span.textContent = c.name;
                                } else {
                                    link.textContent = c.name;
                                }
                            } else {
                                nameEl.innerHTML = '<a href="' + bb + '/game/public/personaje.php?pj=' + c.id + '" class="rpg-pjcard-name-link">' + c.name + '</a>';
                            }
                        }
                        if (rankEl) rankEl.textContent = c.rango || '';
                        if (crewEl) crewEl.textContent = c.tripulacion || '';

                        // Make the entire card sidebar clickable to lead to the character sheet
                        card.style.cursor = 'pointer';
                        card.title = 'Ver ficha de ' + c.name;
                        card.addEventListener('click', function(e) {
                            if (e.target.closest('a') || e.target.closest('button')) return;
                            window.location.href = bb + '/game/public/personaje.php?pj=' + c.id;
                        });
                        
                        var msgIcon = card.querySelector('.fa-comment');
                        if (msgIcon && msgIcon.parentNode) {
                            msgIcon.parentNode.innerHTML = '<i class="fas fa-comment rpg-pjcard-postcount-icon"></i> ' + (c.postnum || 0);
                        }
                    }
                })
                .catch(function(){});
        });
    }

    // --- 7. THREAD LIST: Replace author/lastposter usernames with character names ---
    var threadEls = document.querySelectorAll('.rpg-thread-author[data-uid], .rpg-thread-lastpost [data-uid]');
    if (threadEls.length > 0) {
        var bb = getCleanBase();
        threadEls.forEach(function(el) {
            var uid = el.getAttribute('data-uid');
            if (!uid) return;
            var threadId = el.getAttribute('data-thread-id');
            if (!threadId) {
                var row = el.closest('.rpg-thread-row, .trow1, .trow2, tr');
                if (row) {
                    var authorEl = row.querySelector('.rpg-thread-author');
                    if (authorEl) threadId = authorEl.getAttribute('data-thread-id');
                }
            }
            var url = bb + '/game/ajax/get_active_pj_for_user.php?uid=' + uid;
            if (threadId && el.closest('.rpg-thread-author')) {
                url += '&thread_id=' + threadId;
            } else if (threadId && el.closest('.rpg-thread-lastpost')) {
                url += '&last_post_for_thread_id=' + threadId;
            }
            fetch(url)
                .then(function(r){ return r.json() })
                .then(function(d){
                    if (d.ok && d.data) {
                        var link = el.querySelector('a');
                        if (link) {
                            var span = link.querySelector('span');
                            if (span) {
                                span.textContent = d.data.name;
                            } else {
                                link.textContent = d.data.name;
                            }
                        } else {
                            el.textContent = d.data.name;
                        }
                    }
                })
                .catch(function(){});
        });
    }

    // --- 8. NOTIFICATION BELL POLLING ---
    (function() {
        var bellBtn = document.getElementById('notification-bell');
        if (!bellBtn) return;
        var bb = getCleanBase();
        var badge = document.getElementById('notification-badge');
        function pollUnread() {
            fetch(bb + '/game/ajax/notifications_count.php?_t=' + Date.now())
                .then(function(r){ return r.json() })
                .then(function(d){
                    if (d.ok && d.data) {
                        var cnt = d.data.unread || 0;
                        if (cnt > 0) {
                            if (badge) {
                                badge.textContent = cnt > 99 ? '99+' : cnt;
                                badge.classList.remove('is-hidden');
                            }
                            bellBtn.classList.add('has-unread');
                        } else {
                            if (badge) badge.classList.add('is-hidden');
                            bellBtn.classList.remove('has-unread');
                        }
                    }
                })
                .catch(function(){});
        }
        pollUnread();
        setInterval(pollUnread, 30000);
    })();

    // --- 9. BOARD STATS: Replace newestmember/top user with character name ---
    var newestMemberEls = document.querySelectorAll('.rpg-stat-number a[href*="uid="]');
    if (newestMemberEls.length > 0) {
        var bb = getCleanBase();
        newestMemberEls.forEach(function(el) {
            var statTextDiv = el.closest('.rpg-stat-text');
            var isTopUsuario = false;
            if (statTextDiv) {
                var label = statTextDiv.querySelector('.rpg-stat-label');
                if (label && label.textContent.trim().toLowerCase().includes('top')) {
                    isTopUsuario = true;
                }
            }

            if (isTopUsuario) {
                fetch(bb + '/game/ajax/get_active_pj_for_user.php?global_top_poster=1')
                    .then(function(r){ return r.json() })
                    .then(function(d){
                        if (d.ok && d.data) {
                            el.textContent = d.data.name;
                        }
                    })
                    .catch(function(){});
            } else {
                var href = el.getAttribute('href');
                var uidMatch = href.match(/uid=(\d+)/);
                if (uidMatch && uidMatch[1]) {
                    var uid = uidMatch[1];
                    fetch(bb + '/game/ajax/get_active_pj_for_user.php?uid=' + uid + '&top_poster=1')
                        .then(function(r){ return r.json() })
                        .then(function(d){
                            if (d.ok && d.data) {
                                el.textContent = d.data.name;
                            }
                        })
                        .catch(function(){});
                }
            }
        });
    }
    // --- 10. THREAD META BADGES (type + on-rol date) ---
    (function() {
        var bb = getCleanBase();
        var badgeEls = document.querySelectorAll('.rpg-thread-header-badge[data-thread-id], .rpg-thread-meta-badge[data-thread-id]');
        if (badgeEls.length === 0) return;
        var catColors = {'Pasado':'#8b5cf6','Presente':'#10b981','Mision':'#f59e0b','Evento':'#3b82f6','Trama':'#ef4444','Fic':'#ec4899','Off_Rol':'#6b7280'};
        var seasonNames = ['Primavera','Verano','Otoño','Invierno'];
        var fetched = {};
        badgeEls.forEach(function(el) {
            var tid = el.getAttribute('data-thread-id');
            if (!tid || fetched[tid]) return;
            fetched[tid] = true;
            fetch(bb + '/game/ajax/get_thread_diary_data.php?thread_id=' + tid)
                .then(function(r){ return r.json() })
                .then(function(d){
                    if (!d.ok || !d.data) return;
                    var td = d.data;
                    var color = catColors[td.category] || '#6b7280';
                    var catLabel = td.category === 'Off_Rol' ? 'Off Rol' : td.category;
                    var dateStr = '';
                    if (td.day) {
                        var sName = seasonNames[td.season] || '?';
                        dateStr = td.day + ' ' + sName + ' ' + td.year;
                    }
                    var html = '<span class="rpg-meta-badge-wrap">';
                    html += '<span class="rpg-meta-type" style="--meta-bg:' + color + ';">' + catLabel + '</span>';
                    if (dateStr) {
                        html += '<span class="rpg-meta-date">' + dateStr + '</span>';
                    }
                    html += '</span>';
                    document.querySelectorAll('.rpg-thread-header-badge[data-thread-id="' + tid + '"], .rpg-thread-meta-badge[data-thread-id="' + tid + '"]').forEach(function(b) {
                        b.innerHTML = html;
                        b.classList.add('is-visible');
                    });
                })
                .catch(function(){});
        });
    })();

});

window.switchPJNav = function(pjId) {
    var menu = document.getElementById('pj-nav-submenu');
    var base = menu ? menu.getAttribute('data-base') || '' : '';
    var url = base + '/game/ajax/set_active_pj.php';
    var req = window.gamePostJson
        ? window.gamePostJson(url, { pj_id: pjId })
        : fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Mybb-Post-Key': window.GAME_CSRF || '' },
            credentials: 'same-origin',
            body: JSON.stringify({ pj_id: pjId, my_post_key: window.GAME_CSRF || '' })
        }).then(function (r) { return r.json(); });
    req.then(function (d) {
        if (d.ok) { location.reload(); }
        else { alert(d.error && d.error.message ? d.error.message : 'Error'); }
    })
    .catch(function(){ alert('Error de conexión'); });
};

// RPG System tabs (newthread / newreply / quickreply)
window.switchRpgTab = function(tabName, btn) {
    var container = btn.closest('.rpg-system-container');
    if (!container) return;
    container.querySelectorAll('.rpg-system-content').forEach(function(p) {
        p.classList.remove('active');
    });
    container.querySelectorAll('.rpg-system-tab-btn').forEach(function(b) {
        b.classList.remove('active');
    });
    var tab = container.querySelector('#rpg-tab-' + tabName);
    if (tab) tab.classList.add('active');
    btn.classList.add('active');
};

document.addEventListener('DOMContentLoaded', function() {
    var toggle = document.getElementById('rpg-card-toggle-btn');
    if (!toggle || toggle.dataset.rpgClearBound) return;
    toggle.dataset.rpgClearBound = '1';
    toggle.addEventListener('change', function() {
        if (this.checked) return;
        var deckPanel = document.getElementById('rpg-card-deck-panel');
        if (deckPanel) {
            deckPanel.querySelectorAll('.rpg-selectable-card').forEach(function(cardEl) {
                cardEl.classList.remove('selected');
            });
        }
        var input = document.getElementById('rpg_played_cards');
        if (input) input.value = '';
    });

    var navToggle = document.getElementById('nav-welcome-toggle');
    if (navToggle) {
        navToggle.addEventListener('click', function(e) {
            if (e.target.closest('#nav-user-dropdown a')) return;
            navToggle.classList.toggle('is-open');
        });
        document.addEventListener('click', function(e) {
            if (!navToggle.contains(e.target)) navToggle.classList.remove('is-open');
        });
    }
});
