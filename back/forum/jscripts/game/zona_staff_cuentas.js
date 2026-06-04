/**
 * Zona staff — gestión de cuentas (nivel 3)
 */
(function () {
  'use strict';

  var app = document.getElementById('staffCuentasApp');
  if (!app) return;

  var cfg = window.ZONA_STAFF_CUENTAS_CONFIG || {};
  var base = cfg.bburl || window.GAME_BBURL || '';
  var queryEl = document.getElementById('staffAccountQuery');
  var searchBtn = document.getElementById('staffAccountSearchBtn');
  var panel = document.getElementById('staffAccountPanel');
  var empty = document.getElementById('staffAccountEmpty');
  var flash = document.getElementById('staffAccountFlash');

  var state = { account: null };

  function esc(s) {
    if (s == null) return '';
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function showFlash(type, msg) {
    flash.className = 'rpg-post-mods-container rpg-flash rpg-flash--' + type;
    flash.innerHTML = '<span class="rpg-post-mods-title">' + esc(msg) + '</span>';
    flash.classList.remove('rpg-is-hidden');
  }

  function hideFlash() {
    flash.classList.add('rpg-is-hidden');
  }

  function apiGet(url) {
    return fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }

  function apiPost(body) {
    body.my_post_key = window.GAME_CSRF || '';
    return fetch(base + '/game/ajax/staff_account_action.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    }).then(function (r) { return r.json(); });
  }

  function renderPanel(data) {
    state.account = data;
    var u = data.user;
    var cfg = data.config;
    var ban = data.ban;

    var html = '';
    html += '<div class="rpg-staff-account-head">';
    html += '<div><h2>' + esc(u.username) + '</h2>';
    html += '<div class="rpg-staff-cell-sub">UID ' + u.uid + ' &bull; ' + esc(u.email) + '</div></div>';
    html += '<div class="rpg-staff-account-badges">';
    if (data.is_banned) {
      html += '<span class="rpg-pj-card-status rpg-pj-card-status--rechazada">Baneado</span>';
    }
    if (cfg.is_narrator) {
      html += '<span class="rpg-pj-card-status rpg-pj-card-status--revision">Narrador</span>';
    }
    if (u.suspendposting) {
      html += '<span class="rpg-pj-card-status rpg-pj-card-status--pendiente">Post suspendido</span>';
    }
    if (u.moderateposts) {
      html += '<span class="rpg-pj-card-status rpg-pj-card-status--warn">Posts moderados</span>';
    }
    html += '</div></div>';

    html += '<div class="rpg-staff-account-grid">';

    html += '<section class="rpg-staff-account-card">';
    html += '<h3><i class="fas fa-gavel"></i> Baneo del foro</h3>';
    if (data.is_banned) {
      html += '<p class="rpg-staff-empty-note">Motivo: ' + esc(ban && ban.reason ? ban.reason : '—') + '</p>';
      html += '<button type="button" class="rpg-btn-approve-lg" data-action="unban"><i class="fas fa-unlock"></i> Quitar baneo</button>';
    } else {
      html += '<label class="rpg-form-label">Motivo del baneo</label>';
      html += '<input type="text" id="staffBanReason" class="textbox rpg-staff-ban-reason" placeholder="Motivo visible en moderación..." />';
      html += '<button type="button" class="rpg-btn-reject-lg rpg-staff-mt-sm" data-action="ban"><i class="fas fa-ban"></i> Banear cuenta</button>';
    }
    html += '</section>';

    html += '<section class="rpg-staff-account-card">';
    html += '<h3><i class="fas fa-book-open"></i> Narrador</h3>';
    html += '<p class="rpg-staff-empty-note">Permite usar personajes NPC mayores asignados por staff.</p>';
    if (cfg.is_narrator) {
      html += '<button type="button" class="rpg-btn-reject-lg" data-action="set_narrator" data-enabled="0"><i class="fas fa-times"></i> Quitar narrador</button>';
    } else {
      html += '<button type="button" class="rpg-btn-approve-lg" data-action="set_narrator" data-enabled="1"><i class="fas fa-check"></i> Hacer narrador</button>';
    }
    html += '</section>';

    html += '<section class="rpg-staff-account-card">';
    html += '<h3><i class="fas fa-layer-group"></i> Slots de personaje</h3>';
    html += '<p class="rpg-staff-account-slots">En uso: <strong>' + cfg.slots_used + '</strong> / Máximo: <strong id="staffMaxSlotsVal">' + cfg.max_slots + '</strong></p>';
    html += '<div class="rpg-staff-account-slot-btns">';
    html += '<button type="button" class="rpg-btn-reject-lg rpg-btn-staff-sm" data-action="adjust_slots" data-delta="-1"><i class="fas fa-minus"></i> Quitar slot</button>';
    html += '<button type="button" class="rpg-btn-approve-lg rpg-btn-staff-sm" data-action="adjust_slots" data-delta="1"><i class="fas fa-plus"></i> Añadir slot</button>';
    html += '<button type="button" class="rpg-back-btn rpg-back-btn--flat" data-action="sync_slots"><i class="fas fa-sync"></i> Recalcular uso</button>';
    html += '</div></section>';

    html += '<section class="rpg-staff-account-card">';
    html += '<h3><i class="fas fa-comment-slash"></i> Moderación de publicación</h3>';
    html += '<div class="rpg-staff-account-mod-row">';
    if (u.suspendposting) {
      html += '<button type="button" class="rpg-btn-approve-lg rpg-btn-staff-sm" data-action="suspend_posting" data-enabled="0">Quitar suspensión de posts</button>';
    } else {
      html += '<button type="button" class="rpg-btn-reject-lg rpg-btn-staff-sm" data-action="suspend_posting" data-enabled="1">Suspender publicación</button>';
    }
    if (u.moderateposts) {
      html += '<button type="button" class="rpg-btn-approve-lg rpg-btn-staff-sm" data-action="moderate_posts" data-enabled="0">Quitar moderación de posts</button>';
    } else {
      html += '<button type="button" class="rpg-btn-reject-lg rpg-btn-staff-sm" data-action="moderate_posts" data-enabled="1">Moderar nuevos posts</button>';
    }
    html += '</div></section>';

    html += '<section class="rpg-staff-account-card">';
    html += '<h3><i class="fas fa-user-slash"></i> Personaje activo</h3>';
    html += '<p class="rpg-staff-empty-note">Activo: ' + (cfg.active_pj_id ? ('ID ' + cfg.active_pj_id) : 'Ninguno') + '</p>';
    html += '<button type="button" class="rpg-back-btn rpg-back-btn--flat" data-action="clear_active_pj"><i class="fas fa-eraser"></i> Limpiar personaje activo</button>';
    html += '</section>';

    html += '</div>';

    if (cfg.is_narrator && data.npcs && data.npcs.length) {
      html += '<section class="rpg-staff-account-card rpg-staff-account-card--wide">';
      html += '<h3><i class="fas fa-users-cog"></i> NPCs asignados al narrador</h3>';
      html += '<div class="rpg-staff-npc-pick-grid" id="staffNpcAssignGrid">';
      data.npcs.forEach(function (npc) {
        var checked = data.assigned_npc_ids.indexOf(npc.id) >= 0 ? ' checked' : '';
        html += '<label class="rpg-staff-npc-pick">';
        html += '<input type="checkbox" name="staff_npc_ids" value="' + npc.id + '"' + checked + ' />';
        html += '<div class="rpg-staff-pick-body"><strong>' + esc(npc.name) + '</strong>';
        html += '<div class="rpg-staff-pick-meta">' + esc(npc.faction || 'Civil') + '</div></div></label>';
      });
      html += '</div>';
      html += '<button type="button" class="rpg-btn-approve-lg rpg-btn-submit-lg" data-action="save_npc_assignments"><i class="fas fa-save"></i> Guardar NPCs</button>';
      html += '</section>';
    }

    if (data.characters && data.characters.length) {
      html += '<section class="rpg-staff-account-card rpg-staff-account-card--wide">';
      html += '<h3><i class="fas fa-users"></i> Personajes de la cuenta</h3>';
      html += '<table class="rpg-staff-table"><thead><tr><th>Nombre</th><th>Estado</th><th>Staff PJ</th><th></th></tr></thead><tbody>';
      data.characters.forEach(function (c) {
        html += '<tr><td><strong>' + esc(c.name) + '</strong></td>';
        html += '<td>' + esc(c.status) + '</td>';
        html += '<td>' + (c.staff_level > 0 ? ('Nv. ' + c.staff_level) : '—') + '</td>';
        html += '<td><a class="rpg-btn-approve-lg rpg-btn-staff-sm" href="' + base + '/game/public/personaje.php?pj=' + c.id + '">Ver ficha</a></td></tr>';
      });
      html += '</tbody></table></section>';
    }

    panel.innerHTML = html;
    panel.classList.remove('rpg-is-hidden');
    empty.classList.add('rpg-is-hidden');

    panel.querySelectorAll('[data-action]').forEach(function (btn) {
      btn.addEventListener('click', onActionClick);
    });
  }

  function loadAccount(q) {
    hideFlash();
    apiGet(base + '/game/ajax/staff_account_lookup.php?q=' + encodeURIComponent(q))
      .then(function (res) {
        if (!res.ok) {
          showFlash('error', (res.error && res.error.message) || 'Error al buscar.');
          panel.classList.add('rpg-is-hidden');
          empty.classList.remove('rpg-is-hidden');
          return;
        }
        renderPanel(res.data);
      })
      .catch(function () {
        showFlash('error', 'Error de red al buscar la cuenta.');
      });
  }

  function onActionClick(ev) {
    var btn = ev.currentTarget;
    var action = btn.getAttribute('data-action');
    if (!state.account) return;

    var body = { target_uid: state.account.user.uid, action: action };

    if (action === 'ban') {
      var reasonEl = document.getElementById('staffBanReason');
      body.reason = reasonEl ? reasonEl.value.trim() : '';
      if (!body.reason) {
        showFlash('warn', 'Escribe un motivo de baneo.');
        return;
      }
      if (!confirm('¿Banear a ' + state.account.user.username + '?')) return;
    }
    if (action === 'unban' && !confirm('¿Quitar el baneo a esta cuenta?')) return;
    if (action === 'set_narrator') {
      body.enabled = btn.getAttribute('data-enabled') === '1';
    }
    if (action === 'suspend_posting' || action === 'moderate_posts') {
      body.enabled = btn.getAttribute('data-enabled') === '1';
    }
    if (action === 'adjust_slots') {
      body.delta = parseInt(btn.getAttribute('data-delta'), 10) || 0;
      body.action = 'adjust_slots';
    }
    if (action === 'save_npc_assignments') {
      var ids = [];
      panel.querySelectorAll('input[name="staff_npc_ids"]:checked').forEach(function (cb) {
        ids.push(parseInt(cb.value, 10));
      });
      body.npc_ids = ids;
    }
    if (action === 'clear_active_pj' && !confirm('¿Limpiar el personaje activo de esta cuenta?')) return;

    apiPost(body).then(function (res) {
      if (!res.ok) {
        showFlash('error', (res.error && res.error.message) || 'No se pudo aplicar la acción.');
        return;
      }
      showFlash('success', res.data.message || 'Guardado.');
      renderPanel(res.data.account);
    }).catch(function () {
      showFlash('error', 'Error de red.');
    });
  }

  function doSearch() {
    var q = queryEl ? queryEl.value.trim() : '';
    if (!q) {
      showFlash('warn', 'Escribe un nombre de usuario o UID.');
      return;
    }
    loadAccount(q);
  }

  if (searchBtn) searchBtn.addEventListener('click', doSearch);
  if (queryEl) {
    queryEl.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        doSearch();
      }
    });
    var pre = new URLSearchParams(window.location.search).get('uid') || new URLSearchParams(window.location.search).get('q');
    if (pre) {
      queryEl.value = pre;
      doSearch();
    }
  }
})();
