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

// Load central catalog
$catalog_path = __DIR__ . '/../data/linaje_system.json';
$catalog_json = '{}';
if (file_exists($catalog_path)) {
    $catalog_json = file_get_contents($catalog_path);
}

ob_start();
?>
<style>
/* New Perk-based Linaje Styles */
.gene-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}
.gene-card {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    padding: 16px;
    background: var(--bg-main);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
    text-align: left;
}
.gene-card:hover { border-color: rgba(99,102,241,0.4); transform: translateX(3px); }
.gene-card.passive-primary { border-left: 3px solid #10b981; }
.gene-card.passive-secondary { border-left: 3px solid #f59e0b; }
.gene-card.perk-racial { border-left: 3px solid var(--accent-indigo); }
.gene-card.perk-general { border-left: 3px solid var(--accent-purple); }
.gene-card-icon { width: 44px; height: 44px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.gene-card-info { flex: 1; display: flex; flex-direction: column; }
.gene-card-name { font-weight: 800; font-size: 13px; color: var(--text-primary); margin-bottom: 2px; font-family: var(--font-heading); text-transform: uppercase; letter-spacing: 0.5px; }
.gene-card-desc { font-size: 11px; color: var(--text-muted); line-height: 1.4; margin-top: 4px; margin-bottom: 6px; }
.gene-card-badge {
    align-self: flex-start;
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 2px 8px;
    border-radius: 10px;
    flex-shrink: 0;
}
</style>
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
// ==================== LINAJE PERK SYSTEM CATALOG ===================
var LINAJE_DATA = <?php echo $catalog_json; ?>;
function enrichPerk(p) {
    if (!p) return p;
    if (p.icon && p.iconColor) return p;
    var icon = 'fa-dna';
    var iconColor = '#6366f1';
    var id = p.id || '';
    if (id.startsWith('pp_')) { p.icon = 'fa-shield-alt'; p.iconColor = '#10b981'; return p; }
    if (id.startsWith('ps_')) { p.icon = 'fa-crown'; p.iconColor = '#f59e0b'; return p; }
    if (id.startsWith('g_linaje_fuego')) { icon = 'fa-fire'; iconColor = '#ef4444'; }
    else if (id.startsWith('g_linaje_rayo')) { icon = 'fa-bolt'; iconColor = '#eab308'; }
    else if (id.startsWith('g_linaje_hielo')) { icon = 'fa-snowflake'; iconColor = '#06b6d4'; }
    else if (id.startsWith('g_linaje_viento')) { icon = 'fa-wind'; iconColor = '#a855f7'; }
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
    else if (id.startsWith('g_voluntad_ferrea')) { icon = 'fa-fingerprint'; iconColor = '#6366f1'; }
    else if (id.startsWith('g_instinto')) { icon = 'fa-compass'; iconColor = '#8b5cf6'; }
    else if (id.startsWith('g_paso') || id.startsWith('g_sombra')) { icon = 'fa-user-ninja'; iconColor = '#475569'; }
    else if (id.startsWith('g_agilidad')) { icon = 'fa-running'; iconColor = '#10b981'; }
    else if (id.startsWith('g_evasion')) { icon = 'fa-wind'; iconColor = '#06b6d4'; }
    else if (id.startsWith('g_parkour')) { icon = 'fa-shoe-prints'; iconColor = '#f59e0b'; }
    else if (id.startsWith('g_haki_obs')) { icon = 'fa-eye'; iconColor = '#6366f1'; }
    else if (id.startsWith('g_haki_arm')) { icon = 'fa-shield-alt'; iconColor = '#6b7280'; }
    else if (id.startsWith('g_haki_conq')) { icon = 'fa-crown'; iconColor = '#db2777'; }
    else if (id.startsWith('g_suerte') || id.startsWith('g_golpe') || id.startsWith('g_fortuna')) { icon = 'fa-dice-d20'; iconColor = '#f59e0b'; }
    else if (id.startsWith('g_carisma') || id.startsWith('g_presencia') || id.startsWith('g_inspiracion') || id.startsWith('g_nombre_temido') || id.startsWith('g_voz_rey')) { icon = 'fa-comments'; iconColor = '#ec4899'; }
    else if (id.startsWith('g_manos_') || id.startsWith('g_dedos_') || id.startsWith('g_ojo_') || id.startsWith('g_genio_') || id.startsWith('g_cocinero_')) { icon = 'fa-tools'; iconColor = '#06b6d4'; }
    else if (id.startsWith('g_cuatro_brazos')) { icon = 'fa-hand-paper'; iconColor = '#3b82f6'; }
    else if (id.startsWith('g_tercer_ojo')) { icon = 'fa-eye'; iconColor = '#a855f7'; }
    else if (id.startsWith('g_sangre_fria')) { icon = 'fa-snowflake'; iconColor = '#06b6d4'; }
    else if (id.startsWith('g_linaje_marino')) { icon = 'fa-anchor'; iconColor = '#3b82f6'; }
    else if (id.startsWith('g_gula')) { icon = 'fa-cookie-bite'; iconColor = '#b45309'; }
    else if (id.startsWith('g_pelo')) { icon = 'fa-magic'; iconColor = '#db2777'; }
    else if (id.startsWith('g_piel_color')) { icon = 'fa-palette'; iconColor = '#10b981'; }
    else if (id.startsWith('g_no_dormir')) { icon = 'fa-eye-slash'; iconColor = '#64748b'; }
    else if (id.startsWith('g_sangre_de_gigante')) { icon = 'fa-expand-arrows-alt'; iconColor = '#ef4444'; }
    else if (id.startsWith('g_cuerpo_elastico')) { icon = 'fa-dumbbell'; iconColor = '#10b981'; }
    else if (id.startsWith('rh_')) { icon = 'fa-user'; iconColor = '#6366f1'; }
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
    if (LINAJE_DATA.arbol_general) {
        for (var catKey in LINAJE_DATA.arbol_general) {
            var cat = LINAJE_DATA.arbol_general[catKey];
            if (cat && cat.perks) {
                var found = cat.perks.find(function(item) { return item.id === id; });
                if (found) return enrichPerk(found);
            }
        }
    }
    if (LINAJE_DATA.arboles_raciales) {
        for (var race in LINAJE_DATA.arboles_raciales) {
            var tree = LINAJE_DATA.arboles_raciales[race];
            if (tree && tree.perks) {
                var found = tree.perks.find(function(item) { return item.id === id; });
                if (found) return enrichPerk(found);
            }
        }
    }
    if (LINAJE_DATA.pasivas_primarias) {
        for (var race in LINAJE_DATA.pasivas_primarias) {
            var list = LINAJE_DATA.pasivas_primarias[race] || [];
            var found = list.find(function(item) { return item.id === id; });
            if (found) return enrichPerk(found);
        }
    }
    if (LINAJE_DATA.pasivas_secundarias) {
        for (var race in LINAJE_DATA.pasivas_secundarias) {
            var list = LINAJE_DATA.pasivas_secundarias[race] || [];
            var found = list.find(function(item) { return item.id === id; });
            if (found) return enrichPerk(found);
        }
    }
    return null;
}

function makeAprobarPerkCard(p, cssClass, iconBg, badgeLabel, badgeColor) {
    var costBadge = p.cost ? '<div style="position: absolute; top: 12px; right: 80px; font-family: var(--font-heading); font-size: 10px; font-weight: 800; background: rgba(99, 102, 241, 0.1); color: var(--accent-indigo); padding: 2px 6px; border-radius: 4px;">' + p.cost + ' PTS</div>' : '';
    return '<div class="gene-card ' + cssClass + '" style="position: relative;">' +
        costBadge +
        '<div class="gene-card-icon" style="' + iconBg + '">' +
            '<i class="fas ' + p.icon + '" style="color:' + p.iconColor + ';"></i>' +
        '</div>' +
        '<div class="gene-card-info">' +
            '<div class="gene-card-name">' + escapeHtml(p.name) + '</div>' +
            '<div class="gene-card-desc">' + escapeHtml(p.desc) + '</div>' +
        '</div>' +
        '<div class="gene-card-badge" style="background:' + badgeColor + '22; color:' + badgeColor + ';">' + badgeLabel + '</div>' +
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
      html += '    <div class="linaje-slots-bar" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; padding: 14px 20px; margin-bottom: 20px; background: linear-gradient(135deg, rgba(99,102,241,0.05), rgba(168,85,247,0.03)); border-radius: var(--radius-lg); border: 1px solid rgba(99,102,241,0.2);">';
      html += '        <div class="linaje-slots-group" style="display: flex; align-items: center; gap: 12px;">';
      html += '            <span class="linaje-slots-label" style="font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted);"><i class="fas fa-gem" style="color:var(--accent-indigo);"></i> Puntos de Linaje:</span>';
      if (maxPoints <= 10) {
          html += '            <div class="linaje-slots-dots" style="display: flex; gap: 6px;">';
          for (var i = 0; i < maxPoints; i++) {
              var filledClass = (i < spentPoints) ? 'filled' : '';
              var dotBg = (i < spentPoints) ? 'var(--accent-indigo)' : 'var(--bg-main)';
              var dotShadow = (i < spentPoints) ? 'box-shadow: 0 0 8px rgba(99,102,241,0.5);' : '';
              html += '                <div class="linaje-slot-dot ' + filledClass + '" style="width: 12px; height: 12px; border-radius: 50%; border: 2px solid var(--border-color); background: ' + dotBg + '; ' + dotShadow + '"></div>';
          }
          html += '            </div>';
      }
      html += '            <span class="linaje-slots-count" style="font-family: var(--font-heading); font-weight: 900; font-size: 22px; color: var(--accent-purple);">' + spentPoints + '/' + maxPoints + '</span>';
      html += '        </div>';
      html += '        <div id="linajeSobranteBonus" style="font-size: 12px; font-weight: 700; color: #10b981; text-transform: uppercase; letter-spacing: 0.5px;">';
      html += '            Puntos Sobrantes: ' + sobrante + ' PL = ' + bonusPP + ' PP de Bonus';
      html += '        </div>';
      html += '    </div>';
    var hasAnyPerks = false;
    
    // Pasivas
    var pasivas = linaje.pasivas || [];
    if (pasivas.length > 0) {
      hasAnyPerks = true;
      html += '    <div style="font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; color:#10b981; margin-bottom:10px; display:flex; align-items:center; gap:8px;">';
      html += '      <i class="fas fa-shield-alt"></i> Pasivas Innatas';
      html += '    </div>';
      html += '    <div class="gene-cards-grid">';
      pasivas.forEach(function(pid) {
        var p = findPerkById(pid);
        if (p) {
          var is_prim = (p.type === 'primaria');
          html += makeAprobarPerkCard(p,
            is_prim ? 'passive-primary' : 'passive-secondary',
            is_prim ? 'background:rgba(16,185,129,0.12); border:2px solid rgba(16,185,129,0.35);' : 'background:rgba(245,158,11,0.1); border:2px solid rgba(245,158,11,0.3);',
            is_prim ? 'PRIMARIA' : 'SECUNDARIA',
            is_prim ? '#10b981' : '#f59e0b'
          );
        }
      });
      html += '    </div>';
    }

    // Racial
    var elegidos_racial = linaje.elegidos_racial || [];
    if (elegidos_racial.length > 0) {
      hasAnyPerks = true;
      html += '    <div style="font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; color:var(--accent-indigo); margin-top:20px; margin-bottom:10px; display:flex; align-items:center; gap:8px;">';
      html += '      <i class="fas fa-dna"></i> Linaje Racial';
      html += '    </div>';
      html += '    <div class="gene-cards-grid">';
      elegidos_racial.forEach(function(pid) {
        var p = findPerkById(pid) || { id: pid, name: pid, icon: 'fa-dna', iconColor: 'var(--accent-indigo)', desc: 'Perk racial seleccionado.' };
        html += makeAprobarPerkCard(p, 'perk-racial',
          'background:rgba(99,102,241,0.1); border:2px solid rgba(99,102,241,0.3);',
          'RACIAL', '#6366f1');
      });
      html += '    </div>';
    }

    // General
    var elegidos_general = linaje.elegidos_general || [];
    if (elegidos_general.length > 0) {
      hasAnyPerks = true;
      html += '    <div style="font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1.5px; color:var(--accent-purple); margin-top:20px; margin-bottom:10px; display:flex; align-items:center; gap:8px;">';
      html += '      <i class="fas fa-star"></i> Linaje General';
      html += '    </div>';
      html += '    <div class="gene-cards-grid">';
      elegidos_general.forEach(function(pid) {
        var p = findPerkById(pid) || { id: pid, name: pid, icon: 'fa-star', iconColor: 'var(--accent-purple)', desc: 'Perk general seleccionado.' };
        html += makeAprobarPerkCard(p, 'perk-general',
          'background:rgba(168,85,247,0.1); border:2px solid rgba(168,85,247,0.3);',
          'GENERAL', '#a855f7');
      });
      html += '    </div>';
    }

    if (!hasAnyPerks) {
      html += '    <div style="padding: 30px; text-align:center; background: var(--bg-surface); border-radius: var(--radius-md); border: 1px dashed var(--border-color);">';
      html += '      <i class="fas fa-scroll" style="font-size: 40px; color: var(--accent-indigo); opacity: 0.5; margin-bottom:15px;"></i>';
      html += '      <h4 style="color:var(--text-primary); margin-bottom:5px;">Sin Perks de Linaje</h4>';
      html += '      <p style="color:var(--text-muted); font-size:13px;">Este personaje no tiene perks de linaje asignados todavía.</p>';
      html += '    </div>';
    }
  } else {
    // Legacy v1
    html += '    <p style="color:var(--text-muted); font-size:14px; margin-bottom:20px;">Perks de Linaje del personaje — pasivas innatas y habilidades elegidas.</p>';
    html += '    <div style="padding:12px 16px; background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.3); border-radius:var(--radius-md); margin-bottom:20px; display:flex; align-items:center; gap:12px;">';
    html += '      <i class="fas fa-info-circle" style="color:#f59e0b; font-size:18px;"></i>';
    html += '      <div style="text-align:left;">';
    html += '        <div style="font-weight:800; font-size:12px; color:#f59e0b; text-transform:uppercase; letter-spacing:0.5px;">Ficha en formato antiguo</div>';
    html += '        <div style="font-size:12px; color:var(--text-muted);">El sistema de Linaje de este personaje será actualizado en la próxima revisión de ficha.</div>';
    html += '      </div>';
    html += '    </div>';

    if (geneNames.length) {
      html += '    <div class="gene-cards-grid">';
      geneNames.forEach(function(g) {
        var dummyPerk = { id: 'legacy', name: g, icon: 'fa-dna', iconColor: 'var(--accent-indigo)', desc: 'Gen activo (formato antiguo).' };
        html += makeAprobarPerkCard(dummyPerk, 'perk-racial',
          'background:rgba(99,102,241,0.1); border:2px solid rgba(99,102,241,0.3);',
          'RACIAL', '#6366f1');
      });
      html += '    </div>';
    } else {
      html += '    <div style="padding:30px; text-align:center; background:var(--bg-surface); border-radius:var(--radius-md); border:1px dashed var(--border-color);">';
      html += '      <i class="fas fa-dna" style="font-size:40px; color:var(--accent-purple); opacity:0.5; margin-bottom:15px;"></i>';
      html += '      <h4 style="color:var(--text-primary); margin-bottom:5px;">Sin Genes Extra</h4>';
      html += '      <p style="color:var(--text-muted); font-size:13px;">Este personaje no ha desarrollado genes mas alla de los basicos de su raza.</p>';
      html += '    </div>';
    }
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
