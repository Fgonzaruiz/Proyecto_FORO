/**
 * Akuma no Mi — tirada aleatoria + catálogo visual
 */
(function () {
  'use strict';

  var cfg = window.PETICION_AKUMA_ALEATORIA_CONFIG || {};
  var bburl = (cfg.bburl || window.GAME_BBURL || '').replace(/\/$/, '');

  var catalogEl = document.getElementById('akuma-catalog');
  var rollBtn = document.getElementById('akuma-roll-btn');
  var countBadge = document.getElementById('akuma-available-count');
  var stageEl = document.getElementById('akuma-roll-stage');
  var spinnerEl = document.getElementById('akuma-roll-spinner');
  var statusEl = document.getElementById('akuma-roll-status');
  var resultEl = document.getElementById('akuma-roll-result');

  var fruits = [];
  var available = [];
  var rolling = false;

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

  function groupByCategory(list) {
    var groups = { logia: [], zoan: [], paramecia: [] };
    list.forEach(function (f) {
      var c = f.category || 'paramecia';
      if (!groups[c]) groups[c] = [];
      groups[c].push(f);
    });
    return groups;
  }

  function groupByRange(list) {
    var ranges = {};
    list.forEach(function (f) {
      var r = f.power_range || 'Sin asignar';
      if (!ranges[r]) ranges[r] = [];
      ranges[r].push(f);
    });
    return ranges;
  }

  function renderCatalog() {
    var groups = groupByCategory(fruits);
    var html = '';
    ['logia', 'zoan', 'paramecia'].forEach(function (cat) {
      var items = groups[cat] || [];
      if (!items.length) return;
      html += '<section class="rpg-akuma-cat-section rpg-akuma-cat-section--' + cat + '">';
      html += '<h2 class="rpg-akuma-cat-title"><i class="fas fa-layer-group"></i> ' + categoryLabel(cat) + '</h2>';
      var byRange = groupByRange(items);
      Object.keys(byRange).sort().forEach(function (range) {
        html += '<div class="rpg-akuma-range-block">';
        html += '<h3 class="rpg-akuma-range-title">' + escapeHtml(range) + '</h3>';
        html += '<div class="rpg-akuma-fruit-grid">';
        byRange[range].forEach(function (f) {
          var occupied = f.is_occupied;
          var reserved = f.is_reserved;
          var stateClass = occupied ? ' is-occupied' : (reserved ? ' is-reserved' : ' is-free');
          html += '<article class="rpg-akuma-fruit-card' + stateClass + '" data-fruit-id="' + f.id + '">';
          html += '<div class="rpg-akuma-fruit-card-head">';
          html += '<span class="rpg-akuma-fruit-type">' + escapeHtml(f.class_name || f.class) + '</span>';
          if (occupied) {
            html += '<span class="rpg-akuma-fruit-badge rpg-akuma-fruit-badge--busy">Ocupada</span>';
          } else if (reserved) {
            html += '<span class="rpg-akuma-fruit-badge rpg-akuma-fruit-badge--reserved">Reservada</span>';
          } else {
            html += '<span class="rpg-akuma-fruit-badge rpg-akuma-fruit-badge--free">Libre</span>';
          }
          html += '</div>';
          html += '<h4>' + escapeHtml(f.name) + '</h4>';
          html += '<p>' + escapeHtml((f.desc || '').substring(0, 140)) + (f.desc && f.desc.length > 140 ? '…' : '') + '</p>';
          html += '</article>';
        });
        html += '</div></div>';
      });
      html += '</section>';
    });
    catalogEl.innerHTML = html || '<p class="rpg-peticiones-empty">No hay frutas en el catálogo.</p>';
  }

  function loadCatalog() {
    fetch(bburl + '/game/ajax/akuma_catalog.php', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.ok || !res.data) {
          catalogEl.innerHTML = '<p class="rpg-peticiones-empty">Error al cargar el catálogo.</p>';
          return;
        }
        fruits = res.data.fruits || [];
        available = fruits.filter(function (f) { return !f.is_occupied && !f.is_reserved; });
        countBadge.textContent = available.length + ' disponibles';
        rollBtn.disabled = available.length === 0;
        if (available.length === 0) {
          document.getElementById('akuma-roll-subtitle').textContent =
            'No quedan frutas libres para tirada aleatoria.';
        }
        renderCatalog();
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
    rollBtn.disabled = true;
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
    if (rolling || available.length === 0) return;
    rolling = true;
    rollBtn.disabled = true;
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
        rollBtn.disabled = available.length === 0;
        alert(window.gameFormatError ? window.gameFormatError(res) : (res.error && res.error.message) || 'Error');
        return;
      }
      var fruit = res.data.fruit;
      runRollAnimation(fruit.name, function () {
        showResult(fruit);
        var won = fruits.find(function (f) { return f.id === fruit.id; });
        if (won) won.is_reserved = true;
        available = fruits.filter(function (f) { return !f.is_occupied && !f.is_reserved; });
        countBadge.textContent = available.length + ' disponibles';
        renderCatalog();
      });
    }).catch(function () {
      rolling = false;
      rollBtn.disabled = false;
      alert('Error de conexión');
    });
  }

  if (rollBtn) rollBtn.addEventListener('click', submitRoll);
  loadCatalog();
})();
