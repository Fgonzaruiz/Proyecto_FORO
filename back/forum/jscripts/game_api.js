/**
 * Helpers AJAX del módulo game (CSRF MyBB en POST JSON).
 */
(function (global) {
    'use strict';

    function gamePostJson(url, payload) {
        var body = payload || {};
        if (global.GAME_CSRF) {
            body.my_post_key = global.GAME_CSRF;
        }
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Mybb-Post-Key': global.GAME_CSRF || ''
            },
            credentials: 'same-origin',
            body: JSON.stringify(body)
        }).then(function (r) {
            return r.json();
        });
    }

    function gamePostForm(url, formData) {
        var fd = formData instanceof FormData ? formData : new FormData();
        if (global.GAME_CSRF && !fd.has('my_post_key')) {
            fd.append('my_post_key', global.GAME_CSRF);
        }
        return fetch(url, {
            method: 'POST',
            headers: { 'X-Mybb-Post-Key': global.GAME_CSRF || '' },
            credentials: 'same-origin',
            body: fd
        }).then(function (r) {
            return r.json();
        });
    }

    function gameFormatError(res) {
        var e = res && res.error;
        if (e && typeof e === 'object') {
            return e.message || String(e.code || 'Error');
        }
        return e || 'Error desconocido';
    }

    global.gamePostJson = gamePostJson;
    global.gamePostForm = gamePostForm;
    global.gameFormatError = gameFormatError;
})(typeof window !== 'undefined' ? window : this);
