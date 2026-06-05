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
    var equippedContainer = document.getElementById("rpg-inv-equipped-list");
    var deckContainer = document.getElementById("rpg-inv-deck-list");
    
    if (equippedContainer) equippedContainer.innerHTML = '<div class="rpg-inv-loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Cargando inventario...</div>';
    if (deckContainer) deckContainer.innerHTML = '<div class="rpg-inv-loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Cargando deck...</div>';

    fetch(AJAX_BASE + "/inventory_get.php?character_id=" + cfg.characterId)
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.ok && res.data) {
          currentInventoryData = res.data;
          renderUI();
        } else {
          var errorMsg = res.error ? res.error.message : "Error desconocido";
          showError(equippedContainer, errorMsg);
          showError(deckContainer, errorMsg);
        }
      })
      .catch(function () {
        showError(equippedContainer, "Error de conexión.");
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
    var equipped = currentInventoryData.equipped || [];
    var owned = currentInventoryData.owned || [];

    // 1. Update Vitals and Slot Indicators
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

    // 2. Render Equipped Items Column
    var equippedContainer = document.getElementById("rpg-inv-equipped-list");
    if (equippedContainer) {
      equippedContainer.innerHTML = renderEquippedListHTML(currentInventoryData);
    }

    // 3. Render Deck Column
    renderDeckList();
  }

  function renderEquippedListHTML(data) {
    var html = "";
    var equipped = data.equipped || [];
    var char = data.character || {};

    // 1. Carga Section
    html += '<div class="rpg-inv-equipped-section">';
    html += '  <h5 class="rpg-inv-equipped-section-title"><i class="fas fa-weight-hanging"></i> Sección de Carga (' + char.cc_used + '/' + char.cc_max + ' CC)</h5>';
    var cargaItems = equipped.filter(function (i) { return i.slot_type === "carga"; });
    if (cargaItems.length > 0) {
      cargaItems.forEach(function (item) {
        html += '<div class="rpg-inv-equipped-item-row">';
        html += '  <span class="rpg-inv-dot">•</span>';
        html += '  <span class="rpg-inv-name">' + escapeHtml(item.name) + '</span>';
        html += '  <span class="rpg-inv-type">[' + escapeHtml(item.card_type === "equipo" ? "Carga" : item.card_type) + ']</span>';
        html += '  <span class="rpg-inv-weight">Peso: ' + item.peso + '</span>';
        html += '  <button class="rpg-inv-equipped-remove-btn" data-card-id="' + item.card_id + '" title="Desequipar"><i class="fas fa-times"></i></button>';
        html += '</div>';
      });
    }

    var freeCc = char.cc_max - char.cc_used;
    if (freeCc > 0) {
      html += '<div class="rpg-inv-equipped-item-row free-slot">';
      html += '  <span class="rpg-inv-dot">•</span>';
      html += '  <span class="rpg-inv-name">(slot libre)</span>';
      html += '  <span class="rpg-inv-weight">Peso: ' + freeCc + '</span>';
      html += '</div>';
    }
    html += "</div>";

    // 2. Compañeros Section
    html += '<div class="rpg-inv-equipped-section">';
    html += '  <h5 class="rpg-inv-equipped-section-title"><i class="fas fa-paw"></i> Compañeros (' + char.companion_used + '/' + char.companion_max + ')</h5>';
    var companionItems = equipped.filter(function (i) { return i.slot_type === "companero"; });
    if (companionItems.length > 0) {
      companionItems.forEach(function (item) {
        html += '<div class="rpg-inv-equipped-item-row">';
        html += '  <span class="rpg-inv-dot">•</span>';
        html += '  <span class="rpg-inv-name">' + escapeHtml(item.name) + '</span>';
        html += '  <span class="rpg-inv-type">[Compañero]</span>';
        html += '  <button class="rpg-inv-equipped-remove-btn" data-card-id="' + item.card_id + '" title="Desequipar"><i class="fas fa-times"></i></button>';
        html += '</div>';
      });
    }

    var freeCompanions = char.companion_max - char.companion_used;
    for (var i = 0; i < freeCompanions; i++) {
      html += '<div class="rpg-inv-equipped-item-row free-slot">';
      html += '  <span class="rpg-inv-dot">•</span>';
      html += '  <span class="rpg-inv-name">(slot libre)</span>';
      html += "</div>";
    }
    html += "</div>";

    // 3. Barco Section
    html += '<div class="rpg-inv-equipped-section">';
    html += '  <h5 class="rpg-inv-equipped-section-title"><i class="fas fa-ship"></i> Barco Activo (' + char.barco_used + '/1)</h5>';
    var barcoItems = equipped.filter(function (i) { return i.slot_type === "barco"; });
    if (barcoItems.length > 0) {
      barcoItems.forEach(function (item) {
        html += '<div class="rpg-inv-equipped-item-row">';
        html += '  <span class="rpg-inv-dot">•</span>';
        html += '  <span class="rpg-inv-name">' + escapeHtml(item.name) + '</span>';
        html += '  <span class="rpg-inv-type">[Barco]</span>';
        html += '  <button class="rpg-inv-equipped-remove-btn" data-card-id="' + item.card_id + '" title="Desequipar"><i class="fas fa-times"></i></button>';
        html += '</div>';
      });
    } else {
      html += '<div class="rpg-inv-equipped-item-row free-slot">';
      html += '  <span class="rpg-inv-dot">•</span>';
      html += '  <span class="rpg-inv-name">(slot libre)</span>';
      html += "</div>";
    }
    html += "</div>";

    return html;
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
    // 1. Hook into switchGestionSubtab
    var originalSwitch = window.switchGestionSubtab;
    window.switchGestionSubtab = function (subtabId) {
      if (originalSwitch) {
        originalSwitch(subtabId);
      }
      if (subtabId === "equipamiento") {
        loadInventory();
      }
    };

    // 2. Filter Buttons Event Listeners
    var filterBtns = document.querySelectorAll(".rpg-inv-filter-btn");
    filterBtns.forEach(function (btn) {
      btn.addEventListener("click", function () {
        filterBtns.forEach(function (b) { b.classList.remove("active"); });
        this.classList.add("active");
        activeFilter = this.getAttribute("data-filter");
        renderDeckList();
      });
    });

    // 3. Delegate Equip/Unequip buttons in lists
    var containers = ["rpg-inv-deck-list", "rpg-inv-equipped-list"];
    containers.forEach(function (id) {
      var el = document.getElementById(id);
      if (el) {
        el.addEventListener("click", function (e) {
          var btn = e.target.closest(".rpg-inv-toggle-btn, .rpg-inv-equipped-remove-btn");
          if (btn) {
            var cardId = parseInt(btn.getAttribute("data-card-id"), 10);
            if (cardId) {
              toggleEquip(cardId);
            }
          }
        });
      }
    });
  }

  if (document.readyState === "interactive" || document.readyState === "complete") {
    init();
  } else {
    document.addEventListener("DOMContentLoaded", init);
  }
})();
