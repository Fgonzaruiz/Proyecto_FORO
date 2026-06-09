(function() {
    'use strict';

    function api(path, opts) {
        opts = opts || {};
        var headers = { 'Content-Type': 'application/json' };
        if (window.GAME_CSRF) headers['X-CSRF-Token'] = window.GAME_CSRF;
        return fetch(path, Object.assign({ headers: headers, credentials: 'same-origin' }, opts)).then(function(r) { return r.json(); });
    }

    var modal = document.getElementById('oficio-modal');
    function openModal(data) {
        document.getElementById('oficio-id').value = data.id || 0;
        document.getElementById('oficio-slug').value = data.slug || '';
        document.getElementById('oficio-name').value = data.name || '';
        document.getElementById('oficio-desc').value = data.description || '';
        document.getElementById('oficio-category').value = data.category || 'oficio';
        document.getElementById('oficio-icon').value = data.icon || 'fa-briefcase';
        document.getElementById('oficio-active').checked = data.active !== 0;
        document.getElementById('oficio-modal-title').textContent = data.id ? 'Editar Oficio' : 'Nuevo Oficio';
        modal.classList.remove('is-hidden');
    }

    document.getElementById('btn-new-oficio')?.addEventListener('click', function() { openModal({}); });
    document.getElementById('oficio-modal-close')?.addEventListener('click', function() { modal.classList.add('is-hidden'); });
    modal?.querySelector('.rpg-modal-backdrop')?.addEventListener('click', function() { modal.classList.add('is-hidden'); });

    document.querySelectorAll('.btn-edit-oficio').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var tr = btn.closest('tr');
            openModal({
                id: tr.dataset.id,
                slug: tr.dataset.slug,
                name: tr.dataset.name,
                description: tr.dataset.description,
                category: tr.dataset.category,
                icon: tr.dataset.icon,
                active: parseInt(tr.dataset.active, 10)
            });
        });
    });

    document.getElementById('oficio-save-btn')?.addEventListener('click', function() {
        api('/game/ajax/oficios_save.php', {
            method: 'POST',
            body: JSON.stringify({
                my_post_key: window.GAME_CSRF,
                id: parseInt(document.getElementById('oficio-id').value, 10),
                slug: document.getElementById('oficio-slug').value,
                name: document.getElementById('oficio-name').value,
                description: document.getElementById('oficio-desc').value,
                category: document.getElementById('oficio-category').value,
                icon: document.getElementById('oficio-icon').value,
                is_active: document.getElementById('oficio-active').checked ? 1 : 0
            })
        }).then(function(res) {
            if (res.ok) location.reload();
            else alert(res.error?.message || 'Error');
        });
    });

    document.getElementById('btn-assign-oficio')?.addEventListener('click', function() {
        var charId = parseInt(document.getElementById('assign-char-id').value, 10);
        if (!charId) return alert('ID de personaje requerido');
        api('/game/ajax/character_oficios_save.php', {
            method: 'POST',
            body: JSON.stringify({
                my_post_key: window.GAME_CSRF,
                character_id: charId,
                oficio_id: parseInt(document.getElementById('assign-oficio-id').value, 10),
                rank: parseInt(document.getElementById('assign-rank').value, 10)
            })
        }).then(function(res) {
            if (res.ok) alert('Oficio asignado (Grado ' + (res.data.rank_label || '') + ')');
            else alert(res.error?.message || 'Error');
        });
    });
})();
