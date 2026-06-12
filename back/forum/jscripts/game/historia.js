document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('historia-search');
    const triggers = document.querySelectorAll('.js-historia-trigger');
    const views = document.querySelectorAll('.js-historia-view');
    // Modal Elements
    const modal = document.getElementById('historia-modal');
    const modalClose = document.getElementById('historia-modal-close');
    const mTag = document.getElementById('historia-modal-tag');
    const mTitle = document.getElementById('historia-modal-title');
    const mBody = document.getElementById('historia-modal-body');

    function openModal(title, subtitle, details) {
        mTitle.textContent = title;
        mTag.textContent = subtitle;
        
        let formatted = details;
        
        // Siempre aplicar negritas
        formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

        // Check if there are complex HTML tags like <p>, <h1>, <div>, <article>, etc.
        const hasHtml = /<(p|h1|h2|h3|div|article|section)[> ]/i.test(details);

        if (!hasHtml) {
            // Si es texto plano, hacer que se vea bien como párrafos.
            const paragraphs = formatted.split(/\n\n+/).map(p => p.trim()).filter(p => p !== '');
            // Si no hay párrafos grandes, puede que solo tenga saltos simples.
            if (paragraphs.length <= 1 && formatted.includes('\n')) {
                formatted = formatted.replace(/\n/g, '<br>');
            } else {
                formatted = paragraphs.map(p => `<p>${p.replace(/\n/g, '<br>')}</p>`).join('');
            }
            
            // Si es un periódico, añadir el título para darle el estilo de "periódico real"
            if (subtitle && subtitle.includes('News Coo')) {
                formatted = `<h1>${title}</h1>` + formatted;
            }
        }
        
        // Wrap everything in a newspaper layout if it's a newspaper
        if (subtitle && subtitle.includes('News Coo')) {
            formatted = `<div class="rpg-news-paper-layout">${formatted}</div>`;
        }
        
        mBody.innerHTML = formatted;
        modal.classList.remove('rpg-is-hidden');
        
        // Redirecciones en enlaces cruzados dentro del modal
        mBody.querySelectorAll('.rpg-lore-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                let targetId = link.getAttribute('data-lore-id');
                if (targetId) {
                    targetId = 'lore-' + targetId;
                } else {
                    targetId = link.getAttribute('data-event-id');
                    if (targetId) {
                        targetId = 'event-' + targetId;
                    } else {
                        targetId = link.getAttribute('data-news-id');
                        if (targetId) targetId = 'news-' + targetId;
                    }
                }
                
                const targetTrigger = document.querySelector(`.js-historia-trigger[data-id="${targetId}"]`);
                if (targetTrigger) {
                    openModal(
                        targetTrigger.dataset.title,
                        targetTrigger.dataset.subtitle,
                        targetTrigger.dataset.details
                    );
                }
            });
        });
    }

    triggers.forEach(trigger => {
        trigger.addEventListener('click', () => {
            openModal(
                trigger.dataset.title,
                trigger.dataset.subtitle,
                trigger.dataset.details
            );
        });
    });

    modalClose.addEventListener('click', () => { modal.classList.add('rpg-is-hidden'); });
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.add('rpg-is-hidden'); });

    // Buscador unificado
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            triggers.forEach(item => {
                const txt = (item.dataset.title + ' ' + item.dataset.subtitle + ' ' + item.innerText).toLowerCase();
                if (txt.includes(query)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }

    // Filtro por Eras
    const eraFilter = document.getElementById('historia-era-filter');
    if (eraFilter) {
        eraFilter.addEventListener('change', (e) => {
            const selectedEra = e.target.value;
            const filterables = document.querySelectorAll('.js-era-filterable');
            filterables.forEach(item => {
                if (selectedEra === 'all' || item.dataset.era === selectedEra) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });

            // Ya no hay panel de contexto en el DOM para actualizar
        });
    }

    const btnResumen = document.getElementById('btn-era-resumen');
    if (btnResumen) {
        btnResumen.addEventListener('click', () => {
            const eraFilterVal = eraFilter ? eraFilter.value : 'all';
            if (btnResumen.dataset.contextInfo) {
                try {
                    const loreContextData = JSON.parse(btnResumen.dataset.contextInfo);
                    
                    if (eraFilterVal === 'all') {
                        let html = '';
                        for (const key in loreContextData) {
                            if (key !== 'all') {
                                const data = loreContextData[key];
                                html += `<h2>${data.title}</h2>`;
                                if (data.quote) {
                                    html += `<blockquote>"${data.quote}"</blockquote><br>`;
                                }
                                html += `<p>${data.text}</p><hr>`;
                            }
                        }
                        openModal('Resumen de Todas las Eras', 'Registro Histórico', html);
                    } else {
                        const data = loreContextData[eraFilterVal];
                        if (data) {
                            let html = '';
                            if (data.quote) {
                                html += `<blockquote>"${data.quote}"</blockquote><br><br>`;
                            }
                            html += `<p>${data.text}</p>`;
                            openModal(data.title, 'Registro Histórico', html);
                        }
                    }
                } catch(e) { console.error(e); }
            }
        });
    }
});
