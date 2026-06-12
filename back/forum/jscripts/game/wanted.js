document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('wanted-search');
    const triggers = document.querySelectorAll('.js-wanted-trigger');
    
    // Modal Elements
    const modal = document.getElementById('wanted-modal');
    const modalClose = document.getElementById('wanted-modal-close');
    const mTag = document.getElementById('wanted-modal-tag');
    const mTitle = document.getElementById('wanted-modal-title');
    const mBody = document.getElementById('wanted-modal-body');

    function openModal(title, subtitle, details) {
        mTitle.textContent = title;
        mTag.textContent = subtitle;
        mBody.innerHTML = details; // El HTML del cartel ya viene formateado
        modal.classList.remove('rpg-is-hidden');
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

    if (modalClose) modalClose.addEventListener('click', () => { modal.classList.add('rpg-is-hidden'); });
    if (modal) modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.add('rpg-is-hidden'); });

    // Buscador
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            triggers.forEach(item => {
                const txt = (item.dataset.title + ' ' + item.dataset.subtitle + ' ' + item.dataset.details).toLowerCase();
                if (txt.includes(query)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
});
