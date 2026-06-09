(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    var si = document.getElementById("lib-search");
    var fc = document.querySelectorAll("input[name='faction']");
    var cd = document.querySelectorAll(".rpg-lib-card");
    var m = document.getElementById("lib-modal");
    var mc = document.getElementById("modal-close");
    var mt = document.getElementById("modal-title");
    var mbd = document.getElementById("modal-badge");
    var sl = document.getElementById("modal-stats-list");
    var mdh = document.getElementById("modal-history");
    var sT = document.getElementById("modal-stat-tripulacion");
    var sR = document.getElementById("modal-stat-rango");
    var sRc = document.getElementById("modal-stat-recompensa");
    var sRa = document.getElementById("modal-stat-raza");
    var mP = document.getElementById("modal-portrait");
    var mLf = document.getElementById("modal-link-ficha");

    var STAT_META = [
      ['fue', 'Fuerza', 'fa-dumbbell'],
      ['res', 'Resistencia', 'fa-shield-alt'],
      ['agi', 'Agilidad', 'fa-running'],
      ['des', 'Destreza', 'fa-bullseye'],
      ['int', 'Intelecto', 'fa-brain'],
      ['inst', 'Instinto', 'fa-eye'],
      ['esp', 'Espíritu', 'fa-fire'],
    ];

    function statCssClass(effRank) {
      if (effRank <= 0) return 'rpg-stat-rank--none';
      if (effRank <= 6) return 'rpg-stat-rank--' + ['', 'd', 'c', 'b', 'a', 's', 'ss'][effRank] || 'rpg-stat-rank--d';
      if (effRank === 7) return 'rpg-stat-rank--ss-plus';
      if (effRank === 8) return 'rpg-stat-rank--ss-plus-plus';
      return 'rpg-stat-rank--ss-beyond';
    }

    function renderStatRows(stats) {
      var html = '';
      STAT_META.forEach(function (m) {
        var k = m[0], label = m[1], icon = m[2];
        var s = stats[k] || { trained: 1, eff_rank: 1, display: 'D' };
        var trained = s.trained || 1;
        var effRank = s.eff_rank || 1;
        var display = s.display || 'D';
        var rankClass = statCssClass(effRank);
        var segments = '';
        for (var seg = 1; seg <= 6; seg++) {
          var filled = seg <= trained ? ' rpg-stat-rank-segment--filled rpg-stat-rank-segment--' + k : '';
          segments += '<span class="rpg-stat-rank-segment' + filled + '"></span>';
        }
        html += '<div class="rpg-pj-stat-row rpg-pj-stat-row--rank">' +
          '<div class="rpg-pj-stat-label">' +
            '<span><i class="fas ' + icon + '"></i> ' + label + '</span>' +
            '<span class="rpg-stat-rank ' + rankClass + '">' + display + '</span>' +
          '</div>' +
          '<div class="rpg-stat-rank-track">' + segments + '</div>' +
        '</div>';
      });
      return html;
    }

    function fl() {
      var t = si.value.toLowerCase().trim();
      var af = [];
      fc.forEach(function (c) { if (c.checked) af.push(c.value); });
      cd.forEach(function (c) {
        var n = c.getAttribute("data-name").toLowerCase();
        var f = c.getAttribute("data-faction");
        c.style.display = (n.includes(t) && af.includes(f)) ? "flex" : "none";
      });
    }

    si.addEventListener("input", fl);
    fc.forEach(function (c) { c.addEventListener("change", fl); });

    cd.forEach(function (c) {
      c.addEventListener("click", function () {
        var n = this.getAttribute("data-name");
        var f = this.querySelector(".rpg-lib-card-badge").textContent;
        var i = this.getAttribute("data-img");
        var sd = JSON.parse(this.getAttribute("data-stats") || "{}");
        var h = this.getAttribute("data-history") || '';

        if (mP) mP.src = i;
        mt.textContent = n;
        mbd.textContent = f;
        if (sl) sl.innerHTML = renderStatRows(sd);
        if (mdh) mdh.textContent = h || 'Sin historia registrada.';
        sT.textContent = this.getAttribute("data-tripulacion");
        sR.textContent = this.getAttribute("data-rango");
        sRc.textContent = this.getAttribute("data-recompensa");
        if (sRa) sRa.textContent = this.getAttribute("data-race-name");
        if (mLf) mLf.href = this.getAttribute("data-link") || '#';

        m.classList.add("open");
        document.body.classList.add("modal-open");
      });
    });

    mc.addEventListener("click", function () {
      m.classList.remove("open");
      document.body.classList.remove("modal-open");
    });
    m.addEventListener("click", function (e) {
      if (e.target === m) {
        m.classList.remove("open");
        document.body.classList.remove("modal-open");
      }
    });
  });
})();
