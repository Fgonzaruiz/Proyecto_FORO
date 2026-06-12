/**
 * Awakening — formulario de petición
 */
(function () {
  'use strict';

  var cfg = window.PETICION_AWAKENING_CONFIG || {};
  var bburl = (cfg.bburl || window.GAME_BBURL || '').replace(/\/$/, '');
  var form = document.getElementById('awakening-form');
  var msgEl = document.getElementById('awakening-msg');

  if (!form) return;

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = document.getElementById('awakening-submit');
    var fd = new FormData();
    
    var reqType = document.getElementById('awakening_type').value;
    var title = 'Solicitud de ' + cfg.typeLabel;
    
    fd.append('source', 'tramites_awakening');
    fd.append('request_kind', 'awakening');
    fd.append('title', title);
    
    // El "motivo" para el backend será el tipo de awakening
    fd.append('motivo', reqType);
    
    // La justificación será la propuesta de poderes
    var propuesta = document.getElementById('propuesta_poderes').value.trim();
    fd.append('justificacion', propuesta);
    
    // Link a la condición
    fd.append('link', document.getElementById('link_condicion').value.trim());

    var desc = "Tipo: " + cfg.typeLabel + "\n\nPropuesta de Poderes/Efectos:\n" + propuesta;
    fd.append('description', desc);

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
        msgEl.innerHTML = '<span class="rpg-text-success"><i class="fas fa-check-circle"></i> Petición enviada. El staff revisará tu solicitud de Awakening.</span>';
        msgEl.classList.remove('rpg-is-hidden');
        form.reset();
      } else {
        msgEl.innerHTML = '<span class="rpg-modal-title-icon"><i class="fas fa-exclamation-circle"></i> ' +
          (window.gameFormatError ? window.gameFormatError(res) : (res.error ? res.error.message : 'Error al enviar')) + '</span>';
        msgEl.classList.remove('rpg-is-hidden');
      }
    }).catch(function () {
      btn.disabled = false;
      alert('Error de conexión');
    });
  });
})();
