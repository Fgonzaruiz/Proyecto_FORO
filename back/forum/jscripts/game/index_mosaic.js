(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.hxh-location-mosaic').forEach(function (mosaic) {
            var cards = mosaic.querySelectorAll('.hxh-manga-panel[data-coord-x][data-coord-y]');
            if (cards.length < 1) {
                return;
            }
            mosaic.classList.add('hxh-location-mosaic--linked');
            cards.forEach(function (card, idx) {
                var x = parseInt(card.getAttribute('data-coord-x') || '0', 10);
                var y = parseInt(card.getAttribute('data-coord-y') || '0', 10);
                if (x > 0 || y > 0) {
                    card.style.setProperty('--loc-x', String(x));
                    card.style.setProperty('--loc-y', String(y));
                    card.style.setProperty('--loc-order', String(idx));
                }
            });
        });
    });
})();
