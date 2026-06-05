(function () {
  "use strict";

  function init() {
    var app = document.getElementById("staffCuentasApp");
    if (!app) return;

    // 1. Slots change redirection
    app.addEventListener("change", function (e) {
      if (e.target && e.target.classList.contains("rpg-slots-select")) {
        var uid = e.target.getAttribute("data-uid");
        var val = e.target.value;
        if (uid && val) {
          window.location.href = "zona_staff_cuentas.php?action=set_max_slots&uid=" + uid + "&slots=" + val;
        }
      }
    });

    // 2. Click delegation for confirmations and ban input prompt
    app.addEventListener("click", function (e) {
      var target = e.target;
      
      // Confirm unban click
      var unbanLink = target.closest(".rpg-unban-action");
      if (unbanLink) {
        var username = unbanLink.getAttribute("data-username") || "";
        if (!confirm("¿Quitar baneo a la cuenta de " + username + "?")) {
          e.preventDefault();
        }
        return;
      }

      // Confirm clear active PJ click
      var clearLink = target.closest(".rpg-clear-active-pj");
      if (clearLink) {
        if (!confirm("¿Limpiar personaje activo de esta cuenta?")) {
          e.preventDefault();
        }
        return;
      }

      // Ban button prompt & redirect
      var banBtn = target.closest(".rpg-btn-ban-action");
      if (banBtn) {
        e.preventDefault();
        var uid = banBtn.getAttribute("data-uid");
        var username = banBtn.getAttribute("data-username") || "";
        var reason = prompt("Escribe el motivo del baneo para la cuenta " + username + ":");
        if (reason === null) {
          return; // Cancelled
        }
        reason = reason.trim();
        if (!reason) {
          alert("Debes especificar un motivo para el baneo.");
          return;
        }
        window.location.href = "zona_staff_cuentas.php?action=ban&uid=" + uid + "&reason=" + encodeURIComponent(reason);
      }
    });
  }

  if (document.readyState === "interactive" || document.readyState === "complete") {
    init();
  } else {
    document.addEventListener("DOMContentLoaded", init);
  }
})();
