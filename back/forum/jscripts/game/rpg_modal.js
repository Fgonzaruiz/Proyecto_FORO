/**
 * rpg_modal.js — Modales centrados reutilizables (staff y juego).
 * Uso: overlay con class rpg-modal-overlay + data-rpg-modal; cerrar con [data-rpg-modal-close].
 */
'use strict';

(function () {
  function getOverlay(id) {
    if (!id) return null;
    var el = document.getElementById(id);
    return el && el.classList.contains('rpg-modal-overlay') ? el : null;
  }

  function open(id) {
    var overlay = getOverlay(id);
    if (!overlay) return;
    overlay.classList.add('is-open');
    overlay.setAttribute('aria-hidden', 'false');
    document.body.classList.add('rpg-modal-open');
  }

  function close(id) {
    var overlay = getOverlay(id);
    if (!overlay) return;
    overlay.classList.remove('is-open');
    overlay.setAttribute('aria-hidden', 'true');
    if (!document.querySelector('.rpg-modal-overlay.is-open')) {
      document.body.classList.remove('rpg-modal-open');
    }
  }

  function bind(id) {
    var overlay = getOverlay(id);
    if (!overlay || overlay.dataset.rpgModalBound === '1') return;
    overlay.dataset.rpgModalBound = '1';
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) close(id);
    });
    overlay.querySelectorAll('[data-rpg-modal-close]').forEach(function (btn) {
      btn.addEventListener('click', function () { close(id); });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && overlay.classList.contains('is-open')) close(id);
    });
  }

  function bindAll() {
    document.querySelectorAll('.rpg-modal-overlay[data-rpg-modal]').forEach(function (el) {
      if (el.id) bind(el.id);
    });
  }

  window.RpgModal = { open: open, close: close, bind: bind, bindAll: bindAll };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindAll);
  } else {
    bindAll();
  }
})();
