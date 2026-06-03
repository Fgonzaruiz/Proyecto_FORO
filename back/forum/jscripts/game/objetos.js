/**
 * Auto-extracted from back/forum/game/public/objetos.php
 * Config: window.OBJETOS_CONFIG
 */
(function () {
  "use strict";
  var cfg = window.OBJETOS_CONFIG || {};

  document.addEventListener("DOMContentLoaded", function () {
    var searchInput = document.getElementById("lib-search");
    var categoryCheckboxes = document.querySelectorAll("input[name='category']");
    var rarityCheckboxes = document.querySelectorAll("input[name='rarity']");
    var cards = document.querySelectorAll(".rpg-lib-card");

    var modal = document.getElementById("lib-modal");
    var modalClose = document.getElementById("modal-close");
    var modalBanner = document.getElementById("modal-banner");
    var modalTitle = document.getElementById("modal-title");
    var modalBadge = document.getElementById("modal-badge");
    var modalDetails = document.getElementById("modal-details");
    var modalStats = document.getElementById("modal-stats");

    function filterCards() {
      var searchText = searchInput.value.toLowerCase().trim();
      var activeCategories = Array.from(categoryCheckboxes).filter(function (cb) { return cb.checked; }).map(function (cb) { return cb.value; });
      var activeRarities = Array.from(rarityCheckboxes).filter(function (cb) { return cb.checked; }).map(function (cb) { return cb.value; });

      cards.forEach(function (card) {
        var name = card.getAttribute("data-name").toLowerCase();
        var category = card.getAttribute("data-category");
        var rarity = card.getAttribute("data-rarity");
        var matchesSearch = name.includes(searchText);
        var matchesCategory = activeCategories.includes(category);
        var matchesRarity = activeRarities.includes(rarity);
        card.style.display = (matchesSearch && matchesCategory && matchesRarity) ? "flex" : "none";
      });
    }

    searchInput.addEventListener("input", filterCards);
    categoryCheckboxes.forEach(function (cb) { cb.addEventListener("change", filterCards); });
    rarityCheckboxes.forEach(function (cb) { cb.addEventListener("change", filterCards); });

    cards.forEach(function (card) {
      card.addEventListener("click", function () {
        var name = this.getAttribute("data-name");
        var category = this.querySelector(".rpg-lib-card-badge").textContent;
        var details = this.getAttribute("data-details");
        var img = this.getAttribute("data-img");
        var stats = JSON.parse(this.getAttribute("data-stats"));

        modalBanner.setAttribute("data-bg", img);
        if (window.applyRpgDataAttrs) window.applyRpgDataAttrs(modalBanner);
        modalTitle.textContent = name;
        modalBadge.textContent = category;
        modalDetails.textContent = details;

        modalStats.innerHTML = "";
        Object.entries(stats).forEach(function (entry) {
          var statBox = document.createElement("div");
          statBox.className = "rpg-lib-modal-stat-box";
          statBox.innerHTML = '<div class="rpg-lib-modal-stat-lbl">' + entry[0] + '</div><div class="rpg-lib-modal-stat-val">' + entry[1] + '</div>';
          modalStats.appendChild(statBox);
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
