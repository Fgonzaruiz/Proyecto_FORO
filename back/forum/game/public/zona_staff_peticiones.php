<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

if (!isset($mybb) || !is_object($mybb) || (int)($mybb->user['uid'] ?? 0) === 0) {
    header('Location: ../member.php?action=login');
    exit;
}

$uid = (int)$mybb->user['uid'];
$prefix = TABLE_PREFIX;

$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;
$pj_name = '';

if ($active_pj_id > 0) {
    $pj_q = $db->query("SELECT name, staff_level FROM {$prefix}game_personajes WHERE id = {$active_pj_id} AND user_id = {$uid} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    if ($pj) {
        $staff_level = (int)$pj['staff_level'];
        $pj_name = $pj['name'];
    }
}

if ($staff_level < 2) {
    header('Location: ../index.php');
    exit;
}

$b_url = $mybb->settings['bburl'];

ob_start();
?>
<div class="rpg-staff-zone">
  <div class="rpg-staff-header" style="background: linear-gradient(135deg, rgba(139,92,246,0.15), rgba(99,102,241,0.1));">
    <div class="rpg-staff-header-content">
      <h1><i class="fas fa-clipboard-check"></i> Peticiones</h1>
      <p>Revisa y gestiona todas las peticiones enviadas por los jugadores.</p>
    </div>
  </div>

  <!-- PESTAÑAS -->
  <div style="display:flex; gap:0; border-bottom: 2px solid var(--border-color); margin-top: 20px;">
    <button id="tab-btn-cartas" onclick="switchTab('cartas')" style="padding: 12px 28px; font-family: var(--font-heading); font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; border: none; border-bottom: 3px solid var(--accent-indigo); margin-bottom: -2px; background: transparent; cursor: pointer; color: var(--accent-indigo); display: flex; align-items: center; gap: 8px; transition: all 0.2s;">
      <i class="fas fa-layer-group"></i> Peticiones de Cartas
      <span id="tab-count-cartas" style="background: var(--accent-indigo); color: #fff; padding: 1px 7px; border-radius: 10px; font-size: 11px;">0</span>
    </button>
    <button id="tab-btn-busquedas" onclick="switchTab('busquedas')" style="padding: 12px 28px; font-family: var(--font-heading); font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; border: none; border-bottom: 3px solid transparent; margin-bottom: -2px; background: transparent; cursor: pointer; color: var(--text-muted); display: flex; align-items: center; gap: 8px; transition: all 0.2s;">
      <i class="fas fa-search"></i> Búsquedas de Rol
      <span id="tab-count-busquedas" style="background: var(--text-muted); color: #fff; padding: 1px 7px; border-radius: 10px; font-size: 11px;">0</span>
    </button>
  </div>

  <!-- ═══════════════════════════════════════════════ -->
  <!-- PESTAÑA: CARTAS                                -->
  <!-- ═══════════════════════════════════════════════ -->
  <div id="tab-cartas" style="display:block;">
    <div class="aprobar-layout" style="display:flex; gap:20px; margin-top:20px;">
      <!-- LEFT: Requests List -->
      <div class="aprobar-list" id="requests-list" style="width:320px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:var(--radius-lg); flex-shrink:0; display:flex; flex-direction:column; overflow:hidden; box-shadow:var(--shadow-card); min-height:750px;">
        <div class="aprobar-list-header" style="padding:15px; border-bottom:1px solid var(--border-color); background:var(--bg-surface); font-weight:700; display:flex; justify-content:space-between; align-items:center;">
          <span>Solicitudes Pendientes</span>
          <span class="aprobar-count" id="requests-count" style="background:var(--accent-indigo); color:#fff; padding:2px 8px; border-radius:10px; font-size:11px;">0</span>
        </div>
        <div id="requests-list-items" style="flex:1; overflow-y:auto; max-height:680px;">
          <div class="aprobar-empty" style="padding:20px; text-align:center; color:var(--text-muted);">Cargando...</div>
        </div>
      </div>

      <!-- RIGHT: Preview Panel -->
      <div class="aprobar-preview" id="request-preview" style="flex:1; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:var(--radius-lg); overflow:hidden; box-shadow:var(--shadow-card); min-height:750px; display:flex; flex-direction:column; justify-content:center; align-items:center; padding:40px 20px; text-align:center; color:var(--text-muted);">
        <i class="fas fa-clipboard-list" style="font-size:48px; display:block; margin-bottom:15px; opacity:0.3; color:var(--accent-purple);"></i>
        Selecciona una solicitud para procesarla
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════ -->
  <!-- PESTAÑA: BÚSQUEDAS DE ROL                      -->
  <!-- ═══════════════════════════════════════════════ -->
  <div id="tab-busquedas" style="display:none; margin-top:20px;">
    <div id="busquedas-pending-list">
      <div style="text-align:center; padding:40px; color:var(--text-muted);">
        <i class="fas fa-spinner fa-spin fa-2x"></i><br><br>Cargando búsquedas...
      </div>
    </div>
  </div>

  <!-- Modal revisión búsqueda -->
  <div id="busqueda-review-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.75); z-index:9999; align-items:center; justify-content:center; backdrop-filter:blur(4px);">
    <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: 12px; width: 90%; max-width: 650px; max-height: 90vh; overflow-y: auto; box-shadow: var(--shadow-main);">
      <div style="padding: 25px 30px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between;">
        <h3 id="brm-titulo" style="margin:0; font-size: 20px; color: var(--text-primary); display:flex; align-items:center; gap:10px;"><i class="fas fa-search" style="color:var(--accent-rose);"></i> <span id="brm-titulo-text"></span></h3>
        <button onclick="closeBusquedaReview()" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:20px;"><i class="fas fa-times"></i></button>
      </div>
      <div style="padding: 25px 30px;">
        <img id="brm-img" src="" style="width:100%; height:220px; object-fit:cover; border-radius:8px; margin-bottom:20px; display:none;" />
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
          <img id="brm-avatar" src="" style="width:50px; height:50px; border-radius:50%; object-fit:cover; border:2px solid var(--accent-rose);" />
          <div>
            <div id="brm-pj" style="font-weight: 700; color: var(--text-primary);"></div>
            <div id="brm-date" style="font-size: 12px; color: var(--text-muted);"></div>
          </div>
        </div>
        <div id="brm-desc" style="font-size: 14px; color: var(--text-secondary); line-height: 1.7; margin-bottom: 20px; background: var(--bg-main); padding: 15px; border-radius: 8px; white-space: pre-wrap;"></div>
        <input type="hidden" id="brm-id" value="" />
        <label style="font-size: 13px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px; display: block;">Nota para el jugador (opcional):</label>
        <textarea id="brm-nota" rows="3" style="width:100%; background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; color: var(--text-primary); font-size: 13px; resize: vertical; box-sizing: border-box;" placeholder="Añade una nota que recibirá el jugador..."></textarea>
        <div style="display: flex; gap: 10px; margin-top: 20px;">
          <button onclick="accionBusqueda('aprobar')" style="flex:1; background: linear-gradient(135deg,#10b981,#059669); color: #fff; border: none; border-radius: 6px; padding: 12px; font-weight: 700; cursor: pointer; font-size: 15px; display:flex; align-items:center; justify-content:center; gap:8px;">
            <i class="fas fa-check"></i> Aprobar y publicar
          </button>
          <button onclick="accionBusqueda('denegar')" style="flex:1; background: linear-gradient(135deg,#ef4444,#dc2626); color: #fff; border: none; border-radius: 6px; padding: 12px; font-weight: 700; cursor: pointer; font-size: 15px; display:flex; align-items:center; justify-content:center; gap:8px;">
            <i class="fas fa-times"></i> Denegar
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
var allRequests = [];
var currentReq = null;
var busquedasList = [];
var bburl = '<?= $b_url ?>';
var staffLevel = <?= $staff_level ?>;

// ─── TABS ───────────────────────────────────────────
function switchTab(tab) {
  document.getElementById('tab-cartas').style.display    = tab === 'cartas'    ? 'block' : 'none';
  document.getElementById('tab-busquedas').style.display = tab === 'busquedas' ? 'block' : 'none';

  var btnCartas    = document.getElementById('tab-btn-cartas');
  var btnBusquedas = document.getElementById('tab-btn-busquedas');

  if (tab === 'cartas') {
    btnCartas.style.color       = 'var(--accent-indigo)';
    btnCartas.style.borderBottomColor = 'var(--accent-indigo)';
    btnBusquedas.style.color    = 'var(--text-muted)';
    btnBusquedas.style.borderBottomColor = 'transparent';
  } else {
    btnBusquedas.style.color    = 'var(--accent-rose)';
    btnBusquedas.style.borderBottomColor = 'var(--accent-rose)';
    btnCartas.style.color       = 'var(--text-muted)';
    btnCartas.style.borderBottomColor = 'transparent';
    loadBusquedasPending(true);
  }
}

// ─── CARTAS ─────────────────────────────────────────
function loadRequests() {
  fetch(bburl + '/game/ajax/cards_pending_requests.php')
    .then(r => r.json())
    .then(res => {
      if (!res.ok) {
        document.getElementById('requests-list-items').innerHTML = `<div style="padding:20px; color:var(--accent-rose); text-align:center;">Error: ${res.error.message}</div>`;
        return;
      }
      allRequests = res.data;
      document.getElementById('tab-count-cartas').textContent = res.data.length;
      renderList(res.data);
    })
    .catch(() => {
      document.getElementById('requests-list-items').innerHTML = `<div style="padding:20px; color:var(--accent-rose); text-align:center;">Error de conexión.</div>`;
    });
}

function renderList(list) {
  document.getElementById('requests-count').textContent = list.length.toString();
  const container = document.getElementById('requests-list-items');
  if (list.length === 0) {
    container.innerHTML = `<div style="padding:40px 20px; color:var(--text-muted); text-align:center;"><i class="fas fa-check-circle" style="font-size:32px; color:var(--accent-emerald); display:block; margin-bottom:10px; opacity:0.7;"></i>No hay solicitudes pendientes</div>`;
    return;
  }
  let html = '';
  list.forEach(req => {
    let resolvedName = req.card_name || 'Carta Personalizada';
    let typeLabel = 'MEJORA';
    let typeColor = '#10b981';
    let typeBg = 'rgba(16,185,129,0.1)';
    
    if (req.request_type === 'delete') {
      typeLabel = 'BORRADO';
      typeColor = '#ef4444';
      typeBg = 'rgba(239,68,68,0.1)';
    } else if (req.request_type === 'create') {
      typeLabel = 'CREACIÓN';
      typeColor = '#a855f7';
      typeBg = 'rgba(168,85,247,0.1)';
      try {
        if (req.card_details_json) {
          const details = JSON.parse(req.card_details_json);
          if (details && details.name) resolvedName = details.name;
        }
      } catch(e) {}
    } else if (req.request_type === 'add_existing') {
      typeLabel = 'ADICIÓN';
      typeColor = '#3b82f6';
      typeBg = 'rgba(59,130,246,0.1)';
    }

    const avatar = req.character_avatar || 'https://placehold.co/100x100';
    
    // Status label
    const statusLabel = req.status.toUpperCase();
    const statusStyle = req.status === 'conforme' ? 'border:1px solid #6366f1; color:#6366f1; background:rgba(99,102,241,0.05); font-weight:800; font-size:9px; padding:2px 6px; border-radius:4px; float:right;' : '';
    const statusBadge = req.status === 'conforme' ? `<span style="${statusStyle}">${statusLabel}</span>` : '';

    html += `
      <div class="aprobar-list-item request-item" data-id="${req.id}" onclick="selectRequest(${req.id})" style="display:flex; gap:12px; padding:15px; border-bottom:1px solid var(--border-color); cursor:pointer; transition:background 0.2s;">
        <div style="width:45px; height:45px; border-radius:50%; background-image:url('${avatar}'); background-size:cover; background-position:center; flex-shrink:0; border:2px solid var(--border-color);"></div>
        <div style="flex:1; min-width:0;">
          <div style="font-weight:700; color:var(--text-primary); font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
            ${escapeHtml(req.character_name)}
            ${statusBadge}
          </div>
          <div style="font-size:12px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px;">Carta: ${escapeHtml(resolvedName)}</div>
          <span style="display:inline-block; font-size:9px; font-weight:800; padding:2px 6px; border-radius:4px; margin-top:5px; background:${typeBg}; color:${typeColor}; border:1px solid ${typeColor}20;">${typeLabel}</span>
        </div>
      </div>`;
  });
  container.innerHTML = html;
}

function selectRequest(id) {
  document.querySelectorAll('.request-item').forEach(el => {
    el.style.background = parseInt(el.dataset.id) === id ? 'rgba(99,102,241,0.08)' : '';
  });
  currentReq = allRequests.find(r => parseInt(r.id) === id);
  if (!currentReq) return;
  const preview = document.getElementById('request-preview');
  preview.style.textAlign = 'left';
  preview.style.justifyContent = 'flex-start';
  preview.style.alignItems = 'stretch';
  preview.style.padding = '30px';
  preview.innerHTML = `<div style="text-align:center; padding:40px;"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i></div>`;

  const isUpgrade = currentReq.request_type === 'upgrade';
  const isDelete = currentReq.request_type === 'delete';
  const isCreate = currentReq.request_type === 'create';
  const isAddExisting = currentReq.request_type === 'add_existing';

  let typeLabel = 'Solicitud de Mejora de Carta';
  let typeIcon = 'fa-arrow-up-long';
  let typeColor = '#10b981';
  if (isDelete) {
    typeLabel = 'Solicitud de Borrado de Carta';
    typeIcon = 'fa-trash-can';
    typeColor = '#ef4444';
  } else if (isCreate) {
    typeLabel = 'Propuesta de Creación de Carta';
    typeIcon = 'fa-wand-magic-sparkles';
    typeColor = '#a855f7';
  } else if (isAddExisting) {
    typeLabel = 'Petición de Adición del Catálogo';
    typeIcon = 'fa-clone';
    typeColor = '#3b82f6';
  }

  // Handle discussion chat rendering
  let chatHtml = '';
  let discussion = [];
  try {
    if (currentReq.discussion_json) {
      discussion = JSON.parse(currentReq.discussion_json);
    }
  } catch(e){}

  if (isCreate || isAddExisting) {
    chatHtml += `
      <div style="margin-top:10px; display:flex; flex-direction:column; gap:6px;">
        <strong style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-family:var(--font-heading);">Hilo de Discusión</strong>
        <div style="display:flex; flex-direction:column; height:320px; background:var(--bg-main); border:1px solid var(--border-color); border-radius:8px; overflow:hidden;">
          <div id="rpg-chat-messages-container" style="flex:1; overflow-y:auto; padding:10px; display:flex; flex-direction:column; gap:8px;">`;
    
    if (discussion.length > 0) {
      discussion.forEach(msg => {
        const bubbleClass = (msg.sender === 'player') ? 'player' : 'staff';
        const senderLabel = (msg.sender === 'player') ? 'JUGADOR' : 'STAFF';
        const senderColor = (msg.sender === 'player') ? '#818cf8' : '#a78bfa';
        const bubbleBg = (msg.sender === 'player') ? 'rgba(99,102,241,0.08)' : 'rgba(168,85,247,0.08)';
        const bubbleBorder = (msg.sender === 'player') ? 'rgba(99,102,241,0.15)' : 'rgba(168,85,247,0.15)';
        const align = (msg.sender === 'player') ? 'align-self:flex-end;' : 'align-self:flex-start;';
        const msgTime = msg.timestamp ? msg.timestamp.split(' ')[1] : '';
        chatHtml += `
          <div style="padding:8px 12px; border-radius:8px; max-width:85%; font-size:12px; line-height:1.4; word-break:break-word; border:1px solid ${bubbleBorder}; background:${bubbleBg}; ${align}">
            <div style="display:flex; justify-content:space-between; font-size:9px; color:var(--text-muted); font-weight:700; margin-bottom:3px;">
              <span style="color:${senderColor};">${escapeHtml(msg.sender_name)} (${senderLabel})</span>
              <span style="margin-left:10px;">${escapeHtml(msgTime)}</span>
            </div>
            <div style="white-space:pre-wrap; color:var(--text-primary);">${escapeHtml(msg.message)}</div>
          </div>`;
      });
    } else {
      chatHtml += `<div style="padding:20px; color:var(--text-muted); text-align:center;">No hay mensajes.</div>`;
    }
    chatHtml += `
          </div>
        </div>
      </div>`;
  }

  if (isUpgrade || isDelete) {
    let nextRankInfo = '';
    if (isUpgrade) {
      const ranks = ['C', 'B', 'A', 'S'];
      const idx = ranks.indexOf(currentReq.current_rank);
      const nextRank = idx !== -1 && idx < ranks.length - 1 ? ranks[idx + 1] : 'S';
      nextRankInfo = `<div style="display:flex; align-items:center; gap:10px; margin-top:8px; font-size:14px; font-weight:700;"><span style="background:var(--bg-main); border:1px solid var(--border-color); padding:4px 10px; border-radius:4px; color:var(--text-muted);">${currentReq.current_rank}</span><i class="fas fa-arrow-right" style="color:var(--text-muted);"></i><span style="background:rgba(16,185,129,0.1); border:1px solid #10b98120; padding:4px 10px; border-radius:4px; color:#10b981;">${nextRank}</span></div>`;
    } else {
      nextRankInfo = `<div style="font-size:13px; margin-top:8px; color:var(--text-muted);">La carta será desvinculada del inventario del personaje.</div>`;
    }
    const tags = JSON.parse(currentReq.tags_json || '[]');
    const cleanedTags = tags.map(t => t.replace(/[\[\]]/g, '').trim().toUpperCase()).filter(Boolean);
    let tagsHtml = '';
    cleanedTags.forEach(t => { tagsHtml += `<span style="display:inline-block; font-size:9px; font-weight:700; padding:2px 8px; border:1px solid var(--border-color); border-radius:12px; color:var(--text-muted); text-transform:uppercase;">${t}</span>`; });
    let statsHtml = '';
    if (currentReq.cost_pe !== '—' || currentReq.execution_stat !== '' || currentReq.dice !== '') {
      statsHtml = `<div style="display:flex; gap:15px; margin:15px 0; background:var(--bg-main); padding:10px 15px; border-radius:8px; border:1px solid var(--border-color);">`;
      if (currentReq.cost_pe !== '—') statsHtml += `<div><span style="display:block; font-size:9px; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Coste PE</span><strong style="font-size:13px; color:var(--text-primary);">${currentReq.cost_pe}</strong></div>`;
      if (currentReq.execution_stat !== '') statsHtml += `<div><span style="display:block; font-size:9px; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Atributo</span><strong style="font-size:13px; color:var(--text-primary);">${currentReq.execution_stat}</strong></div>`;
      if (currentReq.dice !== '') statsHtml += `<div><span style="display:block; font-size:9px; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Dados</span><strong style="font-size:13px; color:var(--text-primary);">${currentReq.dice}</strong></div>`;
      statsHtml += `</div>`;
    }
    const cardImage = currentReq.image_url ? `<div style="width:100%; height:130px; background-image:url('${currentReq.image_url}'); background-size:cover; background-position:center; border-radius:6px; margin-bottom:12px;"></div>` : '';
    
    preview.innerHTML = `
      <h2 style="font-family:var(--font-heading); font-size:18px; color:var(--text-primary); font-weight:800; display:flex; align-items:center; gap:8px; margin-bottom:15px;">
        <i class="fas ${typeIcon}" style="color:${typeColor};"></i> ${typeLabel}
      </h2>
      <div style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:20px;">
        <div class="rpg-card" style="width:230px; border:2px solid var(--border-color); border-radius:8px; background:var(--bg-card); overflow:hidden; flex-shrink:0;">
          <div style="padding:10px 15px; background:var(--bg-surface); border-bottom:1px solid var(--border-color);">
            <div style="font-weight:800; font-size:14px; color:var(--text-primary);">${escapeHtml(currentReq.card_name)}</div>
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; margin-top:2px;">[${currentReq.current_rank}] ${escapeHtml(currentReq.card_type.toUpperCase())}</div>
          </div>
          ${cardImage}
          <div style="padding:12px 15px;">
            <div style="display:flex; gap:5px; flex-wrap:wrap; margin-bottom:10px;">${tagsHtml}</div>
            ${statsHtml}
            <div style="font-size:12px; color:var(--text-secondary); line-height:1.5; height:120px; overflow-y:auto; padding-right:5px;">${escapeHtml(currentReq.description)}</div>
          </div>
        </div>
        <div style="flex:1; min-width:250px; display:flex; flex-direction:column; gap:15px;">
          <div style="background:var(--bg-surface); border:1px solid var(--border-color); border-radius:8px; padding:15px;">
            <div style="font-size:11px; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Personaje Solicitante</div>
            <div style="font-size:15px; font-weight:800; color:var(--text-primary); margin-top:3px;">${escapeHtml(currentReq.character_name)}</div>
            <div style="font-size:11px; color:var(--text-muted); font-weight:700; text-transform:uppercase; margin-top:15px;">Tipo de Acción</div>
            <div style="font-size:14px; font-weight:700; color:${typeColor}; margin-top:3px;">${isUpgrade ? 'Mejora de Rango' : 'Borrado de Inventario'}</div>
            <div style="font-size:11px; color:var(--text-muted); font-weight:700; text-transform:uppercase; margin-top:15px;">Cambio Aplicado</div>
            ${nextRankInfo}
          </div>
          <div style="background:var(--bg-surface); border:1px solid var(--border-color); border-radius:8px; padding:15px; display:flex; flex-direction:column; gap:12px;">
            <div style="font-size:11px; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Mensaje para el Jugador (Opcional)</div>
            <textarea id="staff-message-text" rows="3" style="width:100%; background:var(--bg-main); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); padding:10px; font-size:13px; resize:none;" placeholder="Escribe un comentario sobre esta resolución..."></textarea>
            <div style="display:flex; gap:10px; margin-top:5px;">
              <button onclick="resolveRequest('approve', this)" style="flex:1; background:linear-gradient(135deg,#10b981,#059669); color:#fff; border:none; padding:10px 15px; border-radius:6px; font-weight:700; font-size:13px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px;"><i class="fas fa-check"></i> Aprobar</button>
              <button onclick="resolveRequest('reject', this)" style="flex:1; background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff; border:none; padding:10px 15px; border-radius:6px; font-weight:700; font-size:13px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px;"><i class="fas fa-times"></i> Rechazar</button>
            </div>
          </div>
        </div>
      </div>
    `;
  } else if (isAddExisting) {
    const tags = JSON.parse(currentReq.tags_json || '[]');
    let tagsHtml = '';
    tags.forEach(t => { tagsHtml += `<span style="display:inline-block; font-size:9px; font-weight:700; padding:2px 8px; border:1px solid var(--border-color); border-radius:12px; color:var(--text-muted); text-transform:uppercase;">${escapeHtml(t)}</span>`; });
    
    let statsHtml = '';
    if (currentReq.cost_pe !== '—' || currentReq.execution_stat !== '' || currentReq.dice !== '') {
      statsHtml = `<div style="display:flex; gap:15px; margin:15px 0; background:var(--bg-main); padding:10px 15px; border-radius:8px; border:1px solid var(--border-color);">`;
      if (currentReq.cost_pe !== '—') statsHtml += `<div><span style="display:block; font-size:9px; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Coste PE</span><strong style="font-size:13px; color:var(--text-primary);">${currentReq.cost_pe}</strong></div>`;
      if (currentReq.execution_stat !== '') statsHtml += `<div><span style="display:block; font-size:9px; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Atributo</span><strong style="font-size:13px; color:var(--text-primary);">${currentReq.execution_stat}</strong></div>`;
      if (currentReq.dice !== '') statsHtml += `<div><span style="display:block; font-size:9px; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Dados</span><strong style="font-size:13px; color:var(--text-primary);">${currentReq.dice}</strong></div>`;
      statsHtml += `</div>`;
    }
    const cardImage = currentReq.image_url ? `<div style="width:100%; height:130px; background-image:url('${currentReq.image_url}'); background-size:cover; background-position:center; border-radius:6px; margin-bottom:12px;"></div>` : '';
    
    let approveBtn = '';
    if (staffLevel >= 3) {
      approveBtn = `<button onclick="resolveRequest('approve', this)" style="flex:1; background:linear-gradient(135deg,#10b981,#059669); color:#fff; border:none; padding:10px 15px; border-radius:6px; font-weight:700; font-size:13px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px;"><i class="fas fa-check"></i> Aprobar y Asignar</button>`;
    } else {
      approveBtn = `<div style="font-size:11px; color:var(--text-muted); text-align:center; padding:10px; background:var(--bg-main); border-radius:6px; width:100%;">Esperando aprobación final de Administrador (Nivel 3)</div>`;
    }

    preview.innerHTML = `
      <h2 style="font-family:var(--font-heading); font-size:18px; color:var(--text-primary); font-weight:800; display:flex; align-items:center; gap:8px; margin-bottom:15px;">
        <i class="fas ${typeIcon}" style="color:${typeColor};"></i> ${typeLabel}
      </h2>
      <div style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:20px;">
        <div class="rpg-card" style="width:230px; border:2px solid var(--border-color); border-radius:8px; background:var(--bg-card); overflow:hidden; flex-shrink:0;">
          <div style="padding:10px 15px; background:var(--bg-surface); border-bottom:1px solid var(--border-color);">
            <div style="font-weight:800; font-size:14px; color:var(--text-primary);">${escapeHtml(currentReq.card_name)}</div>
            <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; margin-top:2px;">[${currentReq.current_rank}] ${escapeHtml(currentReq.card_type.toUpperCase())}</div>
          </div>
          ${cardImage}
          <div style="padding:12px 15px;">
            <div style="display:flex; gap:5px; flex-wrap:wrap; margin-bottom:10px;">${tagsHtml}</div>
            ${statsHtml}
            <div style="font-size:12px; color:var(--text-secondary); line-height:1.5; height:120px; overflow-y:auto; padding-right:5px;">${escapeHtml(currentReq.description)}</div>
          </div>
        </div>
        <div style="flex:1; min-width:250px; display:flex; flex-direction:column; gap:15px;">
          <div style="background:var(--bg-surface); border:1px solid var(--border-color); border-radius:8px; padding:15px;">
            <div style="font-size:11px; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Personaje Solicitante</div>
            <div style="font-size:15px; font-weight:800; color:var(--text-primary); margin-top:3px;">${escapeHtml(currentReq.character_name)}</div>
            <div style="font-size:11px; color:var(--text-muted); font-weight:700; text-transform:uppercase; margin-top:15px;">Acción</div>
            <div style="font-size:14px; font-weight:700; color:${typeColor}; margin-top:3px;">Adición de Carta Existente</div>
          </div>
          ${chatHtml}
          <div style="background:var(--bg-surface); border:1px solid var(--border-color); border-radius:8px; padding:15px; display:flex; flex-direction:column; gap:12px;">
            <div style="font-size:11px; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Responder / Resolver</div>
            <textarea id="staff-message-text" rows="3" style="width:100%; background:var(--bg-main); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); padding:10px; font-size:13px; resize:none;" placeholder="Escribe un comentario en el hilo o justificación de resolución..."></textarea>
            <div style="display:flex; gap:10px; margin-top:5px; flex-wrap:wrap;">
              <button onclick="resolveRequest('reply', this)" style="flex:1; background:var(--accent-indigo); color:#fff; border:none; padding:10px 15px; border-radius:6px; font-weight:700; font-size:13px; cursor:pointer;"><i class="fas fa-reply"></i> Responder</button>
              ${approveBtn}
              <button onclick="resolveRequest('reject', this)" style="background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff; border:none; padding:10px 15px; border-radius:6px; font-weight:700; font-size:13px; cursor:pointer; display:flex; align-items:center; gap:6px;"><i class="fas fa-times"></i> Rechazar</button>
            </div>
          </div>
        </div>
      </div>
    `;
    setTimeout(() => {
      const container = document.getElementById('rpg-chat-messages-container');
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
    
    let approveBtn = '';
    if (staffLevel >= 3) {
      if (currentReq.status === 'conforme') {
        approveBtn = `<button onclick="resolveRequest('approve', this)" style="flex:1; background:linear-gradient(135deg,#10b981,#059669); color:#fff; border:none; padding:12px; border-radius:6px; font-weight:800; font-size:14px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 4px 15px rgba(16,185,129,0.3);"><i class="fas fa-check-double"></i> APROBAR Y CREAR CARTA</button>`;
      } else {
        approveBtn = `<div style="font-size:11px; color:var(--text-muted); text-align:center; padding:10px; background:var(--bg-main); border-radius:6px; width:100%;">Esperando conformidad del Jugador antes de la creación final.</div>`;
      }
    } else {
      approveBtn = `<div style="font-size:11px; color:var(--text-muted); text-align:center; padding:10px; background:var(--bg-main); border-radius:6px; width:100%;">Esperando conformidad del Jugador y aprobación final del Administrador (Nivel 3).</div>`;
    }

    preview.innerHTML = `
      <h2 style="font-family:var(--font-heading); font-size:18px; color:var(--text-primary); font-weight:800; display:flex; align-items:center; gap:8px; margin-bottom:15px;">
        <i class="fas ${typeIcon}" style="color:${typeColor};"></i> ${typeLabel}
      </h2>
      <div style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:20px;">
        
        <div style="flex:1.3; min-width:340px; background:var(--bg-main); border:1px solid var(--border-color); border-radius:8px; padding:20px; display:flex; flex-direction:column; gap:12px;">
          <strong style="font-size:11px; color:var(--text-muted); text-transform:uppercase; font-family:var(--font-heading); border-bottom:1px solid var(--border-color); padding-bottom:5px; display:block; margin-bottom: 12px;">Datos de Moderación de Carta</strong>
          
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <!-- FILA 1: Nombre + Tipo -->
                    <div>
                        <label class="rpg-form-label" style="font-size:11px; font-weight:700; color:var(--text-muted); display:block; margin-bottom:4px;">Nombre</label>
                        <input type="text" id="mod-name" class="textbox" value="${escapeHtml(details.name || '')}" required style="width: 100%;">
                    </div>
                    <div>
                        <label class="rpg-form-label" style="font-size:11px; font-weight:700; color:var(--text-muted); display:block; margin-bottom:4px;">Tipo</label>
                        <select id="mod-type" class="textbox" style="width: 100%;">
                            <option value="tecnica" ${details.card_type === 'tecnica' ? 'selected' : ''}>Técnica</option>
                            <option value="equipo" ${details.card_type === 'equipo' ? 'selected' : ''}>Equipo</option>
                            <option value="akuma_no_mi" ${details.card_type === 'akuma_no_mi' ? 'selected' : ''}>Akuma no Mi</option>
                            <option value="haki" ${details.card_type === 'haki' ? 'selected' : ''}>Haki</option>
                            <option value="npc_menor" ${details.card_type === 'npc_menor' ? 'selected' : ''}>NPC Menor</option>
                        </select>
                    </div>

                    <!-- FILA 2: Activación + Rango -->
                    <div>
                        <label class="rpg-form-label" style="font-size:11px; font-weight:700; color:var(--text-muted); display:block; margin-bottom:4px;">Activación</label>
                        <select id="mod-activation" class="textbox" style="width: 100%;">
                            <option value="activa" ${details.activation === 'activa' ? 'selected' : ''}>Activa</option>
                            <option value="pasiva" ${details.activation === 'pasiva' ? 'selected' : ''}>Pasiva</option>
                            <option value="reactiva" ${details.activation === 'reactiva' ? 'selected' : ''}>Reactiva</option>
                        </select>
                    </div>
                    <div>
                        <label class="rpg-form-label" style="font-size:11px; font-weight:700; color:var(--text-muted); display:block; margin-bottom:4px;">Rango</label>
                        <select id="mod-rank" class="textbox" style="width: 100%;">
                            <option value="C" ${details.rank === 'C' ? 'selected' : ''}>C (Común)</option>
                            <option value="B" ${details.rank === 'B' ? 'selected' : ''}>B (Poco común)</option>
                            <option value="A" ${details.rank === 'A' ? 'selected' : ''}>A (Raro)</option>
                            <option value="S" ${details.rank === 'S' ? 'selected' : ''}>S (Épico)</option>
                            <option value="SS" ${details.rank === 'SS' ? 'selected' : ''}>SS (Legendario)</option>
                        </select>
                    </div>

                    <!-- FILA 3: Tags -->
                    <div style="grid-column: 1 / -1;">
                        <label class="rpg-form-label" style="font-size:11px; font-weight:700; color:var(--text-muted); display:block; margin-bottom:4px;">Tags</label>
                        <div id="tag-selector">
                            <div id="tag-selected" style="display: flex; flex-wrap: wrap; gap: 4px; min-height: 28px; padding: 4px 0;"></div>
                            <div id="tag-dropdown" style="display: none; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-card); max-height: 320px; overflow-y: auto; margin-top: 8px;"></div>
                            <button type="button" id="tag-toggle-btn" class="rpg-action-btn rpg-btn-secondary" style="margin-top: 6px; padding: 4px 12px; font-size: 13px;">Seleccionar Tags</button>
                            <input type="hidden" id="mod-tags" value="">
                        </div>
                    </div>

                    <!-- FILA 4: Descripción -->
                    <div style="grid-column: 1 / -1;">
                        <label class="rpg-form-label" style="font-size:11px; font-weight:700; color:var(--text-muted); display:block; margin-bottom:4px;">Descripción / Efectos</label>
                        <textarea id="mod-desc" class="textbox" rows="3" style="width: 100%;">${escapeHtml(details.description || '')}</textarea>
                    </div>

                    <!-- FILA 5: Coste PE + Ejecución -->
                    <div>
                        <label class="rpg-form-label" style="font-size:11px; font-weight:700; color:var(--text-muted); display:block; margin-bottom:4px;">Coste PE</label>
                        <input type="text" id="mod-cost" class="textbox" placeholder="3 PE" value="${escapeHtml(details.cost_pe || '')}" style="width: 100%;">
                    </div>
                    <div>
                        <label class="rpg-form-label" style="font-size:11px; font-weight:700; color:var(--text-muted); display:block; margin-bottom:4px;">Ejecución</label>
                        <select id="mod-stat" class="textbox" style="width: 100%;">
                            <option value="" ${details.execution_stat === '' ? 'selected' : ''}>—</option>
                            <option value="FUE" ${details.execution_stat === 'FUE' ? 'selected' : ''}>FUE (Fuerza)</option>
                            <option value="AGI" ${details.execution_stat === 'AGI' ? 'selected' : ''}>AGI (Agilidad)</option>
                            <option value="DES" ${details.execution_stat === 'DES' ? 'selected' : ''}>DES (Destreza)</option>
                            <option value="INST" ${details.execution_stat === 'INST' ? 'selected' : ''}>INST (Instinto)</option>
                            <option value="ESP" ${details.execution_stat === 'ESP' ? 'selected' : ''}>ESP (Espíritu)</option>
                            <option value="INT" ${details.execution_stat === 'INT' ? 'selected' : ''}>INT (Inteligencia)</option>
                        </select>
                    </div>

                    <!-- FILA 6: Dados -->
                    <div style="grid-column: 1 / -1; border-top: 1px solid var(--border-color); padding-top: 12px;">
                        <label class="rpg-form-label" style="font-size:11px; font-weight:700; color:var(--text-muted); display:block; margin-bottom:4px;">Dados / Fórmula de daño</label>
                        <div id="dice-builder">
                            <div id="dice-groups"></div>
                            <div style="display: flex; gap: 8px; margin-top: 4px;">
                                <button type="button" id="dice-add-group" class="rpg-action-btn rpg-btn-secondary" style="padding: 2px 10px; font-size: 12px;">+ Añadir dados</button>
                                <button type="button" id="dice-add-arma" class="rpg-action-btn rpg-btn-secondary" style="padding: 2px 10px; font-size: 12px;">+ Añadir [ARMA]</button>
                                <button type="button" id="dice-add-municion" class="rpg-action-btn rpg-btn-secondary" style="padding: 2px 10px; font-size: 12px;">+ Añadir [MUNICION]</button>
                            </div>

                            <div style="display: flex; gap: 12px; margin-top: 8px; flex-wrap: wrap;">
                                <div>
                                    <label style="font-size: 0.8em; color: var(--text-secondary); display: block; margin-bottom: 2px;">Bonus fijo</label>
                                    <input type="number" id="dice-fixed" min="0" value="0" class="textbox" style="width: 70px; padding: 4px 8px !important; height: 28px; font-size: 12px; line-height: 20px;">
                                </div>
                                <div>
                                    <label style="font-size: 0.8em; color: var(--text-secondary); display: block; margin-bottom: 2px;">Stat</label>
                                    <select id="dice-stat" class="textbox" style="width: 90px; padding: 4px 20px 4px 8px !important; height: 28px; font-size: 12px; line-height: 20px; background-position: right 6px top 50% !important; background-size: 8px auto !important;">
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
                                    <label style="font-size: 0.8em; color: var(--text-secondary); display: block; margin-bottom: 2px;">Mult/Div</label>
                                    <input type="text" id="dice-stat-mod" class="textbox" placeholder="Ej: 2.5* o /2" style="width: 100px; padding: 4px 8px !important; height: 28px; font-size: 12px; line-height: 20px;">
                                </div>
                                <div>
                                    <label style="font-size: 0.8em; color: var(--text-secondary); display: block; margin-bottom: 2px;">Sufijo</label>
                                    <input type="text" id="dice-suffix" class="textbox" placeholder="[FUEGO]" style="width: 110px; padding: 4px 8px !important; height: 28px; font-size: 12px; line-height: 20px;">
                                </div>
                                <div style="display: flex; align-items: flex-end;">
                                    <div style="padding: 0 12px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); font-family: monospace; font-size: 0.95em; height: 28px; display: flex; align-items: center; box-shadow: var(--shadow-card);">
                                        <span style="font-size: 0.8em; color: var(--text-muted); margin-right: 6px;">→</span>
                                        <span id="dice-preview" style="color: var(--text-primary); font-weight: bold;">—</span>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" id="mod-dice" value="">
                        </div>
                    </div>

                    <!-- FILA 8: Reposo y Duración -->
                    <div style="grid-column: 1 / -1; border-top: 1px solid var(--border-color); padding-top: 12px; display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div>
                            <label class="rpg-form-label" style="font-size:11px; font-weight:700; color:var(--text-muted); display:block; margin-bottom:4px;">Turnos de Reposo</label>
                            <input type="number" id="mod-reposo" min="0" value="${parseInt(details.reposo) || 0}" class="textbox" style="width: 100%;">
                        </div>
                        <div>
                            <label class="rpg-form-label" style="font-size:11px; font-weight:700; color:var(--text-muted); display:block; margin-bottom:4px;">Duración (Turnos)</label>
                            <input type="number" id="mod-duracion" min="0" value="${parseInt(details.duracion) || 0}" class="textbox" style="width: 100%;">
                        </div>
                    </div>

                    <!-- FILA 9: Notas + URL Imagen -->
                    <div style="grid-column: 1 / -1;">
                        <label class="rpg-form-label" style="font-size:11px; font-weight:700; color:var(--text-muted); display:block; margin-bottom:4px;">URL Imagen</label>
                        <input type="text" id="mod-img" class="textbox" placeholder="https://..." value="${escapeHtml(details.image_url || '')}" style="width: 100%;">
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <label class="rpg-form-label" style="font-size:11px; font-weight:700; color:var(--text-muted); display:block; margin-bottom:4px;">Notas Internas / Upgrades</label>
                        <textarea id="mod-notes" class="textbox" rows="2" style="width: 100%;">${escapeHtml(details.notes || '')}</textarea>
                    </div>

          </div>
        </div>

        <div style="flex:1; min-width:340px; display:flex; flex-direction:column; gap:15px;">
          <div style="background:var(--bg-surface); border:1px solid var(--border-color); border-radius:8px; padding:15px;">
            <div style="font-size:11px; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Personaje Solicitante</div>
            <div style="font-size:15px; font-weight:800; color:var(--text-primary); margin-top:3px;">${escapeHtml(currentReq.character_name)}</div>
            <div style="font-size:11px; color:var(--text-muted); font-weight:700; text-transform:uppercase; margin-top:15px;">Estado de la Solicitud</div>
            <div style="font-size:14px; font-weight:800; color:var(--accent-indigo); margin-top:3px; text-transform:uppercase;">${currentReq.status}</div>
          </div>
          
          ${chatHtml}
          
          <div style="background:var(--bg-surface); border:1px solid var(--border-color); border-radius:8px; padding:15px; display:flex; flex-direction:column; gap:12px;">
            <div style="font-size:11px; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Comentario para el Jugador</div>
            <textarea id="staff-message-text" rows="3" style="width:100%; background:var(--bg-main); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); padding:10px; font-size:13px; resize:none;" placeholder="Escribe un mensaje aclaratorio en el hilo..."></textarea>
            
            <div style="display:flex; flex-direction:column; gap:10px; margin-top:5px;">
              <div style="display:flex; gap:8px;">
                <button onclick="resolveRequest('reply', this)" style="flex:1; background:var(--accent-indigo); color:#fff; border:none; padding:10px; border-radius:6px; font-weight:700; font-size:12px; cursor:pointer;"><i class="fas fa-reply"></i> Responder</button>
                <button onclick="saveModeration(this)" style="flex:1.2; background:linear-gradient(135deg,var(--accent-purple),#8b5cf6); color:#fff; border:none; padding:10px; border-radius:6px; font-weight:700; font-size:12px; cursor:pointer;"><i class="fas fa-save"></i> Guardar Moderación</button>
              </div>
              
              ${approveBtn}
              
              <button onclick="resolveRequest('reject', this)" style="width:100%; background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff; border:none; padding:10px; border-radius:6px; font-weight:700; font-size:12px; cursor:pointer;"><i class="fas fa-times"></i> Rechazar Petición</button>
            </div>
          </div>
        </div>

      </div>
    `;
    setTimeout(() => {
      const container = document.getElementById('rpg-chat-messages-container');
      if (container) container.scrollTop = container.scrollHeight;
      
      initModerationForm(details);
    }, 50);
  }
}

function saveModeration(btn) {
  if (!currentReq) return;
  const msg = document.getElementById('staff-message-text').value.trim();
  
  const details = {
    name: document.getElementById('mod-name').value.trim(),
    card_type: document.getElementById('mod-type').value,
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
    notes: document.getElementById('mod-notes').value.trim()
  };

  btn.disabled = true;
  const originalHtml = btn.innerHTML;
  btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Guardando...`;

  fetch(bburl + '/game/ajax/cards_resolve_request.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      request_id: currentReq.id,
      action: 'moderate',
      staff_message: msg,
      card_details: details
    })
  })
  .then(r => r.json())
  .then(res => {
    btn.disabled = false;
    btn.innerHTML = originalHtml;
    if (res.ok) {
      loadRequests();
      document.getElementById('request-preview').innerHTML = `<div style="text-align:center; color:var(--text-muted); padding:40px 20px;"><i class="fas fa-check-circle" style="font-size:48px; display:block; margin-bottom:15px; opacity:0.5; color:var(--accent-emerald);"></i>Datos de moderación guardados y enviados al jugador.</div>`;
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
  fetch(bburl + '/game/ajax/cards_resolve_request.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ request_id: currentReq.id, action: action, staff_message: msg })
  })
  .then(r => r.json())
  .then(res => {
    btn.disabled = false;
    btn.innerHTML = originalHtml;
    if (res.ok) {
      loadRequests();
      document.getElementById('request-preview').innerHTML = `<div style="text-align:center; color:var(--text-muted); padding:40px 20px;"><i class="fas fa-check-circle" style="font-size:48px; display:block; margin-bottom:15px; opacity:0.5; color:var(--accent-emerald);"></i>Solicitud procesada con éxito</div>`;
      currentReq = null;
    } else {
      alert('Error: ' + res.error.message);
    }
  })
  .catch(() => { btn.disabled = false; btn.innerHTML = originalHtml; alert('Error de conexión.'); });
}

// ─── BÚSQUEDAS ──────────────────────────────────────
var _busquedasLoaded = false;
function loadBusquedasPending(force = false) {
  if (_busquedasLoaded && !force) return;
  _busquedasLoaded = true;

  fetch(bburl + '/game/ajax/busquedas_pending.php')
    .then(r => r.json())
    .then(res => {
      var container = document.getElementById('busquedas-pending-list');
      if (!res.ok) {
        container.innerHTML = `<div style="text-align:center; color:var(--accent-rose); padding:30px;">${res.error}</div>`;
        return;
      }
      busquedasList = res.data;
      document.getElementById('tab-count-busquedas').textContent = res.data.length;
      document.getElementById('tab-count-busquedas').style.background = res.data.length > 0 ? 'var(--accent-rose)' : 'var(--text-muted)';
      if (!res.data || res.data.length === 0) {
        container.innerHTML = `<div style="text-align:center; padding:60px; color:var(--text-muted);"><i class="fas fa-check-circle fa-3x" style="color:var(--accent-emerald); margin-bottom:15px; display:block;"></i><strong>¡Todo al día!</strong><br>No hay búsquedas pendientes de revisión.</div>`;
        return;
      }
      var html = '<div style="display:flex; flex-direction:column; gap:12px;">';
      res.data.forEach(function(b) {
        html += `<div style="background:var(--bg-surface); border:1px solid var(--border-color); border-radius:10px; padding:18px 20px; display:flex; gap:18px; align-items:center; transition:border-color 0.2s;" onmouseover="this.style.borderColor='var(--accent-rose)'" onmouseout="this.style.borderColor='var(--border-color)'">
          ${b.imagen_url ? `<img src="${b.imagen_url}" style="width:75px; height:75px; object-fit:cover; border-radius:8px; flex-shrink:0;">` : `<div style="width:75px; height:75px; background:linear-gradient(135deg,var(--accent-rose),var(--accent-purple)); border-radius:8px; flex-shrink:0; display:flex; align-items:center; justify-content:center;"><i class="fas fa-search" style="color:#fff; font-size:24px;"></i></div>`}
          <div style="flex:1;">
            <div style="font-weight:800; font-size:16px; color:var(--text-primary); margin-bottom:4px;">${b.titulo}</div>
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;"><img src="${b.pj_avatar}" style="width:22px; height:22px; border-radius:50%; object-fit:cover;"><span style="font-size:12px; color:var(--text-secondary);">${b.pj_name} · ${b.date}</span></div>
            <div style="font-size:12px; color:var(--text-muted);">${b.descripcion.substring(0,100)}${b.descripcion.length > 100 ? '...' : ''}</div>
          </div>
          <button onclick="openBusquedaReview(${b.id})" style="background:linear-gradient(135deg,var(--accent-rose),var(--accent-purple)); color:#fff; border:none; border-radius:6px; padding:10px 16px; font-weight:700; cursor:pointer; flex-shrink:0; font-size:13px; transition:opacity 0.2s;" onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">Revisar</button>
        </div>`;
      });
      html += '</div>';
      container.innerHTML = html;
    });
}

function openBusquedaReview(id) {
  var b = busquedasList.find(function(x) { return x.id === id; });
  if (!b) return;
  document.getElementById('brm-id').value = b.id;
  document.getElementById('brm-titulo-text').textContent = b.titulo;
  document.getElementById('brm-desc').textContent = b.descripcion;
  document.getElementById('brm-pj').textContent = b.pj_name;
  document.getElementById('brm-date').textContent = b.date;
  document.getElementById('brm-avatar').src = b.pj_avatar;
  document.getElementById('brm-nota').value = '';
  var img = document.getElementById('brm-img');
  if (b.imagen_url) { img.src = b.imagen_url; img.style.display = 'block'; }
  else { img.style.display = 'none'; }
  document.getElementById('busqueda-review-modal').style.display = 'flex';
}

function closeBusquedaReview() {
  document.getElementById('busqueda-review-modal').style.display = 'none';
}

function accionBusqueda(accion) {
  var id   = document.getElementById('brm-id').value;
  var nota = document.getElementById('brm-nota').value;
  var fd   = new FormData();
  fd.append('id', id); fd.append('accion', accion); fd.append('nota', nota);
  fetch(bburl + '/game/ajax/busquedas_action.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.ok) {
        closeBusquedaReview();
        _busquedasLoaded = false;
        loadBusquedasPending();
      } else {
        alert('Error: ' + res.error);
      }
    });
}

function escapeHtml(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.appendChild(document.createTextNode(str));
  return div.innerHTML;
}

// Click outside review modal to close it
document.getElementById('busqueda-review-modal').addEventListener('click', function(e) {
  if (e.target === this) closeBusquedaReview();
});

// Robust DOM initialization that runs immediately if DOM is already parsed
function init() {
  loadRequests();
  loadBusquedasPending();
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
        group.style = 'border-bottom: 1px solid var(--border-color);';
        const header = document.createElement('div');
        header.textContent = cat.name;
        header.style = 'padding: 8px 12px; font-weight: bold; font-size: 0.85em; background: var(--bg-main); cursor: pointer; user-select: none; display: flex; align-items: center; gap: 6px;';
        header.innerHTML = '<span style="font-size: 0.7em; opacity: 0.5;">▸</span> ' + cat.name;
        header.addEventListener('click', () => {
            const body = header.nextElementSibling;
            const arrow = header.querySelector('span');
            body.style.display = body.style.display === 'none' ? 'flex' : 'none';
            arrow.textContent = body.style.display === 'none' ? '▸' : '▾';
        });
        group.appendChild(header);
        const body = document.createElement('div');
        body.style = 'display: none; flex-wrap: wrap; gap: 3px; padding: 6px 12px 10px;';
        cat.tags.forEach(tag => {
            const label = document.createElement('label');
            label.style = 'display: flex; align-items: center; gap: 3px; padding: 2px 7px; font-size: 0.8em; cursor: pointer; border-radius: 4px; background: var(--bg-card); border: 1px solid var(--border-color);';
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
        tagDropdown.style.display = tagDropdown.style.display === 'none' ? 'block' : 'none';
    });

    function updateTagDisplay() {
        tagSelected.innerHTML = '';
        selectedTags.forEach(tag => {
            const pill = document.createElement('span');
            pill.style = 'display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; border-radius: 12px; font-size: 0.8em; background: var(--accent-indigo); color: #fff;';
            pill.textContent = tag;
            const remove = document.createElement('span');
            remove.textContent = '×';
            remove.style = 'cursor: pointer; margin-left: 2px; font-weight: bold; font-size: 1.1em;';
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
        group.className = 'dice-group';
        group.style = 'display: inline-flex; align-items: center; gap: 6px; margin: 4px 8px 4px 0; padding: 6px 10px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md);';

        const qtyInput = document.createElement('input');
        qtyInput.type = 'number';
        qtyInput.className = 'dice-qty textbox';
        qtyInput.min = 1;
        qtyInput.max = 100;
        qtyInput.value = qty || 2;
        qtyInput.style = 'width: 60px; padding: 4px 6px !important; height: 28px; font-size: 12px; border-radius: 4px; line-height: 20px; box-shadow: none !important;';
        qtyInput.addEventListener('input', buildDiceFormula);

        const typeSelect = document.createElement('select');
        typeSelect.className = 'dice-type textbox';
        typeSelect.style = 'width: 80px; padding: 4px 20px 4px 8px !important; height: 28px; font-size: 12px; border-radius: 4px; background-position: right 6px top 50% !important; background-size: 8px auto !important; box-shadow: none !important;';
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
        removeBtn.style = 'background: none; border: none; color: var(--accent-rose); cursor: pointer; font-size: 16px; padding: 0 2px; line-height: 1;';
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
        group.className = 'dice-placeholder';
        group.style = 'display: inline-flex; align-items: center; gap: 6px; margin: 4px 8px 4px 0; padding: 6px 10px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); font-weight: bold; color: var(--accent-indigo);';

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
        removeBtn.style = 'background: none; border: none; color: var(--accent-rose); cursor: pointer; font-size: 16px; padding: 0 2px; line-height: 1;';
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
</script>
<?php
$content = ob_get_clean();
game_render_page("Peticiones", $content);
