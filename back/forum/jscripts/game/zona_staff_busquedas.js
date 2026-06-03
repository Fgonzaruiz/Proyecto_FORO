/**
 * Auto-extracted from back/forum/game/public/zona_staff_busquedas.php
 * Config: window.ZONA_STAFF_BUSQUEDAS_CONFIG
 */
(function () {
  "use strict";
  var cfg = window.ZONA_STAFF_BUSQUEDAS_CONFIG || {};
  var bburl = cfg.bburl || (window.GAME_BBURL || '');
  var busquedas_list = [];

  function loadBusquedasStaff() {
    fetch(bburl + '/game/ajax/busquedas_pending.php')
      .then(function (r) { return r.json(); })
      .then(function (res) {
        var container = document.getElementById('busquedas-staff-list');
        if (!res.ok) {
          container.innerHTML = '<div class="rpg-busquedas-error">' + (window.gameFormatError ? window.gameFormatError(res) : res.error) + '</div>';
          return;
        }
        busquedas_list = res.data;
        if (!res.data || res.data.length === 0) {
          container.innerHTML = '<div class="rpg-busquedas-empty"><i class="fas fa-check-circle fa-3x"></i><br><strong>¡Todo al día!</strong><br>No hay búsquedas pendientes de revisión.</div>';
          return;
        }
        var html = '<div class="rpg-busquedas-list">';
        res.data.forEach(function (b) {
          html += '<div class="rpg-busqueda-card rpg-busqueda-card--staff">' +
            (b.imagen_url ? '<img src="' + b.imagen_url + '" class="rpg-busqueda-thumb rpg-busqueda-thumb--staff" alt="">' : '<div class="rpg-busqueda-thumb-placeholder rpg-busqueda-thumb-placeholder--staff"><i class="fas fa-image fa-2x"></i></div>') +
            '<div class="rpg-busqueda-body">' +
              '<div class="rpg-busqueda-title rpg-busqueda-title--staff">' + b.titulo + '</div>' +
              '<div class="rpg-busqueda-meta"><img src="' + b.pj_avatar + '" class="rpg-busqueda-avatar-sm" alt=""><span>' + b.pj_name + ' · ' + b.date + '</span></div>' +
              '<div class="rpg-busqueda-desc">' + b.descripcion.substring(0, 120) + (b.descripcion.length > 120 ? '...' : '') + '</div>' +
            '</div>' +
            '<button type="button" onclick="openBusquedaReview(' + b.id + ')" class="rpg-btn-busqueda-review">Revisar</button>' +
          '</div>';
        });
        html += '</div>';
        container.innerHTML = html;
      });
  }

  function openBusquedaReview(id) {
    var b = busquedas_list.find(function (x) { return x.id === id; });
    if (!b) return;
    document.getElementById('modal-review-id').value = b.id;
    document.getElementById('modal-review-titulo').textContent = b.titulo;
    document.getElementById('modal-review-desc').textContent = b.descripcion;
    document.getElementById('modal-review-pj').textContent = b.pj_name;
    document.getElementById('modal-review-date').textContent = b.date;
    document.getElementById('modal-review-avatar').src = b.pj_avatar;
    document.getElementById('modal-review-nota').value = '';
    var img = document.getElementById('modal-review-img');
    if (b.imagen_url) {
      img.src = b.imagen_url;
      img.classList.remove('rpg-is-hidden');
    } else {
      img.classList.add('rpg-is-hidden');
    }
    document.getElementById('busqueda-review-modal').classList.add('is-open');
  }

  function closeBusquedaReview() {
    document.getElementById('busqueda-review-modal').classList.remove('is-open');
  }

  function accionBusqueda(accion) {
    var id = document.getElementById('modal-review-id').value;
    var nota = document.getElementById('modal-review-nota').value;
    var fd = new FormData();
    fd.append('id', id);
    fd.append('accion', accion);
    fd.append('nota', nota);

    (window.gamePostForm
      ? window.gamePostForm(bburl + '/game/ajax/busquedas_action.php', fd)
      : fetch(bburl + '/game/ajax/busquedas_action.php', {
          method: 'POST',
          headers: { 'X-Mybb-Post-Key': window.GAME_CSRF || '' },
          credentials: 'same-origin',
          body: (function () {
            if (window.GAME_CSRF) { fd.append('my_post_key', window.GAME_CSRF); }
            return fd;
          })()
        }).then(function (r) { return r.json(); })
    ).then(function (res) {
      if (res.ok) {
        closeBusquedaReview();
        loadBusquedasStaff();
      } else {
        alert('Error: ' + (window.gameFormatError ? window.gameFormatError(res) : res.error));
      }
    });
  }

  loadBusquedasStaff();

  window.openBusquedaReview = openBusquedaReview;
  window.closeBusquedaReview = closeBusquedaReview;
  window.accionBusqueda = accionBusqueda;
  window.loadBusquedasStaff = loadBusquedasStaff;
})();
