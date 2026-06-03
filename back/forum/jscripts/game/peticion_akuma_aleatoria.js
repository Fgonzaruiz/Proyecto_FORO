/**
 * Akuma no Mi — Reconstrucción completa del frontend (Grid & Modal)
 */
(function () {
  'use strict';

  var cfg = window.PETICION_AKUMA_ALEATORIA_CONFIG || {};
  var bburl = (cfg.bburl || window.GAME_BBURL || '').replace(/\/$/, '');

  document.addEventListener('DOMContentLoaded', function () {
    var catalogEl = document.getElementById('akuma-catalog');
    var statsListEl = document.getElementById('akuma-stats-list');
    var filterEl = document.getElementById('akuma-filter-tabs');
    var searchInput = document.getElementById('akuma-search-input');
    
    var rollBtn = document.getElementById('akuma-roll-btn');
    var countBadge = document.getElementById('akuma-available-count');
    var blockedEl = document.getElementById('akuma-roll-blocked');
    var stageEl = document.getElementById('akuma-roll-stage');
    var spinnerEl = document.getElementById('akuma-roll-spinner');
    var statusEl = document.getElementById('akuma-roll-status');
    var resultEl = document.getElementById('akuma-roll-result');
    var subtitleEl = document.getElementById('akuma-roll-subtitle');

    var modal = document.getElementById("lib-modal");
    var modalClose = document.getElementById("modal-close");
    var modalBanner = document.getElementById("modal-banner");
    var modalTitle = document.getElementById("modal-title");
    var modalBadge = document.getElementById("modal-badge");
    var modalDetails = document.getElementById("modal-details");
    var modalStats = document.getElementById("modal-stats");

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
      var list = fruits;
      
      // Filter by category tab
      if (activeFilter !== 'all') {
        list = list.filter(function (f) { return f.category === activeFilter; });
      }
      
      // Filter by search query
      if (searchInput && searchInput.value) {
        var query = searchInput.value.toLowerCase().trim();
        list = list.filter(function (f) {
          return f.name.toLowerCase().includes(query) || 
                 (f.class_name && f.class_name.toLowerCase().includes(query)) ||
                 (f.desc && f.desc.toLowerCase().includes(query));
        });
      }
      
      return list;
    }

    function renderStatsList(stats) {
      if (!statsListEl || !stats) return;
      statsListEl.innerHTML =
        '<div class="rpg-akuma-stat-item">' +
        '  <span class="rpg-akuma-stat-name"><i class="fas fa-apple-alt"></i> Total registradas</span>' +
        '  <strong class="rpg-akuma-stat-val">' + stats.total + '</strong>' +
        '</div>' +
        '<div class="rpg-akuma-stat-item rpg-akuma-stat-item--libre">' +
        '  <span class="rpg-akuma-stat-name"><i class="fas fa-check-circle"></i> Disponibles</span>' +
        '  <strong class="rpg-akuma-stat-val">' + stats.libre + '</strong>' +
        '</div>' +
        '<div class="rpg-akuma-stat-item rpg-akuma-stat-item--reservada">' +
        '  <span class="rpg-akuma-stat-name"><i class="fas fa-clock"></i> Reservadas</span>' +
        '  <strong class="rpg-akuma-stat-val">' + stats.reservada + '</strong>' +
        '</div>' +
        '<div class="rpg-akuma-stat-item rpg-akuma-stat-item--ocupada">' +
        '  <span class="rpg-akuma-stat-name"><i class="fas fa-times-circle"></i> Ocupadas</span>' +
        '  <strong class="rpg-akuma-stat-val">' + stats.ocupada + '</strong>' +
        '</div>';
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
          renderCatalogGrid();
        });
      });
    }

    function renderCatalogGrid() {
      var list = filteredFruits();
      if (!list.length) {
        catalogEl.innerHTML =
          '<div class="rpg-akuma-empty">' +
          '  <div class="rpg-akuma-empty-icon"><i class="fas fa-apple-alt"></i></div>' +
          '  <p>No se encontraron frutas.</p>' +
          '</div>';
        return;
      }

      var html = '<div class="rpg-akuma-grid">';
      list.forEach(function (f) {
        var st = statusLabel(f);
        var icon = categoryIcon(f.category);
        var cat = escapeHtml(f.category);
        var typeLabel = escapeHtml(f.class_name || categoryLabel(f.category));
        var range = escapeHtml(f.power_range || '—');
        var occupiedClass = f.is_occupied ? ' is-occupied' : '';
        var reservedClass = f.is_reserved ? ' is-reserved' : '';

        html += '<div class="rpg-akuma-card' + occupiedClass + reservedClass + '" data-id="' + f.id + '">';
        
        // Circular Type Icon
        html += '  <div class="rpg-akuma-card-icon-wrap rpg-akuma-card-icon-wrap--' + cat + '">';
        html += '    <i class="fas ' + icon + '"></i>';
        html += '  </div>';

        // Content Area
        html += '  <div class="rpg-akuma-card-content">';
        html += '    <h4 class="rpg-akuma-card-title">' + escapeHtml(f.name) + '</h4>';
        html += '    <div class="rpg-akuma-card-tags">';
        html += '      <span class="rpg-akuma-badge rpg-akuma-badge--' + cat + '">' + typeLabel + '</span>';
        html += '      <span class="rpg-akuma-range-badge">Rango ' + range + '</span>';
        html += '    </div>';
        html += '  </div>';

        // Status Pill
        html += '  <div class="rpg-akuma-card-status">';
        html += '    <span class="rpg-akuma-state-pill rpg-akuma-state-pill--' + st.className + '">';
        html += '      <i class="fas ' + st.icon + '"></i> ' + st.text;
        html += '    </span>';
        html += '  </div>';

        html += '</div>';
      });
      html += '</div>';
      catalogEl.innerHTML = html;

      // Click event to show modal
      catalogEl.querySelectorAll('.rpg-akuma-card').forEach(function (card) {
        card.addEventListener('click', function () {
          var id = parseInt(card.getAttribute('data-id'), 10);
          var fruit = fruits.find(function (f) { return f.id === id; });
          if (fruit) {
            openFruitModal(fruit);
          }
        });
      });
    }

    function openFruitModal(fruit) {
      if (!modal) return;
      var bannerUrl = bburl + '/images/game/akuma_banner.png';
      modalBanner.setAttribute("data-bg", bannerUrl);
      if (window.applyRpgDataAttrs) window.applyRpgDataAttrs(modalBanner);
      modalTitle.textContent = fruit.name;
      modalBadge.textContent = fruit.class_name || categoryLabel(fruit.category);
      modalDetails.textContent = fruit.details || fruit.desc || 'Sin descripción detallada en el registro.';

      modalStats.innerHTML = "";
      var statsData = {
        'Clase': fruit.class_name || categoryLabel(fruit.category),
        'Rango': fruit.power_range,
        'Estado': fruit.is_occupied ? 'Ocupada' : (fruit.is_reserved ? 'Reservada' : 'Libre'),
        'Efectos': fruit.tipo_fruta || 'Por asignar'
      };

      Object.entries(statsData).forEach(function (entry) {
        var statBox = document.createElement("div");
        statBox.className = "rpg-lib-modal-stat-box";
        statBox.innerHTML = '<div class="rpg-lib-modal-stat-lbl">' + entry[0] + '</div><div class="rpg-lib-modal-stat-val">' + entry[1] + '</div>';
        modalStats.appendChild(statBox);
      });

      modal.classList.add("open");
      document.body.classList.add("modal-open");
    }

    if (modalClose) {
      modalClose.addEventListener("click", function () {
        modal.classList.remove("open");
        document.body.classList.remove("modal-open");
      });
    }

    if (modal) {
      modal.addEventListener("click", function (e) {
        if (e.target === modal) {
          modal.classList.remove("open");
          document.body.classList.remove("modal-open");
        }
      });
    }

    if (searchInput) {
      searchInput.addEventListener('input', function () {
        renderCatalogGrid();
      });
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
              '<p>Error al cargar el catálogo.</p></div>';
            return;
          }
          fruits = res.data.fruits || [];
          available = fruits.filter(function (f) { return !f.is_occupied && !f.is_reserved; });
          canRoll = res.data.roll ? res.data.roll.can_roll !== false : true;
          rollBlockReason = (res.data.roll && res.data.roll.reason) ? res.data.roll.reason : '';
          countBadge.textContent = available.length + ' en pool';
          renderStatsList(res.data.stats);
          renderFilters();
          renderCatalogGrid();
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
        (fruit.class_name || fruit.class || '') + (fruit.power_range ? ' · Rango ' + fruit.power_range : '');
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
          if (won) {
            won.is_reserved = true;
          }
          available = fruits.filter(function (f) { return !f.is_occupied && !f.is_reserved; });
          countBadge.textContent = available.length + ' en pool';
          renderStatsList({
            total: fruits.length,
            libre: fruits.filter(function (f) { return !f.is_occupied && !f.is_reserved; }).length,
            reservada: fruits.filter(function (f) { return f.is_reserved && !f.is_occupied; }).length,
            ocupada: fruits.filter(function (f) { return f.is_occupied; }).length
          });
          renderCatalogGrid();
        });
      }).catch(function () {
        rolling = false;
        updateRollUi();
        alert('Error de conexión');
      });
    }

    if (rollBtn) rollBtn.addEventListener('click', submitRoll);
    loadCatalog();
  });
})();
