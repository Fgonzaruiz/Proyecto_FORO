/**
 * UI del sistema de oráculos — frontend.
 * Renderiza resultados en posts y selector en el editor.
 */
var RpgOracles = {
  config: {
    baseUrl: ''
  },

  _oracleCache: null,

  init: function() {
    if (RpgCards && RpgCards.config && RpgCards.config.baseUrl) {
      this.config.baseUrl = RpgCards.config.baseUrl;
    } else {
      var idx = window.location.pathname.toLowerCase().indexOf('/game/');
      if (idx !== -1) {
        this.config.baseUrl = window.location.origin + window.location.pathname.substring(0, idx);
      } else {
        var firstFolder = window.location.pathname.split('/')[1] || '';
        if (firstFolder.toLowerCase() === 'foro') {
          this.config.baseUrl = window.location.origin + '/' + firstFolder;
        } else {
          this.config.baseUrl = window.location.origin;
        }
      }
    }

    // Los oráculos en posts los renderiza RpgCards.renderSinglePostZone (cards_for_post.php)

    // Inicializar selector en editor
    this.initOracleSelector();
  },

  // ─── CARGA Y RENDERIZADO EN POSTS ───

  loadPostOracles: function() {
    var self = this;
    var zones = document.querySelectorAll('.rpg-post-cards-zone');
    if (zones.length === 0) return;

    zones.forEach(function(zone) {
      self.renderOraclesForZone(zone);
    });
  },

  renderOraclesForZone: function(zone) {
    var self = this;
    var postId = zone.dataset.postId;
    if (!postId) return;

    fetch(self.config.baseUrl + '/game/ajax/oracles_for_post.php?post_id=' + postId)
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (!d.ok || !d.data || !d.data.length) return;
        self._appendOraclesToZone(zone, d.data);
      })
      .catch(function() {});
  },

  _appendOraclesToZone: function(zone, oracles) {
    var bodyId = 'rpg-oracles-body-' + zone.dataset.postId;
    var arrowId = 'rpg-oracles-arrow-' + zone.dataset.postId;
    var toggleId = 'rpg-oracles-toggle-' + zone.dataset.postId;

    var hasAutoInvoked = oracles.some(function(o) { return o.auto_invoked; });

    var html =
      '<div id="' + toggleId + '" class="rpg-post-cards-toggle is-open" onclick="RpgCards.togglePostCards(\'' + bodyId + '\',\'' + arrowId + '\',\'' + toggleId + '\')">' +
        '<span class="rpg-post-cards-toggle__label"><i class="fas fa-crystal-ball"></i> Oráculos (' + oracles.length + ')' +
          (hasAutoInvoked ? ' <span class="rpg-oracle-invoked-badge"><i class="fas fa-link"></i> con auto-invocados</span>' : '') +
        '</span>' +
        '<span id="' + arrowId + '" class="rpg-post-cards-toggle__arrow is-open"><i class="fas fa-chevron-down"></i></span>' +
      '</div>' +
      '<div id="' + bodyId + '" class="rpg-post-cards-body is-open">';

    oracles.forEach(function(o) {
      html += self._renderOracleResult(o);
    });

    html += '</div>';

    // Insertar antes del primer contenido existente en la zona, o al final
    var existingContent = zone.innerHTML;
    if (existingContent.trim()) {
      // Buscar el último toggle o body y añadir después
      zone.innerHTML = existingContent + html;
    } else {
      zone.innerHTML = html;
    }

    zone.classList.add('is-visible');
  },

  _renderOracleResult: function(o) {
    var aiLabel = o.auto_invoked
      ? '<span class="rpg-oracle-auto-badge"><i class="fas fa-link"></i> Auto-invocado</span>'
      : '';

    return '<div class="rpg-oracle-card" data-oracle-id="' + o.oracle_id + '" data-post-oracle-id="' + o.id + '">' +
      '<div class="rpg-oracle-card-header">' +
        '<div class="rpg-oracle-card-title">' +
          o.name +
          (o.subtype ? ' <span class="rpg-oracle-subtype">' + o.subtype + '</span>' : '') +
        '</div>' +
        '<div class="rpg-oracle-card-dice">' +
          aiLabel +
          ' <span class="rpg-oracle-roll-badge"><i class="fas fa-dice-d6"></i> ' + (o.dice_type || 'd100') + ' → <strong>' + o.roll_value + '</strong></span>' +
        '</div>' +
      '</div>' +
      (o.description ? '<div class="rpg-oracle-card-desc">' + o.description + '</div>' : '') +
      '<div class="rpg-oracle-card-result">' +
        '<div class="rpg-oracle-result-range">Rango <strong>' + o.result_range + '</strong></div>' +
        '<div class="rpg-oracle-result-text">' + o.result_text + '</div>' +
        (o.result_description ? '<div class="rpg-oracle-result-desc">' + o.result_description + '</div>' : '') +
      '</div>' +
    '</div>';
  },

  // ─── SELECTOR EN EDITOR ───

  initOracleSelector: function() {
    var selector = document.getElementById('rpg-oracle-selector');
    var toggleBtn = document.getElementById('rpg-oracle-toggle-btn');
    var panel = document.getElementById('rpg-oracle-panel');
    var input = document.getElementById('rpg_oracles');

    if (!selector || !toggleBtn || !panel || !input) return;

    var self = this;
    var selectedOracles = [];

    // Cargar catálogo de oráculos
    var url = this.config.baseUrl + '/game/ajax/oracles_list.php';
    var catInput = document.querySelector('input[name="category"]');
    if (catInput && catInput.value) {
      url += '&category=' + encodeURIComponent(catInput.value);
    }

    fetch(url)
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (!d.ok || !d.data) {
          selector.classList.add('is-hidden');
          return;
        }

        self._oracleCache = d.data;
        selector.classList.remove('is-hidden');

        var html = '';
        var grouped = {};
        d.data.forEach(function(o) {
          var type = o.oracle_type || 'custom';
          if (!grouped[type]) grouped[type] = [];
          grouped[type].push(o);
        });

        var typeNames = {
          'custom': 'Personalizados',
          'yes_no': 'Sí/No',
          'action': 'Acción',
          'theme': 'Tema',
          'action_theme': 'Acción + Tema',
          'place_descriptor': 'Lugares',
          'character_role': 'Personajes',
          'pay_the_price': 'Paga el Precio'
        };

        var typeOrder = ['custom','yes_no','action','theme','action_theme','place_descriptor','place_focus','character_role','character_trait','character_goal','pay_the_price','delve_theme','delve_domain'];

        typeOrder.forEach(function(type) {
          var items = grouped[type];
          if (!items || !items.length) return;
          var label = typeNames[type] || type.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });

          html +=
            '<div class="rpg-deck-section">' +
              '<div class="rpg-deck-section-header" onclick="RpgOracles.toggleOracleSection(\'' + type + '\',this)">' +
                '<div class="rpg-deck-section-title">' +
                  '<i class="fas fa-dice-d6"></i> ' + label + ' <span class="rpg-deck-section-count">(' + items.length + ')</span>' +
                '</div>' +
                '<div class="rpg-deck-section-arrow"><i class="fas fa-chevron-down"></i></div>' +
              '</div>' +
              '<div id="rpg-oracle-section-' + type + '" class="rpg-deck-section-content">';

          items.forEach(function(o) {
            var resultsCount = (o.results && o.results.length) || 0;
            var hasVariations = false;
            if (o.variations) {
              for (var k in o.variations) {
                if (o.variations.hasOwnProperty(k) && o.variations[k].length > 0) hasVariations = true;
              }
            }

            html +=
              '<div class="rpg-selectable-card-container">' +
                '<div class="rpg-selectable-card rpg-selectable-oracle" data-oid="' + o.id + '">' +
                  '<div class="rpg-card rpg-card--oracle">' +
                    '<div class="rpg-card-header">' +
                      '<div class="rpg-card-title">' + o.name + '</div>' +
                      '<div class="rpg-card-subtitle">' + o.dice_type + ' · ' + resultsCount + ' resultados' +
                        (hasVariations ? ' · <i class="fas fa-globe"></i> var.' : '') +
                      '</div>' +
                    '</div>' +
                    '<div class="rpg-card-body">' +
                      (o.description ? '<div class="rpg-card-desc">' + o.description + '</div>' : '') +
                    '</div>' +
                  '</div>' +
                '</div>' +
              '</div>';
          });

          html += '</div></div>';
        });

        panel.innerHTML = html;

        // Oracle click toggle
        panel.querySelectorAll('.rpg-selectable-oracle').forEach(function(el) {
          el.addEventListener('click', function() {
            var oid = parseInt(this.dataset.oid);
            var idx = selectedOracles.indexOf(oid);
            if (idx === -1) {
              selectedOracles.push(oid);
              el.classList.add('selected');
            } else {
              selectedOracles.splice(idx, 1);
              el.classList.remove('selected');
            }
            self._updateOracleInput(selectedOracles);
            self._updateOracleCount();
          });
        });

        // Toggle panel
        toggleBtn.addEventListener('change', function(e) {
          panel.classList[e.target.checked ? 'add' : 'remove']('is-visible');
        });
        if (toggleBtn.checked) panel.classList.add('is-visible');

        self._updateOracleCount();
      });
  },

  toggleOracleSection: function(type, header) {
    var content = document.getElementById('rpg-oracle-section-' + type);
    if (!content) return;
    var isOpen = content.classList.toggle('is-open');
    header.classList.toggle('is-open', isOpen);
  },

  _updateOracleInput: function(selected) {
    var input = document.getElementById('rpg_oracles');
    if (input) {
      input.value = JSON.stringify(selected);
    }
  },

  _updateOracleCount: function() {
    var countEl = document.getElementById('rpg-oracle-count');
    var selected = document.querySelectorAll('.rpg-selectable-oracle.selected').length;
    if (countEl) countEl.textContent = selected;
  }
};

if (typeof window !== 'undefined') {
  window.RpgOracles = RpgOracles;
}

// Integrar con el DOMContentLoaded existente
document.addEventListener('DOMContentLoaded', function() {
  RpgOracles.init();
});
