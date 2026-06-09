(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    const si = document.getElementById("lib-search");
    const fc = document.querySelectorAll("input[name='faction']");
    const cds = document.querySelectorAll(".rpg-lib-card");
    const m = document.getElementById("lib-modal");
    const mc = document.getElementById("modal-close");
    const mt = document.getElementById("modal-title");
    const mbd = document.getElementById("modal-badge");
    const sl = document.getElementById("modal-stats-list");
    const mdh = document.getElementById("modal-history");
    const mis = document.getElementById("modal-info-stats");
    const mp = document.getElementById("modal-portrait");
    const mLf = document.getElementById("modal-link-ficha");

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

    function statLabel(effRank) {
      if (effRank <= 0) return '—';
      var names = ['', 'D', 'C', 'B', 'A', 'S', 'SS'];
      if (effRank <= 6) return names[effRank] || 'D';
      if (effRank === 7) return 'SS+';
      if (effRank === 8) return 'SS++';
      return 'M';
    }

    function renderStatRows(stats) {
      var html = '';
      STAT_META.forEach(function (m) {
        var k = m[0], label = m[1], icon = m[2];
        var r = stats[k] || 1;
        var trained = Math.min(r, 6);
        var effRank = r;
        var display = statLabel(effRank);
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
      var aa = [];
      fc.forEach(function (c) { if (c.checked) aa.push(c.value); });
      cds.forEach(function (c) {
        var d = JSON.parse(c.getAttribute("data-npc"));
        var n = d.nombre.toLowerCase();
        var f = c.getAttribute("data-faction");
        c.style.display = (n.includes(t) && aa.includes(f)) ? "flex" : "none";
      });
    }

    si.addEventListener("input", fl);
    fc.forEach(function (c) { c.addEventListener("change", fl); });

    cds.forEach(function (c) {
      c.addEventListener("click", function () {
        var d = JSON.parse(this.getAttribute("data-npc"));
        mt.textContent = d.nombre;
        mbd.textContent = (d.afiliacion || 'Desconocido');

        if (d.portrait) {
          mp.src = d.portrait;
          mp.style.display = "block";
        } else {
          mp.style.display = "none";
        }

        if (sl) sl.innerHTML = renderStatRows(d.stats || {});

        if (mdh) mdh.textContent = d.history || 'Sin historia registrada.';

        if (mis) {
          mis.innerHTML = "";
          var info = [
            { l: 'Apodos', v: (d.apodos || []).join(', ') || '—' },
            { l: 'Edad', v: d.edad || '—' },
            { l: 'Raza', v: d.raza || '—' },
            { l: 'Ocupaci\u00f3n', v: d.ocupacion || '—' },
            { l: 'Estado', v: d.estado || '—' }
          ];
          info.forEach(function (i) {
            var bx = document.createElement("div");
            bx.className = "rpg-lib-modal-info-item";
            bx.innerHTML = '<span class="rpg-lib-modal-info-icon"><i class="fas fa-circle"></i></span><div><div class="rpg-lib-modal-info-label">' + i.l + '</div><div class="rpg-lib-modal-info-value">' + i.v + '</div></div>';
            mis.appendChild(bx);
          });
        }

        if (mLf) {
          var link = d.link || '';
          if (link) {
            mLf.href = link;
            mLf.classList.remove('is-hidden');
          } else {
            mLf.classList.add('is-hidden');
          }
        }

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
