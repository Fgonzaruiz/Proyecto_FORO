/**
 * Staff — peticiones administrativas (Akuma + formulario general)
 */
(function () {
  'use strict';

  var cfg = window.ZONA_STAFF_PETICIONES_CONFIG || {};
  var bburl = (cfg.bburl || window.GAME_BBURL || '').replace(/\/$/, '');
  var adminList = [];

  function escapeHtml(text) {
    if (!text) return '';
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function sourceLabel(src) {

    return 'Manual';
  }

  function renderList() {
    var el = document.getElementById('admin-requests-list');
    var countEl = document.getElementById('tab-count-admin');
    if (!el) return;

    if (countEl) countEl.textContent = String(adminList.length);

    if (!adminList.length) {
      el.innerHTML = '<div class="rpg-peticiones-empty"><i class="fas fa-inbox fa-2x"></i><p>No hay peticiones administrativas pendientes.</p></div>';
      return;
    }

    var html = '<div class="rpg-admin-req-grid">';
    adminList.forEach(function (r) {
      html += '<article class="rpg-admin-req-card" data-id="' + r.id + '">';
      html += '<div class="rpg-admin-req-card-head">';
      html += '<span class="rpg-admin-req-badge">' + escapeHtml(sourceLabel(r.source)) + '</span>';
      html += '</div>';
      html += '<h3>' + escapeHtml(r.title) + '</h3>';
      html += '<p class="rpg-admin-req-pj"><i class="fas fa-user"></i> ' + escapeHtml(r.character_name) + '</p>';
      html += '<p class="rpg-admin-req-snippet">' + escapeHtml((r.description || '').substring(0, 120)) + '…</p>';
      html += '<button type="button" class="rpg-system-tab-btn rpg-system-tab-btn--compact rpg-admin-req-review-btn" data-id="' + r.id + '">Revisar</button>';
      html += '</article>';
    });
    html += '</div>';
    el.innerHTML = html;

    el.querySelectorAll('.rpg-admin-req-review-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openAdminReview(parseInt(btn.getAttribute('data-id'), 10));
      });
    });
  }

  window.loadAdminRequestsPending = function () {
    fetch(bburl + '/game/ajax/admin_requests_pending.php', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.ok) {
          document.getElementById('admin-requests-list').innerHTML =
            '<div class="rpg-error-box">Error al cargar</div>';
          return;
        }
        adminList = res.data.requests || [];
        renderList();
      });
  };

  window.openAdminReview = function (id) {
    var r = adminList.find(function (x) { return x.id === id; });
    if (!r) return;
    document.getElementById('arm-id').value = String(id);
    document.getElementById('arm-title-text').textContent = r.title;
    document.getElementById('arm-pj').textContent = r.character_name;
    document.getElementById('arm-source').textContent = sourceLabel(r.source) + ' · ' + (r.created_at || '');
    document.getElementById('arm-desc').textContent = r.description;
    document.getElementById('arm-nota').value = '';
    var av = document.getElementById('arm-avatar');
    if (r.character_avatar) av.src = r.character_avatar;
    document.getElementById('admin-review-modal').classList.add('is-open');
  };

  window.closeAdminReview = function () {
    document.getElementById('admin-review-modal').classList.remove('is-open');
  };

  window.accionAdminRequest = function (accion) {
    var id = parseInt(document.getElementById('arm-id').value, 10);
    var nota = document.getElementById('arm-nota').value.trim();
    var fd = new FormData();
    fd.append('id', String(id));
    fd.append('accion', accion);
    fd.append('nota', nota);

    var post = window.gamePostForm
      ? window.gamePostForm(bburl + '/game/ajax/admin_requests_action.php', fd)
      : fetch(bburl + '/game/ajax/admin_requests_action.php', {
          method: 'POST',
          headers: { 'X-Mybb-Post-Key': window.GAME_CSRF || '' },
          credentials: 'same-origin',
          body: (function () {
            if (window.GAME_CSRF) fd.append('my_post_key', window.GAME_CSRF);
            return fd;
          })()
        }).then(function (r) { return r.json(); });

    post.then(function (res) {
      if (res.ok) {
        closeAdminReview();
        loadAdminRequestsPending();
      } else {
        alert(window.gameFormatError ? window.gameFormatError(res) : 'Error');
      }
    });
  };

  var modal = document.getElementById('admin-review-modal');
  if (modal) {
    modal.addEventListener('click', function (e) {
      if (e.target === modal) closeAdminReview();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { loadAdminRequestsPending(); });
  } else {
    loadAdminRequestsPending();
  }
})();
