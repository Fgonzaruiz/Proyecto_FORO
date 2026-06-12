(function () {
  "use strict";

  var cfg = window.ZONA_STAFF_MISIONES_CONFIG || {};
  var bburl = cfg.bburl || "";
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

  function openMissionModal(mode, data) {
    var modal = document.getElementById("mission-editor-modal");
    var title = document.getElementById("modal-mission-title");
    var inputId = document.getElementById("edit_mission_id");
    
    var tInput = document.getElementById("mission_title");
    var dInput = document.getElementById("mission_description");
    var rSelect = document.getElementById("mission_rank");
    var iInput = document.getElementById("mission_isla");
    var minInput = document.getElementById("mission_min_level");
    var maxInput = document.getElementById("mission_max_level");
    var pdInput = document.getElementById("mission_points_reward");
    var bInput = document.getElementById("mission_berry_reward");
    var cSelect = document.getElementById("mission_categoria");
    var pInput = document.getElementById("mission_max_posts");

    if (!modal || !title) return;

    if (mode === "create") {
      title.innerHTML = '<i class="fas fa-plus-circle"></i> Nueva Misión';
      if (inputId) inputId.value = "";
      if (tInput) tInput.value = "";
      if (dInput) dInput.value = "";
      if (rSelect) rSelect.value = "D";
      if (iInput) iInput.value = "";
      if (minInput) minInput.value = "1";
      if (maxInput) maxInput.value = "99";
      if (pdInput) pdInput.value = "1";
      if (bInput) bInput.value = "500";
      if (cSelect) cSelect.value = "combate";
      if (pInput) pInput.value = "15";
    } else if (mode === "edit" && data) {
      title.innerHTML = '<i class="fas fa-edit"></i> Editar Misión';
      if (inputId) inputId.value = data.id || "";
      if (tInput) tInput.value = data.title || "";
      if (dInput) dInput.value = data.description || "";
      if (rSelect) rSelect.value = data.rank || "D";
      if (iInput) iInput.value = data.isla || "";
      if (minInput) minInput.value = data.min_level || "1";
      if (maxInput) maxInput.value = data.max_level || "99";
      if (pdInput) pdInput.value = data.points_reward || "0";
      if (bInput) bInput.value = data.berry_reward || "0";
      if (cSelect) cSelect.value = data.categoria || "combate";
      if (pInput) pInput.value = data.max_posts || "15";
      var fSelect = document.getElementById("mission_faction");
      if (fSelect) fSelect.value = data.faction || "Global";
    }

    modal.classList.add("is-open");
  }

  function closeMissionModal() {
    var modal = document.getElementById("mission-editor-modal");
    if (modal) modal.classList.remove("is-open");
  }

  function submitMissionForm() {
    var inputId = document.getElementById("edit_mission_id");
    var id = inputId ? parseInt(inputId.value) || 0 : 0;
    var mode = id > 0 ? "edit" : "create";

    var tInput = document.getElementById("mission_title");
    var dInput = document.getElementById("mission_description");
    var rSelect = document.getElementById("mission_rank");
    var iInput = document.getElementById("mission_isla");
    var minInput = document.getElementById("mission_min_level");
    var maxInput = document.getElementById("mission_max_level");
    var pdInput = document.getElementById("mission_points_reward");
    var bInput = document.getElementById("mission_berry_reward");
    var cSelect = document.getElementById("mission_categoria");
    var pInput = document.getElementById("mission_max_posts");

    var title = tInput ? tInput.value.trim() : "";
    var description = dInput ? dInput.value.trim() : "";
    var rank = rSelect ? rSelect.value : "D";
    var isla = iInput ? iInput.value.trim() : "";
    var minLevel = minInput ? parseInt(minInput.value) || 1 : 1;
    var maxLevel = maxInput ? parseInt(maxInput.value) || 99 : 99;
    var pdReward = pdInput ? parseInt(pdInput.value) || 0 : 0;
    var bReward = bInput ? parseInt(bInput.value) || 0 : 0;
    var category = cSelect ? cSelect.value : "combate";
    var maxPosts = pInput ? parseInt(pInput.value) || 15 : 15;
    var fSelect = document.getElementById("mission_faction");
    var faction = fSelect ? fSelect.value : "Global";

    if (!title || !description || !isla) {
      alert("Por favor, rellena el título, la descripción y la isla de destino.");
      return;
    }

    var btn = document.getElementById("btn_submit_mission");
    if (btn) btn.disabled = true;

    gameFetchPost("admin_missions_action.php", {
      action: mode,
      id: id,
      title: title,
      description: description,
      rank: rank,
      min_level: minLevel,
      max_level: maxLevel,
      points_reward: pdReward,
      berry_reward: bReward,
      isla: isla,
      categoria: category,
      max_posts: maxPosts,
      faction: faction
    })
      .then(function (res) {
        if (res.ok) {
          alert("¡Misión guardada correctamente!");
          window.location.reload();
        } else {
          alert("Error: " + (res.error ? res.error.message : "Desconocido"));
          if (btn) btn.disabled = false;
        }
      })
      .catch(function () {
        alert("Error de conexión al guardar la misión.");
        if (btn) btn.disabled = false;
      });
  }

  function deleteMission(id, title) {
    if (!confirm("¿Confirmas que deseas desactivar del catálogo la misión: '" + title + "'?")) {
      return;
    }

    gameFetchPost("admin_missions_action.php", {
      action: "delete",
      id: id
    })
      .then(function (res) {
        if (res.ok) {
          alert("Misión desactivada correctamente.");
          window.location.reload();
        } else {
          alert("Error: " + (res.error ? res.error.message : "Desconocido"));
        }
      })
      .catch(function () {
        alert("Error de conexión al deactivar la misión.");
      });
  }

  // Expose to window
  window.openMissionModal = openMissionModal;
  window.closeMissionModal = closeMissionModal;
  window.submitMissionForm = submitMissionForm;
  window.deleteMission = deleteMission;
})();
