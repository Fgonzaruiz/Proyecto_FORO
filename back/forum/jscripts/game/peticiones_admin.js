/**
 * Petición administrativa (formulario general)
 */
(function () {
  'use strict';

  var cfg = window.PETICIONES_ADMIN_CONFIG || {};
  var bburl = (cfg.bburl || window.GAME_BBURL || '').replace(/\/$/, '');
  var form = document.getElementById('peticion-admin-form');
  var msgEl = document.getElementById('peticion-admin-msg');

  var kindLabels = {
    creacion_personaje: 'Creación de personaje',
    modificacion_personaje: 'Modificación de personaje',
    eliminacion_personaje: 'Eliminación de personaje',
    fruta_diablo: 'Fruta del diablo',
    haki: 'Haki',
    objeto: 'Objeto / equipamiento',
    mision: 'Misión',
    evento: 'Evento',
    sancion: 'Apelación / sanción',
    otro: 'Otro'
  };

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var kind = document.getElementById('tipo_peticion').value;
    var titulo = document.getElementById('titulo_admin').value.trim();
    var desc = document.getElementById('descripcion').value.trim();
    var link = document.getElementById('link').value.trim();
    var btn = document.getElementById('peticion-admin-submit');

    if (!titulo) {
      titulo = kindLabels[kind] || 'Petición administrativa';
    }

    var fd = new FormData();
    fd.append('source', 'manual');
    fd.append('request_kind', kind);
    fd.append('title', titulo);
    fd.append('description', desc);
    fd.append('link', link);

    btn.disabled = true;
    msgEl.classList.add('rpg-is-hidden');

    var post = window.gamePostForm
      ? window.gamePostForm(bburl + '/game/ajax/admin_requests_submit.php', fd)
      : fetch(bburl + '/game/ajax/admin_requests_submit.php', {
          method: 'POST',
          headers: { 'X-Mybb-Post-Key': window.GAME_CSRF || '' },
          credentials: 'same-origin',
          body: (function () {
            if (window.GAME_CSRF) fd.append('my_post_key', window.GAME_CSRF);
            return fd;
          })()
        }).then(function (r) { return r.json(); });

    post.then(function (res) {
      btn.disabled = false;
      if (res.ok) {
        msgEl.innerHTML = '<span class="rpg-text-success"><i class="fas fa-check-circle"></i> Petición enviada correctamente.</span>';
        msgEl.classList.remove('rpg-is-hidden');
        form.reset();
      } else {
        msgEl.innerHTML = '<span class="rpg-modal-title-icon"><i class="fas fa-exclamation-circle"></i> ' +
          (window.gameFormatError ? window.gameFormatError(res) : 'Error') + '</span>';
        msgEl.classList.remove('rpg-is-hidden');
      }
    }).catch(function () {
      btn.disabled = false;
      alert('Error de conexión');
    });
  });
})();
