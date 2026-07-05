(function () {
  "use strict";

  var cfg = window.TABLON_MISIONES_CONFIG || {};
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

  function openAcceptMissionModal(missionId, title) {
    var modal = document.getElementById("accept-mission-modal");
    var inputId = document.getElementById("accept_mission_id");
    var titleText = document.getElementById("accept_mission_title_text");
    if (!modal || !inputId || !titleText) return;

    inputId.value = missionId;
    titleText.textContent = title;
    modal.classList.add("is-open");
  }

  function closeAcceptMissionModal() {
    var modal = document.getElementById("accept-mission-modal");
    if (modal) modal.classList.remove("is-open");
  }

  function openMissionDetailsModal(data) {
    var modal = document.getElementById("mission-details-modal");
    if (!modal) return;
    
    document.getElementById("md-rank").textContent = data.rank || '';
    document.getElementById("md-rank").className = "rpg-mission-details-rank rpg-stat-rank--" + (data.rank || 'D').toLowerCase();
    document.getElementById("md-title").textContent = data.title || '';
    document.getElementById("md-isla").innerHTML = '<i class="fas fa-map-marker-alt"></i> ' + (data.isla || '');
    document.getElementById("md-cat").innerHTML = '<i class="fas fa-tag"></i> ' + (data.categoria || '');
    document.getElementById("md-niv").innerHTML = '<i class="fas fa-user"></i> Niv. ' + (data.min_level || 1) + ' - ' + (data.max_level || 99);
    
    // Add faction badge
    var badgeBox = document.querySelector(".rpg-mission-details-badges");
    var existingFac = document.getElementById("md-fac");
    if (existingFac) existingFac.remove();
    var facSpan = document.createElement("span");
    facSpan.id = "md-fac";
    facSpan.className = "rpg-pd-cost-badge";
    facSpan.innerHTML = '<i class="fas fa-flag"></i> ' + (data.faction || 'Global');
    badgeBox.appendChild(facSpan);

    document.getElementById("md-desc").textContent = data.description || '';
    document.getElementById("md-pd").innerHTML = '<i class="fas fa-star"></i> ' + (data.points_reward || 0) + ' PD';
    document.getElementById("md-berry").innerHTML = '<i class="fas fa-coins"></i> ' + (data.jenny_reward || 0) + ' Jenny';
    
    var btn = document.getElementById("btn_open_accept");
    if (btn) {
      if (data.can_accept) {
        btn.disabled = false;
        btn.title = "";
        btn.onclick = function() {
          closeMissionDetailsModal();
          openAcceptMissionModal(data.id, data.title);
        };
      } else {
        btn.disabled = true;
        btn.title = data.error || "No puedes aceptar esta misión";
        btn.onclick = null;
      }
    }
    
    modal.classList.add("is-open");
  }

  function closeMissionDetailsModal() {
    var modal = document.getElementById("mission-details-modal");
    if (modal) modal.classList.remove("is-open");
  }

  // Set widths of progress bars dynamically from data attributes
  function initProgressBars() {
    document.querySelectorAll(".rpg-misiones-progress-fill").forEach(function (fill) {
      var progress = fill.getAttribute("data-progress") || "0";
      fill.style.width = progress + "%";
    });
  }

  // Run progress bars init immediately
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initProgressBars);
  } else {
    initProgressBars();
  }


  function submitAcceptMission() {
    var missionId = parseInt(document.getElementById("accept_mission_id").value) || 0;
    var btn = document.getElementById("btn_submit_accept_mission");
    if (missionId <= 0 || !btn) return;

    // Collect companions checked
    var companions = [];
    document.querySelectorAll(".companion-checkbox:checked").forEach(function (cb) {
      companions.push(parseInt(cb.value));
    });

    btn.disabled = true;

    gameFetchPost("mission_accept.php", {
      character_id: characterId,
      mission_id: missionId,
      companions: companions
    })
      .then(function (res) {
        if (res.ok) {
          alert("¡Misión aceptada correctamente! Hilo generado. Redirigiendo...");
          window.location.reload();
        } else {
          alert("Error: " + (res.error ? res.error.message : "Desconocido"));
          btn.disabled = false;
        }
      })
      .catch(function () {
        alert("Error de conexión al aceptar la misión.");
        btn.disabled = false;
      });
  }

  function respondInvitation(activeMissionId, action) {
    if (!confirm("¿Confirmas que deseas " + (action === "accept" ? "aceptar" : "rechazar") + " esta invitación?")) {
      return;
    }

    gameFetchPost("mission_confirm.php", {
      character_id: characterId,
      active_mission_id: activeMissionId,
      action: action
    })
      .then(function (res) {
        if (res.ok) {
          alert("Invitación " + (action === "accept" ? "aceptada" : "rechazada") + ".");
          window.location.reload();
        } else {
          alert("Error: " + (res.error ? res.error.message : "Desconocido"));
        }
      })
      .catch(function () {
        alert("Error de conexión.");
      });
  }

  function completeMission(activeMissionId) {
    if (!confirm("¿Confirmas que has terminado la misión? Esto cerrará el hilo de rol y lo enviará al staff para revisión.")) {
      return;
    }

    var btn = document.querySelector(".btn-complete-mission");
    if (btn) btn.disabled = true;

    gameFetchPost("mission_complete.php", {
      character_id: characterId,
      active_mission_id: activeMissionId
    })
      .then(function (res) {
        if (res.ok) {
          alert("¡Misión declarada como completada! Ha sido enviada a revisión por el staff. Hilo cerrado.");
          window.location.reload();
        } else {
          alert("Error: " + (res.error ? res.error.message : "Desconocido"));
          if (btn) btn.disabled = false;
        }
      })
      .catch(function () {
        alert("Error de conexión.");
        if (btn) btn.disabled = false;
      });
  }

  // Expose to window
  window.openAcceptMissionModal = openAcceptMissionModal;
  window.closeAcceptMissionModal = closeAcceptMissionModal;
  window.openMissionDetailsModal = openMissionDetailsModal;
  window.closeMissionDetailsModal = closeMissionDetailsModal;
  window.submitAcceptMission = submitAcceptMission;
  window.respondInvitation = respondInvitation;
  window.completeMission = completeMission;
})();
