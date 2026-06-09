(function () {
  "use strict";

  var cfg = window.HISTORIA_CONFIG || {};
  var tipos = cfg.tipos || { event_types: [], lore_subtypes: [] };

  var currentFilter = "todo";
  var currentEraId = 0;

  var app = document.getElementById("historia-app");
  var modal = document.getElementById("lib-modal");
  var modalClose = document.getElementById("modal-close");
  var modalTitle = document.getElementById("modal-title");
  var modalBadge = document.getElementById("modal-badge");
  var modalDetails = document.getElementById("modal-details");
  var modalStats = document.getElementById("modal-stats");
  var modalForumLinkWrap = document.getElementById("modal-forum-link-wrap");
  var modalForumLink = document.getElementById("modal-forum-link");
  var filterPillsEl = document.getElementById("filter-pills");

  function buildFilterPills() {
    if (!filterPillsEl) return;

    var html = '<span class="rpg-filter-pill active" data-filter="todo">Todo</span>';
    html += '<span class="rpg-filter-pill" data-filter="__lore__">Lore</span>';
    tipos.event_types.forEach(function (t) {
      html += '<span class="rpg-filter-pill" data-filter="' + t.id + '">' + t.label + "</span>";
    });
    filterPillsEl.innerHTML = html;
  }

  function getOpenSection() {
    return document.querySelector(".rpg-era-section.open");
  }

  function applyFilter(filter) {
    currentFilter = filter;

    if (filterPillsEl) {
      filterPillsEl.querySelectorAll(".rpg-filter-pill").forEach(function (p) {
        p.classList.toggle("active", p.getAttribute("data-filter") === filter);
      });
    }

    var section = getOpenSection();
    if (!section) return;

    var showLore = filter === "todo" || filter === "__lore__";
    var showEvent = filter === "todo" || tipos.event_types.some(function (t) { return t.id === filter; });

    var loreBlock = section.querySelector(".rpg-lore-basal-block");
    if (loreBlock) {
      loreBlock.hidden = !showLore;
    }

    section.querySelectorAll(".rpg-timeline-item").forEach(function (item) {
      var type = item.getAttribute("data-type");
      item.hidden = !(showEvent || type === filter);
    });

    section.querySelectorAll(".rpg-timeline-row").forEach(function (row) {
      var visibleItems = Array.from(row.querySelectorAll(".rpg-timeline-item")).filter(function (it) {
        return !it.hidden;
      });
      row.hidden = visibleItems.length === 0;
    });

    var eventosBlock = section.querySelector(".rpg-eventos-block");
    var emptyState = section.querySelector(".rpg-era-empty-state");
    var loreVisible = loreBlock && !loreBlock.hidden;
    var eventosVisible = false;

    if (eventosBlock) {
      var visibleEvents = Array.from(eventosBlock.querySelectorAll(".rpg-timeline-item")).filter(function (it) {
        return !it.hidden;
      });
      eventosVisible = visibleEvents.length > 0;
      eventosBlock.hidden = !eventosVisible && !loreVisible;
    }

    if (emptyState) {
      emptyState.hidden = loreVisible || eventosVisible;
    }
  }

  function renderModalMeta(items) {
    if (!modalStats) return;
    modalStats.innerHTML = "";
    if (!items.length) {
      modalStats.hidden = true;
      return;
    }
    items.forEach(function (item) {
      var li = document.createElement("li");
      li.className = "rpg-historia-modal__meta-item";
      li.innerHTML =
        '<span class="rpg-historia-modal__meta-label">' + item.label + "</span>" +
        '<span class="rpg-historia-modal__meta-value">' + item.value + "</span>";
      modalStats.appendChild(li);
    });
    modalStats.hidden = false;
  }

  function formatModalBody(text) {
    if (!text) return "";
    if (text.indexOf("<") !== -1) return text;
    return "<p>" + text.replace(/\n\n+/g, "</p><p>").replace(/\n/g, "<br>") + "</p>";
  }

  function openModal(dataset) {
    var modalType = dataset.modalType || "event";

    modalTitle.textContent = dataset.name || "Sin nombre";
    modalBadge.textContent = modalType === "lore"
      ? (dataset.subtypeLabel || "Lore")
      : (dataset.typeName || dataset.type || "Evento");

    modalDetails.innerHTML = formatModalBody(dataset.details || dataset.desc || "");

    if (modalType === "lore") {
      var loreMeta = [{ label: "Tipo", value: dataset.subtypeLabel || "—" }];
      if (dataset.ubicacion) {
        loreMeta.push({ label: "Alcance", value: dataset.ubicacion });
      }
      renderModalMeta(loreMeta);
    } else {
      var eventMeta = [];
      try {
        var stats = JSON.parse(dataset.stats || "{}");
        Object.keys(stats).forEach(function (key) {
          if (stats[key]) {
            eventMeta.push({ label: key, value: stats[key] });
          }
        });
      } catch (e) {
        // ignore
      }
      renderModalMeta(eventMeta);
    }

    var forumLink = (dataset.link || "").trim();
    if (modalForumLinkWrap && modalForumLink) {
      if (modalType === "event" && forumLink) {
        modalForumLink.href = forumLink;
        modalForumLinkWrap.hidden = false;
      } else {
        modalForumLink.href = "#";
        modalForumLinkWrap.hidden = true;
      }
    }

    modal.classList.add("open");
    document.body.classList.add("modal-open");
  }

  function closeModal() {
    modal.classList.remove("open");
    document.body.classList.remove("modal-open");
  }

  function selectEra(eraId) {
    currentEraId = eraId;

    document.querySelectorAll(".rpg-sidebar-item").forEach(function (item) {
      item.classList.toggle("active", parseInt(item.getAttribute("data-era"), 10) === eraId);
    });

    document.querySelectorAll(".rpg-era-section").forEach(function (section) {
      section.classList.remove("open");
    });

    var section = document.getElementById("era-" + eraId);
    if (section) {
      section.classList.add("open");
    }

    applyFilter(currentFilter);

    var main = document.getElementById("eras-vertical-wrap");
    if (main) {
      main.scrollTop = 0;
    }
  }

  function initDelegation() {
    if (!app) return;

    app.addEventListener("click", function (e) {
      var pill = e.target.closest(".rpg-filter-pill");
      if (pill) {
        applyFilter(pill.getAttribute("data-filter"));
        return;
      }

      var item = e.target.closest(".rpg-sidebar-item");
      if (item) {
        e.preventDefault();
        selectEra(parseInt(item.getAttribute("data-era"), 10));
        return;
      }

      var card = e.target.closest(".rpg-lore-basal-card");
      if (card) {
        openModal({
          modalType: "lore",
          name: card.getAttribute("data-name"),
          subtypeLabel: card.getAttribute("data-subtype-label"),
          desc: card.getAttribute("data-desc"),
          details: card.getAttribute("data-details"),
          ubicacion: card.getAttribute("data-ubicacion"),
        });
        return;
      }

      var timelineItem = e.target.closest(".rpg-timeline-item");
      if (timelineItem) {
        openModal({
          modalType: "event",
          name: timelineItem.getAttribute("data-name"),
          typeName: timelineItem.getAttribute("data-type-name"),
          desc: timelineItem.getAttribute("data-desc"),
          details: timelineItem.getAttribute("data-details"),
          link: timelineItem.getAttribute("data-link"),
          stats: timelineItem.getAttribute("data-stats"),
        });
      }
    });
  }

  function initModalClose() {
    if (modalClose) {
      modalClose.addEventListener("click", closeModal);
    }
    if (modal) {
      modal.addEventListener("click", function (e) {
        if (e.target === modal) closeModal();
      });
    }
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && modal && modal.classList.contains("open")) {
        closeModal();
      }
    });
  }

  function autoOpenFirst() {
    var firstSection = document.querySelector(".rpg-era-section");
    if (!firstSection) return;
    selectEra(parseInt(firstSection.id.replace("era-", ""), 10));
  }

  document.addEventListener("DOMContentLoaded", function () {
    buildFilterPills();
    initDelegation();
    initModalClose();
    autoOpenFirst();
  });
})();
