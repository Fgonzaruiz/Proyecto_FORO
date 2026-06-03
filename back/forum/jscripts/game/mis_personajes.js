/**
 * Auto-extracted from back/forum/game/public/mis_personajes.php
 * Config: window.MIS_PERSONAJES_CONFIG
 */
(function () {
  "use strict";
  var cfg = window.MIS_PERSONAJES_CONFIG || {};
  var bburl = cfg.bburl || (window.GAME_BBURL || '');

  function switchPJ(pjId, btn) {
    btn.disabled = true;
    btn.textContent = '...';

    var url = bburl + '/game/ajax/set_active_pj.php';
    var req = window.gamePostJson
      ? window.gamePostJson(url, { pj_id: pjId })
      : fetch(url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-Mybb-Post-Key': window.GAME_CSRF || '' },
          credentials: 'same-origin',
          body: JSON.stringify({ pj_id: pjId, my_post_key: window.GAME_CSRF || '' })
        }).then(function (r) { return r.json(); });

    req.then(function (d) {
      if (!d.ok) { alert(d.error.message); btn.disabled = false; btn.textContent = 'Seleccionar'; return; }

      document.querySelectorAll('.rpg-pj-card').forEach(function (card) {
        card.classList.remove('rpg-pj-card--active');
        var b = card.querySelector('.rpg-pj-active-badge');
        if (b) b.remove();
      });

      document.querySelectorAll('.rpg-pj-btn-active').forEach(function (span) {
        var card = span.closest('.rpg-pj-card');
        var pid = card ? card.getAttribute('data-pj-id') : null;
        var outer = span.parentNode;
        var newBtn = document.createElement('button');
        newBtn.className = 'rpg-pj-btn rpg-pj-btn-primary';
        newBtn.textContent = 'Seleccionar';
        if (pid) newBtn.setAttribute('onclick', 'switchPJ(' + pid + ', this)');
        outer.replaceChild(newBtn, span);
      });

      var card = document.querySelector('.rpg-pj-card[data-pj-id="' + pjId + '"]');
      if (card) {
        card.classList.add('rpg-pj-card--active');
        var avatar = card.querySelector('.rpg-pj-card-avatar');
        var badge = document.createElement('div');
        badge.className = 'rpg-pj-active-badge';
        badge.innerHTML = '<i class="fas fa-check-circle"></i>';
        avatar.appendChild(badge);
        var wb = document.querySelector('.nav-welcome-text');
        if (wb) {
          var text = wb.childNodes[0];
          if (text) text.textContent = ' ' + card.querySelector('.rpg-pj-card-name').textContent + ' ';
        }
        btn.outerHTML = '<span class="rpg-pj-btn rpg-pj-btn-active"><i class="fas fa-check"></i> Activo</span>';
      }
    }).catch(function () {
      alert('Error de conexión');
      btn.disabled = false;
      btn.textContent = 'Seleccionar';
    });
  }

  window.switchPJ = switchPJ;
})();
