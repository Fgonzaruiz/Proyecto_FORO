/**
 * Gestión de la Línea de Tiempo de Historia (Lore)
 * Config: window.LORE_CONFIG
 */
(function () {
  "use strict";

  var config = window.LORE_CONFIG || {};
  var loreData = config.data || { eras: [], eventos: [] };
  var selectedType = null; // 'era' o 'evento'
  var selectedId = null;   // INT

  document.addEventListener("DOMContentLoaded", function () {
    var treeContainer = document.getElementById("historia-tree");
    var saveBtn = document.getElementById("btn-save-lore");
    var addEraBtn = document.getElementById("btn-add-era");

    // Renderizar el árbol inicial
    renderTree();

    // Evento de guardar cambios de forma global
    saveBtn.addEventListener("click", function () {
      saveBtn.disabled = true;
      saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

      if (window.gamePostJson) {
        window.gamePostJson(config.bburl + "/game/ajax/zona_staff_historia_save.php", buildSavePayload())
          .then(function (res) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fas fa-save"></i> Guardar Cambios en Lore.json';
            if (res.ok) {
              alert("¡Cambios de historia guardados con éxito en lore.json!");
            } else {
              alert("Error al guardar: " + ((res.error && res.error.message) || "Error desconocido"));
            }
          })
          .catch(function () {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="fas fa-save"></i> Guardar Cambios en Lore.json';
            alert("Error de conexión al guardar.");
          });
      } else {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fas fa-save"></i> Guardar Cambios en Lore.json';
        alert("Error: No se encuentra el cliente API global (gamePostJson).");
      }
    });

    // Agregar Era
    addEraBtn.addEventListener("click", function () {
      var nextId = 1;
      if (loreData.eras.length > 0) {
        nextId = Math.max.apply(Math, loreData.eras.map(function (e) { return e.id; })) + 1;
      }
      var newEra = {
        id: nextId,
        name: "Nueva Era " + nextId,
        numeral: "Era " + nextId,
        start_year: 100,
        end_year: 200,
        intro_quote: "",
        intro_text: ""
      };
      loreData.eras.push(newEra);
      renderTree();
      selectItem("era", nextId);
    });

    // Delegación de eventos en el árbol de eras
    treeContainer.addEventListener("click", function (e) {
      var target = e.target;

      // Click en info de Era
      var eraInfo = target.closest(".rpg-historia-tree-era__info");
      if (eraInfo) {
        var eraId = parseInt(eraInfo.closest(".rpg-historia-tree-era").getAttribute("data-id"), 10);
        selectItem("era", eraId);
        return;
      }

      // Click en Evento
      var eventNode = target.closest(".rpg-historia-tree-event");
      if (eventNode) {
        var eventId = parseInt(eventNode.getAttribute("data-id"), 10);
        selectItem("evento", eventId);
        return;
      }

      // Click en Eliminar Era
      var delEra = target.closest(".btn-delete-era");
      if (delEra) {
        e.stopPropagation();
        var eraId = parseInt(delEra.closest(".rpg-historia-tree-era").getAttribute("data-id"), 10);
        if (confirm("¿Estás seguro de eliminar esta Era? Se eliminarán todos sus eventos asociados permanentemente en memoria.")) {
          deleteEra(eraId);
        }
        return;
      }

      // Click en Agregar Evento a Era
      var addEvent = target.closest(".btn-add-event");
      if (addEvent) {
        e.stopPropagation();
        var eraId = parseInt(addEvent.closest(".rpg-historia-tree-era").getAttribute("data-id"), 10);
        addNewEvent(eraId);
        return;
      }

      // Click en Eliminar Evento
      var delEvent = target.closest(".btn-delete-event");
      if (delEvent) {
        e.stopPropagation();
        var eventId = parseInt(delEvent.closest(".rpg-historia-tree-event").getAttribute("data-id"), 10);
        if (confirm("¿Estás seguro de eliminar este Evento permanentemente en memoria?")) {
          deleteEvent(eventId);
        }
        return;
      }
    });

    // Renderiza el listado (árbol) de eras y eventos
    function renderTree() {
      if (!loreData.eras || loreData.eras.length === 0) {
        treeContainer.innerHTML = '<div class="rpg-staff-historia-empty">No hay eras creadas. Comienza agregando una.</div>';
        return;
      }

      var html = "";
      
      // Ordenamos eras localmente para la vista del árbol (no mutamos el array original aquí)
      var sortedEras = loreData.eras.slice().sort(function (a, b) {
        return (a.start_year || 0) - (b.start_year || 0);
      });

      sortedEras.forEach(function (era) {
        var eraEvents = (loreData.eventos || []).filter(function (ev) {
          return ev.era_id === era.id;
        }).sort(function (a, b) {
          return (a.start_year || 0) - (b.start_year || 0);
        });

        var isSelected = (selectedType === "era" && selectedId === era.id) ? " selected" : "";

        html += '<div class="rpg-historia-tree-era' + isSelected + '" data-id="' + era.id + '">';
        html += '  <div class="rpg-historia-tree-era__header">';
        html += '    <div class="rpg-historia-tree-era__info">';
        html += '      <h4 class="rpg-historia-tree-era__title">' + escapeHtml(era.numeral) + ': ' + escapeHtml(era.name) + '</h4>';
        html += '      <span class="rpg-historia-tree-era__years">Años ' + era.start_year + ' &ndash; ' + era.end_year + '</span>';
        html += '    </div>';
        html += '    <div class="rpg-historia-tree-era__actions">';
        html += '      <button type="button" class="rpg-tree-btn btn-add-event" title="Agregar Evento a esta Era"><i class="fas fa-plus"></i></button>';
        html += '      <button type="button" class="rpg-tree-btn rpg-tree-btn--danger btn-delete-era" title="Eliminar Era"><i class="fas fa-trash-alt"></i></button>';
        html += '    </div>';
        html += '  </div>';

        // Eventos anidados
        if (eraEvents.length > 0) {
          html += '  <div class="rpg-historia-tree-events">';
          eraEvents.forEach(function (ev) {
            var evSelected = (selectedType === "evento" && selectedId === ev.id) ? " selected" : "";
            var linkIcon = (ev.link && ev.link.trim() !== "") ? ' <i class="fas fa-link" title="Evento de Foro con Link" style="color: var(--color-laton); margin-left: 4px;"></i>' : '';
            html += '    <div class="rpg-historia-tree-event' + evSelected + '" data-id="' + ev.id + '">';
            html += '      <span class="rpg-historia-tree-event__title">' + escapeHtml(ev.name) + linkIcon + '</span>';
            html += '      <div style="display:flex; align-items:center; gap:6px;">';
            html += '        <span class="rpg-historia-tree-event__badge">' + escapeHtml(ev.type_name || ev.type) + '</span>';
            html += '        <button type="button" class="rpg-tree-btn rpg-tree-btn--danger btn-delete-event" title="Eliminar Evento"><i class="fas fa-times"></i></button>';
            html += '      </div>';
            html += '    </div>';
          });
          html += '  </div>';
        }

        html += '</div>';
      });

      treeContainer.innerHTML = html;
    }

    // Seleccionar elemento y cargar su formulario de edición
    function selectItem(type, id) {
      selectedType = type;
      selectedId = id;
      renderTree(); // Actualizar clase 'selected' en la vista
      loadEditor();
    }

    // Carga el panel de edición según la selección
    function loadEditor() {
      var container = document.getElementById("editor-form-container");
      if (!selectedType || !selectedId) {
        container.innerHTML = '<div class="rpg-historia-editor-empty">Selecciona un elemento para editar.</div>';
        return;
      }

      if (selectedType === "era") {
        var era = loreData.eras.find(function (e) { return e.id === selectedId; });
        if (!era) {
          container.innerHTML = '<div class="rpg-historia-editor-empty">Era no encontrada.</div>';
          return;
        }

        var html = '<form class="rpg-historia-editor-form" onsubmit="return false;">';
        html += '  <h3>Editando Era: ' + escapeHtml(era.numeral) + '</h3>';
        
        html += '  <div class="rpg-form-group">';
        html += '    <label class="rpg-form-label">Numeral de Era</label>';
        html += '    <input type="text" id="edit-era-numeral" class="rpg-form-input" value="' + escapeHtml(era.numeral) + '">';
        html += '  </div>';

        html += '  <div class="rpg-form-group">';
        html += '    <label class="rpg-form-label">Nombre de Era</label>';
        html += '    <input type="text" id="edit-era-name" class="rpg-form-input" value="' + escapeHtml(era.name) + '">';
        html += '  </div>';

        html += '  <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">';
        html += '    <div class="rpg-form-group">';
        html += '      <label class="rpg-form-label">Año Inicio</label>';
        html += '      <input type="number" id="edit-era-start" class="rpg-form-input" value="' + era.start_year + '">';
        html += '    </div>';
        html += '    <div class="rpg-form-group">';
        html += '      <label class="rpg-form-label">Año Fin</label>';
        html += '      <input type="number" id="edit-era-end" class="rpg-form-input" value="' + era.end_year + '">';
        html += '    </div>';
        html += '  </div>';

        html += '  <div class="rpg-form-group">';
        html += '    <label class="rpg-form-label">Cita Introductoria (Intro Quote)</label>';
        html += '    <textarea id="edit-era-quote" class="rpg-editor-textarea" rows="2">' + escapeHtml(era.intro_quote || "") + '</textarea>';
        html += '  </div>';

        html += '  <div class="rpg-form-group">';
        html += '    <label class="rpg-form-label">Texto Introductorio / Resumen</label>';
        html += '    <textarea id="edit-era-text" class="rpg-editor-textarea" rows="4">' + escapeHtml(era.intro_text || "") + '</textarea>';
        html += '  </div>';

        html += '</form>';

        container.innerHTML = html;

        // Añadir escuchadores en vivo para actualizar loreData
        document.getElementById("edit-era-numeral").addEventListener("input", function () {
          era.numeral = this.value;
          document.querySelector('.rpg-historia-tree-era.selected .rpg-historia-tree-era__title').textContent = this.value + ': ' + era.name;
        });
        document.getElementById("edit-era-name").addEventListener("input", function () {
          era.name = this.value;
          document.querySelector('.rpg-historia-tree-era.selected .rpg-historia-tree-era__title').textContent = era.numeral + ': ' + this.value;
        });
        document.getElementById("edit-era-start").addEventListener("input", function () {
          era.start_year = parseInt(this.value, 10) || 0;
          updateEraRangeText(era);
        });
        document.getElementById("edit-era-end").addEventListener("input", function () {
          era.end_year = parseInt(this.value, 10) || 0;
          updateEraRangeText(era);
        });
        document.getElementById("edit-era-quote").addEventListener("input", function () {
          era.intro_quote = this.value;
        });
        document.getElementById("edit-era-text").addEventListener("input", function () {
          era.intro_text = this.value;
        });
      }

      if (selectedType === "evento") {
        var ev = loreData.eventos.find(function (e) { return e.id === selectedId; });
        if (!ev) {
          container.innerHTML = '<div class="rpg-historia-editor-empty">Evento no encontrado.</div>';
          return;
        }

        var html = '<form class="rpg-historia-editor-form" onsubmit="return false;">';
        html += '  <h3>Editando Evento: ' + escapeHtml(ev.name) + '</h3>';

        html += '  <div class="rpg-form-group">';
        html += '    <label class="rpg-form-label">Nombre del Evento</label>';
        html += '    <input type="text" id="edit-ev-name" class="rpg-form-input" value="' + escapeHtml(ev.name) + '">';
        html += '  </div>';

        html += '  <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">';
        html += '    <div class="rpg-form-group">';
        html += '      <label class="rpg-form-label">Identificador Tipo (ej: batalla-historica)</label>';
        html += '      <input type="text" id="edit-ev-type" class="rpg-form-input" value="' + escapeHtml(ev.type) + '">';
        html += '    </div>';
        html += '    <div class="rpg-form-group">';
        html += '      <label class="rpg-form-label">Nombre del Tipo Visible (ej: Batalla Histórica)</label>';
        html += '      <input type="text" id="edit-ev-typename" class="rpg-form-input" value="' + escapeHtml(ev.type_name) + '">';
        html += '    </div>';
        html += '  </div>';

        html += '  <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">';
        html += '    <div class="rpg-form-group">';
        html += '      <label class="rpg-form-label">Año Inicio</label>';
        html += '      <input type="number" id="edit-ev-start" class="rpg-form-input" value="' + ev.start_year + '">';
        html += '    </div>';
        html += '    <div class="rpg-form-group">';
        html += '      <label class="rpg-form-label">Año Fin</label>';
        html += '      <input type="number" id="edit-ev-end" class="rpg-form-input" value="' + ev.end_year + '">';
        html += '    </div>';
        html += '  </div>';

        html += '  <div class="rpg-form-group">';
        html += '    <label class="rpg-form-label">Era a la que pertenece</label>';
        html += '    <select id="edit-ev-era" class="rpg-form-input">';
        loreData.eras.forEach(function (era) {
          var sel = (era.id === ev.era_id) ? " selected" : "";
          html += '      <option value="' + era.id + '"' + sel + '>' + escapeHtml(era.numeral) + ': ' + escapeHtml(era.name) + '</option>';
        });
        html += '    </select>';
        html += '  </div>';

        html += '  <div class="rpg-form-group">';
        html += '    <label class="rpg-form-label" style="color: var(--color-laton); font-weight: 700;"><i class="fas fa-link"></i> Link a Suceso Real del Foro (Opcional)</label>';
        html += '    <input type="url" id="edit-ev-link" class="rpg-form-input" placeholder="Ej: http://fororolprueba.infinityfree.me/showthread.php?tid=123" value="' + escapeHtml(ev.link || "") + '">';
        html += '    <span style="font-size: 11px; color: var(--text-muted);">Si agregas un enlace, aparecerá un botón de acceso directo en el modal de lectura del foro.</span>';
        html += '  </div>';

        html += '  <div class="rpg-form-group">';
        html += '    <label class="rpg-form-label">Ubicación Clave</label>';
        html += '    <input type="text" id="edit-ev-ubicacion" class="rpg-form-input" value="' + escapeHtml(ev.ubicacion) + '">';
        html += '  </div>';

        html += '  <div class="rpg-form-group">';
        html += '    <label class="rpg-form-label">Personajes Clave</label>';
        html += '    <input type="text" id="edit-ev-personajes" class="rpg-form-input" value="' + escapeHtml(ev.personajes) + '">';
        html += '  </div>';

        html += '  <div class="rpg-form-group">';
        html += '    <label class="rpg-form-label">Impacto Rol</label>';
        html += '    <input type="text" id="edit-ev-impacto" class="rpg-form-input" value="' + escapeHtml(ev.impacto) + '">';
        html += '  </div>';

        html += '  <div class="rpg-form-group">';
        html += '    <label class="rpg-form-label">Descripción Corta (Tarjeta)</label>';
        html += '    <textarea id="edit-ev-desc" class="rpg-editor-textarea" rows="3">' + escapeHtml(ev.desc) + '</textarea>';
        html += '  </div>';

        html += '  <div class="rpg-form-group">';
        html += '    <label class="rpg-form-label">Crónica Detallada (Modal - Acepta HTML básico)</label>';
        html += '    <textarea id="edit-ev-details" class="rpg-editor-textarea" rows="6">' + escapeHtml(ev.details) + '</textarea>';
        html += '  </div>';

        html += '</form>';

        container.innerHTML = html;

        // Escuchadores en vivo para eventos
        document.getElementById("edit-ev-name").addEventListener("input", function () {
          ev.name = this.value;
          document.querySelector('.rpg-historia-tree-event.selected .rpg-historia-tree-event__title').innerHTML = escapeHtml(this.value) + (ev.link && ev.link.trim() !== "" ? ' <i class="fas fa-link" style="color: var(--color-laton); margin-left: 4px;"></i>' : '');
        });
        document.getElementById("edit-ev-type").addEventListener("input", function () {
          ev.type = this.value;
        });
        document.getElementById("edit-ev-typename").addEventListener("input", function () {
          ev.type_name = this.value;
          document.querySelector('.rpg-historia-tree-event.selected .rpg-historia-tree-event__badge').textContent = this.value;
        });
        document.getElementById("edit-ev-start").addEventListener("input", function () {
          ev.start_year = parseInt(this.value, 10) || 0;
        });
        document.getElementById("edit-ev-end").addEventListener("input", function () {
          ev.end_year = parseInt(this.value, 10) || 0;
        });
        document.getElementById("edit-ev-era").addEventListener("change", function () {
          ev.era_id = parseInt(this.value, 10);
          renderTree();
          selectItem("evento", ev.id);
        });
        document.getElementById("edit-ev-link").addEventListener("input", function () {
          if (this.value && this.value.trim() !== "") {
            ev.link = this.value.trim();
          } else {
            delete ev.link;
          }
          document.querySelector('.rpg-historia-tree-event.selected .rpg-historia-tree-event__title').innerHTML = escapeHtml(ev.name) + (ev.link ? ' <i class="fas fa-link" style="color: var(--color-laton); margin-left: 4px;"></i>' : '');
        });
        document.getElementById("edit-ev-ubicacion").addEventListener("input", function () {
          ev.ubicacion = this.value;
        });
        document.getElementById("edit-ev-personajes").addEventListener("input", function () {
          ev.personajes = this.value;
        });
        document.getElementById("edit-ev-impacto").addEventListener("input", function () {
          ev.impacto = this.value;
        });
        document.getElementById("edit-ev-desc").addEventListener("input", function () {
          ev.desc = this.value;
        });
        document.getElementById("edit-ev-details").addEventListener("input", function () {
          ev.details = this.value;
        });
      }
    }

    function updateEraRangeText(era) {
      var textNode = document.querySelector('.rpg-historia-tree-era[data-id="' + era.id + '"] .rpg-historia-tree-era__years');
      if (textNode) {
        textNode.innerHTML = 'Años ' + era.start_year + ' &ndash; ' + era.end_year;
      }
    }

    // Agregar Evento
    function addNewEvent(eraId) {
      var nextId = 1;
      if (loreData.eventos && loreData.eventos.length > 0) {
        nextId = Math.max.apply(Math, loreData.eventos.map(function (e) { return e.id; })) + 1;
      }
      var newEvent = {
        id: nextId,
        era_id: eraId,
        name: "Nuevo Evento " + nextId,
        type: "trama-global",
        type_name: "Trama Global",
        desc: "Breve descripción...",
        details: "Crónica detallada...",
        ubicacion: "Desconocida",
        personajes: "Ninguno",
        impacto: "Ninguno",
        start_year: 100,
        end_year: 100
      };
      if (!loreData.eventos) loreData.eventos = [];
      loreData.eventos.push(newEvent);
      renderTree();
      selectItem("evento", nextId);
    }

    // Eliminar Era
    function deleteEra(eraId) {
      loreData.eras = loreData.eras.filter(function (e) { return e.id !== eraId; });
      loreData.eventos = (loreData.eventos || []).filter(function (ev) { return ev.era_id !== eraId; });

      if (selectedType === "era" && selectedId === eraId) {
        selectedType = null;
        selectedId = null;
      }
      renderTree();
      loadEditor();
    }

    // Eliminar Evento
    function deleteEvent(eventId) {
      loreData.eventos = loreData.eventos.filter(function (e) { return e.id !== eventId; });

      if (selectedType === "evento" && selectedId === eventId) {
        selectedType = null;
        selectedId = null;
      }
      renderTree();
      loadEditor();
    }

    function buildSavePayload() {
      var eventos = (loreData.eventos || []).map(function (ev) {
        var copy = Object.assign({}, ev);
        if (!copy.link || String(copy.link).trim() === "") {
          delete copy.link;
        } else {
          copy.link = String(copy.link).trim();
        }
        return copy;
      });
      return { eras: loreData.eras || [], eventos: eventos };
    }

    // Utilidad escape HTML
    function escapeHtml(text) {
      if (!text) return "";
      var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      };
      return String(text).replace(/[&<>"']/g, function (m) { return map[m]; });
    }
  });
})();
