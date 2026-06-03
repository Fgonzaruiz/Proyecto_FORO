/**
 * Auto-extracted from back/forum/game/public/akuma_no_mi.php
 * Config: window.AKUMA_NO_MI_CONFIG
 */
(function () {
  "use strict";
  var cfg = window.AKUMA_NO_MI_CONFIG || {};

  document.addEventListener("DOMContentLoaded", function () {
    var searchInput = document.getElementById("lib-search");
    var classCheckboxes = document.querySelectorAll("input[name='class']");
    var statusCheckboxes = document.querySelectorAll("input[name='status']");
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
      var activeClasses = Array.from(classCheckboxes).filter(function (cb) { return cb.checked; }).map(function (cb) { return cb.value; });
      var activeStatuses = Array.from(statusCheckboxes).filter(function (cb) { return cb.checked; }).map(function (cb) { return cb.value; });

      cards.forEach(function (card) {
        var name = card.getAttribute("data-name").toLowerCase();
        var clazz = card.getAttribute("data-class");
        var status = card.getAttribute("data-status");
        var matchesSearch = name.includes(searchText);
        var matchesClass = activeClasses.includes(clazz);
        var matchesStatus = activeStatuses.includes(status);
        card.style.display = (matchesSearch && matchesClass && matchesStatus) ? "flex" : "none";
      });
    }

    searchInput.addEventListener("input", filterCards);
    classCheckboxes.forEach(function (cb) { cb.addEventListener("change", filterCards); });
    statusCheckboxes.forEach(function (cb) { cb.addEventListener("change", filterCards); });

    cards.forEach(function (card) {
      card.addEventListener("click", function () {
        var name = this.getAttribute("data-name");
        var clazz = this.querySelector(".rpg-lib-card-badge").textContent;
        var details = this.getAttribute("data-details");
        var img = this.getAttribute("data-img");
        var stats = JSON.parse(this.getAttribute("data-stats"));

        modalBanner.setAttribute("data-bg", img);
        if (window.applyRpgDataAttrs) window.applyRpgDataAttrs(modalBanner);
        modalTitle.textContent = name;
        modalBadge.textContent = clazz;
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
