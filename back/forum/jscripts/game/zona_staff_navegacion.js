(function() {
    'use strict';

    var cfg = window.NAV_STAFF_CONFIG || {};
    var ajaxBase = cfg.ajaxBase || (window.GAME_AJAX_BASE || '');

    function apiUrl(path) {
        var p = String(path || '').replace(/^\//, '');
        if (ajaxBase) {
            return ajaxBase.replace(/\/$/, '') + '/' + p;
        }
        return '/' + p;
    }

    function api(path) {
        return fetch(apiUrl(path), { credentials: 'same-origin' })
            .then(function(r) {
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status);
                }
                return r.json();
            });
    }

    function postApi(path, body) {
        var headers = { 'Content-Type': 'application/json' };
        if (window.GAME_CSRF) headers['X-CSRF-Token'] = window.GAME_CSRF;
        return fetch(apiUrl(path), {
            method: 'POST',
            headers: headers,
            credentials: 'same-origin',
            body: JSON.stringify(body)
        }).then(function(r) {
            if (!r.ok) {
                throw new Error('HTTP ' + r.status);
            }
            return r.json();
        });
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function reviewLabel(review) {
        if (review === 'approved') return 'Aprobado';
        if (review === 'denied') return 'Denegado';
        return 'Pendiente';
    }

    function showListError(el, message) {
        if (!el) return;
        el.innerHTML = '<p class="rpg-staff-info rpg-flash--error"><i class="fas fa-exclamation-circle"></i> ' + escapeHtml(message) + '</p>';
    }

    function loadVoyages() {
        var el = document.getElementById('voyages-list');
        if (!el) return;

        if (!ajaxBase) {
            showListError(el, 'Configuración AJAX no disponible (ajaxBase).');
            return;
        }

        el.innerHTML = '<p class="rpg-staff-info">Cargando…</p>';

        var tid = document.getElementById('filter-thread')?.value || '';
        var cid = document.getElementById('filter-char')?.value || '';
        var review = document.getElementById('filter-review')?.value || '';
        var q = 'navigation_voyages_list.php?';
        if (tid) q += 'thread_id=' + encodeURIComponent(tid) + '&';
        if (cid) q += 'character_id=' + encodeURIComponent(cid) + '&';
        if (review) q += 'staff_review=' + encodeURIComponent(review) + '&';

        api(q).then(function(res) {
            var badge = document.getElementById('nav-pending-badge');
            if (!res.ok) {
                showListError(el, res.error?.message || 'No se pudieron cargar los viajes.');
                return;
            }

            if (badge && res.data && typeof res.data.pending_count === 'number') {
                badge.textContent = res.data.pending_count + ' pendiente(s)';
            }

            var list = (res.data && res.data.voyages) || [];
            if (!list.length) {
                el.innerHTML = '<p class="rpg-staff-info">Sin viajes registrados.</p>';
                return;
            }

            var html = '<table class="rpg-staff-table"><thead><tr>';
            html += '<th>ID</th><th>PJ</th><th>Ruta</th><th>Días IC</th><th>Fin on-rol previsto</th><th>Revisión</th><th>Post</th><th></th>';
            html += '</tr></thead><tbody>';

            list.forEach(function(v) {
                var review = v.staff_review || 'pending';
                html += '<tr>';
                html += '<td>' + v.id + '</td>';
                html += '<td>' + escapeHtml(v.char_name || v.character_id) + '</td>';
                html += '<td>' + escapeHtml(v.from_name || '') + ' → ' + escapeHtml(v.to_name || '') + '</td>';
                html += '<td>' + (v.duration_days || 0) + '</td>';
                html += '<td>' + escapeHtml(v.expected_end_rol_label || '—') + '</td>';
                html += '<td><span class="rpg-nav-review rpg-nav-review--' + review + '">' + reviewLabel(review) + '</span></td>';
                html += '<td>';
                if (v.post_url) {
                    html += '<a href="' + escapeHtml(v.post_url) + '" target="_blank" rel="noopener">Ver post</a>';
                } else {
                    html += '—';
                }
                html += '</td><td>';
                if (review === 'pending') {
                    html += '<button type="button" class="rpg-btn--primary rpg-btn--sm btn-nav-approve" data-id="' + v.id + '">Aprobar</button> ';
                    html += '<button type="button" class="rpg-btn--secondary rpg-btn--sm btn-nav-deny" data-id="' + v.id + '">Denegar</button>';
                } else {
                    html += '<span class="rpg-staff-info">' + escapeHtml(v.status || '') + '</span>';
                }
                html += '</td></tr>';
            });

            html += '</tbody></table>';
            el.innerHTML = html;

            el.querySelectorAll('.btn-nav-approve').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var id = parseInt(btn.dataset.id, 10);
                    if (!confirm('¿Aprobar la navegación? Se publicará un mensaje automático en el hilo.')) return;
                    postApi('navigation_voyage_review.php', {
                        my_post_key: window.GAME_CSRF,
                        id: id,
                        decision: 'approve'
                    }).then(function(r) {
                        if (r.ok) loadVoyages();
                        else alert(r.error?.message || 'Error');
                    }).catch(function() { alert('Error de conexión.'); });
                });
            });

            el.querySelectorAll('.btn-nav-deny').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var id = parseInt(btn.dataset.id, 10);
                    if (!confirm('¿Denegar la navegación? Se publicará un mensaje automático en el hilo.')) return;
                    postApi('navigation_voyage_review.php', {
                        my_post_key: window.GAME_CSRF,
                        id: id,
                        decision: 'deny'
                    }).then(function(r) {
                        if (r.ok) loadVoyages();
                        else alert(r.error?.message || 'Error');
                    }).catch(function() { alert('Error de conexión.'); });
                });
            });
        }).catch(function(err) {
            showListError(el, 'Error al cargar viajes: ' + (err.message || 'conexión'));
        });
    }

    document.getElementById('btn-filter-voyages')?.addEventListener('click', loadVoyages);
    document.getElementById('filter-review')?.addEventListener('change', loadVoyages);
    loadVoyages();
})();
