document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('manual-search');
    const tocLinks = document.querySelectorAll('.js-manual-link');
    const sections = document.querySelectorAll('.js-manual-section');

    // Marcar la primera opción del menú como activa por defecto
    if (tocLinks.length > 0) {
        tocLinks[0].classList.add('active');
    }

    // Búsqueda en el índice
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            tocLinks.forEach(link => {
                const txt = link.textContent.toLowerCase();
                if (txt.includes(query)) {
                    link.parentElement.style.display = '';
                } else {
                    link.parentElement.style.display = 'none';
                }
            });
        });
    }

    // Navegación (Tabs)
    tocLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = link.getAttribute('href').substring(1);
            
            // Quitar clase activa del menú
            tocLinks.forEach(l => l.classList.remove('active'));
            link.classList.add('active');

            // Ocultar todas las secciones y mostrar la elegida
            sections.forEach(sec => {
                sec.style.display = 'none';
                sec.classList.remove('active');
            });
            const targetSec = document.getElementById(targetId);
            if (targetSec) {
                targetSec.style.display = 'block';
                // Pequeña animación (opcional, si hay css para la clase active)
                setTimeout(() => targetSec.classList.add('active'), 10);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    });
});
