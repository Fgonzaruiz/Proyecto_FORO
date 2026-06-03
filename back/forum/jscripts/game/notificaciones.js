/**
 * Auto-extracted from back/forum/game/public/notificaciones.php
 * Config: window.NOTIFICACIONES_CONFIG
 */
(function () {
  "use strict";
  var cfg = window.NOTIFICACIONES_CONFIG || {};
  var bburl = cfg.bburl || (window.GAME_BBURL || '');
  var ajaxBase = cfg.ajaxBase || (bburl ? bburl + '/game/ajax' : '');

function notifPost(path, payload) {
    var url = ajaxBase + path;
    if (window.gamePostJson) {
        return window.gamePostJson(url, payload || {});
    }
    var body = payload || {};
    if (window.GAME_CSRF) {
        body.my_post_key = window.GAME_CSRF;
    }
    return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Mybb-Post-Key': window.GAME_CSRF || '' },
        credentials: 'same-origin',
        body: JSON.stringify(body)
    }).then(function (r) { return r.json(); });
}

function markRowRead(row) {
    if (!row) return;
    row.classList.remove('notif-unread');
    var title = row.querySelector('.notif-title');
    if (title) title.classList.remove('notif-title--bold');
}

function marcarLeida(id, el) {
    notifPost('/notifications_mark_read.php', { id: id }).then(function(d){
        if (d.ok) {
            markRowRead(document.querySelector('.notif-row[data-id="' + id + '"]'));
            actualizarBadge();
        }
    }).catch(function(){});
    return true;
}

function marcarTodasLeidas() {
    notifPost('/notifications_mark_all_read.php', {}).then(function(d){
        if (d.ok) {
            document.querySelectorAll('.notif-row').forEach(markRowRead);
            actualizarBadge();
        }
    }).catch(function(){});
}

function toggleDismiss(id, dismissed, btn) {
    notifPost('/notifications_dismiss.php', { id: id, dismissed: dismissed }).then(function(d){
        if (d.ok) {
            var icon = btn.querySelector('i');
            if (dismissed) {
                icon.className = 'fas fa-bell-slash';
                btn.title = 'Reactivar notificación';
                btn.setAttribute('onclick', 'toggleDismiss(' + id + ', false, this)');
            } else {
                icon.className = 'fas fa-bell';
                btn.title = 'Silenciar (quitar globo)';
                btn.setAttribute('onclick', 'toggleDismiss(' + id + ', true, this)');
            }
            actualizarBadge();
        }
    }).catch(function(){});
}

function deleteNotif(id, btn) {
    if (!confirm('¿Seguro que deseas borrar esta notificación?')) return;
    notifPost('/notifications_delete.php', { id: id }).then(function(d){
        if (d.ok) {
            var row = document.querySelector('.notif-row[data-id="' + id + '"]');
            if (row) row.remove();
            actualizarBadge();
        } else {
            alert('Error al borrar: ' + (d.error ? d.error.message : 'Desconocido'));
        }
    }).catch(function(){});
}

function actualizarBadge() {
    var badge = document.getElementById('notification-badge');
    if (!badge) return;
    fetch(ajaxBase + '/notifications_count.php?_t=' + Date.now())
        .then(function(r){ return r.json() })
        .then(function(d){
            if (d.ok && d.data) {
                var cnt = d.data.unread || 0;
                var bell = document.getElementById('notification-bell');
                if (cnt > 0) {
                    badge.textContent = cnt;
                    badge.classList.remove('is-hidden');
                    if (bell) bell.classList.add('has-unread');
                } else {
                    badge.classList.add('is-hidden');
                    if (bell) bell.classList.remove('has-unread');
                }
            }
        })
        .catch(function(){});
}

function resolverPropuestaTrama(notifId, action, btn) {
    if (btn.disabled) return;
    btn.disabled = true;
    var origText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ...';

    var fd = new FormData();
    fd.append('notification_id', notifId);
    fd.append('action', action);

    (window.gamePostForm
        ? window.gamePostForm(ajaxBase + '/busquedas_resolve_contact.php', fd)
        : fetch(ajaxBase + '/busquedas_resolve_contact.php', {
            method: 'POST',
            headers: { 'X-Mybb-Post-Key': window.GAME_CSRF || '' },
            credentials: 'same-origin',
            body: (function () {
                if (window.GAME_CSRF) { fd.append('my_post_key', window.GAME_CSRF); }
                return fd;
            })()
        }).then(function (r) { return r.json(); })
    ).then(function (res) {
            btn.disabled = false;
            btn.innerHTML = origText;
            if (res.ok) {
                var row = document.querySelector('.notif-row[data-id="' + notifId + '"]');
                if (row) {
                    row.classList.add('is-processed');
                    markRowRead(row);
                    var descDiv = row.querySelector('.notif-row-body');
                    if (descDiv) {
                        var statusMsg = action === 'aceptar'
                            ? '<div class="notif-status-msg notif-status-msg--ok"><i class="fas fa-check-circle"></i> Aceptaste la trama. Búsqueda eliminada.</div>'
                            : '<div class="notif-status-msg notif-status-msg--no"><i class="fas fa-info-circle"></i> Declinaste la propuesta. Sigues buscando.</div>';
                        descDiv.insertAdjacentHTML('beforeend', statusMsg);
                    }
                    var btnWrap = row.querySelector('.propuesta-btn-wrap');
                    if (btnWrap) btnWrap.remove();
                }
                actualizarBadge();
            } else {
                alert('Error: ' + res.error);
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.innerHTML = origText;
            alert('Error de conexión.');
        });
}
  window.marcarLeida = marcarLeida;
  window.marcarTodasLeidas = marcarTodasLeidas;
  window.toggleDismiss = toggleDismiss;
  window.deleteNotif = deleteNotif;
  window.resolverPropuestaTrama = resolverPropuestaTrama;

})();
