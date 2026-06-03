/**
 * Biblioteca de NPCs Javascript
 * Config: window.NPC_CONFIG
 */
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    const si = document.getElementById("lib-search");
    const fc = document.querySelectorAll("input[name='faction']");
    const cds = document.querySelectorAll(".rpg-lib-card");
    const m = document.getElementById("lib-modal");
    const mc = document.getElementById("modal-close");
    const mb = document.getElementById("modal-banner");
    const mt = document.getElementById("modal-title");
    const mbd = document.getElementById("modal-badge");
    const mrw = document.getElementById("modal-radar-wrapper");
    const mi = document.getElementById("modal-info-grid");
    const md = document.getElementById("modal-descripcion");
    const mr = document.getElementById("modal-resumen");
    const mp = document.getElementById("modal-portrait");
    const mps = document.getElementById("modal-portrait-section");

    function radar(s) {
      var k = ['FP', 'DP', 'RP', 'IP', 'VP', 'HP'];
      var l = ['Fuerza', 'Destreza', 'Resist.', 'Intel.', 'Voluntad', 'Haki'];
      var mv = 150, cx = 170, cy = 170, ra = 100, g = '', a = '', lm = [];
      for (var i = 1; i <= 5; i++) {
        var r = ra * (i / 5), p = [];
        for (var j = 0; j < 6; j++) {
          var A = (j * 60 - 90) * Math.PI / 180;
          p.push((cx + r * Math.cos(A)).toFixed(1) + ',' + (cy + r * Math.sin(A)).toFixed(1));
        }
        g += '<polygon points="' + p.join(' ') + '" class="rpg-radar-polygon-bg"/>';
      }
      for (var j = 0; j < 6; j++) {
        var A = (j * 60 - 90) * Math.PI / 180;
        a += '<line x1="' + cx + '" y1="' + cy + '" x2="' + (cx + ra * Math.cos(A)).toFixed(1) + '" y2="' + (cy + ra * Math.sin(A)).toFixed(1) + '" class="rpg-radar-line"/>';
      }
      var vp = [];
      for (var j = 0; j < 6; j++) {
        var v = s[k[j]] || 10;
        var r = ra * Math.min(v, mv) / mv;
        var A = (j * 60 - 90) * Math.PI / 180;
        vp.push((cx + r * Math.cos(A)).toFixed(1) + ',' + (cy + r * Math.sin(A)).toFixed(1));
      }
      var vg = '<polygon points="' + vp.join(' ') + '" class="rpg-radar-polygon-value"/>';
      for (var j = 0; j < 6; j++) {
        var lb = l[j];
        var v = s[k[j]] || 0;
        var A = (j * 60 - 90) * Math.PI / 180;
        var x = cx + (ra + 22) * Math.cos(A);
        var y = cy + (ra + 22) * Math.sin(A);
        var an = 'middle';
        if (Math.cos(A) > 0.1) an = 'start';
        else if (Math.cos(A) < -0.1) an = 'end';
        lm.push('<text x="' + x.toFixed(1) + '" y="' + (y + 4).toFixed(1) + '" text-anchor="' + an + '" class="rpg-radar-label">' + lb + ' (' + v + ')</text>');
      }
      return '<svg viewBox="0 0 340 340" class="rpg-radar-svg">' + g + a + vg + lm.join('') + '</svg>';
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
        mb.setAttribute("data-bg", d.crew_banner);
        if (window.applyRpgDataAttrs) window.applyRpgDataAttrs(mb);
        mt.textContent = d.nombre;
        mbd.textContent = (d.afiliacion || 'Desconocido');
        md.textContent = (d.descripcion || 'Sin descripción');
        mr.textContent = (d.resumen || 'Sin resumen');

        if (d.portrait) {
          mp.src = d.portrait;
          mps.style.display = "block";
        } else {
          mps.style.display = "none";
        }

        mi.innerHTML = "";
        var info = [
          { l: 'Apodos', v: (d.apodos || []).join(', ') || '—' },
          { l: 'Edad', v: d.edad || '—' },
          { l: 'Raza', v: d.raza || '—' },
          { l: 'Ocupación', v: d.ocupacion || '—' },
          { l: 'Estado', v: d.estado || '—' }
        ];
        info.forEach(function (i) {
          var bx = document.createElement("div");
          bx.className = "rpg-modal-npc-info-item";
          bx.innerHTML = '<span class="rpg-modal-npc-info-lbl">' + i.l + '</span><span class="rpg-modal-npc-info-val">' + i.v + '</span>';
          mi.appendChild(bx);
        });

        mrw.innerHTML = radar(d.stats);
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
