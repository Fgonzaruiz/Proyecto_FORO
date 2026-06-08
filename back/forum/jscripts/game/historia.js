(function () {
  "use strict";

  var cfg = window.HISTORIA_CONFIG || {};
  var tipos = cfg.tipos || { event_types: [], lore_subtypes: [] };

  /* ---------- state ---------- */
  var currentFilter = "todo";

  /* ---------- DOM refs ---------- */
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

  /* ============================================================
     BUILD FILTER PILLS
     ============================================================ */
  function buildFilterPills() {
    if (!filterPillsEl) return;

    var html = '<span class="rpg-filter-pill active" data-filter="todo">Todo</span>';
    html += '<span class="rpg-filter-pill" data-filter="__lore__">Conocimiento Ancestral</span>';
    tipos.event_types.forEach(function (t) {
      html += '<span class="rpg-filter-pill" data-filter="' + t.id + '">' + t.label + "</span>";
    });
    filterPillsEl.innerHTML = html;
  }

  /* ============================================================
     SCROLL SPY
     ============================================================ */
  function initScrollSpy() {
    var sections = document.querySelectorAll(".rpg-era-section");
    if (!sections.length) return;

    var sidebarItems = document.querySelectorAll(".rpg-sidebar-item");

    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          var id = entry.target.id;
          sidebarItems.forEach(function (item) {
            item.classList.toggle("active", item.getAttribute("href") === "#" + id);
          });
        });
      },
      { rootMargin: "-80px 0px -60% 0px", threshold: 0.1 }
    );

    sections.forEach(function (s) {
      observer.observe(s);
    });
  }

  /* ============================================================
     APPLY FILTER
     ============================================================ */
  function applyFilter(filter) {
    currentFilter = filter;

    // pills
    var pills = filterPillsEl.querySelectorAll(".rpg-filter-pill");
    pills.forEach(function (p) {
      p.classList.toggle("active", p.getAttribute("data-filter") === filter);
    });

    var showLore = filter === "todo" || filter === "__lore__";
    var showEvent = filter === "todo" || tipos.event_types.some(function (t) { return t.id === filter; });

    // lore basal cards
    document.querySelectorAll(".rpg-lore-basal-block").forEach(function (block) {
      block.style.display = showLore ? "" : "none";
    });

    // event items
    document.querySelectorAll(".rpg-timeline-item").forEach(function (item) {
      var type = item.getAttribute("data-type");
      item.style.display = showEvent || type === filter ? "" : "none";
    });

    // rows: hide if all children hidden or if event filter hides everything
    document.querySelectorAll(".rpg-timeline-row").forEach(function (row) {
      var visibleItems = Array.from(row.querySelectorAll(".rpg-timeline-item")).filter(function (it) {
        return it.style.display !== "none";
      });
      row.style.display = visibleItems.length === 0 ? "none" : "";
    });

    // sections: hide if both lore and event blocks are empty/ hidden
    document.querySelectorAll(".rpg-era-section").forEach(function (section) {
      var loreBlock = section.querySelector(".rpg-lore-basal-block");
      var eventosBlock = section.querySelector(".rpg-eventos-block");
      var emptyState = section.querySelector(".rpg-era-empty-state");

      var loreVisible = loreBlock && loreBlock.style.display !== "none";
      var eventosVisible = eventosBlock && eventosBlock.style.display !== "none";

      // Check if eventos block has any visible items
      if (eventosBlock) {
        var visibleEvents = Array.from(eventosBlock.querySelectorAll(".rpg-timeline-item")).filter(function (it) {
          return it.style.display !== "none";
        });
        if (visibleEvents.length === 0) eventosVisible = false;
      }

      if (!loreVisible && !eventosVisible) {
        section.style.display = "none";
        if (emptyState) emptyState.style.display = "";
      } else {
        section.style.display = "";
        if (emptyState) emptyState.style.display = "none";
      }
    });
  }

  /* ============================================================
     OPEN MODAL
     ============================================================ */
  function openModal(dataset) {
    var modalType = dataset.modalType || "event";

    // Common fields
    modalTitle.textContent = dataset.name || "Sin nombre";

    if (modalType === "lore") {
      modalBadge.textContent = dataset.subtypeLabel || "Lore Basal";

      modalDetails.innerHTML = dataset.details || "";

      // Stats for lore: subtype, ubicacion
      modalStats.innerHTML = "";
      var subtypeBox = document.createElement("div");
      subtypeBox.className = "rpg-lib-modal-stat-box";
      subtypeBox.innerHTML =
        '<div class="rpg-lib-modal-stat-lbl">Tipo de Conocimiento</div>' +
        '<div class="rpg-lib-modal-stat-val">' + (dataset.subtypeLabel || "—") + "</div>";
      modalStats.appendChild(subtypeBox);

      if (dataset.ubicacion) {
        var ubiBox = document.createElement("div");
        ubiBox.className = "rpg-lib-modal-stat-box";
        ubiBox.innerHTML =
          '<div class="rpg-lib-modal-stat-lbl">Alcance Geográfico</div>' +
          '<div class="rpg-lib-modal-stat-val">' + dataset.ubicacion + "</div>";
        modalStats.appendChild(ubiBox);
      }
    } else {
      modalBadge.textContent = dataset.typeName || dataset.type || "Evento";

      modalDetails.innerHTML = dataset.details || "";

      // Stats from JSON
      modalStats.innerHTML = "";
      try {
        var stats = JSON.parse(dataset.stats || "{}");
        Object.keys(stats).forEach(function (key) {
          var box = document.createElement("div");
          box.className = "rpg-lib-modal-stat-box";
          box.innerHTML =
            '<div class="rpg-lib-modal-stat-lbl">' + key + "</div>" +
            '<div class="rpg-lib-modal-stat-val">' + stats[key] + "</div>";
          modalStats.appendChild(box);
        });
      } catch (e) {
        // ignore parse errors
      }
    }

    // Forum link (only for events)
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

  /* ============================================================
     CLOSE MODAL
     ============================================================ */
  function closeModal() {
    modal.classList.remove("open");
    document.body.classList.remove("modal-open");
  }

  /* ============================================================
     SELECT ERA (scroll + sidebar)
     ============================================================ */
  function selectEra(eraId) {
    var sidebarItems = document.querySelectorAll(".rpg-sidebar-item");
    sidebarItems.forEach(function (item) {
      item.classList.toggle("active", parseInt(item.getAttribute("data-era"), 10) === eraId);
    });

    document.querySelectorAll(".rpg-era-section").forEach(function (s) {
      s.classList.remove("open");
    });

    var section = document.getElementById("era-" + eraId);
    if (section) {
      section.classList.add("open");
      section.scrollIntoView({ behavior: "smooth", block: "start" });
    }
  }

  /* ============================================================
     EVENT DELEGATION
     ============================================================ */
  function initDelegation() {
    if (!app) return;

    // Filter pills
    app.addEventListener("click", function (e) {
      var pill = e.target.closest(".rpg-filter-pill");
      if (pill) {
        applyFilter(pill.getAttribute("data-filter"));
        return;
      }
    });

    // Sidebar nav
    app.addEventListener("click", function (e) {
      var item = e.target.closest(".rpg-sidebar-item");
      if (item) {
        e.preventDefault();
        var eraId = parseInt(item.getAttribute("data-era"), 10);
        selectEra(eraId);
        return;
      }
    });

    // Lore basal card → modal
    app.addEventListener("click", function (e) {
      var card = e.target.closest(".rpg-lore-basal-card");
      if (card) {
        openModal({
          modalType: "lore",
          name: card.getAttribute("data-name"),
          subtype: card.getAttribute("data-subtype"),
          subtypeLabel: card.getAttribute("data-subtype-label"),
          desc: card.getAttribute("data-desc"),
          details: card.getAttribute("data-details"),
          ubicacion: card.getAttribute("data-ubicacion"),
          img: card.getAttribute("data-img"),
        });
        return;
      }
    });

    // Timeline item → modal
    app.addEventListener("click", function (e) {
      var item = e.target.closest(".rpg-timeline-item");
      if (item) {
        openModal({
          modalType: "event",
          name: item.getAttribute("data-name"),
          type: item.getAttribute("data-type"),
          typeName: item.getAttribute("data-type-name"),
          desc: item.getAttribute("data-desc"),
          details: item.getAttribute("data-details"),
          link: item.getAttribute("data-link"),
          stats: item.getAttribute("data-stats"),
        });
        return;
      }
    });
  }

  /* ============================================================
     MODAL CLOSE HANDLERS
     ============================================================ */
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

  /* ============================================================
     AUTO-OPEN FIRST ERA WITH CONTENT
     ============================================================ */
  function autoOpenFirst() {
    var firstSection = document.querySelector(".rpg-era-section");
    if (firstSection) {
      firstSection.classList.add("open");
      var id = parseInt(firstSection.id.replace("era-", ""), 10);
      var firstItem = document.querySelector('.rpg-sidebar-item[data-era="' + id + '"]');
      if (firstItem) {
        firstItem.classList.add("active");
      }
    }
  }

  /* ============================================================
     INIT
     ============================================================ */
  document.addEventListener("DOMContentLoaded", function () {
    buildFilterPills();
    initScrollSpy();
    initDelegation();
    initModalClose();
    autoOpenFirst();
  });
})();
