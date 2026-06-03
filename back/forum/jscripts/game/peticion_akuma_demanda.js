/**
 * Akuma no Mi — petición bajo demanda
 */
(function () {
  'use strict';

  var cfg = window.PETICION_AKUMA_DEMANDA_CONFIG || {};
  var bburl = (cfg.bburl || window.GAME_BBURL || '').replace(/\/$/, '');
  var selectEl = document.getElementById('akuma_fruit_id');
  var previewEl = document.getElementById('akuma-fruit-preview');
  var form = document.getElementById('akuma-demand-form');
  var msgEl = document.getElementById('akuma-demand-msg');
  var fruits = [];

  function loadFruits() {
    fetch(bburl + '/game/ajax/akuma_catalog.php', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.ok) return;
        fruits = (res.data.fruits || []).filter(function (f) { return !f.is_occupied && !f.is_reserved; });
        selectEl.innerHTML = '<option value="" disabled selected>Selecciona una fruta libre...</option>';
        fruits.forEach(function (f) {
          var opt = document.createElement('option');
          opt.value = String(f.id);
          opt.textContent = f.name + ' (' + (f.class_name || f.category) + ' · ' + (f.power_range || 'Sin asignar') + ')';
          opt.dataset.desc = f.desc || '';
          selectEl.appendChild(opt);
        });
        if (!fruits.length) {
          selectEl.innerHTML = '<option value="" disabled>No hay frutas libres</option>';
        }
      });
  }

  selectEl.addEventListener('change', function () {
    var opt = selectEl.options[selectEl.selectedIndex];
    previewEl.textContent = opt && opt.dataset.desc ? opt.dataset.desc : '';
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = document.getElementById('akuma-demand-submit');
    var fd = new FormData();
    fd.append('source', 'akuma_demand');
    fd.append('request_kind', 'fruta_diablo');
    fd.append('akuma_fruit_id', selectEl.value);
    fd.append('motivo', document.getElementById('motivo').value.trim());
    fd.append('justificacion', document.getElementById('justificacion').value.trim());
    fd.append('link', document.getElementById('link_demanda').value.trim());

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
        msgEl.innerHTML = '<span class="rpg-text-success"><i class="fas fa-check-circle"></i> Petición enviada. El staff te responderá por mensaje directo.</span>';
        msgEl.classList.remove('rpg-is-hidden');
        form.reset();
        loadFruits();
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

  loadFruits();
})();
