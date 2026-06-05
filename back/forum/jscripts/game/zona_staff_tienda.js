/**
 * zona_staff_tienda.js — Catálogo del bazar (añadir / quitar cartas).
 */
(function () {
  'use strict';

  var cfg = window.ZONA_STAFF_TIENDA_CONFIG || {};
  var AJAX = cfg.ajaxBase || '';

  var CAT_LABELS = {
    utiles: 'Útiles',
    armeria: 'Armería',
    naval: 'Astillero',
    mascotas: 'Criadero',
  };
  var TYPE_LABELS = {
    equipo: 'Equipo',
    npc_menor: 'NPC menor',
    barco: 'Barco',
  };

  var catalogItems = [];
  var poolItems = [];
  var catalogSearch = '';
  var catalogCat = '';
  var poolSearch = '';
  var pendingAddId = null;

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

  function defaultCategory(cardType) {
    if (cardType === 'barco') return 'naval';
    if (cardType === 'npc_menor') return 'mascotas';
    return 'utiles';
  }

  function thumbHtml(url, name) {
    if (url) {
      return '<img src="' + escapeHtml(url) + '" alt="" class="rpg-shop-catalog-thumb">';
    }
    return '<span class="rpg-shop-catalog-thumb rpg-shop-catalog-thumb--empty"><i class="fas fa-image"></i></span>';
  }

  function filteredCatalog() {
    var q = catalogSearch.trim().toLowerCase();
    return catalogItems.filter(function (item) {
      if (catalogCat && item.shop_category !== catalogCat) return false;
      if (q && item.name.toLowerCase().indexOf(q) === -1) return false;
      return true;
    });
  }

  function filteredPool() {
    var q = poolSearch.trim().toLowerCase();
    return poolItems.filter(function (item) {
      if (q && item.name.toLowerCase().indexOf(q) === -1) return false;
      return true;
    });
  }

  function renderCatalog() {
    var list = document.getElementById('shop-catalog-list');
    var loading = document.getElementById('shop-catalog-loading');
    var empty = document.getElementById('shop-catalog-empty');
    if (!list) return;

    loading.classList.add('rpg-is-hidden');
    var items = filteredCatalog();

    if (items.length === 0) {
      list.classList.add('rpg-is-hidden');
      list.innerHTML = '';
      empty.classList.remove('rpg-is-hidden');
      empty.innerHTML = catalogItems.length
        ? '<i class="fas fa-search"></i> Ninguna carta coincide con la búsqueda.'
        : '<i class="fas fa-box-open"></i> El bazar está vacío. Pulsa <strong>Añadir carta</strong> para elegir qué objetos estarán a la venta.';
      return;
    }

    empty.classList.add('rpg-is-hidden');
    list.classList.remove('rpg-is-hidden');
    list.innerHTML = '';

    items.forEach(function (item) {
      var li = document.createElement('li');
      li.className = 'rpg-shop-catalog-item';
      li.innerHTML =
        thumbHtml(item.image_url, item.name) +
        '<div class="rpg-shop-catalog-item__body">' +
          '<span class="rpg-shop-catalog-item__name">' + escapeHtml(item.name) + '</span>' +
          '<span class="rpg-shop-catalog-item__meta">' +
            escapeHtml(TYPE_LABELS[item.card_type] || item.card_type) +
            ' · ' + escapeHtml(item.rank) +
            ' · <strong>' + Number(item.cost_berries).toLocaleString('es-ES') + ' B.</strong>' +
          '</span>' +
        '</div>' +
        '<select class="textbox rpg-form-select rpg-shop-catalog-item__cat shop-cat-select" data-id="' + item.id + '" aria-label="Categoría">' +
        Object.keys(CAT_LABELS).map(function (k) {
          return '<option value="' + k + '"' + (item.shop_category === k ? ' selected' : '') + '>' + CAT_LABELS[k] + '</option>';
        }).join('') +
        '</select>' +
        '<button type="button" class="rpg-btn rpg-btn--danger rpg-btn--ghost shop-remove-btn" data-id="' + item.id + '" title="Quitar del bazar">' +
          '<i class="fas fa-trash-alt"></i> Quitar' +
        '</button>';
      list.appendChild(li);
    });

    list.querySelectorAll('.shop-cat-select').forEach(function (sel) {
      sel.addEventListener('change', function () {
        var id = parseInt(sel.dataset.id, 10);
        staffPost('shop_catalog_update.php', { card_id: id, shop_category: sel.value })
          .then(function (res) {
            if (!res.ok) {
              alert((res.error && res.error.message) || 'No se pudo guardar la categoría.');
              loadCatalog();
              return;
            }
            var row = catalogItems.find(function (x) { return x.id === id; });
            if (row) row.shop_category = sel.value;
          });
      });
    });

    list.querySelectorAll('.shop-remove-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = parseInt(btn.dataset.id, 10);
        var item = catalogItems.find(function (x) { return x.id === id; });
        if (!item) return;
        if (!confirm('¿Quitar «' + item.name + '» del bazar? Los jugadores dejarán de poder comprarla.')) return;
        btn.disabled = true;
        staffPost('shop_catalog_update.php', { card_id: id, in_shop: 0 })
          .then(function (res) {
            if (!res.ok) {
              alert((res.error && res.error.message) || 'No se pudo quitar la carta.');
              btn.disabled = false;
              return;
            }
            loadCatalog();
            loadPool();
          })
          .catch(function () {
            alert('Error de conexión.');
            btn.disabled = false;
          });
      });
    });
  }

  function renderPool() {
    var list = document.getElementById('shop-pool-list');
    var loading = document.getElementById('shop-pool-loading');
    var empty = document.getElementById('shop-pool-empty');
    var confirmBox = document.getElementById('shop-add-confirm');
    var confirmBtn = document.getElementById('shop-add-confirm-btn');
    if (!list) return;

    loading.classList.add('rpg-is-hidden');
    var items = filteredPool();

    list.innerHTML = '';
    pendingAddId = null;
    if (confirmBox) confirmBox.classList.add('rpg-is-hidden');
    if (confirmBtn) confirmBtn.classList.add('rpg-is-hidden');

    if (items.length === 0) {
      empty.classList.remove('rpg-is-hidden');
      return;
    }
    empty.classList.add('rpg-is-hidden');

    items.forEach(function (item) {
      var li = document.createElement('li');
      li.className = 'rpg-shop-pool-item';
      li.dataset.cardId = String(item.id);
      li.innerHTML =
        thumbHtml(item.image_url, item.name) +
        '<div class="rpg-shop-catalog-item__body">' +
          '<span class="rpg-shop-catalog-item__name">' + escapeHtml(item.name) + '</span>' +
          '<span class="rpg-shop-catalog-item__meta">' +
            escapeHtml(TYPE_LABELS[item.card_type] || item.card_type) +
            ' · ' + Number(item.cost_berries).toLocaleString('es-ES') + ' B.' +
          '</span>' +
        '</div>' +
        '<i class="fas fa-chevron-right rpg-shop-pool-item__arrow"></i>';
      li.addEventListener('click', function () {
        list.querySelectorAll('.rpg-shop-pool-item').forEach(function (el) {
          el.classList.remove('rpg-shop-pool-item--selected');
        });
        li.classList.add('rpg-shop-pool-item--selected');
        pendingAddId = item.id;
        var catSel = document.getElementById('shop-add-category');
        if (catSel) catSel.value = defaultCategory(item.card_type);
        var nameEl = document.getElementById('shop-add-confirm-name');
        if (nameEl) nameEl.textContent = item.name;
        if (confirmBox) confirmBox.classList.remove('rpg-is-hidden');
        if (confirmBtn) confirmBtn.classList.remove('rpg-is-hidden');
      });
      list.appendChild(li);
    });
  }

  function loadCatalog() {
    var loading = document.getElementById('shop-catalog-loading');
    if (loading) loading.classList.remove('rpg-is-hidden');
    return fetch(AJAX + '/shop_catalog_list.php?scope=active', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.ok) {
          alert((res.error && res.error.message) || 'No se pudo cargar el catálogo.');
          return;
        }
        catalogItems = (res.data && res.data.items) ? res.data.items : [];
        renderCatalog();
      });
  }

  function loadPool() {
    var loading = document.getElementById('shop-pool-loading');
    if (loading) loading.classList.remove('rpg-is-hidden');
    return fetch(AJAX + '/shop_catalog_list.php?scope=pool', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.ok) return;
        poolItems = (res.data && res.data.items) ? res.data.items : [];
        if (loading) loading.classList.add('rpg-is-hidden');
        renderPool();
      });
  }

  function openAddModal() {
    poolSearch = '';
    pendingAddId = null;
    var search = document.getElementById('shop-pool-search');
    if (search) search.value = '';
    document.getElementById('shop-add-confirm').classList.add('rpg-is-hidden');
    document.getElementById('shop-add-confirm-btn').classList.add('rpg-is-hidden');
    if (window.RpgModal) RpgModal.open('shop-add-modal');
    loadPool();
  }

  function confirmAdd() {
    if (!pendingAddId) {
      alert('Selecciona una carta de la lista.');
      return;
    }
    var cat = document.getElementById('shop-add-category').value;
    var btn = document.getElementById('shop-add-confirm-btn');
    btn.disabled = true;
    staffPost('shop_catalog_update.php', {
      card_id: pendingAddId,
      in_shop: 1,
      shop_category: cat,
    })
      .then(function (res) {
        if (!res.ok) {
          alert((res.error && res.error.message) || 'No se pudo añadir la carta.');
          return;
        }
        if (window.RpgModal) RpgModal.close('shop-add-modal');
        loadCatalog();
        loadPool();
      })
      .catch(function () {
        alert('Error de conexión.');
      })
      .finally(function () {
        btn.disabled = false;
      });
  }

  function init() {
    if (window.RpgModal) RpgModal.bind('shop-add-modal');

    document.getElementById('shop-btn-add-card').addEventListener('click', openAddModal);
    document.getElementById('shop-add-confirm-btn').addEventListener('click', confirmAdd);

    document.getElementById('shop-catalog-search').addEventListener('input', function (e) {
      catalogSearch = e.target.value;
      renderCatalog();
    });
    document.getElementById('shop-catalog-filter-cat').addEventListener('change', function (e) {
      catalogCat = e.target.value;
      renderCatalog();
    });
    document.getElementById('shop-pool-search').addEventListener('input', function (e) {
      poolSearch = e.target.value;
      renderPool();
    });

    loadCatalog();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
