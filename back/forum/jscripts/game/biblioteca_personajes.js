/**
 * Auto-extracted from back/forum/game/public/biblioteca_personajes.php
 * Config: window.BIBLIOTECA_PERSONAJES_CONFIG
 */
(function () {
  "use strict";
  var cfg = window.BIBLIOTECA_PERSONAJES_CONFIG || {};

  document.addEventListener("DOMContentLoaded", function () {
    var si = document.getElementById("lib-search");
    var rc = document.querySelectorAll("input[name='race']");
    var jc = document.querySelectorAll("input[name='job']");
    var cd = document.querySelectorAll(".rpg-lib-card");
    var m = document.getElementById("lib-modal");
    var mc = document.getElementById("modal-close");
    var mb = document.getElementById("modal-banner");
    var mt = document.getElementById("modal-title");
    var mbd = document.getElementById("modal-badge");
    var md = document.getElementById("modal-details");
    var mrw = document.getElementById("modal-radar-wrapper");
    var sT = document.getElementById("modal-stat-tripulacion");
    var sR = document.getElementById("modal-stat-rango");
    var sRc = document.getElementById("modal-stat-recompensa");

    function radar(s) {
      var k = ['FUE', 'AGI', 'DES', 'INST', 'ESP', 'INT'];
      var l = ['Fuerza', 'Agilidad', 'Destreza', 'Instinto', 'Espíritu', 'Intelecto'];
      var mv = 150, cx = 170, cy = 170, ra = 100;
      var g = '', a = '', lm = [];
      for (var i = 1; i <= 5; i++) {
        var r = ra * (i / 5), p = [];
        for (var j = 0; j < 6; j++) {
          var A = (j * 60 - 90) * Math.PI / 180;
          p.push((cx + r * Math.cos(A)).toFixed(1) + ',' + (cy + r * Math.sin(A)).toFixed(1));
        }
        g += '<polygon points="' + p.join(' ') + '" class="rpg-radar-polygon-bg"/>';
      }
      for (var j2 = 0; j2 < 6; j2++) {
        var A2 = (j2 * 60 - 90) * Math.PI / 180;
        a += '<line x1="' + cx + '" y1="' + cy + '" x2="' + (cx + ra * Math.cos(A2)).toFixed(1) + '" y2="' + (cy + ra * Math.sin(A2)).toFixed(1) + '" class="rpg-radar-line"/>';
      }
      var vp = [];
      for (var j3 = 0; j3 < 6; j3++) {
        var v = s[k[j3]] || 10, r2 = ra * Math.min(v, mv) / mv, A3 = (j3 * 60 - 90) * Math.PI / 180;
        vp.push((cx + r2 * Math.cos(A3)).toFixed(1) + ',' + (cy + r2 * Math.sin(A3)).toFixed(1));
      }
      var vg = '<polygon points="' + vp.join(' ') + '" class="rpg-radar-polygon-value"/>';
      for (var j4 = 0; j4 < 6; j4++) {
        var lb = l[j4], v2 = s[k[j4]] || 0, A4 = (j4 * 60 - 90) * Math.PI / 180;
        var x = cx + (ra + 22) * Math.cos(A4), y = cy + (ra + 22) * Math.sin(A4), an = 'middle';
        if (Math.cos(A4) > 0.1) an = 'start';
        else if (Math.cos(A4) < -0.1) an = 'end';
        lm.push('<text x="' + x.toFixed(1) + '" y="' + (y + 4).toFixed(1) + '" text-anchor="' + an + '" class="rpg-radar-label">' + lb + ' (' + v2 + ')</text>');
      }
      return '<svg viewBox="0 0 340 340" class="rpg-radar-svg">' + g + a + vg + lm.join('') + '</svg>';
    }

    function fl() {
      var t = si.value.toLowerCase().trim();
      var ar = [], aj = [];
      rc.forEach(function (c) { if (c.checked) ar.push(c.value); });
      jc.forEach(function (c) { if (c.checked) aj.push(c.value); });
      cd.forEach(function (c) {
        var n = c.getAttribute("data-name").toLowerCase();
        var r = c.getAttribute("data-race");
        var j = c.getAttribute("data-job");
        c.style.display = (n.includes(t) && ar.includes(r) && aj.includes(j)) ? "flex" : "none";
      });
    }

    si.addEventListener("input", fl);
    rc.forEach(function (c) { c.addEventListener("change", fl); });
    jc.forEach(function (c) { c.addEventListener("change", fl); });

    cd.forEach(function (c) {
      c.addEventListener("click", function () {
        var n = this.getAttribute("data-name");
        var r = this.querySelector(".rpg-lib-card-badge").textContent;
        var d = this.getAttribute("data-details");
        var i = this.getAttribute("data-img");
        var s = JSON.parse(this.getAttribute("data-stats"));
        mb.setAttribute("data-bg", i);
        if (window.applyRpgDataAttrs) window.applyRpgDataAttrs(mb);
        mt.textContent = n;
        mbd.textContent = r;
        md.textContent = d;
        sT.textContent = this.getAttribute("data-tripulacion");
        sR.textContent = this.getAttribute("data-rango");
        sRc.textContent = this.getAttribute("data-recompensa");
        mrw.innerHTML = radar(s);
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
