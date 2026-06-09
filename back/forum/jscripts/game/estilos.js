(function () {
  "use strict";
  var cfg = window.ESTILOS_CONFIG || {};

  document.addEventListener("DOMContentLoaded", function () {
    var searchInput = document.getElementById("lib-search");
    var typeCheckboxes = document.querySelectorAll("input[name='type']");
    var reqCheckboxes = document.querySelectorAll("input[name='req']");
    var cards = document.querySelectorAll(".rpg-lib-card");

    var modal = document.getElementById("lib-modal");
    var modalClose = document.getElementById("modal-close");
    var modalTitle = document.getElementById("modal-title");
    var modalBadge = document.getElementById("modal-badge");
    var modalDetails = document.getElementById("modal-details");
    var modalStats = document.getElementById("modal-stats");
    var modalTecnicas = document.getElementById("modal-tecnicas");

    function filterCards() {
      var searchText = searchInput.value.toLowerCase().trim();
      var activeTypes = Array.from(typeCheckboxes).filter(function (cb) { return cb.checked; }).map(function (cb) { return cb.value; });
      var activeReqs = Array.from(reqCheckboxes).filter(function (cb) { return cb.checked; }).map(function (cb) { return cb.value; });

      cards.forEach(function (card) {
        var name = card.getAttribute("data-name").toLowerCase();
        var type = card.getAttribute("data-type");
        var req = card.getAttribute("data-req");
        var matchesSearch = name.includes(searchText);
        var matchesType = activeTypes.includes(type);
        var matchesReq = activeReqs.includes(req);
        card.style.display = (matchesSearch && matchesType && matchesReq) ? "flex" : "none";
      });
    }

    searchInput.addEventListener("input", filterCards);
    typeCheckboxes.forEach(function (cb) { cb.addEventListener("change", filterCards); });
    reqCheckboxes.forEach(function (cb) { cb.addEventListener("change", filterCards); });

    cards.forEach(function (card) {
      card.addEventListener("click", function () {
        var name = this.getAttribute("data-name");
        var type = this.querySelector(".rpg-lib-card-badge").textContent;
        var desc = this.getAttribute("data-desc");
        var details = this.getAttribute("data-details");
        var stats = JSON.parse(this.getAttribute("data-stats") || "{}");
        var tecnicas = JSON.parse(this.getAttribute("data-tecnicas") || "[]");

        modalTitle.textContent = name;
        modalBadge.textContent = type;
        modalDetails.textContent = desc + (details ? '\n\n' + details : '');

        modalStats.innerHTML = "";
        Object.entries(stats).forEach(function (entry) {
          var statBox = document.createElement("div");
          statBox.className = "rpg-lib-modal-stat-box";
          statBox.innerHTML = '<div class="rpg-lib-modal-stat-lbl">' + entry[0] + '</div><div class="rpg-lib-modal-stat-val">' + entry[1] + '</div>';
          modalStats.appendChild(statBox);
        });

        modalTecnicas.innerHTML = "";
        if (tecnicas.length > 0) {
          var techHtml = '<div class="rpg-tech-list">';
          tecnicas.forEach(function (t) {
            techHtml += '<div class="rpg-tech-card">' +
              '<div class="rpg-tech-header">' +
                '<span class="rpg-tech-name">' + t.name + '</span>' +
                '<span class="rpg-tech-cost">' + (t.energy_cost || '—') + '</span>' +
              '</div>' +
              '<div class="rpg-tech-desc">' + (t.desc || '') + '</div>' +
              (t.damage ? '<div class="rpg-tech-dmg">Daño: ' + t.damage + '</div>' : '') +
            '</div>';
          });
          techHtml += '</div>';
          modalTecnicas.innerHTML = techHtml;
        } else {
          modalTecnicas.innerHTML = '<p class="rpg-estilo-empty">Este estilo no aporta cartas adicionales.</p>';
        }

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
