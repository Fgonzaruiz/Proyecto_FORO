/* 
   RPG Custom Interactive Functions
   Theme Switcher, dynamic icons, and more.
*/

// Theme Manager - Ejecutar inmediatamente para evitar parpadeos si es posible
(function() {
    const savedTheme = localStorage.getItem('rpg_theme');
    if (savedTheme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
    } else if (savedTheme === 'light') {
        document.documentElement.removeAttribute('data-theme');
    } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        document.documentElement.setAttribute('data-theme', 'dark');
    }
})();

document.addEventListener("DOMContentLoaded", function() {
    // --- 1. THEME TOGGLE (LIGHT/DARK) ---
    const themeBtn = document.querySelector('.theme-toggle-btn');
    const updateThemeIcon = () => {
        if (!themeBtn) return;
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        themeBtn.innerHTML = isDark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
    };
    
    if (themeBtn) {
        updateThemeIcon(); // Inicializar icono
        
        themeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const currentTheme = document.documentElement.getAttribute('data-theme');
            if (currentTheme === 'dark') {
                document.documentElement.removeAttribute('data-theme');
                localStorage.setItem('rpg_theme', 'light');
            } else {
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('rpg_theme', 'dark');
            }
            updateThemeIcon();
        });
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

    // --- 3. DYNAMIC FORUM CARD STYLE & ICON MAP ---
    const themeConfig = {
        'reglamento':     { icon: 'fa-bullhorn',       color: '#8b5cf6', shadow: 'rgba(139, 92, 246, 0.4)' },
        'anuncios':       { icon: 'fa-bell',           color: '#6366f1', shadow: 'rgba(99, 102, 241, 0.4)' },
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
        var bb = pjMenu.getAttribute('data-base') || '';
        (function(menu, base) {
            fetch(base + '/game/ajax/my_personajes.php')
                .then(function(r){ return r.json() })
                .then(function(d){
                    if (!d.ok || !d.data) {
                        menu.innerHTML = '<li><a href="' + base + '/game/public/mis_personajes.php"><i class="fas fa-users"></i> Mis Personajes</a></li>';
                        return;
                    }
                    var h = '';
                    var activeChar = null;
                    if (d.data.chars && d.data.chars.length > 0) {
                        d.data.chars.forEach(function(c){
                            if (c.is_active) activeChar = c;
                            var activeClass = c.is_active ? 'pj-nav-item--active' : '';
                            var check = c.is_active ? '<i class="fas fa-check-circle" style="color:var(--accent-emerald);font-size:12px;"></i>' : '';
                            h += '<li class="pj-nav-item ' + activeClass + '" data-pj-id="' + c.id + '"><a href="#" onclick="event.preventDefault();switchPJNav(' + c.id + ')"><span class="pj-nav-avatar" style="background-image:url(' + (c.avatar || base + '/images/game/personaje_banner.png') + ')"></span>' + check + '</a></li>';
                        });
                    }
                    h += '<li class="pj-nav-divider"></li>';
                    h += '<li><a href="' + base + '/game/public/mis_personajes.php"><i class="fas fa-cog"></i> Gestionar Personajes</a></li>';
                    menu.innerHTML = h;

                    // Fill top-right active character name
                    var nameEl = document.getElementById('pj-active-name-top');
                    if (nameEl && activeChar) nameEl.textContent = activeChar.name;
                })
                .catch(function(){
                    menu.innerHTML = '<li><a href="' + base + '/game/public/mis_personajes.php"><i class="fas fa-users"></i> Mis Personajes</a></li>';
                });
        })(pjMenu, bb);
    }

    // --- 6. POSTBIT: Replace with Character Info ---
    var postCards = document.querySelectorAll('.rpg-post-pjcard');
    if (postCards.length > 0) {
        var bb = (document.getElementById('pj-nav-submenu')) ? document.getElementById('pj-nav-submenu').getAttribute('data-base') || '' : '';
        var uidsToFetch = [];
        postCards.forEach(function(card) {
            var uid = card.getAttribute('data-uid');
            if (uid) { uidsToFetch.push(uid); }
        });
        if (uidsToFetch.length > 0) {
            uidsToFetch.forEach(function(uid) {
                fetch(bb + '/game/ajax/get_active_pj_for_user.php?uid=' + uid)
                    .then(function(r){ return r.json() })
                    .then(function(d){
                        if (d.ok && d.data) {
                            var c = d.data;
                            var cards = document.querySelectorAll('.rpg-post-pjcard[data-uid="' + uid + '"]');
                            cards.forEach(function(card) {
                                var img = card.querySelector('img');
                                if (img) {
                                    img.src = c.avatar || bb + '/images/game/personaje_banner.png';
                                    img.style.display = 'block';
                                }
                                var nameEl = card.querySelector('.rpg-post-pj-character-name');
                                var rankEl = card.querySelector('.rpg-post-pj-character-rank');
                                var crewEl = card.querySelector('.rpg-post-pj-character-crew');
                                if (nameEl) nameEl.textContent = c.name;
                                if (rankEl) rankEl.textContent = c.rango || '';
                                if (crewEl) crewEl.textContent = c.tripulacion || '';
                            });
                        }
                    })
                    .catch(function(){});
            });
        }
    }
});

window.switchPJNav = function(pjId) {
    var menu = document.getElementById('pj-nav-submenu');
    var base = menu ? menu.getAttribute('data-base') || '' : '';
    fetch(base + '/game/ajax/set_active_pj.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pj_id: pjId })
    })
    .then(function(r){ return r.json() })
    .then(function(d){
        if (d.ok) { location.reload(); }
        else { alert(d.error.message); }
    })
    .catch(function(){ alert('Error de conexión'); });
};
