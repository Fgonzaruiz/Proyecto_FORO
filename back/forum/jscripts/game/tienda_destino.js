(function () {
  "use strict";

  var cfg = window.TIENDA_DESTINO_CONFIG || {};
  var bburl = cfg.bburl || "";
  var characterId = cfg.characterId || 0;
  var AJAX_BASE = bburl + "/game/ajax";

  function gameFetchPost(path, payload) {
    var url = AJAX_BASE + (path.charAt(0) === "/" ? path : "/" + path);
    var body = payload || {};
    if (window.GAME_CSRF) {
      body.my_post_key = window.GAME_CSRF;
    }
    return fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Mybb-Post-Key": window.GAME_CSRF || ""
      },
      credentials: "same-origin",
      body: JSON.stringify(body)
    }).then(function (r) {
      return r.json();
    });
  }

  function buyPdItem(itemType, cost, itemName) {
    if (!itemType || !itemName) return;

    if (!confirm("¿Confirmas que deseas gastar " + cost + " PD en: '" + itemName + "'?")) {
      return;
    }

    var buttons = document.querySelectorAll(".btn-buy-pd-item");
    buttons.forEach(function(b) { b.disabled = true; });

    gameFetchPost("pd_purchase.php", {
      character_id: characterId,
      item_type: itemType,
      item_name: itemName
    })
      .then(function (res) {
        if (res.ok) {
          alert("¡Compra realizada con éxito!");
          window.location.reload();
        } else {
          alert("Error: " + (res.error ? res.error.message : "Desconocido"));
          buttons.forEach(function(b) { b.disabled = false; });
        }
      })
      .catch(function () {
        alert("Error de conexión al procesar la compra.");
        buttons.forEach(function(b) { b.disabled = false; });
      });
  }

  // Expose to window
  window.buyPdItem = buyPdItem;
})();
