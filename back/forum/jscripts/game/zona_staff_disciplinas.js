(function() {
    'use strict';

    function api(path, opts) {
        opts = opts || {};
        var headers = { 'Content-Type': 'application/json' };
        if (window.GAME_CSRF) headers['X-CSRF-Token'] = window.GAME_CSRF;
        return fetch(path, Object.assign({ headers: headers, credentials: 'same-origin' }, opts)).then(function(r) { return r.json(); });
    }

    var modal = document.getElementById('disciplina-modal');
    function openModal(data) {
        document.getElementById('disciplina-id').value = data.id || 0;
        document.getElementById('disciplina-slug').value = data.slug || '';
        document.getElementById('disciplina-name').value = data.name || '';
        document.getElementById('disciplina-desc').value = data.description || '';
        document.getElementById('disciplina-category').value = data.category || 'combate';
        document.getElementById('disciplina-icon').value = data.icon || 'fa-crosshairs';
        document.getElementById('disciplina-active').checked = data.active !== 0;
        document.getElementById('disciplina-modal-title').textContent = data.id ? 'Editar Disciplina' : 'Nueva Disciplina';
        modal.classList.remove('is-hidden');
    }

    document.getElementById('btn-new-disciplina')?.addEventListener('click', function() { openModal({}); });
    document.getElementById('disciplina-modal-close')?.addEventListener('click', function() { modal.classList.add('is-hidden'); });
    modal?.querySelector('.rpg-modal-backdrop')?.addEventListener('click', function() { modal.classList.add('is-hidden'); });

    document.querySelectorAll('.btn-edit-disciplina').forEach(function(btn) {
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

    document.getElementById('disciplina-save-btn')?.addEventListener('click', function() {
        api('/game/ajax/disciplinas_save.php', {
            method: 'POST',
            body: JSON.stringify({
                my_post_key: window.GAME_CSRF,
                id: parseInt(document.getElementById('disciplina-id').value, 10),
                slug: document.getElementById('disciplina-slug').value,
                name: document.getElementById('disciplina-name').value,
                description: document.getElementById('disciplina-desc').value,
                category: document.getElementById('disciplina-category').value,
                icon: document.getElementById('disciplina-icon').value,
                is_active: document.getElementById('disciplina-active').checked ? 1 : 0
            })
        }).then(function(res) {
            if (res.ok) location.reload();
            else alert(res.error?.message || 'Error');
        });
    });

    document.getElementById('btn-assign-disciplina')?.addEventListener('click', function() {
        var charId = parseInt(document.getElementById('assign-char-id').value, 10);
        if (!charId) return alert('ID de personaje requerido');
        api('/game/ajax/character_disciplinas_save.php', {
            method: 'POST',
            body: JSON.stringify({
                my_post_key: window.GAME_CSRF,
                character_id: charId,
                disciplina_id: parseInt(document.getElementById('assign-disciplina-id').value, 10),
                rank: parseInt(document.getElementById('assign-rank').value, 10)
            })
        }).then(function(res) {
            if (res.ok) alert('Disciplina asignada (Grado ' + (res.data.rank_label || '') + ')');
            else alert(res.error?.message || 'Error');
        });
    });
})();
