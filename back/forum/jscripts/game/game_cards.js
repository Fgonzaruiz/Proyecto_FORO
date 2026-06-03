/**
 * @deprecated Legacy shim — el motor activo es foro_deck_ui.js (headerinclude).
 * Se mantiene por compatibilidad si alguna página antigua lo referencia directamente.
 */
(function () {
    if (typeof RpgCards !== 'undefined') {
        return;
    }
    console.warn('[game_cards.js] Deprecated: carga foro_deck_ui.js desde headerinclude.');
})();
