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

if ($staff_level < 1) {
    header('Location: ../index.php');
    exit;
}

$status_labels = [
    'pendiente' => ['label' => 'Sin Revisar', 'color' => '#ef4444', 'icon' => 'fa-clock'],
    'revision'  => ['label' => 'En Revisión', 'color' => '#f59e0b', 'icon' => 'fa-sync-alt'],
    'aprobada'  => ['label' => 'Aprobada', 'color' => '#10b981', 'icon' => 'fa-check-circle'],
    'rechazada' => ['label' => 'Rechazada', 'color' => '#ef4444', 'icon' => 'fa-times-circle'],
];

$b_url = $mybb->settings['bburl'];

ob_start();
?>
<div class="rpg-staff-zone">
  <div class="rpg-staff-header" style="background: linear-gradient(135deg, rgba(16,185,129,0.15), rgba(99,102,241,0.1));">
    <div class="rpg-staff-header-content">
      <h1><i class="fas fa-user-check"></i> Aprobar Personajes</h1>
      <p>Revisa las fichas de personaje pendientes de aprobaci&oacute;n. <strong><?= htmlspecialchars($pj_name) ?></strong></p>
    </div>
  </div>

  <!-- Filter Bar -->
  <div class="aprobar-filter-bar">
    <button class="aprobar-filter-btn active" data-filter="">Todos</button>
    <button class="aprobar-filter-btn" data-filter="pendiente" style="color:#ef4444;">Sin Revisar</button>
    <button class="aprobar-filter-btn" data-filter="revision" style="color:#f59e0b;">En Revisión</button>
    <button class="aprobar-filter-btn" data-filter="aprobada" style="color:#10b981;">Aprobadas</button>
    <button class="aprobar-filter-btn" data-filter="rechazada" style="color:#ef4444;">Rechazadas</button>
  </div>

  <div class="aprobar-layout">
    <!-- LEFT: Character List -->
    <div class="aprobar-list" id="aprobar-list">
      <div class="aprobar-list-header">
        <span>Personajes</span>
        <span class="aprobar-count" id="aprobar-count">0</span>
      </div>
      <div id="aprobar-list-items">
        <div class="aprobar-empty">Cargando...</div>
      </div>
    </div>

    <!-- RIGHT: Preview Panel -->
    <div class="aprobar-preview" id="aprobar-preview">
      <div class="aprobar-empty" style="padding:60px 20px; text-align:center; color:var(--text-muted);">
        <i class="fas fa-user-check" style="font-size:48px; display:block; margin-bottom:15px; opacity:0.3;"></i>
        Selecciona un personaje para revisar su ficha
      </div>
    </div>
  </div>
</div>



<script>
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
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (!res.ok) { document.getElementById('aprobar-list-items').innerHTML = '<div class="aprobar-empty">Error al cargar</div>'; return; }
      renderList(res.data);
    })
    .catch(function() {
      document.getElementById('aprobar-list-items').innerHTML = '<div class="aprobar-empty">Error de conexi&oacute;n</div>';
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
    html += '  <div class="aprobar-list-item-avatar" style="background-image:url(' + avatarUrl + ');"></div>';
    html += '  <div class="aprobar-list-item-body">';
    html += '    <div class="aprobar-list-item-name">' + escapeHtml(c.name) + '</div>';
    html += '    <div class="aprobar-list-item-user">' + escapeHtml(c.username) + '</div>';
    html += '    <span class="aprobar-list-item-status" style="color:' + cfg.color + ';"><i class="fas ' + cfg.icon + '"></i> ' + cfg.label + '</span>';
    html += '  </div>';
    html += '</div>';
  });
  container.innerHTML = html;
}

function selectChar(id) {
  // Highlight selected
  var items = document.querySelectorAll('.aprobar-list-item');
  items.forEach(function(item) {
    item.classList.toggle('selected', parseInt(item.getAttribute('data-id')) === id);
  });

  // Fetch preview
  var preview = document.getElementById('aprobar-preview');
  preview.innerHTML = '<div class="aprobar-empty" style="padding:40px;"><i class="fas fa-spinner fa-spin" style="font-size:24px;"></i><br>Cargando ficha...</div>';

  var url = '<?= $b_url ?>/game/ajax/get_personaje_preview.php?pj=' + id;
  fetch(url)
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (!res.ok) {
        preview.innerHTML = '<div class="aprobar-empty">Error al cargar la ficha</div>';
        return;
      }
      renderPreview(res.data);
      currentPJ = res.data;
    })
    .catch(function() {
      preview.innerHTML = '<div class="aprobar-empty">Error de conexi&oacute;n</div>';
    });
}

function renderPreview(data) {
  var cfg = statusConfig[data.status] || { label: data.status, color: '#94a3b8', icon: 'fa-question' };
  var avatarUrl = data.avatar || 'https://placehold.co/290x450';
  var stats = data.stats || {};
  var info = data.info || {};

  var html = '';
  // Avatar section
  html += '<div class="aprobar-preview-avatar" style="background-image:url(' + avatarUrl + ');"></div>';

  // Main info
  html += '<div class="aprobar-preview-body">';
  html += '  <h2 class="aprobar-preview-name">' + escapeHtml(data.name) + '</h2>';

  // Badges
  html += '  <div class="aprobar-preview-badges">';
  html += '    <span class="aprobar-preview-badge" style="color:' + cfg.color + ';border-color:' + cfg.color + ';"><i class="fas ' + cfg.icon + '"></i> ' + cfg.label + '</span>';
  if (data.rango) html += '    <span class="aprobar-preview-badge" style="color:var(--accent-purple);border-color:var(--accent-purple);"><i class="fas fa-medal"></i> ' + escapeHtml(data.rango) + '</span>';
  if (data.faction) html += '    <span class="aprobar-preview-badge" style="color:var(--accent-indigo);border-color:var(--accent-indigo);"><i class="fas fa-flag"></i> ' + escapeHtml(data.faction) + '</span>';
  html += '  </div>';

  // Info grid
  html += '  <div class="aprobar-preview-grid">';
  html += '    <div class="aprobar-preview-field"><span class="aprobar-preview-field-label">Jugador</span><span class="aprobar-preview-field-value">' + escapeHtml(data.username) + '</span></div>';
  if (info.race) html += '    <div class="aprobar-preview-field"><span class="aprobar-preview-field-label">Raza</span><span class="aprobar-preview-field-value">' + escapeHtml(info.race) + '</span></div>';
  if (info.edad) html += '    <div class="aprobar-preview-field"><span class="aprobar-preview-field-label">Edad</span><span class="aprobar-preview-field-value">' + escapeHtml(info.edad) + '</span></div>';
  if (info.origen) html += '    <div class="aprobar-preview-field"><span class="aprobar-preview-field-label">Origen</span><span class="aprobar-preview-field-value">' + escapeHtml(info.origen) + '</span></div>';
  if (info.arquetipo) html += '    <div class="aprobar-preview-field"><span class="aprobar-preview-field-label">Arquetipo</span><span class="aprobar-preview-field-value">' + escapeHtml(info.arquetipo) + '</span></div>';
  if (data.occupation_name) html += '    <div class="aprobar-preview-field"><span class="aprobar-preview-field-label">Oficio</span><span class="aprobar-preview-field-value">' + escapeHtml(data.occupation_name) + '</span></div>';
  html += '  </div>';

  // Stats
  html += '  <div class="aprobar-preview-stats">';
  html += '    <div class="aprobar-preview-stats-title">Estad&iacute;sticas</div>';
  var statLabels = { 'FUE': 'FUE', 'AGI': 'AGI', 'RES': 'RES', 'VOL': 'VOL' };
  var statColors = { 'FUE': '#ef4444', 'AGI': '#f59e0b', 'RES': '#10b981', 'VOL': '#6366f1' };
  ['FUE', 'AGI', 'RES', 'VOL'].forEach(function(key) {
    var val = parseInt(stats[key] || 0);
    var maxVal = 120;
    var pct = Math.min(100, (val / maxVal) * 100);
    html += '    <div class="aprobar-preview-stat">';
    html += '      <span class="aprobar-preview-stat-label">' + key + '</span>';
    html += '      <div class="aprobar-preview-stat-bar"><div class="aprobar-preview-stat-fill" style="width:' + pct + '%;background:' + (statColors[key] || '#6366f1') + ';"></div></div>';
    html += '      <span class="aprobar-preview-stat-value">' + val + '</span>';
    html += '    </div>';
  });
  html += '  </div>';

  // Description
  if (info.desc) {
    html += '  <div class="aprobar-preview-section">';
    html += '    <div class="aprobar-preview-section-title"><i class="fas fa-align-left"></i> Descripci&oacute;n</div>';
    html += '    <div class="aprobar-preview-text">' + escapeHtml(info.desc) + '</div>';
    html += '  </div>';
  }

  // Details
  if (info.details) {
    html += '  <div class="aprobar-preview-section">';
    html += '    <div class="aprobar-preview-section-title"><i class="fas fa-info-circle"></i> Detalles</div>';
    html += '    <div class="aprobar-preview-text">' + escapeHtml(info.details) + '</div>';
    html += '  </div>';
  }

  // Actions
  html += '  <div class="aprobar-preview-actions" id="aprobar-actions">';
  if (data.status !== 'aprobada') {
    html += '    <button class="pj-btn-add" onclick="accionAprobar(' + data.id + ',\'aprobar\')" style="background:linear-gradient(135deg,#10b981,#059669) !important;"><i class="fas fa-check"></i> Aprobar</button>';
  }
  html += '    <button class="pj-btn-add" onclick="openModerar(' + data.id + ',\'' + data.status + '\')"><i class="fas fa-comment-dots"></i> Moderar</button>';
  if (data.status !== 'pendiente') {
    html += '    <button class="pj-btn-add" onclick="accionAprobar(' + data.id + ',\'pendiente\')" style="background:linear-gradient(135deg,#f59e0b,#d97706) !important;"><i class="fas fa-undo"></i> Volver a Pendiente</button>';
  }
  if (data.status !== 'rechazada') {
    html += '    <button class="pj-btn-add" onclick="accionAprobar(' + data.id + ',\'rechazar\')" style="background:linear-gradient(135deg,#ef4444,#dc2626) !important;"><i class="fas fa-times"></i> Rechazar</button>';
  }
      html += '  </div>';

  // Inline moderate section (hidden)
  html += '  <div class="aprobar-moderate" id="aprobar-moderate" style="display:none;">';
  html += '    <div class="aprobar-moderate-title"><i class="fas fa-comment-dots"></i> Mensaje al Jugador</div>';
  html += '    <p class="aprobar-moderate-desc">Escribe un mensaje para el jugador. Se le notificar&aacute; junto con el cambio de estado.</p>';
  html += '    <textarea id="moderate-mensaje" class="aprobar-moderate-textarea" placeholder="Escribe tu mensaje aqu&iacute;..."></textarea>';
  html += '    <div class="aprobar-moderate-actions">';
  html += '      <button class="pj-btn-add" onclick="toggleModerate()" style="background:var(--bg-main);color:var(--text-primary);border:1px solid var(--border-color)!important;box-shadow:none!important;">Cancelar</button>';
  html += '      <button class="pj-btn-add" onclick="enviarModeracion()"><i class="fas fa-paper-plane"></i> Enviar</button>';
  html += '    </div>';
  html += '  </div>';

  html += '</div>';

  document.getElementById('aprobar-preview').innerHTML = html;
}

function accionAprobar(personajeId, action) {
  var btn = event && event.currentTarget ? event.currentTarget : document.querySelector('#aprobar-actions button');
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...'; }

  fetch('<?= $b_url ?>/game/ajax/aprobar_personaje.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ personaje_id: personajeId, action: action })
  })
  .then(function(r) { return r.json(); })
  .then(function(res) {
    if (res.ok) {
      loadList(currentFilter);
      selectChar(personajeId);
    } else {
      alert('Error: ' + (res.error && res.error.message ? res.error.message : 'Desconocido'));
    }
  })
  .catch(function() {
    alert('Error de conexi\u00f3n');
  });
}

var currentModeratingId = null;

function openModerar(personajeId, statusActual) {
  currentModeratingId = personajeId;
  var el = document.getElementById('aprobar-moderate');
  el.style.display = 'block';
  document.getElementById('moderate-mensaje').value = '';
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function toggleModerate() {
  var el = document.getElementById('aprobar-moderate');
  el.style.display = el.style.display === 'block' ? 'none' : 'block';
}

function enviarModeracion() {
  var mensaje = document.getElementById('moderate-mensaje').value.trim();
  if (!mensaje) {
    alert('Escribe un mensaje para el jugador.');
    return;
  }

  var btn = event && event.currentTarget ? event.currentTarget : null;
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...'; }

  fetch('<?= $b_url ?>/game/ajax/aprobar_personaje.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ personaje_id: currentModeratingId, action: 'revision', mensaje: mensaje })
  })
  .then(function(r) { return r.json(); })
  .then(function(res) {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar'; }
    document.getElementById('aprobar-moderate').style.display = 'none';
    if (res.ok) {
      loadList(currentFilter);
      selectChar(currentModeratingId);
    } else {
      alert('Error: ' + (res.error && res.error.message ? res.error.message : 'Desconocido'));
    }
  })
  .catch(function() {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar'; }
    alert('Error de conexi\u00f3n');
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
</script>
<?php
$content = ob_get_clean();
game_render_page("Aprobar Personajes", $content);
