document.addEventListener('DOMContentLoaded', function() {
    const filterBtns = document.querySelectorAll('.rpg-btn-filter');
    const cards = document.querySelectorAll('.rpg-crew-card');

    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            filterBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const filter = this.getAttribute('data-filter');
            
            cards.forEach(card => {
                if (filter === 'all') {
                    card.style.display = 'flex';
                } else {
                    const factions = card.getAttribute('data-factions').split('|');
                    if (factions.includes(filter)) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                }
            });
        });
    });
});
