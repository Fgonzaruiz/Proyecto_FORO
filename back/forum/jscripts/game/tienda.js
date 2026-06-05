/**
 * tienda.js — Lógica cliente del Gran Bazar del Mundo
 * Requisitos: window.TIENDA_CONFIG debe definirse antes de cargar este script.
 * F-GATE-02: sin código inline en plantillas.
 */
'use strict';

(function () {
  /* ── Config ─────────────────────────────────────────────── */
  const CFG = window.TIENDA_CONFIG || {};
  const BBURL       = CFG.bburl || '';
  const POST_KEY    = CFG.my_post_key || '';
  const CHAR_ID     = CFG.character_id || 0;
  const IS_APPROVED = CFG.is_approved || false;
  let   currentBerries = CFG.current_berries || 0;

  /* ── Estado del carrito ──────────────────────────────────── */
  // { cardId: { name, cost, cantidad, isConsumable } }
  const cart = {};

  /* ── Helpers ─────────────────────────────────────────────── */
  function formatBerries(n) {
    return Number(n).toLocaleString('es-ES') + ' B.';
  }

  function showMsg(el, text, type) {
    el.textContent = text;
    el.className = 'rpg-cart-msg rpg-shop-msg--' + type;
    el.classList.remove('rpg-is-hidden');
    setTimeout(function () { el.classList.add('rpg-is-hidden'); }, 4000);
  }

  function animateBalance() {
    const el = document.getElementById('shop-berries-value');
    if (!el) return;
    el.classList.add('rpg-balance-flash');
    setTimeout(function () { el.classList.remove('rpg-balance-flash'); }, 600);
  }

  function updateBalanceDisplay(newVal) {
    currentBerries = newVal;
    const el = document.getElementById('shop-berries-value');
    if (el) {
      el.textContent = Number(newVal).toLocaleString('es-ES');
      animateBalance();
    }
  }

  /* ── Tabs de categorías ──────────────────────────────────── */
  document.querySelectorAll('.rpg-shop-tab').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.rpg-shop-tab').forEach(function (b) {
        b.classList.remove('rpg-shop-tab--active');
      });
      document.querySelectorAll('.rpg-shop-panel').forEach(function (p) {
        p.classList.remove('rpg-shop-panel--active');
      });
      btn.classList.add('rpg-shop-tab--active');
      const panel = document.getElementById('tab-panel-' + btn.dataset.tab);
      if (panel) panel.classList.add('rpg-shop-panel--active');
    });
  });

  /* ── Toggle modo Comprar / Vender ────────────────────────── */
  const modeBuyBtn  = document.getElementById('mode-buy-btn');
  const modeSellBtn = document.getElementById('mode-sell-btn');
  const sectionBuy  = document.getElementById('shop-mode-buy');
  const sectionSell = document.getElementById('shop-mode-sell');

  function setMode(mode) {
    if (mode === 'buy') {
      modeBuyBtn.classList.add('rpg-shop-mode-btn--active');
      modeSellBtn.classList.remove('rpg-shop-mode-btn--active');
      sectionBuy.classList.remove('rpg-is-hidden');
      sectionSell.classList.add('rpg-is-hidden');
    } else {
      modeSellBtn.classList.add('rpg-shop-mode-btn--active');
      modeBuyBtn.classList.remove('rpg-shop-mode-btn--active');
      sectionSell.classList.remove('rpg-is-hidden');
      sectionBuy.classList.add('rpg-is-hidden');
    }
  }

  if (modeBuyBtn)  modeBuyBtn.addEventListener('click',  function () { setMode('buy'); });
  if (modeSellBtn) modeSellBtn.addEventListener('click', function () { setMode('sell'); });

  /* ── Carrito: añadir ─────────────────────────────────────── */
  function addToCart(btn) {
    if (!IS_APPROVED) {
      alert('Tu personaje debe estar aprobado para comprar en la tienda.');
      return;
    }
    if (CHAR_ID <= 0) {
      alert('Necesitas seleccionar un personaje activo.');
      return;
    }

    const card = btn.closest('.rpg-shop-card');
    if (!card) return;

    const cid         = card.dataset.cardId;
    const name        = card.dataset.cardName;
    const cost        = parseInt(card.dataset.cardCost, 10);
    const isConsumable = card.dataset.isConsumable === 'true';

    if (cart[cid]) {
      if (!isConsumable) {
        btn.classList.add('rpg-shop-btn-shake');
        setTimeout(function () { btn.classList.remove('rpg-shop-btn-shake'); }, 500);
        return; // objeto único: no duplicar
      }
      cart[cid].cantidad += 1;
    } else {
      cart[cid] = { name: name, cost: cost, cantidad: 1, isConsumable: isConsumable };
    }

    renderCart();
    openCartDrawer();
  }

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.rpg-shop-add-btn');
    if (btn) addToCart(btn);
  });

  /* ── Carrito: renderizar ─────────────────────────────────── */
  function renderCart() {
    const list     = document.getElementById('cart-list');
    const emptyMsg = document.getElementById('cart-empty-msg');
    const checkout = document.getElementById('cart-checkout-btn');
    const totalEl  = document.getElementById('cart-total-display');
    const fabCount = document.getElementById('cart-fab-count');

    const keys = Object.keys(cart);
    let total = 0;
    let totalUnits = 0;

    if (keys.length === 0) {
      list.innerHTML = '';
      emptyMsg.classList.remove('rpg-is-hidden');
      checkout.disabled = true;
      totalEl.innerHTML = '<i class="fas fa-coins"></i> 0 B.';
      fabCount.classList.add('rpg-is-hidden');
      fabCount.textContent = '0';
      return;
    }

    emptyMsg.classList.add('rpg-is-hidden');
    list.innerHTML = '';

    keys.forEach(function (cid) {
      const item  = cart[cid];
      const sub   = item.cost * item.cantidad;
      total      += sub;
      totalUnits += item.cantidad;

      const li = document.createElement('li');
      li.className = 'rpg-cart-item';
      li.innerHTML = [
        '<span class="rpg-cart-item-name">' + item.name + '</span>',
        '<div class="rpg-cart-item-qty">',
          item.isConsumable
            ? '<button type="button" class="rpg-cart-qty-btn" data-action="dec" data-cid="' + cid + '">-</button>'
            : '',
          '<span>' + item.cantidad + '</span>',
          item.isConsumable
            ? '<button type="button" class="rpg-cart-qty-btn" data-action="inc" data-cid="' + cid + '">+</button>'
            : '',
        '</div>',
        '<span class="rpg-cart-item-price"><i class="fas fa-coins"></i> ' + Number(sub).toLocaleString('es-ES') + ' B.</span>',
        '<button type="button" class="rpg-cart-item-remove" data-cid="' + cid + '" aria-label="Eliminar"><i class="fas fa-trash-alt"></i></button>',
      ].join('');
      list.appendChild(li);
    });

    totalEl.innerHTML = '<i class="fas fa-coins"></i> ' + Number(total).toLocaleString('es-ES') + ' B.';
    checkout.disabled = false;
    fabCount.textContent = totalUnits;
    fabCount.classList.remove('rpg-is-hidden');
  }

  /* ── Carrito: eventos de cantidad y eliminar ─────────────── */
  document.addEventListener('click', function (e) {
    // Botones qty del carrito
    const qtyBtn = e.target.closest('.rpg-cart-qty-btn');
    if (qtyBtn) {
      const cid    = qtyBtn.dataset.cid;
      const action = qtyBtn.dataset.action;
      if (!cart[cid]) return;
      if (action === 'inc') {
        cart[cid].cantidad += 1;
      } else {
        cart[cid].cantidad -= 1;
        if (cart[cid].cantidad <= 0) delete cart[cid];
      }
      renderCart();
      return;
    }
    // Eliminar item
    const removeBtn = e.target.closest('.rpg-cart-item-remove');
    if (removeBtn) {
      const cid = removeBtn.dataset.cid;
      delete cart[cid];
      renderCart();
    }
  });

  /* ── Carrito: drawer open/close ──────────────────────────── */
  const cartDrawer  = document.getElementById('cart-drawer');
  const cartOverlay = document.getElementById('cart-overlay');
  const cartFab     = document.getElementById('cart-fab');
  const cartClose   = document.getElementById('cart-close-btn');

  function openCartDrawer() {
    if (!cartDrawer) return;
    cartDrawer.classList.add('rpg-cart-drawer--open');
    cartOverlay.classList.add('rpg-cart-overlay--visible');
    cartDrawer.removeAttribute('aria-hidden');
  }

  function closeCartDrawer() {
    if (!cartDrawer) return;
    cartDrawer.classList.remove('rpg-cart-drawer--open');
    cartOverlay.classList.remove('rpg-cart-overlay--visible');
    cartDrawer.setAttribute('aria-hidden', 'true');
  }

  if (cartFab)     cartFab.addEventListener('click',     openCartDrawer);
  if (cartClose)   cartClose.addEventListener('click',   closeCartDrawer);
  if (cartOverlay) cartOverlay.addEventListener('click', closeCartDrawer);

  /* ── Checkout: comprar ───────────────────────────────────── */
  const checkoutBtn = document.getElementById('cart-checkout-btn');
  const cartMsg     = document.getElementById('cart-msg');

  if (checkoutBtn) {
    checkoutBtn.addEventListener('click', function () {
      const keys = Object.keys(cart);
      if (keys.length === 0) return;

      const cartPayload = keys.map(function (cid) {
        return { card_id: parseInt(cid, 10), cantidad: cart[cid].cantidad };
      });

      checkoutBtn.disabled = true;
      checkoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

      fetch(BBURL + '/game/ajax/tienda_comprar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          my_post_key:  POST_KEY,
          character_id: CHAR_ID,
          cart:         cartPayload,
        }),
      })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res.ok) {
            // Limpiar carrito
            Object.keys(cart).forEach(function (k) { delete cart[k]; });
            renderCart();
            updateBalanceDisplay(res.data.new_berries);
            showMsg(cartMsg, '✓ ' + (res.data.message || 'Compra realizada.'), 'success');
            setTimeout(closeCartDrawer, 1800);
          } else {
            const msg = (res.error && res.error.message) ? res.error.message : 'Error al procesar la compra.';
            showMsg(cartMsg, msg, 'error');
          }
        })
        .catch(function () {
          showMsg(cartMsg, 'Error de conexión. Inténtalo de nuevo.', 'error');
        })
        .finally(function () {
          checkoutBtn.disabled = Object.keys(cart).length === 0;
          checkoutBtn.innerHTML = '<i class="fas fa-check-circle"></i> Confirmar Compra';
        });
    });
  }

  /* ── Venta: cantidad en modo venta ───────────────────────── */
  document.addEventListener('click', function (e) {
    const qtyBtn = e.target.closest('.rpg-shop-qty-btn');
    if (!qtyBtn) return;

    const cid    = qtyBtn.dataset.cardId;
    const action = qtyBtn.dataset.action;
    const max    = parseInt(qtyBtn.dataset.max || '999', 10);
    const valEl  = document.getElementById('sell-qty-' + cid);
    if (!valEl) return;

    let val = parseInt(valEl.textContent, 10) || 1;
    if (action === 'inc') val = Math.min(val + 1, max);
    if (action === 'dec') val = Math.max(val - 1, 1);
    valEl.textContent = val;
  });

  /* ── Venta: vender objeto ────────────────────────────────── */
  document.addEventListener('click', function (e) {
    const sellBtn = e.target.closest('.rpg-shop-sell-btn');
    if (!sellBtn) return;

    if (!IS_APPROVED) {
      alert('Tu personaje debe estar aprobado para vender en la tienda.');
      return;
    }
    if (CHAR_ID <= 0) {
      alert('Necesitas seleccionar un personaje activo.');
      return;
    }

    const card    = sellBtn.closest('.rpg-shop-sell-card');
    if (!card) return;
    const cid     = card.dataset.cardId;
    const qtyEl   = document.getElementById('sell-qty-' + cid);
    const cantidad = qtyEl ? parseInt(qtyEl.textContent, 10) : 1;

    sellBtn.disabled = true;
    sellBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    fetch(BBURL + '/game/ajax/tienda_vender.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        my_post_key:  POST_KEY,
        character_id: CHAR_ID,
        card_id:      parseInt(cid, 10),
        cantidad:     cantidad,
      }),
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.ok) {
          updateBalanceDisplay(res.data.new_berries);
          // Actualizar owned count o eliminar el artículo de la lista
          const owned    = parseInt(card.dataset.owned, 10) || 1;
          const newOwned = owned - cantidad;
          if (newOwned <= 0) {
            card.classList.add('rpg-sell-card--sold');
            setTimeout(function () { card.remove(); }, 400);
          } else {
            card.dataset.owned = newOwned;
            const ownedEl = card.querySelector('.rpg-shop-sell-owned');
            if (ownedEl) ownedEl.textContent = newOwned + ' en posesión';
            if (qtyEl)   qtyEl.textContent = '1';
            const maxBtn = card.querySelector('[data-action="inc"]');
            if (maxBtn)  maxBtn.dataset.max = newOwned;
            sellBtn.disabled = false;
            sellBtn.innerHTML = '<i class="fas fa-hand-holding-usd"></i> Vender';
          }
        } else {
          const msg = (res.error && res.error.message) ? res.error.message : 'Error al procesar la venta.';
          alert(msg);
          sellBtn.disabled = false;
          sellBtn.innerHTML = '<i class="fas fa-hand-holding-usd"></i> Vender';
        }
      })
      .catch(function () {
        alert('Error de conexión. Inténtalo de nuevo.');
        sellBtn.disabled = false;
        sellBtn.innerHTML = '<i class="fas fa-hand-holding-usd"></i> Vender';
      });
  });

})();
