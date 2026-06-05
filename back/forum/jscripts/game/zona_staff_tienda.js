/**
 * zona_staff_tienda.js — Catálogo de venta (staff admin).
 */
(function () {
  'use strict';

  var cfg = window.ZONA_STAFF_TIENDA_CONFIG || {};
  var AJAX = cfg.ajaxBase || '';

  var TYPE_LABELS = {
    equipo: 'Equipo',
    npc_menor: 'NPC menor',
    barco: 'Barco',
  };
  var CAT_LABELS = {
    utiles: 'Útiles',
    armeria: 'Armería',
    naval: 'Astillero',
    mascotas: 'Criadero',
  };

  var allItems = [];
  var searchQ = '';
  var filterCat = '';
  var filterStatus = '';

  function staffPost(endpoint, data) {
    var url = AJAX + '/' + String(endpoint).replace(/^\//, '');
    if (window.gamePostJson) {
      return window.gamePostJson(url, data || {});
    }
    var body = data || {};
    if (window.GAME_CSRF) body.my_post_key = window.GAME_CSRF;
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Mybb-Post-Key': window.GAME_CSRF || '' },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    }).then(function (r) { return r.json(); });
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function filteredItems() {
    var q = searchQ.trim().toLowerCase();
    return allItems.filter(function (item) {
      if (filterCat && item.shop_category !== filterCat) return false;
      if (filterStatus === '1' && !item.in_shop) return false;
      if (filterStatus === '0' && item.in_shop) return false;
      if (q && item.name.toLowerCase().indexOf(q) === -1) return false;
      return true;
    });
  }

  function render() {
    var tbody = document.getElementById('shop-manage-tbody');
    var wrap = document.getElementById('shop-manage-wrap');
    var empty = document.getElementById('shop-manage-empty');
    var loading = document.getElementById('shop-manage-loading');
    if (!tbody) return;

    var list = filteredItems();
    loading.classList.add('rpg-is-hidden');

    if (list.length === 0) {
      wrap.classList.add('rpg-is-hidden');
      empty.classList.remove('rpg-is-hidden');
      empty.textContent = allItems.length
        ? 'Sin resultados con los filtros actuales.'
        : 'No hay cartas comerciables con precio. Define el coste en berries al crear/editar cartas de equipo, NPC o barco.';
      tbody.innerHTML = '';
      return;
    }

    empty.classList.add('rpg-is-hidden');
    wrap.classList.remove('rpg-is-hidden');
    tbody.innerHTML = '';

    list.forEach(function (item) {
      var tr = document.createElement('tr');
      tr.dataset.cardId = String(item.id);
      var img = item.image_url
        ? '<img src="' + escapeHtml(item.image_url) + '" alt="" class="rpg-shop-manage-thumb">'
        : '<span class="rpg-shop-manage-thumb rpg-staff-catalog-empty"></span>';
      tr.innerHTML =
        '<td><div class="rpg-shop-manage-name-cell">' + img +
        '<strong>' + escapeHtml(item.name) + '</strong></div></td>' +
        '<td>' + escapeHtml(TYPE_LABELS[item.card_type] || item.card_type) + '</td>' +
        '<td>' + Number(item.cost_berries).toLocaleString('es-ES') + '</td>' +
        '<td><select class="textbox rpg-shop-manage-cat-select shop-cat-select" data-id="' + item.id + '">' +
        Object.keys(CAT_LABELS).map(function (k) {
          return '<option value="' + k + '"' + (item.shop_category === k ? ' selected' : '') + '>' + CAT_LABELS[k] + '</option>';
        }).join('') +
        '</select></td>' +
        '<td><label class="rpg-shop-toggle" title="En venta en el bazar">' +
        '<input type="checkbox" class="shop-in-sale" data-id="' + item.id + '"' + (item.in_shop ? ' checked' : '') + '>' +
        '<span class="rpg-shop-toggle-slider"></span></label></td>';
      tbody.appendChild(tr);
    });

    tbody.querySelectorAll('.shop-in-sale').forEach(function (cb) {
      cb.addEventListener('change', function () {
        var id = parseInt(cb.dataset.id, 10);
        staffPost('shop_catalog_update.php', { card_id: id, in_shop: cb.checked ? 1 : 0 }).then(function (res) {
          if (!res.ok) {
            cb.checked = !cb.checked;
            alert((res.error && res.error.message) || 'Error al actualizar.');
            return;
          }
          var row = allItems.find(function (x) { return x.id === id; });
          if (row) row.in_shop = cb.checked ? 1 : 0;
        });
      });
    });

    tbody.querySelectorAll('.shop-cat-select').forEach(function (sel) {
      sel.addEventListener('change', function () {
        var id = parseInt(sel.dataset.id, 10);
        staffPost('shop_catalog_update.php', { card_id: id, shop_category: sel.value }).then(function (res) {
          if (!res.ok) {
            alert((res.error && res.error.message) || 'Error al actualizar categoría.');
            load();
            return;
          }
          var row = allItems.find(function (x) { return x.id === id; });
          if (row) row.shop_category = sel.value;
        });
      });
    });
  }

  function load() {
    document.getElementById('shop-manage-loading').classList.remove('rpg-is-hidden');
    document.getElementById('shop-manage-wrap').classList.add('rpg-is-hidden');
    fetch(AJAX + '/shop_catalog_list.php', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.ok) {
          alert((res.error && res.error.message) || 'No se pudo cargar el catálogo.');
          return;
        }
        allItems = res.data.items || [];
        render();
      })
      .catch(function () {
        alert('Error de conexión al cargar el catálogo.');
      });
  }

  function init() {
    document.getElementById('shop-manage-search').addEventListener('input', function (e) {
      searchQ = e.target.value;
      render();
    });
    document.getElementById('shop-manage-filter-cat').addEventListener('change', function (e) {
      filterCat = e.target.value;
      render();
    });
    document.getElementById('shop-manage-filter-status').addEventListener('change', function (e) {
      filterStatus = e.target.value;
      render();
    });
    load();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
