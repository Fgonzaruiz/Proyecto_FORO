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

  function openFastEdit(pjId, name, avatar, signature) {
    var modal = document.getElementById('fast-edit-modal');
    if (!modal) return;
    
    document.getElementById('fast-edit-pj-id').value = pjId;
    document.getElementById('fast-edit-avatar').value = avatar || '';
    document.getElementById('fast-edit-firma').value = signature || '';
    
    modal.querySelector('.rpg-fast-edit-modal__header h3').innerHTML = '<i class="fas fa-user-edit"></i> Editar Perfil de ' + name;
    modal.classList.add('is-open');
  }

  function closeFastEdit() {
    var modal = document.getElementById('fast-edit-modal');
    if (modal) modal.classList.remove('is-open');
  }

  function saveFastEdit(e) {
    if (e) e.preventDefault();
    var btn = document.getElementById('fast-edit-save-btn');
    if (!btn || btn.disabled) return;
    
    var pjId = document.getElementById('fast-edit-pj-id').value;
    var avatar = document.getElementById('fast-edit-avatar').value;
    var signature = document.getElementById('fast-edit-firma').value;
    
    btn.disabled = true;
    btn.textContent = 'Guardando...';
    
    var url = bburl + '/game/ajax/save_avatar_sig.php';
    var payload = {
        pj_id: parseInt(pjId),
        avatar: avatar,
        firma: signature,
        my_post_key: window.GAME_CSRF || ''
    };
    
    var req = window.gamePostJson
      ? window.gamePostJson(url, payload)
      : fetch(url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-Mybb-Post-Key': window.GAME_CSRF || '' },
          credentials: 'same-origin',
          body: JSON.stringify(payload)
        }).then(function (r) { return r.json(); });
        
    req.then(function (d) {
      if (!d.ok) {
        alert(d.error && d.error.message ? d.error.message : 'Error al guardar');
        btn.disabled = false;
        btn.textContent = 'Guardar Cambios';
        return;
      }
      btn.textContent = '¡Cambios guardados!';
      setTimeout(closeFastEdit, 800);
    }).catch(function () {
      alert('Error de conexión');
      btn.disabled = false;
      btn.textContent = 'Guardar Cambios';
    });
  }

  window.openFastEdit = openFastEdit;
  window.closeFastEdit = closeFastEdit;
  window.saveFastEdit = saveFastEdit;

  function toggleCharTab(showNpc) {
    var ownGrid = document.getElementById('char-grid-own');
    var npcGrid = document.getElementById('char-grid-npc');
    var lblOwn = document.getElementById('label-own');
    var lblNpc = document.getElementById('label-npc');
    var toggle = document.getElementById('char-type-toggle');
    
    if (toggle) {
      toggle.checked = showNpc;
    }
    
    if (showNpc) {
      if (ownGrid) ownGrid.classList.remove('active');
      if (npcGrid) npcGrid.classList.add('active');
      if (lblOwn) lblOwn.classList.remove('active');
      if (lblNpc) lblNpc.classList.add('active');
    } else {
      if (ownGrid) ownGrid.classList.add('active');
      if (npcGrid) npcGrid.classList.remove('active');
      if (lblOwn) lblOwn.classList.add('active');
      if (lblNpc) lblNpc.classList.remove('active');
    }
  }

  function setNarratorSwitch(showNpc) {
    toggleCharTab(showNpc);
  }

  window.toggleCharTab = toggleCharTab;
  window.setNarratorSwitch = setNarratorSwitch;
  window.switchCharTab = function(tab) {
    toggleCharTab(tab !== 'own');
  };

  document.addEventListener('DOMContentLoaded', function () {
    var auto = cfg.autoOpenEdit;
    if (!auto || !window.openFastEdit) return;
    if (auto.isNpc && window.toggleCharTab) {
      window.toggleCharTab(true);
    }
    window.openFastEdit(auto.id, auto.name, auto.avatar, auto.signature || '');
  });
})();
