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
      <h1><i class="fas fa-clipboard-check"></i> Peticiones de Cartas</h1>
      <p>Revisa y resuelve las solicitudes de mejora o borrado de cartas enviadas por los jugadores.</p>
    </div>
  </div>

  <div class="aprobar-layout" style="display:flex; gap:20px; margin-top:20px;">
    <!-- LEFT: Requests List -->
    <div class="aprobar-list" id="requests-list" style="width:320px; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:var(--radius-lg); flex-shrink:0; display:flex; flex-direction:column; overflow:hidden; box-shadow:var(--shadow-card);">
      <div class="aprobar-list-header" style="padding:15px; border-bottom:1px solid var(--border-color); background:var(--bg-surface); font-weight:700; display:flex; justify-content:space-between; align-items:center;">
        <span>Solicitudes Pendientes</span>
        <span class="aprobar-count" id="requests-count" style="background:var(--accent-indigo); color:#fff; padding:2px 8px; border-radius:10px; font-size:11px;">0</span>
      </div>
      <div id="requests-list-items" style="flex:1; overflow-y:auto; max-height:600px;">
        <div class="aprobar-empty" style="padding:20px; text-align:center; color:var(--text-muted);">Cargando...</div>
      </div>
    </div>

    <!-- RIGHT: Preview Panel -->
    <div class="aprobar-preview" id="request-preview" style="flex:1; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:var(--radius-lg); overflow:hidden; box-shadow:var(--shadow-card); min-height:500px; display:flex; flex-direction:column; justify-content:center; align-items:center; padding:40px 20px; text-align:center; color:var(--text-muted);">
      <i class="fas fa-clipboard-list" style="font-size:48px; display:block; margin-bottom:15px; opacity:0.3; color:var(--accent-purple);"></i>
      Selecciona una solicitud para procesarla
    </div>
  </div>
</div>

<script>
var allRequests = [];
var currentReq = null;

function loadRequests() {
  fetch('<?= $b_url ?>/game/ajax/cards_pending_requests.php')
    .then(r => r.json())
    .then(res => {
      if (!res.ok) {
        document.getElementById('requests-list-items').innerHTML = `<div style="padding:20px; color:var(--accent-rose); text-align:center;">Error: ${res.error.message}</div>`;
        return;
      }
      allRequests = res.data;
      renderList(res.data);
    })
    .catch(err => {
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
    const isUpgrade = req.request_type === 'upgrade';
    const typeLabel = isUpgrade ? 'MEJORA' : 'BORRADO';
    const typeBg = isUpgrade ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)';
    const typeColor = isUpgrade ? '#10b981' : '#ef4444';
    const avatar = req.character_avatar || 'https://placehold.co/100x100';
    
    html += `
      <div class="aprobar-list-item request-item" data-id="${req.id}" onclick="selectRequest(${req.id})" style="display:flex; gap:12px; padding:15px; border-bottom:1px solid var(--border-color); cursor:pointer; transition:background 0.2s;">
        <div style="width:45px; height:45px; border-radius:50%; background-image:url('${avatar}'); background-size:cover; background-position:center; flex-shrink:0; border:2px solid var(--border-color);"></div>
        <div style="flex:1; min-width:0;">
          <div style="font-weight:700; color:var(--text-primary); font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(req.character_name)}</div>
          <div style="font-size:12px; color:var(--text-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px;">Carta: ${escapeHtml(req.card_name)}</div>
          <span style="display:inline-block; font-size:9px; font-weight:800; padding:2px 6px; border-radius:4px; margin-top:5px; background:${typeBg}; color:${typeColor}; border:1px solid ${typeColor}20;">${typeLabel}</span>
        </div>
      </div>
    `;
  });
  
  container.innerHTML = html;
}

function selectRequest(id) {
  const items = document.querySelectorAll('.request-item');
  items.forEach(el => {
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
  const typeLabel = isUpgrade ? 'Solicitud de Mejora de Carta' : 'Solicitud de Borrado de Carta';
  const typeIcon = isUpgrade ? 'fa-arrow-up-long' : 'fa-trash-can';
  const typeColor = isUpgrade ? '#10b981' : '#ef4444';
  
  let nextRankInfo = '';
  if (isUpgrade) {
    const ranks = ['C', 'B', 'A', 'S'];
    const idx = ranks.indexOf(currentReq.current_rank);
    const nextRank = idx !== -1 && idx < ranks.length - 1 ? ranks[idx + 1] : 'S';
    nextRankInfo = `
      <div style="display:flex; align-items:center; gap:10px; margin-top:8px; font-size:14px; font-weight:700;">
        <span style="background:var(--bg-main); border:1px solid var(--border-color); padding:4px 10px; border-radius:4px; color:var(--text-muted);">${currentReq.current_rank}</span>
        <i class="fas fa-arrow-right" style="color:var(--text-muted);"></i>
        <span style="background:rgba(16,185,129,0.1); border:1px solid #10b98120; padding:4px 10px; border-radius:4px; color:#10b981;">${nextRank}</span>
      </div>
    `;
  } else {
    nextRankInfo = `
      <div style="font-size:13px; margin-top:8px; color:var(--text-muted);">La carta será desvinculada del inventario del personaje.</div>
    `;
  }
  
  // Format tags, cost, etc.
  const tags = JSON.parse(currentReq.tags_json || '[]');
  const cleanedTags = tags.map(t => t.replace(/[\[\]]/g, '').trim().toUpperCase()).filter(Boolean);
  let tagsHtml = '';
  cleanedTags.forEach(t => {
    tagsHtml += `<span style="display:inline-block; font-size:9px; font-weight:700; padding:2px 8px; border:1px solid var(--border-color); border-radius:12px; color:var(--text-muted); text-transform:uppercase;">${t}</span>`;
  });
  
  let statsHtml = '';
  if (currentReq.cost_pe !== '—' || currentReq.execution_stat !== '' || currentReq.dice !== '') {
    statsHtml = `<div style="display:flex; gap:15px; margin: 15px 0; background:var(--bg-main); padding:10px 15px; border-radius:8px; border:1px solid var(--border-color);">`;
    if (currentReq.cost_pe !== '—') statsHtml += `<div><span style="display:block; font-size:9px; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Coste PE</span><strong style="font-size:13px; color:var(--text-primary);">${currentReq.cost_pe}</strong></div>`;
    if (currentReq.execution_stat !== '') statsHtml += `<div><span style="display:block; font-size:9px; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Atributo</span><strong style="font-size:13px; color:var(--text-primary);">${currentReq.execution_stat}</strong></div>`;
    if (currentReq.dice !== '') statsHtml += `<div><span style="display:block; font-size:9px; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Dados</span><strong style="font-size:13px; color:var(--text-primary);">${currentReq.dice}</strong></div>`;
    statsHtml += `</div>`;
  }

  const cardImage = currentReq.image_url ? `<div style="width:100%; height:130px; background-image:url('${currentReq.image_url}'); background-size:cover; background-position:center; border-radius:6px; margin-bottom:12px;"></div>` : '';

  let html = `
    <h2 style="font-family:var(--font-heading); font-size:18px; color:var(--text-primary); font-weight:800; display:flex; align-items:center; gap:8px; margin-bottom:15px;">
      <i class="fas ${typeIcon}" style="color:${typeColor};"></i> ${typeLabel}
    </h2>
    
    <div style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:20px;">
      <!-- Card Display -->
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
      
      <!-- Solicitude Data -->
      <div style="flex:1; min-width:250px; display:flex; flex-direction:column; gap:15px;">
        <div style="background:var(--bg-surface); border:1px solid var(--border-color); border-radius:8px; padding:15px;">
          <div style="font-size:11px; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Personaje Solicitante</div>
          <div style="font-size:15px; font-weight:800; color:var(--text-primary); margin-top:3px;">${escapeHtml(currentReq.character_name)}</div>
          
          <div style="font-size:11px; color:var(--text-muted); font-weight:700; text-transform:uppercase; margin-top:15px;">Tipo de Acción</div>
          <div style="font-size:14px; font-weight:700; color:${typeColor}; margin-top:3px;">
            ${isUpgrade ? 'Mejora de Rango' : 'Borrado de Inventario'}
          </div>
          
          <div style="font-size:11px; color:var(--text-muted); font-weight:700; text-transform:uppercase; margin-top:15px;">Cambio Aplicado</div>
          ${nextRankInfo}
        </div>
        
        <!-- Resolution Form -->
        <div style="background:var(--bg-surface); border:1px solid var(--border-color); border-radius:8px; padding:15px; display:flex; flex-direction:column; gap:12px;">
          <div style="font-size:11px; color:var(--text-muted); font-weight:700; text-transform:uppercase;">Mensaje para el Jugador (Opcional)</div>
          <textarea id="staff-message-text" rows="3" style="width:100%; background:var(--bg-main); border:1px solid var(--border-color); border-radius:6px; color:var(--text-primary); padding:10px; font-size:13px; resize:none;" placeholder="Escribe un comentario sobre esta resolución (se le enviará por notificación)..."></textarea>
          
          <div style="display:flex; gap:10px; margin-top:5px;">
            <button onclick="resolveRequest('approve', this)" style="flex:1; background:linear-gradient(135deg,#10b981,#059669); color:#fff; border:none; padding:10px 15px; border-radius:6px; font-weight:700; font-size:13px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px;"><i class="fas fa-check"></i> Aprobar</button>
            <button onclick="resolveRequest('reject', this)" style="flex:1; background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff; border:none; padding:10px 15px; border-radius:6px; font-weight:700; font-size:13px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px;"><i class="fas fa-times"></i> Rechazar</button>
          </div>
        </div>
      </div>
    </div>
  `;
  
  preview.innerHTML = html;
}

function resolveRequest(action, btn) {
  if (!currentReq) return;
  const msg = document.getElementById('staff-message-text').value.trim();
  
  btn.disabled = true;
  const originalHtml = btn.innerHTML;
  btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ...`;
  
  fetch('<?= $b_url ?>/game/ajax/cards_resolve_request.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      request_id: currentReq.id,
      action: action,
      staff_message: msg
    })
  })
  .then(r => r.json())
  .then(res => {
    btn.disabled = false;
    btn.innerHTML = originalHtml;
    
    if (res.ok) {
      loadRequests();
      // Reset preview
      document.getElementById('request-preview').innerHTML = `
        <div class="aprobar-preview" style="text-align:center; color:var(--text-muted); padding:40px 20px;">
          <i class="fas fa-check-circle" style="font-size:48px; display:block; margin-bottom:15px; opacity:0.5; color:var(--accent-emerald);"></i>
          Solicitud procesada con éxito
        </div>
      `;
      currentReq = null;
    } else {
      alert('Error: ' + res.error.message);
    }
  })
  .catch(err => {
    btn.disabled = false;
    btn.innerHTML = originalHtml;
    alert('Error de conexión.');
  });
}

function escapeHtml(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.appendChild(document.createTextNode(str));
  return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', () => {
  loadRequests();
});
</script>
<?php
$content = ob_get_clean();
game_render_page("Peticiones de Cartas", $content);
