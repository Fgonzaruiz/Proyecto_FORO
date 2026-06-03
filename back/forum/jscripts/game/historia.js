/**
 * Auto-extracted from back/forum/game/public/historia.php
 * Config: window.HISTORIA_CONFIG
 */
(function () {
  "use strict";
  var cfg = window.HISTORIA_CONFIG || {};

  document.addEventListener("DOMContentLoaded", function () {
    var modal = document.getElementById("lib-modal");
    var modalClose = document.getElementById("modal-close");
    var modalBanner = document.getElementById("modal-banner");
    var modalTitle = document.getElementById("modal-title");
    var modalBadge = document.getElementById("modal-badge");
    var modalDetails = document.getElementById("modal-details");
    var modalStats = document.getElementById("modal-stats");

    function selectEra(era) {
      document.querySelectorAll(".rpg-era-card").forEach(function (c) { c.classList.remove("active"); });
      var card = document.querySelector('.rpg-era-card[data-era="' + era + '"]');
      if (card) card.classList.add("active");

      document.querySelectorAll(".rpg-era-section").forEach(function (s) { s.classList.remove("open"); });
      var section = document.getElementById("era-" + era);
      if (section) section.classList.add("open");

      var wrap = document.getElementById("eras-vertical-wrap");
      if (wrap) {
        setTimeout(function () {
          var rect = wrap.getBoundingClientRect();
          var scrollY = window.scrollY + rect.top - 20;
          window.scrollTo({ top: scrollY, behavior: "smooth" });
        }, 100);
      }
    }

    document.querySelectorAll(".rpg-era-card:not(.rpg-era-card--empty)").forEach(function (card) {
      card.addEventListener("click", function () {
        selectEra(parseInt(this.getAttribute("data-era"), 10));
      });
    });

    var firstCard = document.querySelector(".rpg-era-card:not(.rpg-era-card--empty)");
    if (firstCard) {
      selectEra(parseInt(firstCard.getAttribute("data-era"), 10));
    }

    document.querySelectorAll(".rpg-timeline-item").forEach(function (item) {
      item.addEventListener("click", function () {
        var name = this.getAttribute("data-name");
        var type = this.querySelector(".rpg-lib-card-stat").textContent.trim();
        var details = this.getAttribute("data-details");
        var img = this.getAttribute("data-img");
        var stats = JSON.parse(this.getAttribute("data-stats"));

        modalBanner.setAttribute("data-bg", img);
        if (window.applyRpgDataAttrs) window.applyRpgDataAttrs(modalBanner);
        modalTitle.textContent = name;
        modalBadge.textContent = type;
        modalDetails.textContent = details;

        modalStats.innerHTML = "";
        Object.keys(stats).forEach(function (key) {
          var box = document.createElement("div");
          box.className = "rpg-lib-modal-stat-box";
          box.innerHTML = '<div class="rpg-lib-modal-stat-lbl">' + key + '</div><div class="rpg-lib-modal-stat-val">' + stats[key] + '</div>';
          modalStats.appendChild(box);
        });

        modal.classList.add("open");
        document.body.classList.add("modal-open");
      });
    });

    modalClose.addEventListener("click", function () {
      modal.classList.remove("open");
      document.body.classList.remove("modal-open");
    });

    modal.addEventListener("click", function (e) {
      if (e.target === modal) {
        modal.classList.remove("open");
        document.body.classList.remove("modal-open");
      }
    });
  });
})();
