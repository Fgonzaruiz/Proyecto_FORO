/**
 * Auto-extracted from back/forum/game/public/peticiones_general.php
 * Config: window.PETICIONES_GENERAL_CONFIG
 */
(function () {
  "use strict";
  var cfg = window.PETICIONES_GENERAL_CONFIG || {};
  var bburl = cfg.bburl || (window.GAME_BBURL || '');

  function openBusquedaModal(e) {
    e.preventDefault();
    document.getElementById('busqueda-modal').classList.add('is-open');
    document.getElementById('busqueda-titulo').value = '';
    document.getElementById('busqueda-imagen').value = '';
    document.getElementById('busqueda-desc').value = '';
    document.getElementById('busqueda-msg').classList.add('rpg-is-hidden');
    document.getElementById('busqueda-btn').classList.remove('is-success');
  }

  function closeBusquedaModal() {
    document.getElementById('busqueda-modal').classList.remove('is-open');
  }

  function submitBusqueda() {
    var titulo = document.getElementById('busqueda-titulo').value.trim();
    var imagen = document.getElementById('busqueda-imagen').value.trim();
    var desc = document.getElementById('busqueda-desc').value.trim();
    var msg = document.getElementById('busqueda-msg');
    var btn = document.getElementById('busqueda-btn');

    if (titulo.length < 3) {
      msg.innerHTML = '<span class="rpg-modal-title-icon"><i class="fas fa-exclamation-circle"></i> El título es demasiado corto.</span>';
      msg.classList.remove('rpg-is-hidden');
      return;
    }
    if (desc.length < 10) {
      msg.innerHTML = '<span class="rpg-modal-title-icon"><i class="fas fa-exclamation-circle"></i> La descripción es demasiado corta.</span>';
      msg.classList.remove('rpg-is-hidden');
      return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';

    var fd = new FormData();
    fd.append('titulo', titulo);
    fd.append('imagen_url', imagen);
    fd.append('descripcion', desc);

    (window.gamePostForm
      ? window.gamePostForm(bburl + '/game/ajax/busquedas_submit.php', fd)
      : fetch(bburl + '/game/ajax/busquedas_submit.php', {
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
      if (res.ok) {
        btn.innerHTML = '<i class="fas fa-check"></i> ¡Enviado!';
        btn.classList.add('is-success');
        msg.innerHTML = '<span class="rpg-text-success"><i class="fas fa-check-circle"></i> Tu búsqueda ha sido enviada al staff para revisión.</span>';
        msg.classList.remove('rpg-is-hidden');
        setTimeout(closeBusquedaModal, 2000);
      } else {
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar al Staff';
        msg.innerHTML = '<span class="rpg-modal-title-icon"><i class="fas fa-exclamation-circle"></i> ' + res.error + '</span>';
        msg.classList.remove('rpg-is-hidden');
      }
    });
  }

  document.getElementById('busqueda-modal').addEventListener('click', function (e) {
    if (e.target === this) closeBusquedaModal();
  });

  window.openBusquedaModal = openBusquedaModal;
  window.closeBusquedaModal = closeBusquedaModal;
  window.submitBusqueda = submitBusqueda;
})();
