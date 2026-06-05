(function () {
  "use strict";

  var cfg = window.PERSONAJE_PAGE_CONFIG;
  if (!cfg || !cfg.canEdit) return;

  var AJAX_BASE = (cfg.bburl || "") + "/game/ajax";
  var currentInventoryData = null;
  var activeFilter = "all";

  function gameFetchPost(path, payload) {
    var url = AJAX_BASE + path;
    var body = payload || {};
    if (window.GAME_CSRF) {
      body.my_post_key = window.GAME_CSRF;
    }
    return fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Mybb-Post-Key": window.GAME_CSRF || ""
      },
      credentials: "same-origin",
      body: JSON.stringify(body)
    }).then(function (r) {
      return r.json();
    });
  }

  function escapeHtml(str) {
    if (!str) return "";
    return str
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function loadInventory() {
    var deckContainer = document.getElementById("rpg-inv-deck-list");
    if (deckContainer) {
      deckContainer.innerHTML = '<div class="rpg-inv-loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Cargando deck...</div>';
    }

    fetch(AJAX_BASE + "/inventory_get.php?character_id=" + cfg.characterId)
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.ok && res.data) {
          currentInventoryData = res.data;
          renderUI();
        } else {
          var errorMsg = res.error ? res.error.message : "Error desconocido";
          showError(deckContainer, errorMsg);
        }
      })
      .catch(function () {
        showError(deckContainer, "Error de conexión.");
      });
  }

  function showError(container, msg) {
    if (container) {
      container.innerHTML = '<div class="rpg-inv-empty-msg" style="color:var(--accent-rose)"><i class="fas fa-exclamation-triangle"></i> ' + escapeHtml(msg) + '</div>';
    }
  }

  function renderUI() {
    if (!currentInventoryData) return;

    var char = currentInventoryData.character || {};

    var ccPct = char.cc_max > 0 ? (char.cc_used / char.cc_max) * 100 : 0;
    var ccDisplay = document.getElementById("rpg-inv-cc-display");
    var ccBar = document.getElementById("rpg-inv-cc-bar-fill");
    if (ccDisplay) ccDisplay.textContent = char.cc_used + " / " + char.cc_max + " CC";
    if (ccBar) {
      ccBar.style.width = ccPct + "%";
      ccBar.className = "rpg-inv-cc-bar-fill";
      if (ccPct >= 100) {
        ccBar.classList.add("danger");
      } else if (ccPct >= 80) {
        ccBar.classList.add("warning");
      }
    }

    var compDisplay = document.getElementById("rpg-inv-companion-display");
    if (compDisplay) compDisplay.textContent = char.companion_used + " / " + char.companion_max;

    var barcoDisplay = document.getElementById("rpg-inv-barco-display");
    if (barcoDisplay) barcoDisplay.textContent = char.barco_used + " / 1";

    renderDeckList();
  }

  function renderDeckList() {
    var deckContainer = document.getElementById("rpg-inv-deck-list");
    if (!deckContainer || !currentInventoryData) return;

    var owned = currentInventoryData.owned || [];
    var filtered = owned;

    if (activeFilter !== "all") {
      filtered = owned.filter(function (card) {
        return card.card_type === activeFilter;
      });
    }

    if (filtered.length === 0) {
      deckContainer.innerHTML = '<div class="rpg-inv-empty-msg"><i class="fas fa-search"></i> No hay cartas de esta categoría en tu deck.</div>';
      return;
    }

    var html = "";
    filtered.forEach(function (card) {
      var isEquipped = card.is_equipped;
      var btnText = isEquipped ? "Desequipar" : "Equipar";
      var btnClass = isEquipped ? "rpg-inv-toggle-btn--unequip" : "rpg-inv-toggle-btn--equip";

      var typeLabel = "Equipo";
      if (card.card_type === "npc_menor") typeLabel = "Compañero";
      else if (card.card_type === "barco") typeLabel = "Barco";

      var weightInfo = "";
      if (card.card_type === "equipo") {
        weightInfo = '<span class="rpg-inv-item-weight">Peso: ' + card.peso + ' CC</span>';
      }

      var cardDesc = card.description ? '<div class="rpg-inv-item-desc">' + escapeHtml(card.description) + '</div>' : "";

      html += '<div class="rpg-inv-item-card ' + (isEquipped ? "equipped" : "") + '">' +
        '  <div class="rpg-inv-item-info">' +
        '    <div class="rpg-inv-item-name-row">' +
        '      <strong class="rpg-inv-item-name">' + escapeHtml(card.name) + '</strong>' +
        '      <span class="rpg-inv-item-badge rpg-inv-item-badge--' + card.card_type + '">' + typeLabel + '</span>' +
        (isEquipped ? '      <span class="rpg-inv-item-equipped-tag"><i class="fas fa-check-circle"></i> Equipado</span>' : '') +
        '    </div>' +
        "    " + cardDesc +
        '    <div class="rpg-inv-item-meta">' +
        "      <span>Rango: " + escapeHtml(card.rank) + "</span>" +
        "      " + weightInfo +
        "    </div>" +
        "  </div>" +
        '  <div class="rpg-inv-item-action">' +
        '    <button class="rpg-inv-toggle-btn ' + btnClass + '" data-card-id="' + card.id + '">' + btnText + '</button>' +
        "  </div>" +
        "</div>";
    });

    deckContainer.innerHTML = html;
  }

  function toggleEquip(cardId) {
    gameFetchPost("/inventory_toggle.php", {
      character_id: cfg.characterId,
      card_id: cardId
    })
      .then(function (res) {
        if (res.ok) {
          loadInventory();
        } else {
          alert("Error: " + (res.error ? res.error.message : "No se pudo realizar el cambio."));
        }
      })
      .catch(function () {
        alert("Error de conexión al guardar cambios.");
      });
  }

  function init() {
    var originalSwitch = window.switchGestionSubtab;
    window.switchGestionSubtab = function (subtabId) {
      if (originalSwitch) {
        originalSwitch(subtabId);
      }
      if (subtabId === "equipamiento") {
        loadInventory();
      }
    };

    var filterBtns = document.querySelectorAll(".rpg-inv-filter-btn");
    filterBtns.forEach(function (btn) {
      btn.addEventListener("click", function () {
        filterBtns.forEach(function (b) { b.classList.remove("active"); });
        this.classList.add("active");
        activeFilter = this.getAttribute("data-filter");
        renderDeckList();
      });
    });

    var deckEl = document.getElementById("rpg-inv-deck-list");
    if (deckEl) {
      deckEl.addEventListener("click", function (e) {
        var btn = e.target.closest(".rpg-inv-toggle-btn");
        if (btn) {
          var cardId = parseInt(btn.getAttribute("data-card-id"), 10);
          if (cardId) {
            toggleEquip(cardId);
          }
        }
      });
    }
  }

  if (document.readyState === "interactive" || document.readyState === "complete") {
    init();
  } else {
    document.addEventListener("DOMContentLoaded", init);
  }
})();
