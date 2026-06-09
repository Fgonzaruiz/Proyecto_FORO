(function () {
  "use strict";

  var cfg = window.ORACLES_STAFF_CONFIG || {};
  var GAME_AJAX_BASE = cfg.ajaxBase || (window.GAME_AJAX_BASE || "");

  function staffPost(endpoint, data) {
    var url = GAME_AJAX_BASE + "/" + String(endpoint).replace(/^\//, "");
    if (window.gamePostJson) {
      return window.gamePostJson(url, data || {});
    }
    var body = data || {};
    if (window.GAME_CSRF) {
      body.my_post_key = window.GAME_CSRF;
    }
    return fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json", "X-Mybb-Post-Key": window.GAME_CSRF || "" },
      credentials: "same-origin",
      body: JSON.stringify(body)
    }).then(function (r) { return r.json(); });
  }

  var ORACLE_TYPE_LABELS = {
    custom: 'Custom',
    yes_no: 'Sí/No',
    action: 'Acción',
    theme: 'Tema',
    action_theme: 'Acción + Tema',
    place_descriptor: 'Descriptor Lugar',
    place_focus: 'Foco Lugar',
    character_role: 'Rol PNJ',
    character_trait: 'Rasgo PNJ',
    character_goal: 'Meta PNJ',
    pay_the_price: 'Paga el Precio',
    delve_theme: 'Tema Mazmorra',
    delve_domain: 'Dominio Mazmorra'
  };

  function init() {
    var tabs = document.querySelectorAll('.rpg-staff-tabs .rpg-tab-btn');
    var contents = document.querySelectorAll('.rpg-tab-content');
    tabs.forEach(function(tab) {
      tab.addEventListener('click', function() {
        tabs.forEach(function(t) { t.classList.remove('active'); });
        contents.forEach(function(c) { c.classList.add('rpg-is-hidden'); });
        tab.classList.add('active');
        var target = document.getElementById(tab.dataset.target);
        if (target) target.classList.remove('rpg-is-hidden');
      });
    });

    loadCatalog();
    loadForums();

    var editorModal = document.getElementById('oracle-editor-modal');
    if (window.RpgModal) RpgModal.bind('oracle-editor-modal');

    document.getElementById('btn-new-oracle').addEventListener('click', function() {
      openEditor(null);
    });

    document.getElementById('btn-cancel-edit').addEventListener('click', function() {
      closeEditor();
    });

    document.getElementById('oracle-editor-form').addEventListener('submit', function(e) {
      e.preventDefault();
      saveOracle();
    });

    document.getElementById('catalog-search').addEventListener('input', function() {
      filterCatalog(this.value);
    });

    document.getElementById('btn-add-result').addEventListener('click', function() {
      addResultRow('', '', '', '');
    });

    document.getElementById('btn-add-variation').addEventListener('click', function() {
      var sel = document.getElementById('o_category');
      var keys = [];
      for (var i = 0; i < sel.options.length; i++) {
        var v = sel.options[i].value;
        if (v && keys.indexOf(v) === -1) keys.push(v);
      }
      if (!keys.length) { alert('No hay categorías disponibles.'); return; }
      var key = prompt('Nombre de la categoría/isla:\n\n' + keys.join(', '));
      if (key) addVariationGroup(key);
    });

    // Preview tab
    document.getElementById('btn-roll-preview').addEventListener('click', function() {
      rollPreview();
    });
  }

  function loadForums() {
    fetch(GAME_AJAX_BASE + '/forums_list.php?' + new Date().getTime())
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (!d.ok || !d.data) return;
        populateCategorySelect(d.data);
      });
  }

  function populateCategorySelect(forums) {
    var sel = document.getElementById('o_category');
    var currentVal = sel.dataset.current || '';
    sel.innerHTML = '<option value="">— Todas las islas —</option>';

    // Gather categories (type='c')
    var categories = [];
    var catMap = {};
    forums.forEach(function(f) {
      if (f.type === 'c') {
        categories.push(f);
        catMap[f.fid] = f.name;
      }
    });

    // Gather child forums per category
    var childMap = {};
    forums.forEach(function(f) {
      if (f.type === 'f' && f.pid && catMap[f.pid]) {
        var catName = catMap[f.pid];
        if (!childMap[catName]) childMap[catName] = [];
        childMap[catName].push(f.name);
      }
    });

    // Sort categories by name
    categories.sort(function(a, b) { return a.name.localeCompare(b.name); });

    categories.forEach(function(cat) {
      var optgroup = document.createElement('optgroup');
      var childNames = childMap[cat.name] || [];

      // Option for the category itself (value = category name)
      var catOpt = document.createElement('option');
      catOpt.value = cat.name;
      catOpt.textContent = cat.name + (childNames.length ? ' (' + childNames.join(', ') + ')' : '');
      if (cat.name === currentVal) catOpt.selected = true;
      optgroup.appendChild(catOpt);

      sel.appendChild(optgroup);
    });

    // Restore current value if editing
    if (currentVal) sel.value = currentVal;
  }

  function loadCatalog() {
    var list = document.getElementById('oracle-catalog-list');
    fetch(GAME_AJAX_BASE + '/oracles_list.php?' + new Date().getTime())
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (!d.ok || !d.data) {
          list.innerHTML = '<div class="rpg-staff-catalog-empty">Error al cargar.</div>';
          return;
        }
        renderCatalog(d.data);
        populatePreviewSelect(d.data);
      })
      .catch(function() {
        list.innerHTML = '<div class="rpg-staff-catalog-empty">Error de conexión.</div>';
      });
  }

  function renderCatalog(oracles) {
    var list = document.getElementById('oracle-catalog-list');
    if (!oracles.length) {
      list.innerHTML = '<div class="rpg-staff-catalog-empty">No hay oráculos. ¡Crea el primero!</div>';
      return;
    }

    var grouped = {};
    oracles.forEach(function(o) {
      var type = o.oracle_type || 'custom';
      if (!grouped[type]) grouped[type] = [];
      grouped[type].push(o);
    });

    var typeOrder = ['custom','yes_no','action','theme','action_theme','place_descriptor','place_focus','character_role','character_trait','character_goal','pay_the_price','delve_theme','delve_domain'];
    var html = '';
    typeOrder.forEach(function(type) {
      var items = grouped[type];
      if (!items || !items.length) return;
      var label = ORACLE_TYPE_LABELS[type] || type;
      var secId = 'oracle-sec-' + type;
      html += '<div class="rpg-deck-section">' +
        '<div class="rpg-deck-section-header" onclick="RpgCards.toggleDeckSection(\'' + type + '\',this)">' +
          '<div class="rpg-deck-section-title"><i class="fas fa-dice-d6"></i> ' + label + ' <span class="rpg-deck-section-count">(' + items.length + ')</span></div>' +
          '<div class="rpg-deck-section-arrow"><i class="fas fa-chevron-down"></i></div>' +
        '</div>' +
        '<div id="rpg-deck-section-content-' + type + '" class="rpg-deck-section-content">';
      items.forEach(function(o) {
        var resultsCount = (o.results && o.results.length) || 0;
        var variationsCount = 0;
        if (o.variations) {
          for (var k in o.variations) {
            if (o.variations.hasOwnProperty(k)) variationsCount += o.variations[k].length || 0;
          }
        }
        html += '<div class="rpg-catalog-item" data-name="' + o.name.toLowerCase() + '">' +
          '<div class="rpg-catalog-item-info">' +
            '<strong>' + o.name + '</strong>' +
            '<span class="rpg-catalog-meta">' + o.dice_type + ' · ' + resultsCount + ' resultados' +
              (variationsCount ? ' · ' + variationsCount + ' variaciones' : '') +
              (o.category ? ' · [' + o.category + ']' : '') +
            '</span>' +
          '</div>' +
          '<div class="rpg-catalog-actions">' +
            '<button class="rpg-system-tab-btn rpg-system-tab-btn--compact btn-edit-oracle" data-id="' + o.id + '"><i class="fas fa-edit"></i></button>' +
            '<button class="rpg-system-tab-btn rpg-system-tab-btn--compact btn-delete-oracle" data-id="' + o.id + '"><i class="fas fa-trash"></i></button>' +
          '</div>' +
        '</div>';
      });
      html += '</div></div>';
    });
    list.innerHTML = html;

    list.querySelectorAll('.btn-edit-oracle').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var id = parseInt(this.dataset.id);
        loadOracleForEdit(id);
      });
    });

    list.querySelectorAll('.btn-delete-oracle').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var id = parseInt(this.dataset.id);
        if (confirm('¿Eliminar este oráculo definitivamente?')) {
          deleteOracle(id);
        }
      });
    });
  }

  function filterCatalog(query) {
    var items = document.querySelectorAll('.rpg-catalog-item');
    var q = query.toLowerCase().trim();
    items.forEach(function(item) {
      var name = item.dataset.name || '';
      item.style.display = (!q || name.indexOf(q) !== -1) ? '' : 'none';
    });
  }

  function populatePreviewSelect(oracles) {
    var sel = document.getElementById('preview-oracle-select');
    sel.innerHTML = '<option value="">Selecciona un oráculo...</option>';
    if (!oracles) {
      fetch(GAME_AJAX_BASE + '/oracles_list.php?' + new Date().getTime())
        .then(function(r) { return r.json(); })
        .then(function(d) {
          if (d.ok && d.data) populatePreviewOptions(d.data, sel);
        });
    } else {
      populatePreviewOptions(oracles, sel);
    }
  }

  function populatePreviewOptions(oracles, sel) {
    oracles.forEach(function(o) {
      var opt = document.createElement('option');
      opt.value = o.id;
      opt.textContent = o.name + ' (' + o.dice_type + ')';
      sel.appendChild(opt);
    });
  }

  function rollPreview() {
    var sel = document.getElementById('preview-oracle-select');
    var oid = parseInt(sel.value);
    if (!oid) return;
    var container = document.getElementById('oracle-preview-result');

    // Cargar el oráculo completo
    fetch(GAME_AJAX_BASE + '/oracles_list.php?' + new Date().getTime())
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (!d.ok || !d.data) return;
        var oracle = d.data.find(function(o) { return o.id === oid; });
        if (!oracle) return;

        // Roll local (mismo algoritmo que PHP)
        var max = parseInt(oracle.dice_type.replace('d', ''), 10) || 100;
        var roll = Math.floor(Math.random() * max) + 1;
        var results = oracle.results || [];
        var matched = null;
        results.forEach(function(entry) {
          var range = entry.range || '';
          var m = range.match(/^(\d+)\s*-\s*(\d+)$/);
          if (m) {
            if (roll >= parseInt(m[1], 10) && roll <= parseInt(m[2], 10)) {
              matched = entry;
            }
          }
        });

        if (matched) {
          container.innerHTML =
            '<div class="rpg-oracle-result-card">' +
              '<div class="rpg-oracle-result-header">' +
                '<span class="rpg-oracle-result-name">' + oracle.name + '</span>' +
                '<span class="rpg-oracle-result-dice">' + oracle.dice_type + ' → <strong>' + roll + '</strong></span>' +
              '</div>' +
              '<div class="rpg-oracle-result-range">Rango: ' + matched.range + '</div>' +
              '<div class="rpg-oracle-result-text">' + matched.result + '</div>' +
              (matched.description ? '<div class="rpg-oracle-result-desc">' + matched.description + '</div>' : '') +
              (matched.auto_invoke ? '<div class="rpg-oracle-result-invoke"><i class="fas fa-link"></i> Auto-invoca: ' + matched.auto_invoke.label + '</div>' : '') +
            '</div>';
        } else {
          container.innerHTML = '<div class="rpg-oracle-result-card"><div class="rpg-oracle-result-text">Tirada: ' + roll + ' — Sin resultado mapeado.</div></div>';
        }
      });
  }

  function addResultRow(range, result, desc, autoInvoke) {
    var list = document.getElementById('results-list');
    var idx = list.children.length;
    var row = document.createElement('div');
    row.className = 'rpg-result-row';
    row.innerHTML =
      '<input type="text" class="textbox rpg-result-input-range" placeholder="1-10" value="' + (range || '') + '">' +
      '<input type="text" class="textbox rpg-result-input-result" placeholder="Título del resultado" value="' + (result || '') + '">' +
      '<input type="text" class="textbox rpg-result-input-desc rpg-result-input-desc--wide" placeholder="Descripción (opcional)" value="' + (desc || '') + '">' +
      '<select class="textbox rpg-result-input-invoke">' +
        '<option value="">— Sin auto-invocar —</option>' +
      '</select>' +
      '<button type="button" class="rpg-system-tab-btn rpg-system-tab-btn--compact rpg-result-remove" title="Eliminar">&times;</button>';

    row.querySelector('.rpg-result-remove').addEventListener('click', function() {
      row.remove();
    });

    list.appendChild(row);
    populateAutoInvokeSelect(row.querySelector('.rpg-result-input-invoke'), autoInvoke || '');
  }

  function populateAutoInvokeSelect(sel, selectedVal) {
    fetch(GAME_AJAX_BASE + '/oracles_list.php?' + new Date().getTime())
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.ok && d.data) {
          d.data.forEach(function(o) {
            var opt = document.createElement('option');
            opt.value = o.id;
            opt.textContent = o.name;
            if (String(o.id) === String(selectedVal)) opt.selected = true;
            sel.appendChild(opt);
          });
        }
      });
  }

  function addVariationGroup(categoryKey) {
    var list = document.getElementById('variations-list');
    var idx = list.children.length;
    var div = document.createElement('div');
    div.className = 'rpg-variation-group';
    div.innerHTML =
      '<div class="rpg-variation-header">' +
        '<strong><i class="fas fa-globe"></i> ' + categoryKey + '</strong>' +
        '<button type="button" class="rpg-system-tab-btn rpg-system-tab-btn--compact rpg-variation-remove" title="Eliminar variación">&times;</button>' +
        '<input type="hidden" class="rpg-variation-key" value="' + categoryKey + '">' +
      '</div>' +
      '<div class="rpg-variation-results"></div>' +
      '<button type="button" class="rpg-system-tab-btn rpg-system-tab-btn--compact rpg-variation-add-result"><i class="fas fa-plus"></i> Añadir Resultado</button>';

    div.querySelector('.rpg-variation-remove').addEventListener('click', function() {
      div.remove();
    });

    var resultsCt = div.querySelector('.rpg-variation-results');
    div.querySelector('.rpg-variation-add-result').addEventListener('click', function() {
      addVariationResultRow(resultsCt);
    });

    list.appendChild(div);
  }

  function addVariationResultRow(container) {
    var row = document.createElement('div');
    row.className = 'rpg-result-row';
    row.innerHTML =
      '<input type="text" class="textbox rpg-result-input-range" placeholder="1-10">' +
      '<input type="text" class="textbox rpg-result-input-result" placeholder="Resultado">' +
      '<input type="text" class="textbox rpg-result-input-desc rpg-result-input-desc--wide" placeholder="Descripción">' +
      '<span></span>' +
      '<button type="button" class="rpg-system-tab-btn rpg-system-tab-btn--compact rpg-result-remove">&times;</button>';
    row.querySelector('.rpg-result-remove').addEventListener('click', function() {
      row.remove();
    });
    container.appendChild(row);
  }

  function openEditor(oracleData) {
    var title = document.getElementById('editor-title');
    var form = document.getElementById('oracle-editor-form');
    var resultsList = document.getElementById('results-list');
    var variationsList = document.getElementById('variations-list');
    var modal = document.getElementById('oracle-editor-modal');

    form.reset();
    document.getElementById('oracle_id').value = oracleData ? oracleData.id : '';
    resultsList.innerHTML = '';
    variationsList.innerHTML = '';

    if (oracleData) {
      title.innerHTML = '<i class="fas fa-edit"></i> Editar: ' + oracleData.name;
      document.getElementById('o_name').value = oracleData.name || '';
      document.getElementById('o_desc').value = oracleData.description || '';
      document.getElementById('o_type').value = oracleData.oracle_type || 'custom';
      document.getElementById('o_subtype').value = oracleData.subtype || '';
      document.getElementById('o_category').value = oracleData.category || '';
      document.getElementById('o_dice').value = oracleData.dice_type || 'd100';
      document.getElementById('o_image').value = oracleData.image_url || '';

      // Cargar resultados
      var results = oracleData.results || [];
      if (results.length) {
        results.forEach(function(r) {
          var ai = r.auto_invoke || null;
          addResultRow(r.range, r.result, r.description, ai ? ai.oracle_id : '');
        });
      }

      // Cargar variaciones
      var variations = oracleData.variations || {};
      for (var key in variations) {
        if (variations.hasOwnProperty(key)) {
          addVariationGroup(key);
          var lastGroup = variationsList.lastElementChild;
          var resultsCt = lastGroup.querySelector('.rpg-variation-results');
          var varResults = variations[key] || [];
          varResults.forEach(function(vr) {
            addVariationResultRow(resultsCt);
            var rows = resultsCt.querySelectorAll('.rpg-result-row');
            var lastRow = rows[rows.length - 1];
            lastRow.querySelector('.rpg-result-input-range').value = vr.range || '';
            lastRow.querySelector('.rpg-result-input-result').value = vr.result || '';
            lastRow.querySelector('.rpg-result-input-desc').value = vr.description || '';
          });
        }
      }
    } else {
      title.innerHTML = '<i class="fas fa-plus"></i> Crear Nuevo Oráculo';
      // Add one empty result row
      addResultRow('', '', '', '');
    }

    if (window.RpgModal) {
      RpgModal.open('oracle-editor-modal');
    }
  }

  function closeEditor() {
    if (window.RpgModal) {
      RpgModal.close('oracle-editor-modal');
    }
  }

  function gatherFormData() {
    var data = {
      name: document.getElementById('o_name').value,
      description: document.getElementById('o_desc').value,
      oracle_type: document.getElementById('o_type').value,
      subtype: document.getElementById('o_subtype').value,
      category: document.getElementById('o_category').value,
      dice_type: document.getElementById('o_dice').value,
      image_url: document.getElementById('o_image').value,
      results: [],
      variations: {},
      auto_invoke: []
    };

    // Gather results
    document.querySelectorAll('#results-list .rpg-result-row').forEach(function(row) {
      var range = row.querySelector('.rpg-result-input-range').value.trim();
      var result = row.querySelector('.rpg-result-input-result').value.trim();
      var desc = row.querySelector('.rpg-result-input-desc').value.trim();
      var invokeSel = row.querySelector('.rpg-result-input-invoke');
      if (range && result) {
        var entry = { range: range, result: result, description: desc };
        if (invokeSel && invokeSel.value) {
          entry.auto_invoke = { oracle_id: parseInt(invokeSel.value, 10), label: invokeSel.options[invokeSel.selectedIndex].text };
        }
        data.results.push(entry);
      }
    });

    // Gather variations
    document.querySelectorAll('#variations-list .rpg-variation-group').forEach(function(group) {
      var keyInput = group.querySelector('.rpg-variation-key');
      var key = keyInput ? keyInput.value : '';
      if (!key) return;
      data.variations[key] = [];
      group.querySelectorAll('.rpg-variation-results .rpg-result-row').forEach(function(row) {
        var range = row.querySelector('.rpg-result-input-range').value.trim();
        var result = row.querySelector('.rpg-result-input-result').value.trim();
        var desc = row.querySelector('.rpg-result-input-desc').value.trim();
        if (range && result) {
          data.variations[key].push({ range: range, result: result, description: desc });
        }
      });
    });

    return data;
  }

  function saveOracle() {
    var id = document.getElementById('oracle_id').value;
    var data = gatherFormData();
    if (!data.name) { alert('El nombre es obligatorio.'); return; }
    if (!data.results.length) { alert('Debes añadir al menos un resultado.'); return; }

    var endpoint = id ? 'oracles_update.php' : 'oracles_create.php';
    if (id) data.id = parseInt(id, 10);

    var btn = document.querySelector('#oracle-editor-form button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

    staffPost(endpoint, data).then(function(res) {
      btn.disabled = false;
      btn.innerHTML = 'Guardar Oráculo';
      if (res.ok) {
        closeEditor();
        loadCatalog();
        populatePreviewSelect();
      } else {
        alert('Error: ' + (res.error ? res.error.message : 'Desconocido'));
      }
    }).catch(function() {
      btn.disabled = false;
      btn.innerHTML = 'Guardar Oráculo';
      alert('Error de conexión.');
    });
  }

  function loadOracleForEdit(id) {
    fetch(GAME_AJAX_BASE + '/oracles_list.php?' + new Date().getTime())
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.ok && d.data) {
          var oracle = d.data.find(function(o) { return o.id === id; });
          if (oracle) openEditor(oracle);
        }
      });
  }

  function deleteOracle(id) {
    staffPost('oracles_delete.php', { id: id }).then(function(res) {
      if (res.ok) {
        loadCatalog();
        populatePreviewSelect();
      } else {
        alert('Error: ' + (res.error ? res.error.message : 'Desconocido'));
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
