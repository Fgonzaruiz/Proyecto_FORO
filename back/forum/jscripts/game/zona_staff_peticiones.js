/**
 * Auto-extracted from back/forum/game/public/zona_staff_peticiones.php
 * Config: window.ZONA_STAFF_PETICIONES_CONFIG
 */
(function () {
  "use strict";
  var cfg = window.ZONA_STAFF_PETICIONES_CONFIG || {};
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

function requestTypeBadgeClass(requestType) {
  if (requestType === 'delete') return 'rpg-request-type-badge--delete';
  if (requestType === 'create') return 'rpg-request-type-badge--create';
  if (requestType === 'add_existing') return 'rpg-request-type-badge--add';
  return 'rpg-request-type-badge--delete';
}

function requestTypeListLabel(requestType) {
  if (requestType === 'delete') return 'BORRADO';
  if (requestType === 'create') return 'CREACIÓN';
  if (requestType === 'add_existing') return 'ADICIÓN';
  return 'BORRADO';
}

function requestPreviewMeta(requestType) {
  if (requestType === 'delete') {
    return { typeLabel: 'Solicitud de Borrado de Carta', typeIcon: 'fa-trash-can', titleClass: 'rpg-preview-title--delete', panelValueClass: 'rpg-preview-panel-value--delete', actionText: 'Borrado de Inventario' };
  }
  if (requestType === 'create') {
    return { typeLabel: 'Propuesta de Creación de Carta', typeIcon: 'fa-wand-magic-sparkles', titleClass: 'rpg-preview-title--create', panelValueClass: 'rpg-preview-panel-value--create', actionText: '' };
  }
  if (requestType === 'add_existing') {
    return { typeLabel: 'Petición de Adición del Catálogo', typeIcon: 'fa-clone', titleClass: 'rpg-preview-title--add', panelValueClass: 'rpg-preview-panel-value--add', actionText: 'Adición de Carta Existente' };
  }
  return { typeLabel: 'Solicitud de Borrado de Carta', typeIcon: 'fa-trash-can', titleClass: 'rpg-preview-title--delete', panelValueClass: 'rpg-preview-panel-value--delete', actionText: 'Borrado de Inventario' };
}

function buildPreviewTagsHtml(tags) {
  return tags.map(function (t) {
    return '<span class="rpg-preview-tag">' + escapeHtml(t) + '</span>';
  }).join('');
}

function buildPreviewStatsHtml(req) {
  if (req.cost_pe === '—' && req.execution_stat === '' && req.dice === '') return '';
  var html = '<div class="rpg-preview-stats">';
  if (req.cost_pe !== '—') html += '<div><span class="rpg-preview-stat-label">Coste PE</span><strong class="rpg-preview-stat-val">' + req.cost_pe + '</strong></div>';
  if (req.execution_stat !== '') html += '<div><span class="rpg-preview-stat-label">Atributo</span><strong class="rpg-preview-stat-val">' + req.execution_stat + '</strong></div>';
  if (req.dice !== '') html += '<div><span class="rpg-preview-stat-label">Dados</span><strong class="rpg-preview-stat-val">' + req.dice + '</strong></div>';
  return html + '</div>';
}

function buildCardImageHtml(imageUrl) {
  if (!imageUrl) return '';
  return '<div class="rpg-preview-card-img" data-bg="' + escapeHtml(imageUrl) + '"></div>';
}

function buildDiscussionHtml(discussion) {
  var chatHtml = '<div class="rpg-chat-wrap"><strong class="rpg-chat-label">Hilo de Discusión</strong><div class="rpg-chat-box"><div id="rpg-chat-messages-container" class="rpg-chat-messages">';
  if (discussion.length > 0) {
    discussion.forEach(function (msg) {
      var bubbleClass = msg.sender === 'player' ? 'rpg-chat-bubble--player' : 'rpg-chat-bubble--staff';
      var nameClass = msg.sender === 'player' ? 'rpg-chat-bubble-name--player' : 'rpg-chat-bubble-name--staff';
      var senderLabel = msg.sender === 'player' ? 'JUGADOR' : 'STAFF';
      var msgTime = msg.timestamp ? msg.timestamp.split(' ')[1] : '';
      chatHtml += '<div class="rpg-chat-bubble ' + bubbleClass + '"><div class="rpg-chat-bubble-head"><span class="' + nameClass + '">' + escapeHtml(msg.sender_name) + ' (' + senderLabel + ')</span><span class="rpg-chat-bubble-time">' + escapeHtml(msgTime) + '</span></div><div class="rpg-chat-bubble-body">' + escapeHtml(msg.message) + '</div></div>';
    });
  } else {
    chatHtml += '<div class="rpg-chat-empty">No hay mensajes.</div>';
  }
  return chatHtml + '</div></div></div>';
}

var allRequests = [];
var currentReq = null;
var busquedasList = [];
var adminList = [];
var hakiList = [];
var currentSelection = { kind: null, id: null };

// ─── TABS ───────────────────────────────────────────
function switchTab(tab) {
  if (!document.getElementById('tab-cartas')) {
    return;
  }
  var tabCartas = document.getElementById('tab-cartas');
  var tabBusquedas = document.getElementById('tab-busquedas');
  var tabAdmin = document.getElementById('tab-admin');
  var btnCartas = document.getElementById('tab-btn-cartas');
  var btnBusquedas = document.getElementById('tab-btn-busquedas');
  var btnAdmin = document.getElementById('tab-btn-admin');
  var countCartas = document.getElementById('tab-count-cartas');
  var countBusquedas = document.getElementById('tab-count-busquedas');
  var countAdmin = document.getElementById('tab-count-admin');

  tabCartas.classList.add('rpg-is-hidden');
  tabBusquedas.classList.add('rpg-is-hidden');
  if (tabAdmin) tabAdmin.classList.add('rpg-is-hidden');
  btnCartas.classList.remove('is-active');
  btnBusquedas.classList.remove('is-active', 'is-active--rose');
  if (btnAdmin) btnAdmin.classList.remove('is-active');
  countCartas.classList.remove('is-active');
  countBusquedas.classList.remove('is-active', 'is-active--rose');
  if (countAdmin) countAdmin.classList.remove('is-active');

  if (tab === 'busquedas') {
    tabBusquedas.classList.remove('rpg-is-hidden');
    btnBusquedas.classList.add('is-active', 'is-active--rose');
    countBusquedas.classList.add('is-active--rose');
    loadBusquedasPending(true);
  } else if (tab === 'admin') {
    if (tabAdmin) tabAdmin.classList.remove('rpg-is-hidden');
    if (btnAdmin) btnAdmin.classList.add('is-active');
    if (countAdmin) countAdmin.classList.add('is-active');
    if (typeof window.loadAdminRequestsPending === 'function') {
      window.loadAdminRequestsPending(true);
    }
  } else {
    tabCartas.classList.remove('rpg-is-hidden');
    btnCartas.classList.add('is-active');
    countCartas.classList.add('is-active');
  }
}

// ─── LISTADO UNIFICADO ───────────────────────────────
function parseSortDate(val) {
  if (!val) return 0;
  var t = new Date(String(val).replace(' ', 'T')).getTime();
  return isNaN(t) ? 0 : t;
}

function adminSourceLabel(src) {
  if (src === 'akuma_random') return 'Akuma aleatoria';
  if (src === 'akuma_demand') return 'Akuma bajo demanda';
  if (src === 'mision') return 'Misión';
  return 'Manual';
}

function markUnifiedSelection(kind, id) {
  currentSelection = { kind: kind, id: id };
  document.querySelectorAll('.unified-item').forEach(function (el) {
    el.classList.toggle('is-selected', el.getAttribute('data-kind') === kind && parseInt(el.getAttribute('data-id'), 10) === id);
  });
}

function loadAllPending() {
  var container = document.getElementById('requests-list-items');
  if (!container) return;
  container.innerHTML = '<div class="aprobar-empty"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>';

  Promise.all([
    fetch(bburl + '/game/ajax/cards_pending_requests.php', { credentials: 'same-origin' }).then(function (r) { return r.json(); }),
    fetch(bburl + '/game/ajax/busquedas_pending.php', { credentials: 'same-origin' }).then(function (r) { return r.json(); }),
    fetch(bburl + '/game/ajax/admin_requests_pending.php', { credentials: 'same-origin' }).then(function (r) { return r.json(); }),
    fetch(bburl + '/game/ajax/haki_pending_requests.php', { credentials: 'same-origin' }).then(function (r) { return r.json(); })
  ]).then(function (results) {
    var cardsRes = results[0];
    var busqRes = results[1];
    var adminRes = results[2];
    var hakiRes = results[3];

    if (!cardsRes.ok && !busqRes.ok && !adminRes.ok && !hakiRes.ok) {
      container.innerHTML = '<div class="rpg-error-box">Error al cargar las peticiones.</div>';
      return;
    }

    allRequests = cardsRes.ok ? cardsRes.data : [];
    busquedasList = busqRes.ok ? busqRes.data : [];
    adminList = adminRes.ok ? (adminRes.data.requests || []) : [];
    hakiList = hakiRes.ok ? hakiRes.data : [];
    renderUnifiedList();
  }).catch(function () {
    container.innerHTML = '<div class="rpg-error-box">Error de conexión.</div>';
  });
}

function loadRequests() {
  loadAllPending();
}

function renderUnifiedList() {
  var items = [];
  allRequests.forEach(function (req) {
    items.push({
      kind: 'carta',
      id: req.id,
      sortDate: parseSortDate(req.created_at || req.updated_at),
      req: req
    });
  });
  busquedasList.forEach(function (b) {
    items.push({
      kind: 'busqueda',
      id: b.id,
      sortDate: parseSortDate(b.date),
      busqueda: b
    });
  });
  adminList.forEach(function (r) {
    items.push({
      kind: 'admin',
      id: r.id,
      sortDate: parseSortDate(r.created_at),
      admin: r
    });
  });
  hakiList.forEach(function (h) {
    items.push({
      kind: 'haki',
      id: h.id,
      sortDate: parseSortDate(h.date),
      haki: h
    });
  });

  items.sort(function (a, b) { return b.sortDate - a.sortDate; });

  var countEl = document.getElementById('requests-count');
  if (countEl) countEl.textContent = String(items.length);

  var container = document.getElementById('requests-list-items');
  if (!container) return;

  if (items.length === 0) {
    container.innerHTML = '<div class="rpg-empty-state"><i class="fas fa-check-circle"></i>No hay solicitudes pendientes</div>';
    return;
  }

  var html = '';
  items.forEach(function (item) {
    if (item.kind === 'carta') {
      var req = item.req;
      var resolvedName = req.card_name || 'Carta Personalizada';
      if (req.request_type === 'create') {
        try {
          if (req.card_details_json) {
            var details = JSON.parse(req.card_details_json);
            if (details && details.name) resolvedName = details.name;
          }
        } catch (e) {}
      }
      var avatar = req.character_avatar || 'https://placehold.co/100x100';
      var statusBadge = req.status === 'conforme' ? '<span class="rpg-status-badge">CONFORME</span>' : '';
      html += '<div class="rpg-request-row unified-item request-item" data-kind="carta" data-id="' + req.id + '" onclick="selectUnified(\'carta\', ' + req.id + ')">' +
        '<div class="rpg-request-avatar" data-bg="' + escapeHtml(avatar) + '"></div>' +
        '<div class="rpg-request-body">' +
          '<div class="rpg-request-name">' + escapeHtml(req.character_name) + statusBadge + '</div>' +
          '<div class="rpg-request-card-name">Carta: ' + escapeHtml(resolvedName) + '</div>' +
          '<span class="rpg-request-type-badge ' + requestTypeBadgeClass(req.request_type) + '">' + requestTypeListLabel(req.request_type) + '</span>' +
        '</div></div>';
    } else if (item.kind === 'busqueda') {
      var b = item.busqueda;
      var thumb = b.pj_avatar || 'https://placehold.co/100x100';
      html += '<div class="rpg-request-row unified-item" data-kind="busqueda" data-id="' + b.id + '" onclick="selectUnified(\'busqueda\', ' + b.id + ')">' +
        '<div class="rpg-request-avatar" data-bg="' + escapeHtml(thumb) + '"></div>' +
        '<div class="rpg-request-body">' +
          '<div class="rpg-request-name">' + escapeHtml(b.pj_name) + '</div>' +
          '<div class="rpg-request-card-name">' + escapeHtml(b.titulo) + '</div>' +
          '<span class="rpg-request-type-badge rpg-request-type-badge--create">BÚSQUEDA DE ROL</span>' +
        '</div></div>';
    } else if (item.kind === 'haki') {
      var h = item.haki;
      var av = h.character_avatar || 'https://placehold.co/100x100';
      var hakiLabel = h.haki_type === 'kenbunshoku' ? 'Observación' : (h.haki_type === 'busoshoku' ? 'Armamento' : 'Conquistador');
      html += '<div class="rpg-request-row unified-item" data-kind="haki" data-id="' + h.id + '" onclick="selectUnified(\'haki\', ' + h.id + ')">' +
        '<div class="rpg-request-avatar" data-bg="' + escapeHtml(av) + '"></div>' +
        '<div class="rpg-request-body">' +
          '<div class="rpg-request-name">' + escapeHtml(h.character_name) + '</div>' +
          '<div class="rpg-request-card-name">Subida Haki ' + escapeHtml(hakiLabel) + ' a Nivel ' + h.nivel_siguiente + '</div>' +
          '<span class="rpg-request-type-badge rpg-request-type-badge--add">PETICIÓN DE HAKI</span>' +
        '</div></div>';
    } else {
      var r = item.admin;
      var av = r.character_avatar || 'https://placehold.co/100x100';
      html += '<div class="rpg-request-row unified-item" data-kind="admin" data-id="' + r.id + '" onclick="selectUnified(\'admin\', ' + r.id + ')">' +
        '<div class="rpg-request-avatar" data-bg="' + escapeHtml(av) + '"></div>' +
        '<div class="rpg-request-body">' +
          '<div class="rpg-request-name">' + escapeHtml(r.character_name) + '</div>' +
          '<div class="rpg-request-card-name">' + escapeHtml(r.title) + '</div>' +
          '<span class="rpg-request-type-badge rpg-request-type-badge--add">' + escapeHtml(adminSourceLabel(r.source)) + '</span>' +
        '</div></div>';
    }
  });

  container.innerHTML = html;
  applyDataBg(container);
}

function selectUnified(kind, id) {
  markUnifiedSelection(kind, id);
  if (kind === 'carta') {
    selectRequest(id);
  } else if (kind === 'busqueda') {
    openBusquedaReview(id);
  } else if (kind === 'admin') {
    openAdminReview(id);
  } else if (kind === 'haki') {
    openHakiReview(id);
  }
}

function buildStandardCardPreview(req, meta, sidePanelsHtml, actionPanelHtml) {
  var tags = JSON.parse(req.tags_json || '[]');
  var cleanedTags = tags.map(function (t) { return String(t).replace(/[\[\]]/g, '').trim().toUpperCase(); }).filter(Boolean);
  var tagsHtml = buildPreviewTagsHtml(cleanedTags.length ? cleanedTags : tags);
  var statsHtml = buildPreviewStatsHtml(req);
  var cardImage = buildCardImageHtml(req.image_url);
  return '<h2 class="rpg-preview-title ' + meta.titleClass + '"><i class="fas ' + meta.typeIcon + '"></i> ' + meta.typeLabel + '</h2>' +
    '<div class="rpg-preview-layout"><div class="rpg-preview-card-mini"><div class="rpg-preview-card-head">' +
    '<div class="rpg-preview-card-name">' + escapeHtml(req.card_name) + '</div>' +
    '<div class="rpg-preview-card-meta">[' + req.current_rank + '] ' + escapeHtml(req.card_type.toUpperCase()) + '</div></div>' +
    cardImage + '<div class="rpg-preview-card-body"><div class="rpg-preview-tags">' + tagsHtml + '</div>' + statsHtml +
    '<div class="rpg-preview-desc">' + escapeHtml(req.description) + '</div></div></div>' +
    '<div class="rpg-preview-side">' + sidePanelsHtml + actionPanelHtml + '</div></div>';
}

function selectRequest(id) {
  markUnifiedSelection('carta', id);
  currentReq = allRequests.find(function (r) { return parseInt(r.id, 10) === id; });
  if (!currentReq) return;
  var preview = document.getElementById('request-preview');
  preview.classList.add('rpg-preview-active');
  preview.innerHTML = '<div class="rpg-preview-loading"><i class="fas fa-spinner fa-spin"></i></div>';

  var meta = requestPreviewMeta(currentReq.request_type);
  var isDelete = currentReq.request_type === 'delete';
  var isCreate = currentReq.request_type === 'create';
  var isAddExisting = currentReq.request_type === 'add_existing';

  var discussion = [];
  try {
    if (currentReq.discussion_json) discussion = JSON.parse(currentReq.discussion_json);
  } catch (e) {}

  var chatHtml = (isCreate || isAddExisting) ? buildDiscussionHtml(discussion) : '';

  if (isDelete) {
    var nextRankInfo = '<div class="rpg-preview-note">La carta será desvinculada del inventario del personaje.</div>';
    var infoPanel = '<div class="rpg-preview-panel"><div class="rpg-preview-panel-label">Personaje Solicitante</div><div class="rpg-preview-panel-value">' + escapeHtml(currentReq.character_name) + '</div>' +
      '<div class="rpg-preview-panel-label rpg-preview-panel-spaced">Tipo de Acción</div><div class="rpg-preview-panel-value ' + meta.panelValueClass + '">Borrado de Inventario</div>' +
      '<div class="rpg-preview-panel-label rpg-preview-panel-spaced">Cambio Aplicado</div>' + nextRankInfo + '</div>';
    var actionPanel = '<div class="rpg-preview-panel rpg-preview-panel--actions"><div class="rpg-preview-panel-label">Mensaje para el Jugador (Opcional)</div>' +
      '<textarea id="staff-message-text" rows="3" class="rpg-staff-textarea" placeholder="Escribe un comentario sobre esta resolución..."></textarea>' +
      '<div class="rpg-preview-actions"><button type="button" onclick="resolveRequest(\'approve\', this)" class="rpg-action-btn rpg-btn-primary"><i class="fas fa-check"></i> Aprobar</button>' +
      '<button type="button" onclick="resolveRequest(\'reject\', this)" class="rpg-system-tab-btn rpg-staff-btn-danger"><i class="fas fa-times"></i> Rechazar</button></div></div>';
    preview.innerHTML = buildStandardCardPreview(currentReq, meta, infoPanel, actionPanel);
    applyDataBg(preview);
  } else if (isAddExisting) {
    var approveBtn = staffLevel >= 3
      ? '<button type="button" onclick="resolveRequest(\'approve\', this)" class="rpg-action-btn rpg-btn-primary"><i class="fas fa-check"></i> Aprobar y Asignar</button>'
      : '<div class="rpg-staff-wait-msg">Esperando aprobación final de Administrador (Nivel 3)</div>';
    var infoPanel = '<div class="rpg-preview-panel"><div class="rpg-preview-panel-label">Personaje Solicitante</div><div class="rpg-preview-panel-value">' + escapeHtml(currentReq.character_name) + '</div>' +
      '<div class="rpg-preview-panel-label rpg-preview-panel-spaced">Acción</div><div class="rpg-preview-panel-value ' + meta.panelValueClass + '">Adición de Carta Existente</div></div>' + chatHtml;
    var actionPanel = '<div class="rpg-preview-panel rpg-preview-panel--actions"><div class="rpg-preview-panel-label">Responder / Resolver</div>' +
      '<textarea id="staff-message-text" rows="3" class="rpg-staff-textarea" placeholder="Escribe un comentario en el hilo o justificación de resolución..."></textarea>' +
      '<div class="rpg-preview-actions rpg-preview-actions--wrap"><button type="button" onclick="resolveRequest(\'reply\', this)" class="rpg-system-tab-btn"><i class="fas fa-reply"></i> Responder</button>' +
      approveBtn + '<button type="button" onclick="resolveRequest(\'reject\', this)" class="rpg-system-tab-btn rpg-staff-btn-danger"><i class="fas fa-times"></i> Rechazar</button></div></div>';
    preview.innerHTML = buildStandardCardPreview(currentReq, meta, infoPanel, actionPanel);
    applyDataBg(preview);
    setTimeout(function () {
      var container = document.getElementById('rpg-chat-messages-container');
      if (container) container.scrollTop = container.scrollHeight;
    }, 50);
  } else if (isCreate) {
    let details = {
      name: '', card_type: 'tecnica', rank: 'C', activation: 'activa',
      cost_pe: '—', execution_stat: '', dice: '', description: '',
      image_url: '', tags: [], notes: '', reposo: 0, duracion: 0
    };
    try {
      if (currentReq.card_details_json) {
        details = JSON.parse(currentReq.card_details_json);
      }
    } catch(e){}

    const tagsValue = Array.isArray(details.tags) ? details.tags.join(', ') : '';
    
    var approveBtn = '';
    if (staffLevel >= 3) {
      if (currentReq.status === 'conforme') {
        approveBtn = '<button type="button" onclick="resolveRequest(\'approve\', this)" class="rpg-action-btn rpg-btn-primary"><i class="fas fa-check-double"></i> APROBAR Y CREAR CARTA</button>';
      } else {
        approveBtn = '<div class="rpg-staff-wait-msg">Esperando conformidad del Jugador antes de la creación final.</div>';
      }
    } else {
      approveBtn = '<div class="rpg-staff-wait-msg">Esperando conformidad del Jugador y aprobación final del Administrador (Nivel 3).</div>';
    }

    preview.innerHTML = `
      <h2 class="rpg-preview-title ${meta.titleClass}">
        <i class="fas ${meta.typeIcon}"></i> ${meta.typeLabel}
      </h2>
      <div class="rpg-mod-layout">
        <div class="rpg-mod-form-panel">
          <strong class="rpg-mod-form-title">Datos de Moderación de Carta</strong>
          <div class="rpg-mod-form-grid">
                    <!-- FILA 1: Nombre + Tipo -->
                    <div>
                        <label class="rpg-form-label" class="rpg-form-label-sm">Nombre</label>
                        <input type="text" id="mod-name" class="textbox" value="${escapeHtml(details.name || '')}" required class="rpg-input-full">
                    </div>
                    <div>
                        <label class="rpg-form-label" class="rpg-form-label-sm">Tipo</label>
                        <select id="mod-type" class="textbox rpg-input-full">
                            <option value="tecnica" ${details.card_type === 'tecnica' ? 'selected' : ''}>Técnica</option>
                            <option value="equipo" ${details.card_type === 'equipo' ? 'selected' : ''}>Equipo</option>
                            <option value="akuma_no_mi" ${details.card_type === 'akuma_no_mi' ? 'selected' : ''}>Akuma no Mi</option>
                            <option value="haki" ${details.card_type === 'haki' ? 'selected' : ''}>Haki</option>
                            <option value="npc_menor" ${details.card_type === 'npc_menor' ? 'selected' : ''}>NPC Menor</option>
                            <option value="barco" ${details.card_type === 'barco' ? 'selected' : ''}>Barco</option>
                        </select>
                    </div>
                </div>

                <!-- FILA 2: Activación + Rango -->
                <div id="wrapper-mod-activation-rank" class="rpg-mod-form-grid">
                    <div id="wrapper-mod-activation">
                        <label class="rpg-form-label" class="rpg-form-label-sm">Activación</label>
                        <select id="mod-activation" class="textbox rpg-input-full">
                            <option value="activa" ${details.activation === 'activa' ? 'selected' : ''}>Activa</option>
                            <option value="pasiva" ${details.activation === 'pasiva' ? 'selected' : ''}>Pasiva</option>
                            <option value="reactiva" ${details.activation === 'reactiva' ? 'selected' : ''}>Reactiva</option>
                        </select>
                    </div>
                    <div id="wrapper-mod-rank">
                        <label class="rpg-form-label" class="rpg-form-label-sm">Rango</label>
                        <select id="mod-rank" class="textbox rpg-input-full">
                            <option value="C" ${details.rank === 'C' ? 'selected' : ''}>C (Común)</option>
                            <option value="B" ${details.rank === 'B' ? 'selected' : ''}>B (Poco común)</option>
                            <option value="A" ${details.rank === 'A' ? 'selected' : ''}>A (Raro)</option>
                            <option value="S" ${details.rank === 'S' ? 'selected' : ''}>S (Épico)</option>
                            <option value="SS" ${details.rank === 'SS' ? 'selected' : ''}>SS (Legendario)</option>
                        </select>
                    </div>
                </div>

                <!-- FILA 3: Tags -->
                <div class="rpg-grid-full">
                    <label class="rpg-form-label" class="rpg-form-label-sm">Tags</label>
                    <div id="tag-selector">
                        <div id="tag-selected" class="rpg-staff-tag-selected"></div>
                        <div id="tag-dropdown" class="rpg-staff-tag-dropdown"></div>
                        <button type="button" id="tag-toggle-btn" class="rpg-system-tab-btn rpg-staff-tag-toggle">Seleccionar Tags</button>
                        <input type="hidden" id="mod-tags" value="">
                    </div>
                </div>

                <!-- FILA 4: Descripción -->
                <div class="rpg-grid-full">
                    <label class="rpg-form-label" class="rpg-form-label-sm">Descripción / Efectos</label>
                    <textarea id="mod-desc" class="textbox rpg-input-full" rows="3">${escapeHtml(details.description || '')}</textarea>
                </div>

                <!-- FILA 5: Coste PE + Ejecución -->
                <div id="wrapper-mod-cost-stat" class="rpg-mod-form-grid">
                    <div id="wrapper-mod-cost">
                        <label class="rpg-form-label" class="rpg-form-label-sm">Coste PE</label>
                        <input type="text" id="mod-cost" class="textbox" placeholder="3 PE" value="${escapeHtml(details.cost_pe || '')}" class="rpg-input-full">
                    </div>
                    <div id="wrapper-mod-stat">
                        <label class="rpg-form-label" class="rpg-form-label-sm">Ejecución</label>
                        <select id="mod-stat" class="textbox rpg-input-full">
                            <option value="" ${details.execution_stat === '' ? 'selected' : ''}>—</option>
                            <option value="FUE" ${details.execution_stat === 'FUE' ? 'selected' : ''}>FUE (Fuerza)</option>
                            <option value="AGI" ${details.execution_stat === 'AGI' ? 'selected' : ''}>AGI (Agilidad)</option>
                            <option value="DES" ${details.execution_stat === 'DES' ? 'selected' : ''}>DES (Destreza)</option>
                            <option value="INST" ${details.execution_stat === 'INST' ? 'selected' : ''}>INST (Instinto)</option>
                            <option value="ESP" ${details.execution_stat === 'ESP' ? 'selected' : ''}>ESP (Espíritu)</option>
                            <option value="INT" ${details.execution_stat === 'INT' ? 'selected' : ''}>INT (Inteligencia)</option>
                        </select>
                    </div>
                            <div class="rpg-dice-meta-row">
                                <div>
                                    <label class="rpg-dice-label-sm">Bonus fijo</label>
                                    <input type="number" id="dice-fixed" min="0" value="0" class="textbox rpg-dice-input-sm">
                                </div>
                                <div>
                                    <label class="rpg-dice-label-sm">Stat</label>
                                    <select id="dice-stat" class="textbox rpg-dice-select-sm">
                                        <option value="">—</option>
                                        <option value="FUE">FUE</option>
                                        <option value="AGI">AGI</option>
                                        <option value="DES">DES</option>
                                        <option value="INST">INST</option>
                                        <option value="ESP">ESP</option>
                                        <option value="INT">INT</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="rpg-dice-label-sm">Mult/Div</label>
                                    <input type="text" id="dice-stat-mod" class="textbox rpg-dice-input-md" placeholder="Ej: 2.5* o /2">
                                </div>
                                <div>
                                    <label class="rpg-dice-label-sm">Sufijo</label>
                                    <input type="text" id="dice-suffix" class="textbox rpg-dice-input-lg" placeholder="[FUEGO]">
                                </div>
                                <div class="rpg-dice-meta-row">
                                    <div class="rpg-dice-preview-box">
                                        <span class="rpg-dice-preview-arrow">→</span>
                                        <span id="dice-preview" class="rpg-dice-preview-value">—</span>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" id="mod-dice" value="">
                        </div>
                    </div>

                    <!-- SECCIONES DINÁMICAS DE CAMPOS RPG MODERACIÓN -->
                    <div id="fields-mod-akuma" class="rpg-staff-field-section">
                        <div>
                            <label class="rpg-form-label" class="rpg-form-label-sm">Tipo de Akuma</label>
                            <select id="mod-akuma-type" class="textbox rpg-input-full">
                                <option value="paramecia">Paramecia</option>
                                <option value="logia">Logia</option>
                                <option value="zoan">Zoan</option>
                            </select>
                        </div>
                        <div></div>
                        <div class="rpg-grid-full">
                            <label class="rpg-form-label" class="rpg-form-label-sm">Efectos</label>
                            <textarea id="mod-akuma-efectos" class="textbox rpg-input-full" rows="3"></textarea>
                        </div>
                        <div class="rpg-grid-full">
                            <label class="rpg-form-label" class="rpg-form-label-sm">Limitaciones</label>
                            <textarea id="mod-akuma-limitaciones" class="textbox rpg-input-full" rows="3"></textarea>
                        </div>
                        <div class="rpg-grid-full">
                            <label class="rpg-form-label" class="rpg-form-label-sm">Debilidades</label>
                            <textarea id="mod-akuma-debilidades" class="textbox rpg-input-full" rows="3"></textarea>
                        </div>
                    </div>

                    <div id="fields-mod-equipo" class="rpg-staff-field-section">
                        <div>
                            <label class="rpg-form-label" class="rpg-form-label-sm">Tipo de Equipo</label>
                            <select id="mod-equipo-type" class="textbox rpg-input-full">
                                <option value="arma">Arma</option>
                                <option value="util">Útil / Consumible</option>
                                <option value="armadura">Armadura</option>
                            </select>
                        </div>
                        <div>
                            <label class="rpg-form-label" class="rpg-form-label-sm">Subtipo</label>
                            <select id="mod-equipo-subtipo-select" class="textbox rpg-input-full rpg-subtipo-select"></select>
                            <input type="text" id="mod-equipo-subtipo" class="textbox rpg-input-full rpg-subtipo-other" placeholder="Especificar otro subtipo...">
                        </div>
                        <div id="wrapper-mod-equipo-stack" class="rpg-wizard-hidden">
                            <label class="rpg-form-label">Cantidad por stack (consumible)</label>
                            <input type="number" id="mod-equipo-stack-qty" min="1" value="1" class="textbox rpg-input-full">
                        </div>
                    </div>

                    <div id="fields-mod-barco" class="rpg-staff-field-section">
                        <div>
                            <label class="rpg-form-label" class="rpg-form-label-sm">Tipo de Barco</label>
                            <select id="mod-barco-type" class="textbox rpg-input-full">
                                <option value="navio">Navío</option>
                                <option value="carabela">Carabela</option>
                                <option value="galera">Galera</option>
                                <option value="fragata">Fragata</option>
                                <option value="bergantin">Bergantín</option>
                                <option value="acorazado">Acorazado</option>
                                <option value="submarino">Submarino</option>
                                <option value="balsa">Balsa</option>
                            </select>
                        </div>
                        <div>
                            <label class="rpg-form-label" class="rpg-form-label-sm">Tier</label>
                            <input type="number" id="mod-barco-tier" min="1" value="1" class="textbox rpg-input-full">
                        </div>
                        <div>
                            <label class="rpg-form-label" class="rpg-form-label-sm">Vida</label>
                            <input type="number" id="mod-barco-vida" min="0" value="100" class="textbox rpg-input-full">
                        </div>
                        <div>
                            <label class="rpg-form-label" class="rpg-form-label-sm">Ataque</label>
                            <input type="number" id="mod-barco-ataque" min="0" value="0" class="textbox rpg-input-full">
                        </div>
                        <div>
                            <label class="rpg-form-label" class="rpg-form-label-sm">Velocidad</label>
                            <input type="number" id="mod-barco-velocidad" min="0" value="0" class="textbox rpg-input-full">
                        </div>
                        <div>
                            <label class="rpg-form-label" class="rpg-form-label-sm">Resistencia</label>
                            <input type="number" id="mod-barco-resistencia" min="0" value="0" class="textbox rpg-input-full">
                        </div>
                    </div>

                    <div id="fields-mod-npc" class="rpg-staff-field-section">
                        <div>
                            <label class="rpg-form-label" class="rpg-form-label-sm">Subtipo</label>
                            <select id="mod-npc-mascota-type" class="textbox rpg-input-full">
                                <option value="npc">NPC</option>
                                <option value="mascota">Mascota</option>
                            </select>
                        </div>
                        <div>
                            <label class="rpg-form-label" class="rpg-form-label-sm">Vida</label>
                            <input type="number" id="mod-npc-vida" min="0" value="50" class="textbox rpg-input-full">
                        </div>
                        <div id="wrapper-mod-npc-tier">
                            <label class="rpg-form-label" class="rpg-form-label-sm">Tier de Mascota</label>
                            <input type="number" id="mod-npc-tier" min="1" value="1" class="textbox rpg-input-full">
                        </div>
                        <div></div>
                        <div class="rpg-grid-full">
                            <label class="rpg-form-label" class="rpg-form-label-sm">Acciones</label>
                            <div id="mod-npc-actions-container" class="rpg-npc-actions"></div>
                            <button type="button" id="btn-mod-npc-add-action" class="rpg-system-tab-btn rpg-btn-npc-add">+ Añadir Acción</button>
                        </div>
                    </div>

                    <div id="fields-mod-haki" class="rpg-staff-field-section">
                        <div>
                            <label class="rpg-form-label" class="rpg-form-label-sm">Tipo de Haki</label>
                            <select id="mod-haki-type" class="textbox rpg-input-full">
                                <option value="busoshoku">Busoshoku (Armamiento)</option>
                                <option value="kenbunshoku">Kenbunshoku (Observación)</option>
                                <option value="haoshoku">Haoshoku (Conquistador / Rey)</option>
                            </select>
                        </div>
                        <div>
                            <label class="rpg-form-label" class="rpg-form-label-sm">Nivel de Haki</label>
                            <select id="mod-haki-level" class="textbox rpg-input-full">
                                <option value="despertado">Despertado</option>
                                <option value="basico">Básico</option>
                                <option value="medio">Medio</option>
                                <option value="avanzado">Avanzado</option>
                                <option value="maestro">Maestro</option>
                            </select>
                        </div>
                        <div class="rpg-grid-full">
                            <label class="rpg-form-label" class="rpg-form-label-sm">Efecto</label>
                            <textarea id="mod-haki-efecto" class="textbox rpg-input-full" rows="3" placeholder="Detalla el efecto de la habilidad de Haki..."></textarea>
                        </div>
                    </div>

                    <!-- FILA 8: Reposo y Duración -->
                    <div class="rpg-grid-2--turns">
                        <div>
                            <label class="rpg-form-label" class="rpg-form-label-sm">Turnos de Reposo</label>
                            <input type="number" id="mod-reposo" min="0" value="${parseInt(details.reposo) || 0}" class="textbox rpg-input-full">
                        </div>
                        <div>
                            <label class="rpg-form-label" class="rpg-form-label-sm">Duración (Turnos)</label>
                            <input type="number" id="mod-duracion" min="0" value="${parseInt(details.duracion) || 0}" class="textbox rpg-input-full">
                        </div>
                    </div>

                    <!-- FILA 9: Notas + URL Imagen -->
                    <div class="rpg-grid-full">
                        <label class="rpg-form-label" class="rpg-form-label-sm">URL Imagen</label>
                        <input type="text" id="mod-img" class="textbox" placeholder="https://..." value="${escapeHtml(details.image_url || '')}" class="rpg-input-full">
                    </div>
                    <div class="rpg-grid-full">
                        <label class="rpg-form-label" class="rpg-form-label-sm">Notas Internas / Upgrades</label>
                        <textarea id="mod-notes" class="textbox rpg-input-full" rows="2">${escapeHtml(details.notes || '')}</textarea>
                    </div>

          </div>
        </div>

        <div class="rpg-mod-side">
          <div class="rpg-preview-panel">
            <div class="rpg-preview-panel-label">Personaje Solicitante</div>
            <div class="rpg-preview-panel-value">${escapeHtml(currentReq.character_name)}</div>
            <div class="rpg-preview-panel-label rpg-preview-panel-spaced">Estado de la Solicitud</div>
            <div class="rpg-preview-panel-value rpg-preview-panel-value--status">${currentReq.status}</div>
          </div>
          ${chatHtml}
          <div class="rpg-preview-panel rpg-preview-panel--actions">
            <div class="rpg-preview-panel-label">Comentario para el Jugador</div>
            <textarea id="staff-message-text" rows="3" class="rpg-staff-textarea" placeholder="Escribe un mensaje aclaratorio en el hilo..."></textarea>
            <div class="rpg-preview-actions--col">
              <div class="rpg-preview-actions-row">
                <button type="button" onclick="resolveRequest('reply', this)" class="rpg-system-tab-btn rpg-system-tab-btn--compact"><i class="fas fa-reply"></i> Responder</button>
                <button type="button" onclick="saveModeration(this)" class="rpg-action-btn rpg-btn-primary rpg-action-btn--wide"><i class="fas fa-save"></i> Guardar Moderación</button>
              </div>
              ${approveBtn}
              <button type="button" onclick="resolveRequest('reject', this)" class="rpg-system-tab-btn rpg-staff-btn-danger rpg-staff-btn-full"><i class="fas fa-times"></i> Rechazar Petición</button>
            </div>
          </div>
        </div>
      </div>
    `;
    applyDataBg(preview);
    setTimeout(function () {
      var container = document.getElementById('rpg-chat-messages-container');
      if (container) container.scrollTop = container.scrollHeight;
      initModerationForm(details);
    }, 50);
  }
}

function saveModeration(btn) {
  if (!currentReq) return;
  const msg = document.getElementById('staff-message-text').value.trim();
  
  const type = document.getElementById('mod-type').value;
  const details = {
    name: document.getElementById('mod-name').value.trim(),
    card_type: type,
    rank: document.getElementById('mod-rank').value,
    activation: document.getElementById('mod-activation').value,
    cost_pe: document.getElementById('mod-cost').value.trim(),
    execution_stat: document.getElementById('mod-stat').value.trim(),
    dice: document.getElementById('mod-dice').value.trim(),
    tags: document.getElementById('mod-tags').value.split(',').map(t => t.trim()).filter(Boolean),
    reposo: parseInt(document.getElementById('mod-reposo').value) || 0,
    duracion: parseInt(document.getElementById('mod-duracion').value) || 0,
    image_url: document.getElementById('mod-img').value.trim(),
    description: document.getElementById('mod-desc').value.trim(),
    notes: document.getElementById('mod-notes').value.trim(),
    effects: {}
  };

  if (type === 'akuma_no_mi') {
      details.effects = {
          akuma_type: document.getElementById('mod-akuma-type').value,
          efectos: document.getElementById('mod-akuma-efectos').value,
          limitaciones: document.getElementById('mod-akuma-limitaciones').value,
          debilidades: document.getElementById('mod-akuma-debilidades').value
      };
  } else if (type === 'equipo') {
      const eqType = document.getElementById('mod-equipo-type').value;
      details.effects = {
          equipo_type: eqType,
          subtipo: document.getElementById('mod-equipo-subtipo').value,
          damage_dice: '',
          damage_stat: ''
      };
      if (eqType === 'util') {
          details.effects.default_cantidad = parseInt(document.getElementById('mod-equipo-stack-qty')?.value, 10) || 1;
          if (!details.dice || details.dice === '2d20') {
              details.dice = document.getElementById('mod-dice').value.trim() || '';
          }
      } else if (eqType === 'arma') {
          details.effects.damage_dice = details.dice;
          details.effects.damage_stat = document.getElementById('mod-stat').value.trim();
      }
  } else if (type === 'barco') {
      details.effects = {
          barco_type: document.getElementById('mod-barco-type').value,
          tier: parseInt(document.getElementById('mod-barco-tier').value) || 1,
          vida: parseInt(document.getElementById('mod-barco-vida').value) || 0,
          ataque: parseInt(document.getElementById('mod-barco-ataque').value) || 0,
          velocidad: parseInt(document.getElementById('mod-barco-velocidad').value) || 0,
          resistencia: parseInt(document.getElementById('mod-barco-resistencia').value) || 0
      };
  } else if (type === 'npc_menor') {
      const subType = document.getElementById('mod-npc-mascota-type').value;
      const actionsList = window.getModNpcActions();
      details.effects = {
          npc_mascota_type: subType,
          vida: parseInt(document.getElementById('mod-npc-vida').value) || 0,
          tier: subType === 'mascota' ? (parseInt(document.getElementById('mod-npc-tier').value) || 1) : 1,
          acciones: actionsList
      };
  } else if (type === 'haki') {
      details.effects = {
          haki_type: document.getElementById('mod-haki-type').value,
          haki_level: document.getElementById('mod-haki-level').value,
          efecto: document.getElementById('mod-haki-efecto').value
      };
  }

  btn.disabled = true;
  const originalHtml = btn.innerHTML;
  btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Guardando...`;

  (window.gamePostJson
    ? window.gamePostJson(bburl + '/game/ajax/cards_resolve_request.php', {
        request_id: currentReq.id,
        action: 'moderate',
        staff_message: msg,
        card_details: details
      })
    : fetch(bburl + '/game/ajax/cards_resolve_request.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Mybb-Post-Key': window.GAME_CSRF || '' },
        credentials: 'same-origin',
        body: JSON.stringify({
          request_id: currentReq.id,
          action: 'moderate',
          staff_message: msg,
          card_details: details,
          my_post_key: window.GAME_CSRF || ''
        })
      }).then(function (r) { return r.json(); })
  ).then(function (res) {
    btn.disabled = false;
    btn.innerHTML = originalHtml;
    if (res.ok) {
      loadRequests();
      document.getElementById('request-preview').innerHTML = '<div class="rpg-success-empty"><i class="fas fa-check-circle"></i>Datos de moderación guardados y enviados al jugador.</div>';
      currentReq = null;
    } else {
      alert('Error: ' + res.error.message);
    }
  })
  .catch(() => { btn.disabled = false; btn.innerHTML = originalHtml; alert('Error de conexión.'); });
}

function resolveRequest(action, btn) {
  if (!currentReq) return;
  const msg = document.getElementById('staff-message-text').value.trim();
  btn.disabled = true;
  const originalHtml = btn.innerHTML;
  btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ...`;
  (window.gamePostJson
    ? window.gamePostJson(bburl + '/game/ajax/cards_resolve_request.php', {
        request_id: currentReq.id,
        action: action,
        staff_message: msg
      })
    : fetch(bburl + '/game/ajax/cards_resolve_request.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Mybb-Post-Key': window.GAME_CSRF || '' },
        credentials: 'same-origin',
        body: JSON.stringify({
          request_id: currentReq.id,
          action: action,
          staff_message: msg,
          my_post_key: window.GAME_CSRF || ''
        })
      }).then(function (r) { return r.json(); })
  ).then(function (res) {
    btn.disabled = false;
    btn.innerHTML = originalHtml;
    if (res.ok) {
      loadRequests();
      document.getElementById('request-preview').innerHTML = '<div class="rpg-success-empty"><i class="fas fa-check-circle"></i>Solicitud procesada con éxito</div>';
      currentReq = null;
    } else {
      alert('Error: ' + res.error.message);
    }
  })
  .catch(() => { btn.disabled = false; btn.innerHTML = originalHtml; alert('Error de conexión.'); });
}

// ─── BÚSQUEDAS (panel derecho) ───────────────────────
function loadBusquedasPending() {
  loadAllPending();
}

function openBusquedaReview(id) {
  var b = busquedasList.find(function (x) { return parseInt(x.id, 10) === parseInt(id, 10); });
  if (!b) return;
  markUnifiedSelection('busqueda', id);
  var preview = document.getElementById('request-preview');
  if (!preview) return;
  preview.classList.add('rpg-preview-active');
  var imgHtml = b.imagen_url
    ? '<img src="' + escapeHtml(b.imagen_url) + '" alt="" class="rpg-busqueda-preview-img">'
    : '';
  preview.innerHTML =
    '<h2 class="rpg-preview-title rpg-preview-title--create"><i class="fas fa-search"></i> Búsqueda de Rol</h2>' +
    '<div class="rpg-preview-panel">' +
      '<div class="rpg-preview-panel-label">Título</div>' +
      '<div class="rpg-preview-panel-value">' + escapeHtml(b.titulo) + '</div>' +
      '<div class="rpg-preview-panel-label rpg-preview-panel-spaced">Personaje</div>' +
      '<div class="rpg-preview-panel-value"><img src="' + escapeHtml(b.pj_avatar) + '" alt="" class="rpg-busqueda-preview-avatar"> ' + escapeHtml(b.pj_name) + ' · ' + escapeHtml(b.date) + '</div>' +
      imgHtml +
      '<div class="rpg-preview-panel-label rpg-preview-panel-spaced">Descripción</div>' +
      '<div class="rpg-preview-desc">' + escapeHtml(b.descripcion) + '</div>' +
    '</div>' +
    '<input type="hidden" id="brm-id" value="' + b.id + '">' +
    '<div class="rpg-preview-panel rpg-preview-panel--actions">' +
      '<div class="rpg-preview-panel-label">Nota para el jugador (opcional)</div>' +
      '<textarea id="brm-nota" rows="3" class="rpg-staff-textarea" placeholder="Motivo de denegación o comentario..."></textarea>' +
      '<div class="rpg-preview-actions">' +
        '<button type="button" onclick="accionBusqueda(\'aprobar\')" class="rpg-action-btn rpg-btn-primary"><i class="fas fa-check"></i> Aprobar</button>' +
        '<button type="button" onclick="accionBusqueda(\'denegar\')" class="rpg-system-tab-btn rpg-staff-btn-danger"><i class="fas fa-times"></i> Denegar</button>' +
      '</div>' +
    '</div>';
}

function closeBusquedaReview() {}

function accionBusqueda(accion) {
  var idEl = document.getElementById('brm-id');
  var notaEl = document.getElementById('brm-nota');
  if (!idEl) return;
  var fd = new FormData();
  fd.append('id', idEl.value);
  fd.append('accion', accion);
  fd.append('nota', notaEl ? notaEl.value : '');
  (window.gamePostForm
    ? window.gamePostForm(bburl + '/game/ajax/busquedas_action.php', fd)
    : fetch(bburl + '/game/ajax/busquedas_action.php', {
        method: 'POST',
        headers: { 'X-Mybb-Post-Key': window.GAME_CSRF || '' },
        credentials: 'same-origin',
        body: (function () {
          if (window.GAME_CSRF) { fd.append('my_post_key', window.GAME_CSRF); }
          return fd;
        })()
      }).then(function (r) { return r.json(); })
  ).then(function (res) {
    if (res.ok) {
      document.getElementById('request-preview').innerHTML = '<div class="rpg-success-empty"><i class="fas fa-check-circle"></i>Búsqueda procesada con éxito</div>';
      loadAllPending();
    } else {
      alert('Error: ' + (window.gameFormatError ? window.gameFormatError(res) : res.error));
    }
  });
}

// ─── ADMIN (panel derecho) ───────────────────────────
function openAdminReview(id) {
  var r = adminList.find(function (x) { return parseInt(x.id, 10) === parseInt(id, 10); });
  if (!r) return;
  markUnifiedSelection('admin', id);
  var preview = document.getElementById('request-preview');
  if (!preview) return;
  preview.classList.add('rpg-preview-active');
  var akumaLine = r.akuma_name ? '<div class="rpg-preview-panel-label rpg-preview-panel-spaced">Akuma</div><div class="rpg-preview-panel-value"><i class="fas fa-apple-alt"></i> ' + escapeHtml(r.akuma_name) + '</div>' : '';
  preview.innerHTML =
    '<h2 class="rpg-preview-title rpg-preview-title--add"><i class="fas fa-file-signature"></i> Petición Administrativa</h2>' +
    '<div class="rpg-preview-panel">' +
      '<div class="rpg-preview-panel-label">Título</div>' +
      '<div class="rpg-preview-panel-value">' + escapeHtml(r.title) + '</div>' +
      '<div class="rpg-preview-panel-label rpg-preview-panel-spaced">Personaje</div>' +
      '<div class="rpg-preview-panel-value"><img src="' + escapeHtml(r.character_avatar || '') + '" alt="" class="rpg-busqueda-preview-avatar"> ' + escapeHtml(r.character_name) + '</div>' +
      '<div class="rpg-preview-panel-label rpg-preview-panel-spaced">Origen</div>' +
      '<div class="rpg-preview-panel-value">' + escapeHtml(adminSourceLabel(r.source)) + ' · ' + escapeHtml(r.created_at || '') + '</div>' +
      akumaLine +
      '<div class="rpg-preview-panel-label rpg-preview-panel-spaced">Descripción</div>' +
      '<div class="rpg-preview-desc">' + escapeHtml(r.description || '') + '</div>' +
    '</div>' +
    '<input type="hidden" id="arm-id" value="' + r.id + '">' +
    '<div class="rpg-preview-panel rpg-preview-panel--actions">' +
      '<div class="rpg-preview-panel-label">Nota para el jugador (opcional)</div>' +
      '<textarea id="arm-nota" rows="3" class="rpg-staff-textarea" placeholder="Comentario de resolución..."></textarea>' +
      '<div class="rpg-preview-actions">' +
        '<button type="button" onclick="accionAdminRequest(\'aprobar\')" class="rpg-action-btn rpg-btn-primary"><i class="fas fa-check"></i> Aprobar</button>' +
        '<button type="button" onclick="accionAdminRequest(\'denegar\')" class="rpg-system-tab-btn rpg-staff-btn-danger"><i class="fas fa-times"></i> Denegar</button>' +
      '</div>' +
    '</div>';
}

function closeAdminReview() {}

function accionAdminRequest(accion) {
  var idEl = document.getElementById('arm-id');
  var notaEl = document.getElementById('arm-nota');
  if (!idEl) return;
  var fd = new FormData();
  fd.append('id', idEl.value);
  fd.append('accion', accion);
  fd.append('nota', notaEl ? notaEl.value.trim() : '');
  var post = window.gamePostForm
    ? window.gamePostForm(bburl + '/game/ajax/admin_requests_action.php', fd)
    : fetch(bburl + '/game/ajax/admin_requests_action.php', {
        method: 'POST',
        headers: { 'X-Mybb-Post-Key': window.GAME_CSRF || '' },
        credentials: 'same-origin',
        body: (function () {
          if (window.GAME_CSRF) fd.append('my_post_key', window.GAME_CSRF);
          return fd;
        })()
      }).then(function (r) { return r.json(); });
  post.then(function (res) {
    if (res.ok) {
      document.getElementById('request-preview').innerHTML = '<div class="rpg-success-empty"><i class="fas fa-check-circle"></i>Petición administrativa procesada</div>';
      loadAllPending();
    } else {
      alert(window.gameFormatError ? window.gameFormatError(res) : 'Error');
    }
  });
}

function init() {
  loadAllPending();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
// ====== NEW FUNCTION FOR MODERATION FORM INITIALIZATION ======
const TAG_CATEGORIES = [
    { name: 'Activación y temporalidad', tags: ['ACTIVA','PASIVA','REACTIVA','CONTINUA','INSTANTÁNEA','CARGA','CANAL','RETRASADA','ENCADENABLE','UNA VEZ','COOLDOWN X'] },
    { name: 'Alcance y geometría', tags: ['CONTACTO','CUERPO A CUERPO','DISTANCIA CORTA','DISTANCIA MEDIA','DISTANCIA LARGA','AUTOPERSONAL','ALIADOS','ÁREA PEQUEÑA','ÁREA MEDIA','ÁREA GRANDE','LÍNEA','CONO','ANILLO','TRAYECTORIA','TOQUE','GLOBAL'] },
    { name: 'Función de combate', tags: ['OFENSIVA','DEFENSIVA','CONTROL','SOPORTE','MOVILIDAD','CURACIÓN','UTILIDAD','INTERRUPCIÓN','PENETRACIÓN','DESVÍO','ABSORCIÓN','SEÑUELO','ESCUDO'] },
    { name: 'Ejecución', tags: ['EJECUCIÓN: FUE','EJECUCIÓN: AGI','EJECUCIÓN: DES','EJECUCIÓN: INST','EJECUCIÓN: ESP','EJECUCIÓN: INT'] },
    { name: 'Tipo de daño', tags: ['DAÑO FÍSICO','DAÑO CORTANTE','DAÑO CONTUNDENTE','DAÑO PERFORANTE','DAÑO ÍGNEO','DAÑO CRIOGÉNICO','DAÑO ELÉCTRICO','DAÑO TÓXICO','DAÑO EXPLOSIVO','DAÑO INTERNO','DAÑO ESPIRITUAL','DAÑO ESTRUCTURAL','DAÑO OSCURO'] },
    { name: 'Interacción especial', tags: ['ANTI-LOGIA','ANTI-HAKI','KAIROSEKI','IGNORA ARMADURA','DOBLE DAÑO EMPAPADO','VULNERABILIDAD AGUA','ESCALA CON DAÑO RECIBIDO','ESCALA CON PE RESTANTE','ESCALA CON ALIADOS','BONUS VS DERRIBADO','BONUS VS ESTADO','ENCADENADO CON','ROMPE CONCENTRACIÓN'] },
    { name: 'Elemento / naturaleza', tags: ['FUEGO','HIELO','RAYO','VENENO','OSCURIDAD','LUZ','VIENTO','TIERRA','AGUA','HUMO','ARENA','VIBRACIÓN','SONIDO','GRAVEDAD','VACÍO'] },
    { name: 'Akuma no Mi', tags: ['LOGIA','PARAMECIA-PRODUCTOR','PARAMECIA-TRANSFORMADOR','PARAMECIA-MANIPULADOR','ZOAN','ZOAN MÍTICO','ZOAN ANTIGUO','DESPERTAR'] },
    { name: 'Haki', tags: ['HAKI ARMAMENTO','HAKI OBSERVACIÓN','HAKI REY','FLUJO AVANZADO','VISIÓN DE FUTURO','EMISIÓN DE REY'] },
    { name: 'Equipo', tags: ['ARMA','ARMA SECUNDARIA','ARMA ARROJADIZA','ARMADURA','ARMADURA PARCIAL','ACCESORIO','CONSUMIBLE','NAVE','KAIROSEKI INTEGRADO','GRADO MEITO','MODIFICABLE'] },
    { name: 'NPC', tags: ['PIRATA','MARINO','REVOLUCIONARIO','CIVIL','AGENTE CIPHER POL','BOUNTY HUNTER','ALIADO TEMPORAL','OBSTÁCULO','JEFE DE ESCENA'] },
    { name: 'Condición y restricción', tags: ['REQUIERE ARMA','REQUIERE AKUMA NO MI','REQUIERE HAKI','REQUIERE ESTADO PROPIO','REQUIERE ESTADO OBJETIVO','SOLO EN AGUA','SOLO EN TIERRA','SOLO FORMA HÍBRIDA','SOLO FORMA BESTIAL','CONSUMO DOBLE EMPAPADO','AUTO-DAÑO'] }
];

function initModerationForm(details) {
    const selectedTags = new Set();
    const tagDropdown = document.getElementById('tag-dropdown');
    const tagSelected = document.getElementById('tag-selected');
    const tagToggleBtn = document.getElementById('tag-toggle-btn');
    const cTagsInput = document.getElementById('mod-tags');

    tagDropdown.innerHTML = '';
    TAG_CATEGORIES.forEach(cat => {
        const group = document.createElement('div');
        group.className = 'rpg-staff-tag-group';
        const header = document.createElement('div');
        header.className = 'rpg-staff-tag-group-header';
        header.innerHTML = '<span class="rpg-staff-tag-group-arrow">▸</span> ' + cat.name;
        header.addEventListener('click', () => {
            const body = header.nextElementSibling;
            const arrow = header.querySelector('.rpg-staff-tag-group-arrow');
            body.classList.toggle('is-open');
            arrow.textContent = body.classList.contains('is-open') ? '▾' : '▸';
        });
        group.appendChild(header);
        const body = document.createElement('div');
        body.className = 'rpg-staff-tag-group-body';
        cat.tags.forEach(tag => {
            const label = document.createElement('label');
            label.className = 'rpg-staff-tag-option';
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.value = tag;
            cb.addEventListener('change', () => {
                if (cb.checked) selectedTags.add(tag);
                else selectedTags.delete(tag);
                updateTagDisplay();
            });
            label.appendChild(cb);
            label.appendChild(document.createTextNode(tag));
            body.appendChild(label);
        });
        group.appendChild(body);
        tagDropdown.appendChild(group);
    });

    tagToggleBtn.addEventListener('click', () => {
        tagDropdown.classList.toggle('is-open');
    });

    function updateTagDisplay() {
        tagSelected.innerHTML = '';
        selectedTags.forEach(tag => {
            const pill = document.createElement('span');
            pill.className = 'rpg-staff-tag-pill';
            pill.textContent = tag;
            const remove = document.createElement('span');
            remove.textContent = '×';
            remove.className = 'rpg-staff-tag-pill-remove';
            remove.addEventListener('click', (e) => {
                e.stopPropagation();
                selectedTags.delete(tag);
                const cbs = tagDropdown.querySelectorAll('input[type="checkbox"]');
                cbs.forEach(cb => { if (cb.value === tag) cb.checked = false; });
                updateTagDisplay();
            });
            pill.appendChild(remove);
            tagSelected.appendChild(pill);
        });
        cTagsInput.value = Array.from(selectedTags).join(', ');
    }

    function setTags(tagsArray) {
        selectedTags.clear();
        const cbs = tagDropdown.querySelectorAll('input[type="checkbox"]');
        cbs.forEach(cb => { cb.checked = false; });
        (tagsArray || []).forEach(tag => {
            selectedTags.add(tag);
            cbs.forEach(cb => { if (cb.value === tag) cb.checked = true; });
        });
        updateTagDisplay();
    }

    setTags(details.tags || []);

    // Cargar efectos estructurados dinámicos en campos de moderación
    const effects = details.effects || {};
    document.getElementById('mod-akuma-type').value = effects.akuma_type || 'paramecia';
    document.getElementById('mod-akuma-efectos').value = effects.efectos || '';
    document.getElementById('mod-akuma-limitaciones').value = effects.limitaciones || '';
    document.getElementById('mod-akuma-debilidades').value = effects.debilidades || '';

    // ======= NPC ACTIONS DYNAMIC LIST =======
    const modNpcActionsContainer = document.getElementById('mod-npc-actions-container');
    const MOD_DICE_OPTIONS = ['1d4','1d6','1d8','1d10','1d12','2d4','2d6','2d8','2d10','3d6','4d6'];
    const MOD_STAT_OPTIONS = ['','FUE','AGI','DES','INST','ESP','INT'];

    function addModNpcActionRow(action) {
        if (!modNpcActionsContainer) return;
        action = action || '';
        var name = '';
        var dice = '';
        var stat = '';
        if (typeof action === 'string') {
            name = action.replace(/\s*\([^)]*\)\s*$/, '').trim();
            var m = action.match(/(\d+d\d+(?:\s*[+\-]\s*\w+)?)/i);
            dice = m ? m[1].replace(/\s+/g, '') : '';
        } else if (action && typeof action === 'object') {
            name = action.name || '';
            dice = action.dice || '';
            stat = action.stat || '';
        }
        var div = document.createElement('div');
        div.className = 'rpg-npc-action-row mod-npc-action-row';
        var diceOpts = MOD_DICE_OPTIONS.map(function(d) {
            return '<option value="' + d + '"' + (d === dice ? ' selected' : '') + '>' + d + '</option>';
        }).join('');
        diceOpts += '<option value=""' + (!dice ? ' selected' : '') + '>Sin dado</option>';
        var statOpts = MOD_STAT_OPTIONS.map(function(s) {
            return '<option value="' + s + '"' + (s === stat ? ' selected' : '') + '>' + (s || '— Stat —') + '</option>';
        }).join('');
        div.innerHTML =
            '<input type="text" class="textbox rpg-npc-action-name mod-npc-action-name" value="' + name.replace(/"/g, '&quot;') + '" placeholder="Nombre (ej: Picotazo Rápido)">' +
            '<select class="textbox rpg-dice-select-sm mod-npc-action-dice">' + diceOpts + '</select>' +
            '<select class="textbox rpg-dice-select-sm mod-npc-action-stat">' + statOpts + '</select>' +
            '<button type="button" class="rpg-btn-remove-sm remove-mod-npc-action">Eliminar</button>';
        div.querySelector('.remove-mod-npc-action').addEventListener('click', function() {
            div.remove();
            if (modNpcActionsContainer.children.length === 0) {
                addModNpcActionRow('');
            }
        });
        modNpcActionsContainer.appendChild(div);
    }

    const btnModAddAction = document.getElementById('btn-mod-npc-add-action');
    if (btnModAddAction) {
        btnModAddAction.addEventListener('click', function() { addModNpcActionRow(''); });
    }

    window.getModNpcActions = function() {
        var rows = document.querySelectorAll('#mod-npc-actions-container .mod-npc-action-row');
        return Array.from(rows).map(function(row) {
            var nameEl = row.querySelector('.mod-npc-action-name');
            var diceEl = row.querySelector('.mod-npc-action-dice');
            var statEl = row.querySelector('.mod-npc-action-stat');
            var name = nameEl ? nameEl.value.trim() : '';
            var dice = diceEl ? diceEl.value.trim() : '';
            var stat = statEl ? statEl.value.trim() : '';
            if (!name) return null;
            var out = { name: name };
            if (dice) out.dice = dice;
            if (stat) out.stat = stat;
            return out;
        }).filter(Boolean);
    };

    function setModNpcActions(actions) {
        if (!modNpcActionsContainer) return;
        modNpcActionsContainer.innerHTML = '';
        var list = Array.isArray(actions) ? actions : (typeof actions === 'string' ? actions.split('\n') : []);
        if (list.length === 0) {
            addModNpcActionRow('');
        } else {
            list.forEach(function(act) { addModNpcActionRow(act); });
        }
    }

    // ======= DYNAMIC SUBTIPO OPTIONS =======
    const modSubOptions = {
        arma: ['Espada', 'Lanza', 'Arco', 'Ballesta', 'Pistola', 'Rifle', 'Hacha', 'Maza', 'Otros'],
        util: ['Botiquín', 'Comida', 'Brújula', 'Munición', 'Kairooseki', 'Herramienta', 'Otros'],
        armadura: ['Peto', 'Escudo', 'Casco', 'Grebas', 'Guanteletes', 'Otros']
    };

    function updateModSubtipoOptions(currentVal = '') {
        const eqType = document.getElementById('mod-equipo-type').value;
        const sel = document.getElementById('mod-equipo-subtipo-select');
        const input = document.getElementById('mod-equipo-subtipo');
        if (!sel || !input) return;
        
        const list = modSubOptions[eqType] || ['Otros'];
        
        sel.innerHTML = '';
        list.forEach(opt => {
            sel.innerHTML += `<option value="${opt.toLowerCase()}">${opt}</option>`;
        });
        
        const lowerList = list.map(x => x.toLowerCase());
        const searchVal = (currentVal || input.value || '').trim().toLowerCase();
        
        if (searchVal && lowerList.includes(searchVal)) {
            sel.value = searchVal;
            input.value = searchVal;
            input.style.display = 'none';
        } else if (searchVal) {
            sel.value = 'otros';
            input.value = currentVal || input.value;
            input.style.display = 'block';
        } else {
            sel.value = lowerList[0];
            input.value = lowerList[0];
            input.style.display = 'none';
        }
    }

    const selModSub = document.getElementById('mod-equipo-subtipo-select');
    if (selModSub) {
        selModSub.addEventListener('change', (e) => {
            const input = document.getElementById('mod-equipo-subtipo');
            if (e.target.value === 'otros') {
                input.style.display = 'block';
                input.value = '';
                input.focus();
            } else {
                input.style.display = 'none';
                input.value = e.target.value;
            }
        });
    }

    document.getElementById('mod-equipo-type').value = effects.equipo_type || 'util';
    updateModSubtipoOptions(effects.subtipo || '');
    var stackEl = document.getElementById('mod-equipo-stack-qty');
    if (stackEl) stackEl.value = effects.default_cantidad || 1;

    document.getElementById('mod-barco-type').value = effects.barco_type || 'navio';
    document.getElementById('mod-barco-tier').value = effects.tier || 1;
    document.getElementById('mod-barco-vida').value = effects.vida || 100;
    document.getElementById('mod-barco-ataque').value = effects.ataque || 0;
    document.getElementById('mod-barco-velocidad').value = effects.velocidad || 0;
    document.getElementById('mod-barco-resistencia').value = effects.resistencia || 0;

    document.getElementById('mod-npc-mascota-type').value = effects.npc_mascota_type || 'npc';
    document.getElementById('mod-npc-vida').value = effects.vida || 50;
    document.getElementById('mod-npc-tier').value = effects.tier || 1;
    setModNpcActions(effects.acciones || []);

    let hType = effects.haki_type || 'busoshoku';
    if (hType === 'busshoku') hType = 'busoshoku';
    if (hType === 'kenboshuko') hType = 'kenbunshoku';
    document.getElementById('mod-haki-type').value = hType;
    document.getElementById('mod-haki-level').value = effects.haki_level || 'basico';
    document.getElementById('mod-haki-efecto').value = effects.efecto || '';

    // Registrar event listeners para el cambio de visibilidad
    const typeSelect = document.getElementById('mod-type');
    const eqTypeSelect = document.getElementById('mod-equipo-type');
    const npcTypeSelect = document.getElementById('mod-npc-mascota-type');

    function isModUtilConsumibleDice() {
        var eqType = eqTypeSelect ? eqTypeSelect.value : 'util';
        if (eqType !== 'util') return false;
        var sub = (document.getElementById('mod-equipo-subtipo').value || '').toLowerCase();
        var tags = (document.getElementById('mod-tags').value || '').toUpperCase();
        if (sub.indexOf('municion') !== -1 || sub.indexOf('munición') !== -1) return true;
        if (tags.indexOf('MUNICION') !== -1 || tags.indexOf('AMMO') !== -1 || tags.indexOf('CONSUMIBLE') !== -1) return true;
        return true;
    }

    function updateModFieldVisibility() {
        const type = typeSelect.value;
        
        const wActivation = document.getElementById('wrapper-mod-activation');
        const wRank = document.getElementById('wrapper-mod-rank');
        const wCost = document.getElementById('wrapper-mod-cost');
        const wStat = document.getElementById('wrapper-mod-stat');
        const wDice = document.getElementById('wrapper-mod-dice');
        const wTurns = document.getElementById('wrapper-mod-turns');
        
        const fAkuma = document.getElementById('fields-mod-akuma');
        const fEquipo = document.getElementById('fields-mod-equipo');
        const fBarco = document.getElementById('fields-mod-barco');
        const fNpc = document.getElementById('fields-mod-npc');
        const fHaki = document.getElementById('fields-mod-haki');
        
        if (wActivation) wActivation.style.display = 'block';
        if (wRank) wRank.style.display = (type === 'tecnica' || type === 'equipo' || type === 'barco') ? 'block' : 'none';
        if (wCost) wCost.style.display = 'block';
        if (wStat) wStat.style.display = 'block';
        if (wDice) wDice.style.display = 'block';
        if (wTurns) wTurns.style.display = 'grid';
        
        if (fAkuma) fAkuma.classList.remove('is-visible');
        if (fEquipo) fEquipo.classList.remove('is-visible');
        if (fBarco) fBarco.classList.remove('is-visible');
        if (fNpc) fNpc.classList.remove('is-visible');
        if (fHaki) fHaki.classList.remove('is-visible');
        
        if (type === 'akuma_no_mi') {
            if (wActivation) wActivation.style.display = 'none';
            if (wCost) wCost.style.display = 'none';
            if (wStat) wStat.style.display = 'none';
            if (wDice) wDice.style.display = 'none';
            if (wTurns) wTurns.style.display = 'none';
            if (fAkuma) fAkuma.classList.add('is-visible');
        } else if (type === 'equipo') {
            if (wActivation) wActivation.style.display = 'none';
            if (wCost) wCost.style.display = 'none';
            if (wTurns) wTurns.style.display = 'none';
            if (fEquipo) fEquipo.classList.add('is-visible');
            
            var eqType = eqTypeSelect ? eqTypeSelect.value : 'util';
            updateModSubtipoOptions();
            var wStack = document.getElementById('wrapper-mod-equipo-stack');
            if (eqType === 'arma') {
                if (wDice) wDice.style.display = 'block';
                if (wStat) wStat.style.display = 'block';
                if (wStack) wStack.classList.add('rpg-wizard-hidden');
            } else if (eqType === 'util') {
                if (isModUtilConsumibleDice()) {
                    if (wDice) wDice.style.display = 'block';
                } else {
                    if (wDice) wDice.style.display = 'none';
                }
                if (wStat) wStat.style.display = 'none';
                if (wStack) wStack.classList.remove('rpg-wizard-hidden');
            } else {
                if (wDice) wDice.style.display = 'none';
                if (wStat) wStat.style.display = 'none';
                if (wStack) wStack.classList.add('rpg-wizard-hidden');
            }
        } else if (type === 'barco') {
            if (wActivation) wActivation.style.display = 'none';
            if (wCost) wCost.style.display = 'none';
            if (wStat) wStat.style.display = 'none';
            if (wDice) wDice.style.display = 'none';
            if (wTurns) wTurns.style.display = 'none';
            if (fBarco) fBarco.classList.add('is-visible');
        } else if (type === 'npc_menor') {
            if (wActivation) wActivation.style.display = 'none';
            if (wCost) wCost.style.display = 'none';
            if (wStat) wStat.style.display = 'none';
            if (wDice) wDice.style.display = 'none';
            if (wTurns) wTurns.style.display = 'none';
            if (fNpc) fNpc.classList.add('is-visible');
            
            const npcType = npcTypeSelect ? npcTypeSelect.value : 'npc';
            const wNpcTier = document.getElementById('wrapper-mod-npc-tier');
            if (wNpcTier) {
                wNpcTier.style.display = (npcType === 'mascota') ? 'block' : 'none';
            }
        } else if (type === 'haki') {
            if (wActivation) wActivation.style.display = 'none';
            if (wCost) wCost.style.display = 'none';
            if (wStat) wStat.style.display = 'none';
            if (wDice) wDice.style.display = 'none';
            if (wTurns) wTurns.style.display = 'none';
            if (fHaki) fHaki.classList.add('is-visible');
        }
    }

    if (typeSelect) typeSelect.addEventListener('change', updateModFieldVisibility);
    if (eqTypeSelect) eqTypeSelect.addEventListener('change', () => {
        updateModSubtipoOptions();
        updateModFieldVisibility();
    });
    if (npcTypeSelect) npcTypeSelect.addEventListener('change', updateModFieldVisibility);
    
    updateModFieldVisibility();

    // DICE BUILDER
    function buildDiceFormula() {
        const groups = document.querySelectorAll('#dice-groups > div');
        let parts = [];
        groups.forEach(g => {
            if (g.classList.contains('dice-group')) {
                const qty = parseInt(g.querySelector('.dice-qty').value) || 1;
                const type = g.querySelector('.dice-type').value;
                if (qty > 0) parts.push(qty + type);
            } else if (g.classList.contains('dice-placeholder')) {
                const type = g.querySelector('.placeholder-type').value;
                parts.push(type);
            }
        });
        const fixed = parseInt(document.getElementById('dice-fixed').value) || 0;
        const stat = document.getElementById('dice-stat').value;
        const statMod = document.getElementById('dice-stat-mod').value.trim();
        const suffix = document.getElementById('dice-suffix').value.trim();

        let formula = parts.join('+');
        if (fixed > 0) formula += (formula ? '+' : '') + fixed;
        if (stat) {
            let statPart = stat;
            if (statMod) {
                if (statMod.includes('/')) {
                    const divisor = statMod.replace('/', '').trim();
                    statPart = stat + '/' + divisor;
                } else if (statMod.includes('*')) {
                    const mult = statMod.replace('*', '').trim();
                    statPart = mult + '*' + stat;
                } else {
                    if (!isNaN(parseFloat(statMod))) {
                        statPart = statMod + '*' + stat;
                    } else {
                        statPart = statMod + stat;
                    }
                }
            }
            formula += (formula ? '+' : '') + statPart;
        }
        if (suffix) formula += (formula ? ' ' : '') + suffix;

        document.getElementById('dice-preview').textContent = formula || '—';
        document.getElementById('mod-dice').value = formula;
    }

    function addDiceGroup(qty, type) {
        const container = document.getElementById('dice-groups');
        const group = document.createElement('div');
        group.className = 'dice-group rpg-dice-group-chip';

        const qtyInput = document.createElement('input');
        qtyInput.type = 'number';
        qtyInput.className = 'dice-qty textbox rpg-dice-qty-input';
        qtyInput.min = 1;
        qtyInput.max = 100;
        qtyInput.value = qty || 2;
        qtyInput.addEventListener('input', buildDiceFormula);

        const typeSelect = document.createElement('select');
        typeSelect.className = 'dice-type textbox rpg-dice-type-select';
        ['d4', 'd6', 'd8', 'd10', 'd12', 'd20', 'd100'].forEach(d => {
            const opt = document.createElement('option');
            opt.value = d;
            opt.textContent = d;
            if (d === (type || 'd20')) opt.selected = true;
            typeSelect.appendChild(opt);
        });
        typeSelect.addEventListener('change', buildDiceFormula);

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.textContent = '×';
        removeBtn.title = 'Quitar grupo';
        removeBtn.className = 'rpg-dice-remove-btn';
        removeBtn.addEventListener('click', () => {
            container.removeChild(group);
            buildDiceFormula();
        });

        group.appendChild(qtyInput);
        group.appendChild(typeSelect);
        group.appendChild(removeBtn);
        container.appendChild(group);
        buildDiceFormula();
    }

    function addPlaceholderGroup(type) {
        const container = document.getElementById('dice-groups');
        const group = document.createElement('div');
        group.className = 'dice-placeholder rpg-dice-group-chip rpg-dice-group-chip--placeholder';

        const textSpan = document.createElement('span');
        textSpan.textContent = type;

        const typeInput = document.createElement('input');
        typeInput.type = 'hidden';
        typeInput.className = 'placeholder-type';
        typeInput.value = type;

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.textContent = '×';
        removeBtn.title = 'Quitar';
        removeBtn.className = 'rpg-dice-remove-btn';
        removeBtn.addEventListener('click', () => {
            container.removeChild(group);
            buildDiceFormula();
        });

        group.appendChild(textSpan);
        group.appendChild(typeInput);
        group.appendChild(removeBtn);
        container.appendChild(group);
        buildDiceFormula();
    }

    function parseDiceFormula(formula) {
        const container = document.getElementById('dice-groups');
        container.innerHTML = '';
        document.getElementById('dice-fixed').value = '0';
        document.getElementById('dice-stat').value = '';
        document.getElementById('dice-stat-mod').value = '';
        document.getElementById('dice-suffix').value = '';

        if (!formula || formula === '—' || !formula.trim()) {
            addDiceGroup(2, 'd20');
            return;
        }

        let suffix = '';
        let formulaNoSuffix = formula.trim();
        const suffixMatch = formula.match(/\[([^\]]+)\]$/);
        if (suffixMatch) {
            suffix = suffixMatch[0]; 
            formulaNoSuffix = formula.substring(0, formula.length - suffix.length).trim();
        }

        const parts = formulaNoSuffix.split('+');
        let suffixParts = [];

        parts.forEach(part => {
            part = part.trim();
            if (!part) return;
            const diceMatch = part.match(/^(\d+)(d\d+)$/i);
            if (diceMatch) {
                addDiceGroup(parseInt(diceMatch[1]), diceMatch[2]);
                return;
            }
            if (part === '[ARMA]' || part === '[MUNICION]') {
                addPlaceholderGroup(part);
                return;
            }

            const multMatch = part.match(/^([\d.]+)\*(FUE|AGI|DES|INST|ESP|INT)$/i);
            if (multMatch) {
                document.getElementById('dice-stat').value = multMatch[2].toUpperCase();
                document.getElementById('dice-stat-mod').value = multMatch[1] + '*';
                return;
            }
            const divMatch = part.match(/^(FUE|AGI|DES|INST|ESP|INT)\/([\d.]+)$/i);
            if (divMatch) {
                document.getElementById('dice-stat').value = divMatch[1].toUpperCase();
                document.getElementById('dice-stat-mod').value = '/' + divMatch[2];
                return;
            }
            const reverseMultMatch = part.match(/^(FUE|AGI|DES|INST|ESP|INT)\*([\d.]+)$/i);
            if (reverseMultMatch) {
                document.getElementById('dice-stat').value = reverseMultMatch[1].toUpperCase();
                document.getElementById('dice-stat-mod').value = reverseMultMatch[2] + '*';
                return;
            }

            if (['FUE', 'AGI', 'DES', 'INST', 'ESP', 'INT'].includes(part.toUpperCase())) {
                document.getElementById('dice-stat').value = part.toUpperCase();
                return;
            }
            if (/^\d+$/.test(part)) {
                document.getElementById('dice-fixed').value = part;
                return;
            }
            suffixParts.push(part);
        });

        if (suffix) {
            suffixParts.push(suffix);
        }

        if (suffixParts.length > 0) {
            document.getElementById('dice-suffix').value = suffixParts.join(' ');
        }
        buildDiceFormula();
    }

    document.getElementById('dice-add-group').addEventListener('click', () => addDiceGroup(1, 'd6'));
    document.getElementById('dice-add-arma').addEventListener('click', () => addPlaceholderGroup('[ARMA]'));
    document.getElementById('dice-add-municion').addEventListener('click', () => addPlaceholderGroup('[MUNICION]'));
    document.getElementById('dice-fixed').addEventListener('input', buildDiceFormula);
    document.getElementById('dice-stat').addEventListener('change', buildDiceFormula);
    document.getElementById('dice-stat-mod').addEventListener('input', buildDiceFormula);
    document.getElementById('dice-suffix').addEventListener('input', buildDiceFormula);

    parseDiceFormula(details.dice || '');
}

function escapeHtml(text) {
  if (!text) return '';
  return text.toString()
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
}

function openHakiReview(id) {
  var h = hakiList.find(function (x) { return parseInt(x.id, 10) === parseInt(id, 10); });
  if (!h) return;
  markUnifiedSelection('haki', id);
  var preview = document.getElementById('request-preview');
  if (!preview) return;
  preview.classList.add('rpg-preview-active');
  var hakiLabel = h.haki_type === 'kenbunshoku' ? 'Observación' : (h.haki_type === 'busoshoku' ? 'Armamento' : 'Conquistador');
  preview.innerHTML =
    '<h2 class="rpg-preview-title rpg-preview-title--add"><i class="fas fa-gem"></i> Petición de Haki</h2>' +
    '<div class="rpg-preview-panel">' +
      '<div class="rpg-preview-panel-label">Personaje Solicitante</div>' +
      '<div class="rpg-preview-panel-value"><img src="' + escapeHtml(h.character_avatar || '') + '" alt="" class="rpg-busqueda-preview-avatar"> ' + escapeHtml(h.character_name) + '</div>' +
      '<div class="rpg-preview-panel-label rpg-preview-panel-spaced">Tipo de Haki</div>' +
      '<div class="rpg-preview-panel-value">Haki de ' + escapeHtml(hakiLabel) + '</div>' +
      '<div class="rpg-preview-panel-label rpg-preview-panel-spaced">Cambio de Nivel</div>' +
      '<div class="rpg-preview-panel-value">Nivel ' + h.nivel_actual + ' <i class="fas fa-arrow-right"></i> Nivel ' + h.nivel_siguiente + '</div>' +
      '<div class="rpg-preview-panel-label rpg-preview-panel-spaced">PP Reservados</div>' +
      '<div class="rpg-preview-panel-value">' + h.pp_reservados + ' PP</div>' +
      '<div class="rpg-preview-panel-label rpg-preview-panel-spaced">Fecha de Solicitud</div>' +
      '<div class="rpg-preview-panel-value">' + escapeHtml(h.date || '') + '</div>' +
    '</div>' +
    '<input type="hidden" id="haki-req-character-id" value="' + h.character_id + '">' +
    '<input type="hidden" id="haki-req-type" value="' + h.haki_type + '">' +
    '<div class="rpg-preview-panel rpg-preview-panel--actions">' +
      '<div class="rpg-preview-panel-label">Motivo de denegación / Comentario (opcional)</div>' +
      '<textarea id="haki-req-nota" rows="3" class="rpg-staff-textarea" placeholder="Escribe un comentario sobre esta resolución..."></textarea>' +
      '<div class="rpg-preview-actions">' +
        '<button type="button" onclick="accionHakiRequest(\'aprobar\')" class="rpg-action-btn rpg-btn-primary"><i class="fas fa-check"></i> Aprobar</button>' +
        '<button type="button" onclick="accionHakiRequest(\'rechazar\')" class="rpg-system-tab-btn rpg-staff-btn-danger"><i class="fas fa-times"></i> Rechazar</button>' +
      '</div>' +
    '</div>';
}

function accionHakiRequest(action) {
  var charIdEl = document.getElementById('haki-req-character-id');
  var typeEl = document.getElementById('haki-req-type');
  var notaEl = document.getElementById('haki-req-nota');
  if (!charIdEl || !typeEl) return;

  var charId = parseInt(charIdEl.value, 10);
  var hakiType = typeEl.value;
  var motivo = notaEl ? notaEl.value.trim() : '';

  var postData = {
    character_id: charId,
    haki_type: hakiType,
    action: action,
    motivo: motivo,
    my_post_key: window.GAME_CSRF || ''
  };

  (window.gamePostJson
    ? window.gamePostJson(bburl + '/game/ajax/haki_resolve.php', postData)
    : fetch(bburl + '/game/ajax/haki_resolve.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Mybb-Post-Key': window.GAME_CSRF || '' },
        credentials: 'same-origin',
        body: JSON.stringify(postData)
      }).then(function (r) { return r.json(); })
  ).then(function (res) {
    if (res.ok) {
      document.getElementById('request-preview').innerHTML = '<div class="rpg-success-empty"><i class="fas fa-check-circle"></i>Petición de Haki procesada con éxito</div>';
      loadAllPending();
    } else {
      alert('Error: ' + (res.error ? res.error.message : 'Error desconocido'));
    }
  }).catch(function () {
    alert('Error de conexión.');
  });
}

window.switchTab = switchTab;
window.selectUnified = selectUnified;
window.selectRequest = selectRequest;
window.resolveRequest = resolveRequest;
window.saveModeration = saveModeration;
window.openBusquedaReview = openBusquedaReview;
window.closeBusquedaReview = closeBusquedaReview;
window.accionBusqueda = accionBusqueda;
window.openAdminReview = openAdminReview;
window.closeAdminReview = closeAdminReview;
window.accionAdminRequest = accionAdminRequest;
window.openHakiReview = openHakiReview;
window.accionHakiRequest = accionHakiRequest;
window.loadAllPending = loadAllPending;
window.loadAdminRequestsPending = loadAllPending;
})();
