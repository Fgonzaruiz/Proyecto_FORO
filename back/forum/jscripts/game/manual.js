/**
 * Auto-extracted from back/forum/game/public/manual.php
 * Config: window.MANUAL_CONFIG
 */
(function () {
  "use strict";
  var cfg = window.MANUAL_CONFIG || {};
  var bburl = cfg.bburl || (window.GAME_BBURL || '');
var tabs=document.querySelectorAll(".rpg-manual-toc a");
var secs=document.querySelectorAll(".rpg-manual-section");

tabs.forEach(function(a){
a.addEventListener("click",function(e){
e.preventDefault();
var id=this.getAttribute("href").substring(1);
tabs.forEach(function(t){t.classList.remove("active")});
this.classList.add("active");
secs.forEach(function(s){s.classList.remove("active")});
var sec=document.getElementById(id);
if(sec){sec.classList.add("active");sec.scrollTop=0}
});
});

// Select first visible TOC item on load
var first = document.querySelector(".rpg-manual-toc li:not(.rpg-is-hidden) a");
if (first) first.click();

function filterManualToc() {
  var q = document.getElementById("manual-search").value.toLowerCase().trim();
  document.querySelectorAll(".rpg-manual-section").forEach(function (s) {
    var txt = s.getAttribute("data-search") + " " + s.textContent.toLowerCase();
    var show = txt.includes(q);
    var id = s.getAttribute("id");
    document.querySelectorAll('.rpg-manual-toc a[href="#' + id + '"]').forEach(function (a) {
      var li = a.parentElement;
      if (li) li.classList.toggle("rpg-is-hidden", !show);
    });
  });
}
window.filterManualToc = filterManualToc;

})();
