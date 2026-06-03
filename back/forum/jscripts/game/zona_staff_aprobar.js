/**
 * Auto-extracted from back/forum/game/public/zona_staff_aprobar.php
 * Config: window.ZONA_STAFF_APROBAR_CONFIG
 */
(function () {
  "use strict";
  var cfg = window.ZONA_STAFF_APROBAR_CONFIG || {};
  var bburl = cfg.bburl || (window.GAME_BBURL || '');
  var staffLevel = cfg.staffLevel || 0;

function applyDataBg(container) {
  var root = container && container.querySelectorAll ? container : document;
  root.querySelectorAll('[data-bg]').forEach(function (el) {
    var url = el.getAttribute('data-bg');
    if (url) el.style.backgroundImage = "url('" + String(url).replace(/'/g, '%27') + "')";
  });
}

function applyDataIconColor(container) {
  var root = container && container.querySelectorAll ? container : document;
  root.querySelectorAll('[data-icon-color]').forEach(function (el) {
    var c = el.getAttribute('data-icon-color');
    if (c) el.style.color = c;
  });
}

function applyDataPct(container) {
  var root = container && container.querySelectorAll ? container : document;
  root.querySelectorAll('[data-pct]').forEach(function (el) {
    var pct = el.getAttribute('data-pct');
    if (pct != null && pct !== '') el.style.width = pct + '%';
  });
}

function statusListClass(status) {
  return 'aprobar-list-item-status--' + (status || 'pendiente');
}

function statusBadgeClass(status) {
  return 'aprobar-preview-badge--' + (status || 'pendiente');
}

// ==================== LINAJE PERK SYSTEM CATALOG ===================
var LINAJE_DATA = <?php echo $catalog_json; ?>;
function enrichPerk(p) {
    if (!p) return p;
    if (p.icon && p.iconColor) return p;
    var icon = 'fa-dna';
    var iconColor = '#C62828';
    var id = p.id || '';
    if (id.startsWith('pp_')) { p.icon = 'fa-shield-alt'; p.iconColor = '#10b981'; return p; }
    if (id.startsWith('ps_')) { p.icon = 'fa-crown'; p.iconColor = '#f59e0b'; return p; }
    if (id.startsWith('g_linaje_fuego')) { icon = 'fa-fire'; iconColor = '#ef4444'; }
    else if (id.startsWith('g_linaje_rayo')) { icon = 'fa-bolt'; iconColor = '#eab308'; }
    else if (id.startsWith('g_linaje_hielo')) { icon = 'fa-snowflake'; iconColor = '#06b6d4'; }
    else if (id.startsWith('g_linaje_viento')) { icon = 'fa-wind'; iconColor = '#4A148C'; }
    else if (id.startsWith('g_linaje_tierra')) { icon = 'fa-mountain'; iconColor = '#b45309'; }
    else if (id.startsWith('g_linaje_agua')) { icon = 'fa-water'; iconColor = '#3b82f6'; }
    else if (id.startsWith('g_piel_acero')) { icon = 'fa-shield-alt'; iconColor = '#6b7280'; }
    else if (id.startsWith('g_vitalidad')) { icon = 'fa-heartbeat'; iconColor = '#ef4444'; }
    else if (id.startsWith('g_energia')) { icon = 'fa-bolt'; iconColor = '#3b82f6'; }
    else if (id.startsWith('g_constitucion')) { icon = 'fa-dumbbell'; iconColor = '#f43f5e'; }
    else if (id.startsWith('g_metabolismo')) { icon = 'fa-utensils'; iconColor = '#10b981'; }
    else if (id.startsWith('g_resistencia')) { icon = 'fa-hand-rock'; iconColor = '#f59e0b'; }
    else if (id.startsWith('g_regeneracion')) { icon = 'fa-leaf'; iconColor = '#10b981'; }
    else if (id.startsWith('g_mente') || id.startsWith('g_intelecto') || id.startsWith('g_lucidez') || id.startsWith('g_concentracion')) { icon = 'fa-brain'; iconColor = '#3b82f6'; }
    else if (id.startsWith('g_voluntad_ferrea')) { icon = 'fa-fingerprint'; iconColor = '#C62828'; }
    else if (id.startsWith('g_instinto')) { icon = 'fa-compass'; iconColor = '#8b5cf6'; }
    else if (id.startsWith('g_paso') || id.startsWith('g_sombra')) { icon = 'fa-user-ninja'; iconColor = '#475569'; }
    else if (id.startsWith('g_agilidad')) { icon = 'fa-running'; iconColor = '#10b981'; }
    else if (id.startsWith('g_evasion')) { icon = 'fa-wind'; iconColor = '#06b6d4'; }
    else if (id.startsWith('g_parkour')) { icon = 'fa-shoe-prints'; iconColor = '#f59e0b'; }
    else if (id.startsWith('g_haki_obs')) { icon = 'fa-eye'; iconColor = '#C62828'; }
    else if (id.startsWith('g_haki_arm')) { icon = 'fa-shield-alt'; iconColor = '#6b7280'; }
    else if (id.startsWith('g_haki_conq')) { icon = 'fa-crown'; iconColor = '#db2777'; }
    else if (id.startsWith('g_suerte') || id.startsWith('g_golpe') || id.startsWith('g_fortuna')) { icon = 'fa-dice-d20'; iconColor = '#f59e0b'; }
    else if (id.startsWith('g_carisma') || id.startsWith('g_presencia') || id.startsWith('g_inspiracion') || id.startsWith('g_nombre_temido') || id.startsWith('g_voz_rey')) { icon = 'fa-comments'; iconColor = '#ec4899'; }
    else if (id.startsWith('g_manos_') || id.startsWith('g_dedos_') || id.startsWith('g_ojo_') || id.startsWith('g_genio_') || id.startsWith('g_cocinero_')) { icon = 'fa-tools'; iconColor = '#06b6d4'; }
    else if (id.startsWith('g_cuatro_brazos')) { icon = 'fa-hand-paper'; iconColor = '#3b82f6'; }
    else if (id.startsWith('g_tercer_ojo')) { icon = 'fa-eye'; iconColor = '#4A148C'; }
    else if (id.startsWith('g_sangre_fria')) { icon = 'fa-snowflake'; iconColor = '#06b6d4'; }
    else if (id.startsWith('g_linaje_marino')) { icon = 'fa-anchor'; iconColor = '#3b82f6'; }
    else if (id.startsWith('g_gula')) { icon = 'fa-cookie-bite'; iconColor = '#b45309'; }
    else if (id.startsWith('g_pelo')) { icon = 'fa-magic'; iconColor = '#db2777'; }
    else if (id.startsWith('g_piel_color')) { icon = 'fa-palette'; iconColor = '#10b981'; }
    else if (id.startsWith('g_no_dormir')) { icon = 'fa-eye-slash'; iconColor = '#64748b'; }
    else if (id.startsWith('g_sangre_de_gigante')) { icon = 'fa-expand-arrows-alt'; iconColor = '#ef4444'; }
    else if (id.startsWith('g_cuerpo_elastico')) { icon = 'fa-dumbbell'; iconColor = '#10b981'; }
    else if (id.startsWith('rh_')) { icon = 'fa-user'; iconColor = '#C62828'; }
    else if (id.startsWith('rm_')) { icon = 'fa-paw'; iconColor = '#10b981'; }
    else if (id.startsWith('rg_')) { icon = 'fa-fish'; iconColor = '#06b6d4'; }
    else if (id.startsWith('rgi_')) { icon = 'fa-expand-arrows-alt'; iconColor = '#ef4444'; }
    else if (id.startsWith('rt_')) { icon = 'fa-seedling'; iconColor = '#10b981'; }
    else if (id.startsWith('rb_')) { icon = 'fa-anchor'; iconColor = '#f59e0b'; }
    else if (id.startsWith('rl_')) { icon = 'fa-feather-alt'; iconColor = '#ec4899'; }
    else if (id.startsWith('rs_')) { icon = 'fa-cloud'; iconColor = '#06b6d4'; }
    else if (id.startsWith('ro_')) { icon = 'fa-ghost'; iconColor = '#ef4444'; }
    else if (id.startsWith('rsi_')) { icon = 'fa-tint'; iconColor = '#3b82f6'; }
    p.icon = icon;
    p.iconColor = iconColor;
    return p;
}

function findPerkById(id) {
    function findInList(list) {
        if (!Array.isArray(list)) return null;
        for (var i = 0; i < list.length; i++) {
            if (list[i] && list[i].id === id) {
                return list[i];
            }
        }
        return null;
    }

    if (LINAJE_DATA.arbol_general) {
        for (var catKey in LINAJE_DATA.arbol_general) {
            var cat = LINAJE_DATA.arbol_general[catKey];
            if (cat && cat.perks) {
                var found = findInList(cat.perks);
                if (found) return enrichPerk(found);
            }
        }
    }
    if (LINAJE_DATA.arboles_raciales) {
        for (var race in LINAJE_DATA.arboles_raciales) {
            var tree = LINAJE_DATA.arboles_raciales[race];
            if (tree && tree.perks) {
                var found = findInList(tree.perks);
                if (found) return enrichPerk(found);
            }
        }
    }
    if (LINAJE_DATA.pasivas_primarias) {
        for (var race in LINAJE_DATA.pasivas_primarias) {
            var list = LINAJE_DATA.pasivas_primarias[race];
            var found = findInList(list);
            if (found) return enrichPerk(found);
        }
    }
    if (LINAJE_DATA.pasivas_secundarias) {
        for (var race in LINAJE_DATA.pasivas_secundarias) {
            var list = LINAJE_DATA.pasivas_secundarias[race];
            var found = findInList(list);
            if (found) return enrichPerk(found);
        }
    }
    return null;
}

function makeAprobarPerkCard(p, cssClass, iconClass, badgeClass, badgeLabel) {
    var costBadge = p.cost ? '<div class="gene-card-cost-badge">' + p.cost + ' PTS</div>' : '';
    return '<div class="gene-card gene-card--relative ' + cssClass + '">' +
        costBadge +
        '<div class="gene-card-icon ' + iconClass + '">' +
            '<i class="fas ' + p.icon + '" data-icon-color="' + escapeHtml(p.iconColor || '') + '"></i>' +
        '</div>' +
        '<div class="gene-card-info">' +
            '<div class="gene-card-name">' + escapeHtml(p.name) + '</div>' +
            '<div class="gene-card-desc">' + escapeHtml(p.desc) + '</div>' +
        '</div>' +
        '<div class="gene-card-badge ' + badgeClass + '">' + badgeLabel + '</div>' +
    '</div>';
}

var currentPJ = null;
var currentFilter = '';

var statusConfig = {
  'pendiente': { label: 'Sin Revisar', color: '#ef4444', icon: 'fa-clock' },
  'revision':  { label: 'En Revisión', color: '#f59e0b', icon: 'fa-sync-alt' },
  'aprobada':  { label: 'Aprobada', color: '#10b981', icon: 'fa-check-circle' },
  'rechazada': { label: 'Rechazada', color: '#ef4444', icon: 'fa-times-circle' },
};

function loadList(filter) {
  currentFilter = filter || '';
  var url = '<?= $b_url ?>/game/ajax/personajes_pendientes_list.php';
  if (filter) url += '?filter=' + encodeURIComponent(filter);

  fetch(url)
    .then(function(r) {
      if (!r.ok) { throw new Error('HTTP ' + r.status); }
      return r.json();
    })
    .then(function(res) {
      if (!res.ok) { throw new Error((res.error && res.error.message) ? res.error.message : 'Error del servidor'); }
      renderList(res.data);
    })
    .catch(function(err) {
      document.getElementById('aprobar-list-items').innerHTML = '<div class="aprobar-empty">Error: ' + err.message + '</div>';
    });
}

function renderList(chars) {
  var container = document.getElementById('aprobar-list-items');
  var countEl = document.getElementById('aprobar-count');
  countEl.textContent = chars.length;

  if (!chars.length) {
    container.innerHTML = '<div class="aprobar-empty">No hay personajes en esta categor&iacute;a</div>';
    return;
  }

  var html = '';
  chars.forEach(function(c) {
    var cfg = statusConfig[c.status] || { label: c.status, color: '#94a3b8', icon: 'fa-question' };
    var avatarUrl = c.avatar || 'https://placehold.co/290x450';
    html += '<div class="aprobar-list-item" data-id="' + c.id + '" onclick="selectChar(' + c.id + ')">';
    html += '  <div class="aprobar-list-item-avatar" data-bg="' + escapeHtml(avatarUrl) + '"></div>';
    html += '  <div class="aprobar-list-item-body">';
    html += '    <div class="aprobar-list-item-name">' + escapeHtml(c.name) + '</div>';
    html += '    <div class="aprobar-list-item-user">' + escapeHtml(c.username) + '</div>';
    html += '    <span class="aprobar-list-item-status ' + statusListClass(c.status) + '"><i class="fas ' + cfg.icon + '"></i> ' + cfg.label + '</span>';
    html += '  </div>';
    html += '</div>';
  });
  container.innerHTML = html;
  applyDataBg(container);
}

function selectChar(id) {
  // Highlight selected
  var items = document.querySelectorAll('.aprobar-list-item');
  items.forEach(function(item) {
    item.classList.toggle('selected', parseInt(item.getAttribute('data-id')) === id);
  });

  // Fetch preview
  var preview = document.getElementById('aprobar-preview');
  preview.innerHTML = '<div class="aprobar-empty aprobar-empty--loading"><i class="fas fa-spinner fa-spin"></i><br>Cargando ficha...</div>';

  var url = '<?= $b_url ?>/game/ajax/get_personaje_preview.php?pj=' + id;
  fetch(url)
    .then(function(r) {
      if (!r.ok) { throw new Error('HTTP ' + r.status); }
      return r.json();
    })
    .then(function(res) {
      if (!res.ok) { throw new Error((res.error && res.error.message) ? res.error.message : 'Error del servidor'); }
      renderPreview(res.data);
      currentPJ = res.data;
    })
    .catch(function(err) {
      preview.innerHTML = '<div class="aprobar-empty">Error: ' + err.message + '</div>';
    });
}

function renderPreview(data) {
  var cfg = statusConfig[data.status] || { label: data.status, color: '#94a3b8', icon: 'fa-question' };
  var avatarUrl = data.avatar || 'https://placehold.co/290x450';
  var stats = data.stats || {};
  var bio = data.bio || {};
  var linaje = data.linaje || {};

  var html = '';
  // Avatar section
  html += '<div class="aprobar-preview-avatar" data-bg="' + escapeHtml(avatarUrl) + '"></div>';

  html += '<div class="aprobar-preview-body">';
  html += '  <h2 class="aprobar-preview-name">' + escapeHtml(data.name) + '</h2>';
  html += '  <div class="aprobar-preview-badges">';
  html += '    <span class="aprobar-preview-badge ' + statusBadgeClass(data.status) + '"><i class="fas ' + cfg.icon + '"></i> ' + cfg.label + '</span>';
  if (data.rango) html += '    <span class="aprobar-preview-badge aprobar-preview-badge--purple"><i class="fas fa-medal"></i> ' + escapeHtml(data.rango) + '</span>';
  if (data.faction) html += '    <span class="aprobar-preview-badge aprobar-preview-badge--indigo"><i class="fas fa-flag"></i> ' + escapeHtml(data.faction) + '</span>';
  if (data.is_staff) html += '    <span class="aprobar-preview-badge aprobar-preview-badge--staff"><i class="fas fa-star"></i> Staff</span>';
  html += '  </div>';

  html += '  <div class="aprobar-preview-grid">';
  html += '    <div class="aprobar-preview-field-row"><i class="fas fa-fist-raised aprobar-icon--indigo"></i><div class="aprobar-preview-field"><div class="aprobar-preview-field-label">Arquetipo Belico</div><div class="aprobar-field-value--indigo">' + escapeHtml(bio.arquetipo) + '</div></div></div>';
  html += '    <div class="aprobar-preview-field-row"><i class="fas fa-briefcase aprobar-icon--purple"></i><div class="aprobar-preview-field"><div class="aprobar-preview-field-label">Oficio</div><div class="aprobar-field-value--purple">' + escapeHtml(data.occupation_name || 'Ninguno') + '</div></div></div>';
  var geneNames = linaje.geneNames || [];
  var genesText = geneNames.length ? geneNames.slice(0, 3).join(', ') + (geneNames.length > 3 ? ' +' + (geneNames.length - 3) : '') : 'Ninguno';
  html += '    <div class="aprobar-preview-field-row"><i class="fas fa-dna aprobar-icon--purple"></i><div class="aprobar-preview-field"><div class="aprobar-preview-field-label">Genes Activos</div><div class="aprobar-field-value--purple">' + escapeHtml(genesText) + '</div></div></div>';
  html += '    <div class="aprobar-preview-field-row"><i class="fas fa-user aprobar-icon--muted"></i><div class="aprobar-preview-field"><div class="aprobar-preview-field-label">Jugador</div><div class="aprobar-field-value--primary">' + escapeHtml(data.username) + '</div></div></div>';
  html += '  </div>';

  html += '  <h3 class="aprobar-preview-stats-title">Atributos Base</h3>';
  var statMeta = [
    { key: 'str', label: 'FUERZA', color: '#C62828' },
    { key: 'agi', label: 'AGILIDAD', color: '#10b981' },
    { key: 'res', label: 'RESISTENCIA', color: '#f59e0b' },
    { key: 'vol', label: 'VOLUNTAD', color: '#ef4444' },
  ];
  statMeta.forEach(function(s) {
    var val = parseInt(stats[s.key] || 0, 10);
    var pct = Math.min(100, val * 10);
    html += '  <div class="aprobar-preview-stat-row">';
    html += '    <div class="aprobar-preview-stat-header"><span>' + s.label + '</span><span>' + val + '</span></div>';
    html += '    <div class="aprobar-preview-stat"><span class="aprobar-preview-stat-label"></span><div class="aprobar-preview-stat-bar"><div class="aprobar-preview-stat-fill aprobar-preview-stat-fill--' + s.key + '" data-pct="' + pct + '"></div></div></div>';
    html += '  </div>';
  });

  html += '  <div class="pj-preview-tabs">';
  html += '    <div class="pj-preview-tab aprobar-tab active" data-tab="bio" onclick="switchAprobarTab(\'bio\', this)"><i class="fas fa-file-alt"></i> Biografia</div>';
  html += '    <div class="pj-preview-tab aprobar-tab" data-tab="linaje" onclick="switchAprobarTab(\'linaje\', this)"><i class="fas fa-dna"></i> Mapa Genetico</div>';
  html += '  </div>';

  html += '  <div id="aprobTab_bio" class="aprobar-tab-content active">';

  html += '    <div class="aprobar-bio-grid">';
  html += '      <div class="aprobar-bio-cell"><strong>Edad:</strong> ' + escapeHtml(bio.age) + '</div>';
  html += '      <div class="aprobar-bio-cell"><strong>Origen:</strong> ' + escapeHtml(bio.origin) + '</div>';
  html += '      <div class="aprobar-bio-cell"><strong>Raza:</strong> ' + escapeHtml(bio.race) + '</div>';
  html += '      <div class="aprobar-bio-cell"><strong>PB:</strong> ' + escapeHtml(bio.pb) + '</div>';
  html += '    </div>';

  html += '    <h3 class="aprobar-section-heading">Apariencia Fisica</h3>';
  html += '    <div class="aprobar-scroll-box">' + escapeHtml(bio.physique || 'Sin registrar.') + '</div>';

  html += '    <h3 class="aprobar-section-heading aprobar-section-heading--spaced">Perfil Psicologico</h3>';
  html += '    <div class="aprobar-scroll-box">' + escapeHtml(bio.psychology || bio.desc || 'Sin historia registrada.') + '</div>';

  html += '    <h3 class="aprobar-section-heading aprobar-section-heading--spaced">Extras y Notas</h3>';
  html += '    <div class="aprobar-scroll-box">' + escapeHtml(bio.extras || bio.details || 'Sin notas extras.') + '</div>';

  html += '  </div>';

  html += '  <div id="aprobTab_linaje" class="aprobar-tab-content">';

  if (linaje.version === 2) {
      // Calculate max and spent points
      var maxPoints = 4;
      var race = bio.race || '';
      if (race.startsWith('Híbrido') || race.startsWith('Hibrido')) {
          var match = race.match(/Híbrid[o|a]\s*\(([^/]+)\s*\/\s*([^)]+)\)/i);
          var ptsDom = 20;
          if (match) {
              var rDom = match[1].trim();
              if (LINAJE_DATA.puntos_linaje_por_raza[rDom]) ptsDom = LINAJE_DATA.puntos_linaje_por_raza[rDom];
          }
          maxPoints = ptsDom - 4;
      } else {
          if (LINAJE_DATA.puntos_linaje_por_raza[race]) {
              maxPoints = LINAJE_DATA.puntos_linaje_por_raza[race];
          }
      }

      var spentPoints = 0;
      var racialList = linaje.elegidos_racial || [];
      var generalList = linaje.elegidos_general || [];
      racialList.forEach(function(pid) {
          var p = findPerkById(pid);
          if (p) spentPoints += (p.cost || 1);
      });
      generalList.forEach(function(pid) {
          var p = findPerkById(pid);
          if (p) spentPoints += (p.cost || 1);
      });

      var sobrante = maxPoints - spentPoints;
      var bonusPP = sobrante * 3;

      // Let's render a beautiful status bar for points
      html += '    <div class="linaje-slots-bar linaje-slots-bar--column">';
      html += '        <div class="linaje-slots-group">';
      html += '            <span class="linaje-slots-label"><i class="fas fa-gem"></i> Puntos de Linaje:</span>';
      if (maxPoints <= 10) {
          html += '            <div class="linaje-slots-dots">';
          for (var i = 0; i < maxPoints; i++) {
              html += '                <div class="linaje-slot-dot' + (i < spentPoints ? ' filled' : '') + '"></div>';
          }
          html += '            </div>';
      }
      html += '            <span class="linaje-slots-count">' + spentPoints + '/' + maxPoints + '</span>';
      html += '        </div>';
      html += '        <div id="linajeSobranteBonus">Puntos Sobrantes: ' + sobrante + ' PL = ' + bonusPP + ' PP de Bonus</div>';
      html += '    </div>';
    var hasAnyPerks = false;
    
    // Pasivas
    var pasivas = linaje.pasivas || [];
    if (pasivas.length > 0) {
      hasAnyPerks = true;
      html += '    <div class="linaje-section-title linaje-section-title--pasivas"><i class="fas fa-shield-alt"></i> Pasivas Innatas</div>';
      html += '    <div class="gene-cards-grid">';
      pasivas.forEach(function(pid) {
        var p = findPerkById(pid);
        if (p) {
          var is_prim = (p.type === 'primaria');
          html += makeAprobarPerkCard(p,
            is_prim ? 'passive-primary' : 'passive-secondary',
            is_prim ? 'gene-card-icon--primary' : 'gene-card-icon--secondary',
            is_prim ? 'gene-card-badge--primary' : 'gene-card-badge--secondary',
            is_prim ? 'PRIMARIA' : 'SECUNDARIA'
          );
        }
      });
      html += '    </div>';
    }

    // Racial
    var elegidos_racial = linaje.elegidos_racial || [];
    if (elegidos_racial.length > 0) {
      hasAnyPerks = true;
      html += '    <div class="linaje-section-title linaje-section-title--racial"><i class="fas fa-dna"></i> Linaje Racial</div>';
      html += '    <div class="gene-cards-grid">';
      elegidos_racial.forEach(function(pid) {
        var p = findPerkById(pid) || { id: pid, name: pid, icon: 'fa-dna', iconColor: 'var(--accent-indigo)', desc: 'Perk racial seleccionado.' };
        html += makeAprobarPerkCard(p, 'perk-racial', 'gene-card-icon--racial', 'gene-card-badge--racial', 'RACIAL');
      });
      html += '    </div>';
    }

    // General
    var elegidos_general = linaje.elegidos_general || [];
    if (elegidos_general.length > 0) {
      hasAnyPerks = true;
      html += '    <div class="linaje-section-title linaje-section-title--general"><i class="fas fa-star"></i> Linaje General</div>';
      html += '    <div class="gene-cards-grid">';
      elegidos_general.forEach(function(pid) {
        var p = findPerkById(pid) || { id: pid, name: pid, icon: 'fa-star', iconColor: 'var(--accent-purple)', desc: 'Perk general seleccionado.' };
        html += makeAprobarPerkCard(p, 'perk-general', 'gene-card-icon--general', 'gene-card-badge--general', 'GENERAL');
      });
      html += '    </div>';
    }

    if (!hasAnyPerks) {
      html += '    <div class="linaje-empty-box"><i class="fas fa-scroll icon-indigo"></i><h4>Sin Perks de Linaje</h4><p>Este personaje no tiene perks de linaje asignados todavía.</p></div>';
    }
  } else {
    html += '    <p class="rpg-preview-note">Perks de Linaje del personaje — pasivas innatas y habilidades elegidas.</p>';
    html += '    <div class="linaje-legacy-banner"><i class="fas fa-info-circle"></i><div><div class="linaje-legacy-banner-title">Ficha en formato antiguo</div><div class="linaje-legacy-banner-desc">El sistema de Linaje de este personaje será actualizado en la próxima revisión de ficha.</div></div></div>';

    if (geneNames.length) {
      html += '    <div class="gene-cards-grid">';
      geneNames.forEach(function(g) {
        var dummyPerk = { id: 'legacy', name: g, icon: 'fa-dna', iconColor: 'var(--accent-indigo)', desc: 'Gen activo (formato antiguo).' };
        html += makeAprobarPerkCard(dummyPerk, 'perk-racial', 'gene-card-icon--racial', 'gene-card-badge--racial', 'RACIAL');
      });
      html += '    </div>';
    } else {
      html += '    <div class="linaje-empty-box"><i class="fas fa-dna icon-purple"></i><h4>Sin Genes Extra</h4><p>Este personaje no ha desarrollado genes mas alla de los basicos de su raza.</p></div>';
    }
  }
  html += '  </div>';

  // Actions
  html += '  <div class="aprobar-preview-actions" id="aprobar-actions">';
  if (data.status !== 'aprobada') {
    html += '    <button type="button" class="pj-btn-add pj-btn-add--approve" onclick="accionAprobar(' + data.id + ',\'aprobar\')"><i class="fas fa-check"></i> Aprobar</button>';
  }
  html += '    <button type="button" class="pj-btn-add" onclick="openModerar(' + data.id + ',\'' + data.status + '\')"><i class="fas fa-comment-dots"></i> Moderar</button>';
  if (data.status !== 'pendiente') {
    html += '    <button type="button" class="pj-btn-add pj-btn-add--pending" onclick="accionAprobar(' + data.id + ',\'pendiente\')"><i class="fas fa-undo"></i> Volver a Pendiente</button>';
  }
  if (data.status !== 'rechazada') {
    html += '    <button type="button" class="pj-btn-add pj-btn-add--reject" onclick="accionAprobar(' + data.id + ',\'rechazar\')"><i class="fas fa-times"></i> Rechazar</button>';
  }
  html += '  </div>';

  html += '  <div class="aprobar-moderate rpg-is-hidden" id="aprobar-moderate">';
  html += '    <div class="aprobar-moderate-title"><i class="fas fa-comment-dots"></i> Mensaje al Jugador</div>';
  html += '    <p class="aprobar-moderate-desc">Escribe un mensaje para el jugador. Se le notificara junto con el cambio de estado.</p>';
  html += '    <textarea id="moderate-mensaje" class="aprobar-moderate-textarea" placeholder="Escribe tu mensaje aqui..."></textarea>';
  html += '    <div class="aprobar-moderate-actions">';
  html += '      <button type="button" class="pj-btn-add pj-btn-add--cancel" onclick="toggleModerate()">Cancelar</button>';
  html += '      <button class="pj-btn-add" onclick="enviarModeracion()"><i class="fas fa-paper-plane"></i> Enviar</button>';
  html += '    </div>';
  html += '  </div>';

  html += '</div>';

  var previewEl = document.getElementById('aprobar-preview');
  previewEl.innerHTML = html;
  applyDataBg(previewEl);
  applyDataIconColor(previewEl);
  applyDataPct(previewEl);
}

function switchAprobarTab(tab, btn) {
  var tabs = document.querySelectorAll('.aprobar-tab');
  tabs.forEach(function (t) { t.classList.remove('active'); });
  btn.classList.add('active');

  var contents = document.querySelectorAll('.aprobar-tab-content');
  contents.forEach(function (c) { c.classList.remove('active'); });
  document.getElementById('aprobTab_' + tab).classList.add('active');
}

function accionAprobar(personajeId, action) {
  var btn = event && event.currentTarget ? event.currentTarget : document.querySelector('#aprobar-actions button');
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...'; }

  (window.gamePostJson
    ? window.gamePostJson('<?= $b_url ?>/game/ajax/aprobar_personaje.php', { personaje_id: personajeId, action: action })
    : fetch('<?= $b_url ?>/game/ajax/aprobar_personaje.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Mybb-Post-Key': window.GAME_CSRF || '' },
        credentials: 'same-origin',
        body: JSON.stringify({ personaje_id: personajeId, action: action, my_post_key: window.GAME_CSRF || '' })
      }).then(function (r) { return r.json(); })
  ).then(function(res) {
    if (res.ok) {
      loadList(currentFilter);
      selectChar(personajeId);
    } else {
      alert('Error: ' + (res.error && res.error.message ? res.error.message : 'Desconocido'));
    }
  })
  .catch(function(err) {
    alert('Error de red: ' + err.message);
  });
}

var currentModeratingId = null;

function openModerar(personajeId, statusActual) {
  currentModeratingId = personajeId;
  var el = document.getElementById('aprobar-moderate');
  el.classList.remove('rpg-is-hidden');
  document.getElementById('moderate-mensaje').value = '';
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function toggleModerate() {
  document.getElementById('aprobar-moderate').classList.toggle('rpg-is-hidden');
}

function enviarModeracion() {
  var mensaje = document.getElementById('moderate-mensaje').value.trim();
  if (!mensaje) {
    alert('Escribe un mensaje para el jugador.');
    return;
  }

  var btn = event && event.currentTarget ? event.currentTarget : null;
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...'; }

  (window.gamePostJson
    ? window.gamePostJson('<?= $b_url ?>/game/ajax/aprobar_personaje.php', { personaje_id: currentModeratingId, action: 'revision', mensaje: mensaje })
    : fetch('<?= $b_url ?>/game/ajax/aprobar_personaje.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Mybb-Post-Key': window.GAME_CSRF || '' },
        credentials: 'same-origin',
        body: JSON.stringify({ personaje_id: currentModeratingId, action: 'revision', mensaje: mensaje, my_post_key: window.GAME_CSRF || '' })
      }).then(function (r) { return r.json(); })
  ).then(function(res) {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar'; }
    document.getElementById('aprobar-moderate').classList.add('rpg-is-hidden');
    if (res.ok) {
      loadList(currentFilter);
      selectChar(currentModeratingId);
    } else {
      alert('Error: ' + (res.error && res.error.message ? res.error.message : 'Desconocido'));
    }
  })
  .catch(function(err) {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar'; }
    alert('Error de red: ' + err.message);
  });
}

function escapeHtml(str) {
  if (!str) return '';
  var div = document.createElement('div');
  div.appendChild(document.createTextNode(str));
  return div.innerHTML;
}

// Filter buttons
document.addEventListener('DOMContentLoaded', function() {
  var filterBtns = document.querySelectorAll('.aprobar-filter-btn');
  filterBtns.forEach(function(btn) {
    btn.addEventListener('click', function() {
      filterBtns.forEach(function(b) { b.classList.remove('active'); });
      btn.classList.add('active');
      loadList(btn.getAttribute('data-filter'));
    });
  });
  loadList('');
});
})();
