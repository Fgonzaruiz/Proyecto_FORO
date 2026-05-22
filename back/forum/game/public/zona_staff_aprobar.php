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
    .then(function(r) {
      if (!r.ok) { throw new Error('HTTP ' + r.status); }
      return r.json();
    })
    .then(function(res) {
      if (!res.ok) { throw new Error(res.error?.message || 'Error del servidor'); }
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
    .then(function(r) {
      if (!r.ok) { throw new Error('HTTP ' + r.status); }
      return r.json();
    })
    .then(function(res) {
      if (!res.ok) { throw new Error(res.error?.message || 'Error del servidor'); }
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
  html += '<div class="aprobar-preview-avatar" style="background-image:url(' + avatarUrl + ');"></div>';

  // Name + badges row
  html += '<div class="aprobar-preview-body">';
  html += '  <h2 class="aprobar-preview-name">' + escapeHtml(data.name) + '</h2>';
  html += '  <div class="aprobar-preview-badges">';
  html += '    <span class="aprobar-preview-badge" style="color:' + cfg.color + ';border-color:' + cfg.color + ';"><i class="fas ' + cfg.icon + '"></i> ' + cfg.label + '</span>';
  if (data.rango) html += '    <span class="aprobar-preview-badge" style="color:var(--accent-purple);border-color:var(--accent-purple);"><i class="fas fa-medal"></i> ' + escapeHtml(data.rango) + '</span>';
  if (data.faction) html += '    <span class="aprobar-preview-badge" style="color:var(--accent-indigo);border-color:var(--accent-indigo);"><i class="fas fa-flag"></i> ' + escapeHtml(data.faction) + '</span>';
  if (data.is_staff) html += '    <span class="aprobar-preview-badge" style="color:#fff;background:var(--accent-indigo);border-color:var(--accent-indigo);"><i class="fas fa-star"></i> Staff</span>';
  html += '  </div>';

  // Left info box (arquetipo, oficio, genes)
  html += '  <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; background:var(--bg-card); border-radius:var(--radius-md); padding:15px; border:1px solid var(--border-color); margin-bottom:20px;">';
  html += '    <div style="display:flex; align-items:center; gap:10px;">';
  html += '      <i class="fas fa-fist-raised" style="color:var(--accent-indigo); font-size:18px;"></i>';
  html += '      <div><div style="font-size:10px; color:var(--text-muted); text-transform:uppercase; font-weight:bold;">Arquetipo Belico</div><div style="font-weight:700; color:var(--accent-indigo); font-size:13px;">' + escapeHtml(bio.arquetipo) + '</div></div>';
  html += '    </div>';
  html += '    <div style="display:flex; align-items:center; gap:10px;">';
  html += '      <i class="fas fa-briefcase" style="color:var(--accent-purple); font-size:18px;"></i>';
  html += '      <div><div style="font-size:10px; color:var(--text-muted); text-transform:uppercase; font-weight:bold;">Oficio</div><div style="font-weight:700; color:var(--accent-purple); font-size:13px;">' + escapeHtml(data.occupation_name || 'Ninguno') + '</div></div>';
  html += '    </div>';
  var geneNames = linaje.geneNames || [];
  var genesText = geneNames.length ? geneNames.slice(0, 3).join(', ') + (geneNames.length > 3 ? ' +' + (geneNames.length - 3) : '') : 'Ninguno';
  html += '    <div style="display:flex; align-items:center; gap:10px;">';
  html += '      <i class="fas fa-dna" style="color:var(--accent-purple); font-size:18px;"></i>';
  html += '      <div><div style="font-size:10px; color:var(--text-muted); text-transform:uppercase; font-weight:bold;">Genes Activos</div><div style="font-weight:700; color:var(--accent-purple); font-size:13px;">' + escapeHtml(genesText) + '</div></div>';
  html += '    </div>';
  html += '    <div style="display:flex; align-items:center; gap:10px;">';
  html += '      <i class="fas fa-user" style="color:var(--text-muted); font-size:18px;"></i>';
  html += '      <div><div style="font-size:10px; color:var(--text-muted); text-transform:uppercase; font-weight:bold;">Jugador</div><div style="font-weight:700; color:var(--text-primary); font-size:13px;">' + escapeHtml(data.username) + '</div></div>';
  html += '    </div>';
  html += '  </div>';

  // Stats bars
  html += '  <h3 style="font-size:12px; font-family:var(--font-heading); color:var(--text-muted); text-transform:uppercase; margin-bottom:10px;">Atributos Base</h3>';
  var statMeta = [
    { key: 'str', label: 'FUERZA', color: '#6366f1' },
    { key: 'agi', label: 'AGILIDAD', color: '#10b981' },
    { key: 'res', label: 'RESISTENCIA', color: '#f59e0b' },
    { key: 'vol', label: 'VOLUNTAD', color: '#ef4444' },
  ];
  statMeta.forEach(function(s) {
    var val = parseInt(stats[s.key] || 0);
    var pct = Math.min(100, val * 10);
    html += '  <div style="margin-bottom:12px;">';
    html += '    <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>' + s.label + '</span><span>' + val + '</span></div>';
    html += '    <div style="background:var(--bg-card); border-radius:10px; height:8px; width:100%; overflow:hidden; margin-top:4px;">';
    html += '      <div style="height:100%; background:linear-gradient(90deg,' + s.color + ',' + s.color + 'cc); border-radius:10px; width:' + pct + '%;"></div>';
    html += '    </div>';
    html += '  </div>';
  });

  // TABS: Bio, Linaje
  html += '  <div class="pj-preview-tabs" style="display:flex; border-bottom:2px solid var(--border-color); margin:24px 0;">';
  html += '    <div class="pj-preview-tab aprobar-tab active" data-tab="bio" onclick="switchAprobarTab(\'bio\', this)" style="padding:10px 20px; font-weight:700; font-size:14px; color:var(--accent-indigo); cursor:pointer; border-bottom:3px solid var(--accent-indigo); transition:all 0.2s;"><i class="fas fa-file-alt"></i> Biografia</div>';
  html += '    <div class="pj-preview-tab aprobar-tab" data-tab="linaje" onclick="switchAprobarTab(\'linaje\', this)" style="padding:10px 20px; font-weight:700; font-size:14px; color:var(--text-muted); cursor:pointer; border-bottom:3px solid transparent; transition:all 0.2s;"><i class="fas fa-dna"></i> Mapa Genetico</div>';
  html += '  </div>';

  // TAB: BIOGRAFIA
  html += '  <div id="aprobTab_bio" class="aprobar-tab-content" style="display:block;">';

  // Info grid
  html += '    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:30px; background:var(--bg-surface); padding:20px; border-radius:var(--radius-md); border:1px solid var(--border-color);">';
  html += '      <div style="font-size:14px;"><strong>Edad:</strong> ' + escapeHtml(bio.age) + '</div>';
  html += '      <div style="font-size:14px;"><strong>Origen:</strong> ' + escapeHtml(bio.origin) + '</div>';
  html += '      <div style="font-size:14px;"><strong>Raza:</strong> ' + escapeHtml(bio.race) + '</div>';
  html += '      <div style="font-size:14px;"><strong>PB:</strong> ' + escapeHtml(bio.pb) + '</div>';
  html += '    </div>';

  // Apariencia Fisica
  html += '    <h3 style="font-family:var(--font-heading); font-size:16px; color:var(--text-primary); margin-bottom:10px; border-bottom:1px solid var(--border-color); padding-bottom:5px;">Apariencia Fisica</h3>';
  html += '    <div class="aprobar-scroll-box">' + escapeHtml(bio.physique || 'Sin registrar.') + '</div>';

  // Perfil Psicologico
  html += '    <h3 style="font-family:var(--font-heading); font-size:16px; color:var(--text-primary); margin-bottom:10px; border-bottom:1px solid var(--border-color); padding-bottom:5px; margin-top:24px;">Perfil Psicologico</h3>';
  html += '    <div class="aprobar-scroll-box">' + escapeHtml(bio.psychology || bio.desc || 'Sin historia registrada.') + '</div>';

  // Extras
  html += '    <h3 style="font-family:var(--font-heading); font-size:16px; color:var(--text-primary); margin-bottom:10px; border-bottom:1px solid var(--border-color); padding-bottom:5px; margin-top:24px;">Extras y Notas</h3>';
  html += '    <div class="aprobar-scroll-box">' + escapeHtml(bio.extras || bio.details || 'Sin notas extras.') + '</div>';

  html += '  </div>';

  // TAB: LINAJE
  html += '  <div id="aprobTab_linaje" class="aprobar-tab-content" style="display:none;">';
  html += '    <p style="color:var(--text-muted); font-size:14px; margin-bottom:20px;">Genes desbloqueados en el Mapa Genetico de tu personaje.</p>';
  if (geneNames.length) {
    geneNames.forEach(function(g) {
      html += '    <div style="display:flex; align-items:center; gap:15px; padding:12px 15px; background:var(--bg-main); border:1px solid var(--border-color); border-radius:var(--radius-md); margin-bottom:10px;">';
      html += '      <div style="width:42px; height:42px; border-radius:50%; flex-shrink:0; background:linear-gradient(135deg,rgba(99,102,241,0.12),rgba(168,85,247,0.08)); border:2px solid var(--accent-indigo); display:flex; align-items:center; justify-content:center; color:var(--accent-indigo); font-size:16px;"><i class="fas fa-dna"></i></div>';
      html += '      <div style="flex:1;"><div style="font-weight:700; font-size:14px; color:var(--text-primary);">' + escapeHtml(g) + '</div><div style="font-size:12px; color:var(--text-muted);">Gen activo del mapa genetico.</div></div>';
      html += '    </div>';
    });
  } else {
    html += '    <div style="padding:30px; text-align:center; background:var(--bg-surface); border-radius:var(--radius-md); border:1px dashed var(--border-color);">';
    html += '      <i class="fas fa-dna" style="font-size:40px; color:var(--accent-purple); opacity:0.5; margin-bottom:15px;"></i>';
    html += '      <h4 style="color:var(--text-primary); margin-bottom:5px;">Sin Genes Extra</h4>';
    html += '      <p style="color:var(--text-muted); font-size:13px;">Este personaje no ha desarrollado genes mas alla de los basicos de su raza.</p>';
    html += '    </div>';
  }
  html += '  </div>';

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
  html += '    <p class="aprobar-moderate-desc">Escribe un mensaje para el jugador. Se le notificara junto con el cambio de estado.</p>';
  html += '    <textarea id="moderate-mensaje" class="aprobar-moderate-textarea" placeholder="Escribe tu mensaje aqui..."></textarea>';
  html += '    <div class="aprobar-moderate-actions">';
  html += '      <button class="pj-btn-add" onclick="toggleModerate()" style="background:var(--bg-main);color:var(--text-primary);border:1px solid var(--border-color)!important;box-shadow:none!important;">Cancelar</button>';
  html += '      <button class="pj-btn-add" onclick="enviarModeracion()"><i class="fas fa-paper-plane"></i> Enviar</button>';
  html += '    </div>';
  html += '  </div>';

  html += '</div>';

  document.getElementById('aprobar-preview').innerHTML = html;
}

function switchAprobarTab(tab, btn) {
  var tabs = document.querySelectorAll('.aprobar-tab');
  tabs.forEach(function(t) {
    t.style.color = 'var(--text-muted)';
    t.style.borderBottomColor = 'transparent';
  });
  btn.style.color = 'var(--accent-indigo)';
  btn.style.borderBottomColor = 'var(--accent-indigo)';

  var contents = document.querySelectorAll('.aprobar-tab-content');
  contents.forEach(function(c) { c.style.display = 'none'; });
  document.getElementById('aprobTab_' + tab).style.display = 'block';
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
  .catch(function(err) {
    alert('Error de red: ' + err.message);
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
</script>
<?php
$content = ob_get_clean();
game_render_page("Aprobar Personajes", $content);
