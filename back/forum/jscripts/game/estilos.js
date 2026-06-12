(function () {
  "use strict";

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
    var modalRequirements = document.getElementById("modal-requirements");
    var modalAdvantages = document.getElementById("modal-advantages");
    var modalTecnicas = document.getElementById("modal-tecnicas");

    function parseJsonAttr(el, attr) {
      try {
        return JSON.parse(el.getAttribute(attr) || "[]");
      } catch (e) {
        return [];
      }
    }

    function renderList(ul, items, emptyText) {
      if (!ul) return;
      ul.innerHTML = "";
      if (!items || !items.length) {
        ul.innerHTML = "<li class=\"rpg-estilo-empty\">" + emptyText + "</li>";
        return;
      }
      items.forEach(function (text) {
        var li = document.createElement("li");
        li.textContent = text;
        ul.appendChild(li);
      });
    }

    function renderLinkedCards(container, cartas) {
      if (!container) return;
      if (!cartas || !cartas.length) {
        container.innerHTML = "<p class=\"rpg-estilo-empty\">Este estilo no tiene cartas técnicas en el catálogo aún.</p>";
        return;
      }
      var html = "<div class=\"rpg-tech-list\">";
      cartas.forEach(function (c) {
        html += "<div class=\"rpg-tech-card\">" +
          "<div class=\"rpg-tech-header\">" +
            "<span class=\"rpg-tech-name\">" + escapeHtml(c.name) + "</span>" +
            "<span class=\"rpg-tech-cost\">RG " + escapeHtml(c.rank || "—") + " · PE " + escapeHtml(c.cost_pe || "—") + "</span>" +
          "</div>" +
          (c.dice ? "<div class=\"rpg-tech-dmg\">Dados: " + escapeHtml(c.dice) + "</div>" : "") +
          "<div class=\"rpg-tech-desc\">" + escapeHtml(c.description || "") + "</div>" +
        "</div>";
      });
      html += "</div>";
      container.innerHTML = html;
    }

    function escapeHtml(str) {
      var div = document.createElement("div");
      div.textContent = str;
      return div.innerHTML;
    }

    function filterCards() {
      if (!searchInput) return;
      var searchText = searchInput.value.toLowerCase().trim();
      var activeTypes = Array.from(typeCheckboxes).filter(function (cb) { return cb.checked; }).map(function (cb) { return cb.value; });
      var activeReqs = Array.from(reqCheckboxes).filter(function (cb) { return cb.checked; }).map(function (cb) { return cb.value; });

      cards.forEach(function (card) {
        var name = (card.getAttribute("data-name") || "").toLowerCase();
        var type = card.getAttribute("data-type");
        var req = card.getAttribute("data-req");
        var matchesSearch = name.includes(searchText);
        var matchesType = activeTypes.includes(type);
        var matchesReq = !req || activeReqs.includes(req);
        card.style.display = (matchesSearch && matchesType && matchesReq) ? "flex" : "none";
      });
    }

    if (searchInput) {
      searchInput.addEventListener("input", filterCards);
    }
    typeCheckboxes.forEach(function (cb) { cb.addEventListener("change", filterCards); });
    reqCheckboxes.forEach(function (cb) { cb.addEventListener("change", filterCards); });

    function openModal(card) {
      var name = card.getAttribute("data-name");
      var type = card.querySelector(".rpg-lib-card-badge");
      var desc = card.getAttribute("data-desc") || "";
      var details = card.getAttribute("data-details") || "";
      var requirements = parseJsonAttr(card, "data-requirements");
      var advantages = parseJsonAttr(card, "data-advantages");
      var cartas = parseJsonAttr(card, "data-cartas");

      modalTitle.textContent = name;
      modalBadge.textContent = type ? type.textContent : "";
      modalDetails.textContent = desc + (details ? "\n\n" + details : "");

      renderList(modalRequirements, requirements, "Sin requisitos documentados.");
      renderList(modalAdvantages, advantages, "Sin ventajas documentadas.");
      renderLinkedCards(modalTecnicas, cartas);

      modal.classList.add("open");
      document.body.classList.add("modal-open");
    }

    cards.forEach(function (card) {
      card.addEventListener("click", function () { openModal(this); });
      card.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          openModal(this);
        }
      });
    });

    if (modalClose) {
      modalClose.addEventListener("click", function () {
        modal.classList.remove("open");
        document.body.classList.remove("modal-open");
      });
    }

    if (modal) {
      modal.addEventListener("click", function (e) {
        if (e.target === modal) {
          modal.classList.remove("open");
          document.body.classList.remove("modal-open");
        }
      });
    }
  });
})();
