/**
 * Auto-extracted from back/forum/game/public/anuncios_staff.php
 * Config: window.ANUNCIOS_STAFF_CONFIG
 */
(function () {
  "use strict";
  var cfg = window.ANUNCIOS_STAFF_CONFIG || {};
  var bburl = cfg.bburl || (window.GAME_BBURL || '');
function escapeHtml(text) {
  if (!text) return '';
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function loadAnnouncements() {
    fetch(bburl + '/game/ajax/announcements_list.php')
        .then(r => r.json())
        .then(res => {
            const list = document.getElementById('announcements-list');
            if (res.ok && res.data && res.data.length > 0) {
                let html = '';
                res.data.forEach(a => {
                    html += `
                        <div class="rpg-anuncio-item">
                            <div>
                                <div class="rpg-anuncio-date"><i class="far fa-clock"></i> ${escapeHtml(a.date)}</div>
                                <div class="rpg-anuncio-title">${escapeHtml(a.title)}</div>
                                <div class="rpg-anuncio-body">${escapeHtml(a.content)}</div>
                            </div>
                            <div>
                                <button type="button" onclick="deleteAnnouncement(${a.id})" class="rpg-system-tab-btn rpg-btn-danger-outline">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            </div>
                        </div>
                    `;
                });
                list.innerHTML = html;
            } else {
                list.innerHTML = '<div class="rpg-anuncios-loading">No hay anuncios publicados.</div>';
            }
        });
}

function saveAnnouncement() {
    const title = document.getElementById('ann_title').value;
    const content = document.getElementById('ann_content').value;
    
    if (!title || !content) {
        alert("Rellena todos los campos");
        return;
    }
    
    (window.gamePostJson
        ? window.gamePostJson(bburl + '/game/ajax/announcements_save.php', { action: 'create', title: title, content: content })
        : fetch(bburl + '/game/ajax/announcements_save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Mybb-Post-Key': window.GAME_CSRF || '' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'create', title: title, content: content, my_post_key: window.GAME_CSRF || '' })
        }).then(function (r) { return r.json(); })
    ).then(function (res) {
        if (res.ok) {
            document.getElementById('ann_title').value = '';
            document.getElementById('ann_content').value = '';
            loadAnnouncements();
        } else {
            alert("Error: " + (res.error ? res.error.message : "Desconocido"));
        }
    });
}

function deleteAnnouncement(id) {
    if (!confirm("¿Seguro que quieres borrar este anuncio?")) return;
    
    (window.gamePostJson
        ? window.gamePostJson(bburl + '/game/ajax/announcements_save.php', { action: 'delete', id: id })
        : fetch(bburl + '/game/ajax/announcements_save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Mybb-Post-Key': window.GAME_CSRF || '' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'delete', id: id, my_post_key: window.GAME_CSRF || '' })
        }).then(function (r) { return r.json(); })
    ).then(function (res) {
        if (res.ok) {
            loadAnnouncements();
        } else {
            alert("Error al borrar");
        }
    });
}

document.addEventListener('DOMContentLoaded', loadAnnouncements);

  window.loadAnnouncements = loadAnnouncements;
  window.saveAnnouncement = saveAnnouncement;
  window.deleteAnnouncement = deleteAnnouncement;

})();
