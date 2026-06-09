(function() {
    'use strict';

    var cfg = window.RUTAS_STAFF_CONFIG || {};
    var ajaxBase = cfg.ajaxBase || '';

    function apiUrl(path) {
        var p = String(path || '').replace(/^\//, '');
        if (ajaxBase) {
            return ajaxBase.replace(/\/$/, '') + '/' + p;
        }
        return '/' + p;
    }

    function api(path, opts) {
        opts = opts || {};
        var headers = { 'Content-Type': 'application/json' };
        if (cfg.csrf) {
            headers['X-Mybb-Post-Key'] = cfg.csrf;
        }
        return fetch(apiUrl(path), Object.assign({ headers: headers, credentials: 'same-origin' }, opts)).then(function(r) { return r.json(); });
    }

    function loadRoutes() {
        api('navigation_routes_list.php').then(function(res) {
            var el = document.getElementById('routes-list');
            if (!el || !res.ok) {
                if (el) el.innerHTML = '<p class="rpg-staff-info rpg-flash--error"><i class="fas fa-exclamation-circle"></i> Error al cargar rutas.</p>';
                return;
            }
            var routes = res.data.routes || [];
            if (!routes.length) {
                el.innerHTML = '<p class="rpg-staff-info">No hay rutas definidas. Se usará distancia euclidiana.</p>';
                return;
            }
            var html = '<table class="rpg-staff-table"><thead><tr><th>Origen</th><th>Destino</th><th>Dist.</th><th>Peligro</th><th></th></tr></thead><tbody>';
            routes.forEach(function(r) {
                html += '<tr><td>' + (r.from_name || r.island_from_fid) + '</td><td>' + (r.to_name || r.island_to_fid) + '</td><td>' + r.distance + '</td><td>' + (r.danger_override || '—') + '</td>';
                html += '<td><button type="button" class="rpg-btn--secondary rpg-btn--sm btn-del-route" data-id="' + r.id + '">Eliminar</button></td></tr>';
            });
            html += '</tbody></table>';
            el.innerHTML = html;
            el.querySelectorAll('.btn-del-route').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    if (!confirm('¿Eliminar ruta?')) return;
                    api('navigation_routes_save.php', {
                        method: 'POST',
                        body: JSON.stringify({ my_post_key: cfg.csrf, id: parseInt(btn.dataset.id, 10), delete: true })
                    }).then(function() { loadRoutes(); });
                });
            });
        });
    }

    document.getElementById('route-save-btn')?.addEventListener('click', function() {
        var danger = document.getElementById('route-danger').value;
        api('navigation_routes_save.php', {
            method: 'POST',
            body: JSON.stringify({
                my_post_key: cfg.csrf,
                island_from_fid: parseInt(document.getElementById('route-from').value, 10),
                island_to_fid: parseInt(document.getElementById('route-to').value, 10),
                distance: parseInt(document.getElementById('route-distance').value, 10),
                danger_override: danger ? parseInt(danger, 10) : null
            })
        }).then(function(res) {
            if (res.ok) { loadRoutes(); alert('Ruta guardada'); }
            else alert(res.error?.message || 'Error');
        });
    });

    loadRoutes();
})();
