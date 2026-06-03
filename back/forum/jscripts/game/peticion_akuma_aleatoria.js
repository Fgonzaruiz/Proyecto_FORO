/**
 * Akuma no Mi — tirada aleatoria + catálogo en tabla compacta
 */
(function () {
  'use strict';

  var cfg = window.PETICION_AKUMA_ALEATORIA_CONFIG || {};
  var bburl = (cfg.bburl || window.GAME_BBURL || '').replace(/\/$/, '');

  var catalogEl = document.getElementById('akuma-catalog');
  var statsEl = document.getElementById('akuma-stats-row');
  var filterEl = document.getElementById('akuma-filter-tabs');
  var rollBtn = document.getElementById('akuma-roll-btn');
  var countBadge = document.getElementById('akuma-available-count');
  var blockedEl = document.getElementById('akuma-roll-blocked');
  var stageEl = document.getElementById('akuma-roll-stage');
  var spinnerEl = document.getElementById('akuma-roll-spinner');
  var statusEl = document.getElementById('akuma-roll-status');
  var resultEl = document.getElementById('akuma-roll-result');
  var subtitleEl = document.getElementById('akuma-roll-subtitle');

  var fruits = [];
  var available = [];
  var rolling = false;
  var canRoll = true;
  var rollBlockReason = '';
  var activeFilter = 'all';

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function categoryLabel(key) {
    if (key === 'logia') return 'Logia';
    if (key === 'zoan') return 'Zoan';
    return 'Paramecia';
  }

  function categoryIcon(key) {
    if (key === 'logia') return 'fa-fire';
    if (key === 'zoan') return 'fa-paw';
    return 'fa-atom';
  }

  function statusLabel(f) {
    if (f.is_occupied) return { text: 'Ocupada', icon: 'fa-times-circle', className: 'ocupada' };
    if (f.is_reserved) return { text: 'Reservada', icon: 'fa-clock', className: 'reservada' };
    return { text: 'Libre', icon: 'fa-check-circle', className: 'libre' };
  }

  function filteredFruits() {
    if (activeFilter === 'all') return fruits;
    return fruits.filter(function (f) { return f.category === activeFilter; });
  }

  function renderStats(stats) {
    if (!statsEl || !stats) return;
    statsEl.innerHTML =
      '<span class="rpg-akuma-stat"><strong>' + stats.total + '</strong> total</span>' +
      '<span class="rpg-akuma-stat rpg-akuma-stat--libre"><strong>' + stats.libre + '</strong> libres</span>' +
      '<span class="rpg-akuma-stat rpg-akuma-stat--reservada"><strong>' + stats.reservada + '</strong> reservadas</span>' +
      '<span class="rpg-akuma-stat rpg-akuma-stat--ocupada"><strong>' + stats.ocupada + '</strong> ocupadas</span>';
  }

  function renderFilters() {
    if (!filterEl) return;
    var tabs = [
      { key: 'all', label: 'Todas' },
      { key: 'logia', label: 'Logia' },
      { key: 'zoan', label: 'Zoan' },
      { key: 'paramecia', label: 'Paramecia' }
    ];
    filterEl.innerHTML = tabs.map(function (t) {
      var active = activeFilter === t.key ? ' is-active' : '';
      return '<button type="button" class="rpg-akuma-filter-btn' + active + '" data-filter="' + t.key + '">' + t.label + '</button>';
    }).join('');
    filterEl.querySelectorAll('.rpg-akuma-filter-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        activeFilter = btn.getAttribute('data-filter') || 'all';
        renderFilters();
        renderCatalogTable();
      });
    });
  }

  function renderCatalogTable() {
    var list = filteredFruits();
    if (!list.length) {
      catalogEl.innerHTML =
        '<div class="rpg-akuma-empty">' +
        '<div class="rpg-akuma-empty-icon"><i class="fas fa-apple-alt"></i></div>' +
        '<p>No hay frutas en esta categor&iacute;a.</p></div>';
      return;
    }

    var html =
      '<table class="rpg-akuma-table">' +
      '<thead><tr>' +
      '<th class="rpg-akuma-th-icon" scope="col" aria-label="Tipo"></th>' +
      '<th scope="col">Fruta del Diablo</th>' +
      '<th scope="col">Clase</th>' +
      '<th scope="col">Rango</th>' +
      '<th scope="col">Estado</th>' +
      '<th scope="col">Descripci&oacute;n</th>' +
      '</tr></thead><tbody>';

    list.forEach(function (f) {
      var st = statusLabel(f);
      var icon = categoryIcon(f.category);
      var cat = escapeHtml(f.category);
      var typeLabel = escapeHtml(f.class_name || categoryLabel(f.category));
      var range = escapeHtml(f.power_range || '\u2014');

      html += '<tr class="rpg-akuma-table-row rpg-akuma-table-row--' + cat + ' rpg-akuma-table-row--' + st.className + '">';
      html += '<td class="rpg-akuma-td-icon">';
      html += '<span class="rpg-akuma-row-icon rpg-akuma-row-icon--' + cat + '" title="' + typeLabel + '">';
      html += '<i class="fas ' + icon + '"></i></span></td>';
      html += '<td class="rpg-akuma-td-name"><span class="rpg-akuma-name-text">' + escapeHtml(f.name) + '</span></td>';
      html += '<td class="rpg-akuma-td-type"><span class="rpg-akuma-type-pill rpg-akuma-type-pill--' + cat + '">' + typeLabel + '</span></td>';
      html += '<td class="rpg-akuma-td-range"><span class="rpg-akuma-range-tag">' + range + '</span></td>';
      html += '<td class="rpg-akuma-td-state"><span class="rpg-akuma-state-pill rpg-akuma-state-pill--' + st.className + '">';
      html += '<i class="fas ' + st.icon + '"></i> ' + st.text + '</span></td>';
      html += '<td class="rpg-akuma-td-desc">' + escapeHtml(f.desc || '\u2014') + '</td>';
      html += '</tr>';
    });

    html += '</tbody></table>';
    catalogEl.innerHTML = html;
  }

  function updateRollUi() {
    var poolOk = available.length > 0;
    rollBtn.disabled = rolling || !canRoll || !poolOk;
    if (!canRoll) {
      rollBtn.innerHTML = '<i class="fas fa-ban"></i> Tirada no disponible';
      if (blockedEl) {
        blockedEl.innerHTML = '<i class="fas fa-lock"></i> ' + escapeHtml(rollBlockReason);
        blockedEl.classList.remove('rpg-is-hidden');
      }
      if (subtitleEl) subtitleEl.textContent = rollBlockReason;
    } else if (!poolOk) {
      rollBtn.innerHTML = '<i class="fas fa-dice"></i> Sin frutas libres';
      if (blockedEl) blockedEl.classList.add('rpg-is-hidden');
    } else {
      rollBtn.innerHTML = '<i class="fas fa-dice"></i> ¡Tirar aleatorio!';
      if (blockedEl) blockedEl.classList.add('rpg-is-hidden');
    }
  }

  function loadCatalog() {
    fetch(bburl + '/game/ajax/akuma_catalog.php', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.ok || !res.data) {
          catalogEl.innerHTML =
            '<div class="rpg-akuma-empty"><div class="rpg-akuma-empty-icon"><i class="fas fa-exclamation-triangle"></i></div>' +
            '<p>Error al cargar el cat&aacute;logo.</p></div>';
          return;
        }
        fruits = res.data.fruits || [];
        available = fruits.filter(function (f) { return !f.is_occupied && !f.is_reserved; });
        canRoll = res.data.roll ? res.data.roll.can_roll !== false : true;
        rollBlockReason = (res.data.roll && res.data.roll.reason) ? res.data.roll.reason : '';
        countBadge.textContent = available.length + ' en pool';
        renderStats(res.data.stats);
        renderFilters();
        renderCatalogTable();
        updateRollUi();
        if (canRoll && available.length === 0 && subtitleEl) {
          subtitleEl.textContent = 'No quedan frutas libres para tirada aleatoria.';
        }
      })
      .catch(function () {
        catalogEl.innerHTML = '<p class="rpg-peticiones-empty">Error de conexión.</p>';
      });
  }

  function buildSpinnerNames(pool, winnerName) {
    var names = [];
    var i;
    for (i = 0; i < 28; i++) {
      names.push(pool[Math.floor(Math.random() * pool.length)].name);
    }
    names.push(winnerName);
    spinnerEl.innerHTML = names.map(function (n) {
      return '<span class="rpg-akuma-roll-name">' + escapeHtml(n) + '</span>';
    }).join('');
  }

  function showResult(fruit) {
    document.getElementById('akuma-result-name').textContent = fruit.name;
    document.getElementById('akuma-result-meta').textContent =
      (fruit.class_name || fruit.class || '') + (fruit.power_range ? ' · ' + fruit.power_range : '');
    document.getElementById('akuma-result-desc').textContent = fruit.desc || '';
    resultEl.classList.remove('rpg-is-hidden');
    stageEl.classList.add('rpg-is-hidden');
    canRoll = false;
    rollBlockReason = 'Ya realizaste tu tirada aleatoria con este personaje.';
    updateRollUi();
    rolling = false;
  }

  function runRollAnimation(winnerName, onDone) {
    stageEl.classList.remove('rpg-is-hidden');
    resultEl.classList.add('rpg-is-hidden');
    statusEl.textContent = '¡La ruleta gira!';
    buildSpinnerNames(available, winnerName);
    spinnerEl.classList.remove('is-stopping');
    spinnerEl.style.animation = 'none';
    void spinnerEl.offsetWidth;
    spinnerEl.style.animation = '';
    spinnerEl.classList.add('is-spinning');

    setTimeout(function () {
      spinnerEl.classList.remove('is-spinning');
      spinnerEl.classList.add('is-stopping');
      statusEl.textContent = '¡Resultado!';
      setTimeout(onDone, 900);
    }, 3200);
  }

  function submitRoll() {
    if (rolling || !canRoll || available.length === 0) return;
    rolling = true;
    updateRollUi();
    resultEl.classList.add('rpg-is-hidden');

    var fd = new FormData();
    var post = window.gamePostForm
      ? window.gamePostForm(bburl + '/game/ajax/akuma_roll.php', fd)
      : fetch(bburl + '/game/ajax/akuma_roll.php', {
          method: 'POST',
          headers: { 'X-Mybb-Post-Key': window.GAME_CSRF || '' },
          credentials: 'same-origin',
          body: (function () {
            if (window.GAME_CSRF) fd.append('my_post_key', window.GAME_CSRF);
            return fd;
          })()
        }).then(function (r) { return r.json(); });

    post.then(function (res) {
      if (!res.ok) {
        rolling = false;
        if (res.error && res.error.message && res.error.message.indexOf('tirada') !== -1) {
          canRoll = false;
          rollBlockReason = res.error.message;
        }
        updateRollUi();
        alert(window.gameFormatError ? window.gameFormatError(res) : (res.error && res.error.message) || 'Error');
        return;
      }
      var fruit = res.data.fruit;
      runRollAnimation(fruit.name, function () {
        showResult(fruit);
        var won = fruits.find(function (f) { return f.id === fruit.id; });
        if (won) won.is_reserved = true;
        available = fruits.filter(function (f) { return !f.is_occupied && !f.is_reserved; });
        countBadge.textContent = available.length + ' en pool';
        renderStats({
          total: fruits.length,
          libre: fruits.filter(function (f) { return !f.is_occupied && !f.is_reserved; }).length,
          reservada: fruits.filter(function (f) { return f.is_reserved && !f.is_occupied; }).length,
          ocupada: fruits.filter(function (f) { return f.is_occupied; }).length
        });
        renderCatalogTable();
      });
    }).catch(function () {
      rolling = false;
      updateRollUi();
      alert('Error de conexión');
    });
  }

  if (rollBtn) rollBtn.addEventListener('click', submitRoll);
  loadCatalog();
})();
