(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        var container = document.querySelector('.rpg-system-container');
        if (!container) {
            return;
        }
        var threadType = container.getAttribute('data-thread-type') || '';
        var navBtn = document.getElementById('rpg-tab-btn-navegacion');
        if (navBtn && threadType && threadType !== 'Presente') {
            navBtn.classList.add('is-disabled');
            navBtn.title = 'Navegación solo disponible en hilos de tipo Presente.';
            navBtn.onclick = function (e) { e.preventDefault(); };
        }
    });
})();
