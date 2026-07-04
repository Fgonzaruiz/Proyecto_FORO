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
  let   currentJenny = CFG.current_jenny || 0;
  const CARDS_BY_ID = CFG.cardsById || {};
  const TIENDA_PREVIEW_DEBUG = CFG.debug === true || /[?&]tienda_debug=1/i.test(window.location.search);
  if (TIENDA_PREVIEW_DEBUG) {
    console.log('[Tienda preview] Modo debug activo (?tienda_debug=1). Historial en window.__TIENDA_PREVIEW_LOG');
  }

  /* ── Estado del carrito ──────────────────────────────────── */
  // { cardId: { name, cost, cantidad, isConsumable } }
  const cart = {};

  /* ── Helpers ─────────────────────────────────────────────── */
  function formatJenny(n) {
    return Number(n).toLocaleString('es-ES') + ' Jenny';
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
    currentJenny = newVal;
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

  /* ── Buscador global (todas las categorías) ─────────────── */
  const catalogSearch = document.getElementById('shop-catalog-search');
  if (catalogSearch) {
    catalogSearch.addEventListener('input', function () {
      const q = catalogSearch.value.trim().toLowerCase();
      document.querySelectorAll('.rpg-shop-card').forEach(function (card) {
        const name = (card.dataset.cardName || '').toLowerCase();
        card.classList.toggle('rpg-is-hidden', q.length > 0 && name.indexOf(q) === -1);
      });
      document.querySelectorAll('.rpg-shop-panel').forEach(function (panel) {
        const visible = panel.querySelectorAll('.rpg-shop-card:not(.rpg-is-hidden)').length;
        const empty = panel.querySelector('.rpg-shop-empty');
        if (empty) {
          empty.classList.toggle('rpg-is-hidden', visible > 0);
        }
      });
    });
  }

  /* ── Vista previa de carta (como en posts) ───────────────── */
  let previewCardId = null;
  let lastPreviewDiagnostics = null;

  function shopPreviewLog(level, message, data) {
    const prefix = '[Tienda preview]';
    if (level === 'error') {
      console.error(prefix, message, data !== undefined ? data : '');
    } else if (level === 'warn') {
      console.warn(prefix, message, data !== undefined ? data : '');
    } else {
      console.log(prefix, message, data !== undefined ? data : '');
    }
    if (TIENDA_PREVIEW_DEBUG) {
      if (!window.__TIENDA_PREVIEW_LOG) window.__TIENDA_PREVIEW_LOG = [];
      window.__TIENDA_PREVIEW_LOG.push({
        t: new Date().toISOString(),
        level: level,
        message: message,
        data: data,
      });
    }
  }

  function setPreviewDiagnostics(info) {
    lastPreviewDiagnostics = info;
    window.__TIENDA_PREVIEW_LAST = info;
    shopPreviewLog(info.ok ? 'log' : 'error', info.reason || (info.ok ? 'ok' : 'unknown'), info);
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function previewRenderErrorHtml() {
    const d = lastPreviewDiagnostics || { reason: 'unknown' };
    let detail = d.reason || 'desconocido';
    if (d.errorMessage) detail += '\nError: ' + d.errorMessage;
    if (d.htmlLength != null) detail += '\nLongitud HTML: ' + d.htmlLength;
    if (d.cardId != null) detail += '\nCarta ID: ' + d.cardId;
    if (d.card_type) detail += '\nTipo: ' + d.card_type;
    if (d.rpgCardsReady === false) detail += '\nRpgCards no disponible (¿foro_deck_ui.js cargado?)';
    if (d.normalizedSample) {
      detail += '\nDatos normalizados: ' + JSON.stringify(d.normalizedSample, null, 2);
    }
    shopPreviewLog('error', 'UI muestra error de render', d);
    return (
      '<p class="rpg-shop-empty">No se pudo renderizar la vista de la carta.</p>' +
      '<details class="rpg-shop-preview-debug" open>' +
        '<summary>Motivo (también en consola F12 → filtrar «Tienda preview»)</summary>' +
        '<pre class="rpg-shop-preview-debug__pre">' + escapeHtml(detail) + '</pre>' +
      '</details>'
    );
  }

  function getRpgCardsEngine() {
    if (typeof window !== 'undefined' && window.RpgCards) {
      return window.RpgCards;
    }
    if (typeof RpgCards !== 'undefined') {
      return RpgCards;
    }
    return null;
  }

  function ensureRpgCardsReady() {
    const engine = getRpgCardsEngine();
    if (!engine) {
      shopPreviewLog('error', 'RpgCards no disponible', {
        hasWindowRpgCards: !!(typeof window !== 'undefined' && window.RpgCards),
        typeofGlobalRpgCards: typeof RpgCards,
        scripts: Array.prototype.slice.call(document.querySelectorAll('script[src*="foro_deck"]')).map(function (s) { return s.src; }),
      });
      return false;
    }
    if (typeof engine.renderCard !== 'function') {
      shopPreviewLog('error', 'RpgCards.renderCard no es función', { keys: Object.keys(engine) });
      return false;
    }
    if (BBURL) engine.config.baseUrl = BBURL;
    if (!engine.config.baseUrl) {
      const gameIdx = window.location.pathname.toLowerCase().indexOf('/game/');
      if (gameIdx !== -1) {
        engine.config.baseUrl = window.location.origin + window.location.pathname.substring(0, gameIdx);
      }
    }
    shopPreviewLog('log', 'RpgCards listo', { baseUrl: engine.config.baseUrl });
    return true;
  }

  function normalizeCardForRender(card) {
    if (!card || typeof card !== 'object') return null;
    const c = Object.assign({}, card);

    if (typeof c.effects === 'string') {
      try { c.effects = JSON.parse(c.effects); } catch (e) { c.effects = {}; }
    }
    if (!c.effects || typeof c.effects !== 'object' || Array.isArray(c.effects)) {
      c.effects = {};
    }

    if (!Array.isArray(c.tags)) {
      if (typeof c.tags === 'string' && c.tags) {
        c.tags = c.tags.split(',').map(function (t) { return t.trim(); }).filter(Boolean);
      } else {
        c.tags = [];
      }
    }
    c.tags = c.tags.map(function (t) { return String(t); });

    c.name = c.name != null ? String(c.name) : 'Carta';
    c.card_type = c.card_type != null ? String(c.card_type) : 'equipo';
    c.rank = c.rank != null ? String(c.rank) : 'C';
    c.description = c.description != null ? String(c.description) : '';
    c.image_url = c.image_url != null ? String(c.image_url) : '';
    c.dice = c.dice != null && c.dice !== '' ? String(c.dice) : '';
    c.cost_pe = c.cost_pe != null && String(c.cost_pe).trim() !== '' ? String(c.cost_pe) : '—';
    c.execution_stat = c.execution_stat != null ? String(c.execution_stat) : '';
    c.activation = c.activation || 'activa';
    c.reposo = parseInt(c.reposo, 10) || 0;
    c.duracion = parseInt(c.duracion, 10) || 0;
    c.execution_cost = parseInt(c.execution_cost, 10) || 0;
    c.cost_jenny = parseInt(c.cost_jenny, 10) || 0;

    if (c.effects.equipo_type != null) {
      c.effects.equipo_type = String(c.effects.equipo_type);
    }
    if (c.roll_result != null && c.roll_result !== '') {
      c.roll_result = String(c.roll_result);
    } else {
      delete c.roll_result;
    }

    return c;
  }

  function renderFullCard(card) {
    const result = renderFullCardDetailed(card);
    return result.html;
  }

  function renderFullCardDetailed(card) {
    const cardId = card && card.id != null ? card.id : '?';
    shopPreviewLog('log', 'renderFullCardDetailed inicio', { cardId: cardId, source: card && card.name });

    if (!card || typeof card !== 'object') {
      setPreviewDiagnostics({
        ok: false,
        reason: 'no_card_input',
        cardId: cardId,
      });
      return { html: '', diagnostics: lastPreviewDiagnostics };
    }

    const normalized = normalizeCardForRender(card);
    if (!normalized) {
      setPreviewDiagnostics({
        ok: false,
        reason: 'normalize_returned_null',
        cardId: cardId,
        rawKeys: Object.keys(card),
      });
      return { html: '', diagnostics: lastPreviewDiagnostics };
    }

    const rpgReady = ensureRpgCardsReady();
    if (!rpgReady) {
      setPreviewDiagnostics({
        ok: false,
        reason: 'rpg_cards_not_ready',
        cardId: normalized.id,
        rpgCardsReady: false,
        card_type: normalized.card_type,
      });
      return { html: '', diagnostics: lastPreviewDiagnostics };
    }

    const engine = getRpgCardsEngine();
    const orig = engine.truncateDesc;
    engine.truncateDesc = function (text) { return text || ''; };
    let html = '';
    let caught = null;
    try {
      html = engine.renderCard(normalized);
    } catch (err) {
      caught = err;
      html = '';
      shopPreviewLog('error', 'RpgCards.renderCard lanzó excepción', {
        cardId: normalized.id,
        name: normalized.name,
        card_type: normalized.card_type,
        message: err && err.message,
        stack: err && err.stack,
      });
    } finally {
      engine.truncateDesc = orig;
    }

    const htmlStr = html == null ? '' : String(html);
    const trimmedLen = htmlStr.trim().length;

    if (caught) {
      setPreviewDiagnostics({
        ok: false,
        reason: 'renderCard_exception',
        cardId: normalized.id,
        card_type: normalized.card_type,
        errorMessage: caught.message,
        normalizedSample: {
          id: normalized.id,
          name: normalized.name,
          card_type: normalized.card_type,
          rank: normalized.rank,
          tags: normalized.tags,
          effects: normalized.effects,
          dice: normalized.dice,
        },
      });
      return { html: '', diagnostics: lastPreviewDiagnostics };
    }

    if (trimmedLen === 0) {
      setPreviewDiagnostics({
        ok: false,
        reason: 'renderCard_returned_empty',
        cardId: normalized.id,
        card_type: normalized.card_type,
        htmlLength: htmlStr.length,
        normalizedSample: {
          id: normalized.id,
          name: normalized.name,
          card_type: normalized.card_type,
          rank: normalized.rank,
          tags: normalized.tags,
          effects: normalized.effects,
        },
      });
      shopPreviewLog('warn', 'renderCard devolvió HTML vacío', lastPreviewDiagnostics);
      return { html: '', diagnostics: lastPreviewDiagnostics };
    }

    setPreviewDiagnostics({
      ok: true,
      reason: 'ok',
      cardId: normalized.id,
      htmlLength: trimmedLen,
    });
    shopPreviewLog('log', 'render OK', { cardId: normalized.id, htmlLength: trimmedLen });
    return { html: htmlStr, diagnostics: lastPreviewDiagnostics };
  }

  function showCardPreview(card, preRenderedHtml) {
    if (!card || !window.RpgModal) return;
    const mount = document.getElementById('shop-card-preview-render');
    const meta = document.getElementById('shop-card-preview-meta');
    const title = document.getElementById('shop-card-preview-title');
    const addBtn = document.getElementById('shop-preview-add-btn');
    if (!mount) return;

    const normalized = normalizeCardForRender(card);
    if (!normalized) {
      setPreviewDiagnostics({ ok: false, reason: 'show_preview_normalize_failed', card });
      mount.innerHTML = previewRenderErrorHtml();
      return;
    }

    previewCardId = String(normalized.id);

    let html = preRenderedHtml || '';
    if (!html) {
      const rendered = renderFullCardDetailed(normalized);
      html = rendered.html;
      if (!html) {
        shopPreviewLog('error', 'showCardPreview sin HTML tras render', rendered.diagnostics);
      }
    } else {
      shopPreviewLog('log', 'showCardPreview reutiliza HTML precalculado', { cardId: normalized.id });
    }

    if (html) {
      mount.innerHTML = html;
      if (window.applyRpgDataAttrs) window.applyRpgDataAttrs(mount);
    } else {
      mount.innerHTML = previewRenderErrorHtml();
    }

    const isCons = normalized.is_consumible || (
      normalized.card_type === 'equipo'
      && String(normalized.effects.equipo_type || '').toLowerCase() === 'util'
    );

    if (title) title.innerHTML = '<i class="fas fa-id-card"></i> ' + normalized.name;
    if (meta) {
      meta.innerHTML =
        '<span>Precio: <strong>' + Number(normalized.cost_jenny || 0).toLocaleString('es-ES') + ' Jenny</strong></span>' +
        (normalized.cost_pe && normalized.cost_pe !== '—'
          ? '<span>Coste PE: <strong>' + normalized.cost_pe + '</strong></span>'
          : '<span>Coste PE: <strong>—</strong></span>') +
        (normalized.dice ? '<span>Dado: <strong>' + normalized.dice + '</strong></span>' : '');
    }

    if (addBtn) {
      const buyVisible = sectionBuy && !sectionBuy.classList.contains('rpg-is-hidden');
      addBtn.classList.toggle('rpg-is-hidden', !buyVisible);
      addBtn.dataset.cardId = previewCardId;
      addBtn.dataset.isConsumable = isCons ? 'true' : 'false';
    }

    RpgModal.open('shop-card-preview-modal');
  }

  function openCardPreview(cardId) {
    const mount = document.getElementById('shop-card-preview-render');
    const title = document.getElementById('shop-card-preview-title');
    if (!window.RpgModal || !mount) return;

    previewCardId = String(cardId);
    mount.innerHTML = '<p class="rpg-shop-preview-loading"><i class="fas fa-spinner fa-spin"></i> Cargando carta...</p>';
    if (title) title.innerHTML = '<i class="fas fa-id-card"></i> Vista de carta';
    RpgModal.open('shop-card-preview-modal');

    function finish(card, preHtml) {
      if (card) {
        const norm = normalizeCardForRender(card);
        if (norm) CARDS_BY_ID[String(norm.id)] = norm;
        showCardPreview(card, preHtml);
        return;
      }
      setPreviewDiagnostics({ ok: false, reason: 'finish_no_card', cardId: cardId });
      mount.innerHTML = previewRenderErrorHtml();
    }

    const local = CARDS_BY_ID[String(cardId)] || CARDS_BY_ID[cardId];
    if (local) {
      shopPreviewLog('log', 'Intentando carta local TIENDA_CONFIG', { cardId: cardId });
      const localRender = renderFullCardDetailed(local);
      if (localRender.html) {
        finish(local, localRender.html);
        return;
      }
      shopPreviewLog('warn', 'Render local falló; se pedirá AJAX', localRender.diagnostics);
    } else {
      shopPreviewLog('log', 'Sin carta en TIENDA_CONFIG.cardsById', { cardId: cardId, keys: Object.keys(CARDS_BY_ID).slice(0, 20) });
    }

    fetch(BBURL + '/game/ajax/tienda_card_detail.php?card_id=' + encodeURIComponent(cardId), {
      credentials: 'same-origin',
    })
      .then(function (r) {
        shopPreviewLog('log', 'AJAX tienda_card_detail status', { status: r.status, ok: r.ok });
        return r.json();
      })
      .then(function (res) {
        shopPreviewLog('log', 'AJAX tienda_card_detail body', { ok: res.ok, hasCard: !!(res.data && res.data.card) });
        if (!res.ok || !res.data || !res.data.card) {
          setPreviewDiagnostics({
            ok: false,
            reason: 'ajax_no_card',
            cardId: cardId,
            errorMessage: res.error && res.error.message,
            response: res,
          });
          mount.innerHTML = previewRenderErrorHtml();
          return;
        }
        const ajaxRender = renderFullCardDetailed(res.data.card);
        finish(res.data.card, ajaxRender.html);
      })
      .catch(function (err) {
        setPreviewDiagnostics({
          ok: false,
          reason: 'ajax_fetch_error',
          cardId: cardId,
          errorMessage: err && err.message,
        });
        shopPreviewLog('error', 'Fetch tienda_card_detail falló', err);
        mount.innerHTML = previewRenderErrorHtml();
      });
  }

  const previewAddBtn = document.getElementById('shop-preview-add-btn');
  if (previewAddBtn) {
    previewAddBtn.addEventListener('click', function () {
      if (!previewCardId) return;
      const gridCard = document.querySelector('.rpg-shop-card[data-card-id="' + previewCardId + '"]');
      if (gridCard) {
        const addBtn = gridCard.querySelector('.rpg-shop-add-btn');
        if (addBtn) addBtn.click();
      } else {
        const fakeBtn = document.createElement('button');
        fakeBtn.type = 'button';
        fakeBtn.className = 'rpg-shop-add-btn';
        fakeBtn.dataset.cardId = previewCardId;
        const wrap = document.createElement('article');
        wrap.className = 'rpg-shop-card';
        wrap.dataset.cardId = previewCardId;
        wrap.dataset.cardName = (CARDS_BY_ID[previewCardId] && CARDS_BY_ID[previewCardId].name) || 'Carta';
        wrap.dataset.cardCost = (CARDS_BY_ID[previewCardId] && CARDS_BY_ID[previewCardId].cost_jenny) || '0';
        wrap.dataset.isConsumable = previewAddBtn.dataset.isConsumable || 'false';
        wrap.appendChild(fakeBtn);
        document.body.appendChild(wrap);
        addToCart(fakeBtn);
        wrap.remove();
      }
      if (window.RpgModal) RpgModal.close('shop-card-preview-modal');
    });
  }

  document.addEventListener('click', function (e) {
    const card = e.target.closest('.rpg-shop-card--clickable');
    if (!card || e.target.closest('.rpg-shop-add-btn')) return;
    openCardPreview(card.dataset.cardId);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    const card = e.target.closest('.rpg-shop-card--clickable');
    if (card && !e.target.closest('.rpg-shop-add-btn')) {
      e.preventDefault();
      openCardPreview(card.dataset.cardId);
    }
  });

  if (window.RpgModal) RpgModal.bind('shop-card-preview-modal');

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
      totalEl.innerHTML = '<i class="fas fa-coins"></i> 0 Jenny';
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
        '<span class="rpg-cart-item-price"><i class="fas fa-coins"></i> ' + Number(sub).toLocaleString('es-ES') + ' Jenny</span>',
        '<button type="button" class="rpg-cart-item-remove" data-cid="' + cid + '" aria-label="Eliminar"><i class="fas fa-trash-alt"></i></button>',
      ].join('');
      list.appendChild(li);
    });

    totalEl.innerHTML = '<i class="fas fa-coins"></i> ' + Number(total).toLocaleString('es-ES') + ' Jenny';
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
            updateBalanceDisplay(res.data.new_jenny);
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
          updateBalanceDisplay(res.data.new_jenny);
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
