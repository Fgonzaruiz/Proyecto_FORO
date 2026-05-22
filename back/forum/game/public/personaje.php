<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db, $header, $footer, $theme;
$prefix = TABLE_PREFIX;
$user_id = (int)($mybb->user['uid'] ?? 0);

// If ?pj= is specified, load that character (any visible character)
$req_pj_id = isset($_GET['pj']) ? (int)$_GET['pj'] : 0;

// Get active character from user_config
$cfg = null;
if ($user_id) {
    $db->write_query("INSERT INTO {$prefix}game_user_config (user_id, max_slots, slots_used) VALUES ({$user_id}, 1, 0) ON DUPLICATE KEY UPDATE user_id=user_id");
    $cfg_q = $db->query("SELECT * FROM {$prefix}game_user_config WHERE user_id = {$user_id}");
    $cfg = $db->fetch_array($cfg_q);
}

$active_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
$load_id = $req_pj_id ?: $active_id;

$char = null;
if ($load_id) {
    $query = $db->query("SELECT * FROM {$prefix}game_personajes WHERE id = {$load_id} LIMIT 1");
    $row = $db->fetch_array($query);
    if ($row) {
        $data = !empty($row['data_json']) ? json_decode($row['data_json'], true) : [];
        if (!is_array($data)) $data = [];
        $stats = !empty($row['stats_json']) ? json_decode($row['stats_json'], true) : [];
        if (!is_array($stats)) $stats = [];
        $cronologia = !empty($row['cronologia_json']) ? json_decode($row['cronologia_json'], true) : [];
        if (!is_array($cronologia)) $cronologia = [];
        $cronologia['diario'] = $cronologia['diario'] ?? [];
        $cronologia['relaciones'] = $cronologia['relaciones'] ?? [];

        $char = [
            'id'          => (int)$row['id'],
            'user_id'     => (int)$row['user_id'],
            'name'        => $row['name'],
            'race_name'   => !empty($row['race_name']) ? $row['race_name'] : ($data['race'] ?? 'Desconocida'),
            'is_staff'    => (bool)$row['is_staff'],
            'job_name'    => !empty($row['occupation_name']) ? $row['occupation_name'] : ($data['job'] ?? 'Ninguno'),
            'rango'       => !empty($row['rango']) ? $row['rango'] : ($data['rank'] ?? ''),
            'avatar'      => !empty($row['avatar']) ? $row['avatar'] : ($data['avatar'] ?? ''),
            'faction'     => !empty($row['faction']) ? $row['faction'] : ($data['faction'] ?? ''),
            'approved'    => (bool)($row['approved'] ?? 0),
            
            // Legacy fallbacks for bio
            'desc'        => $row['desc'] ?? '',
            'details'     => $row['details'] ?? '',
            
            // JSON Fields
            'age'         => $data['age'] ?? 'Desconocida',
            'origin'      => $data['origin'] ?? 'Desconocido',
            'pb'          => $data['pb'] ?? 'Desconocido',
            'physique'    => $data['physique'] ?? '',
            'psychology'  => $data['psychology'] ?? '',
            'extras'      => $data['extras'] ?? '',
            'arquetipo'   => $data['arquetipo'] ?? 'Desconocido',
            'linaje'      => $data['linaje'] ?? [],
            
            // New Tabs Data
            'cronologia'  => $cronologia,
            
            // New Stats
            'stats'       => [
                'str' => (int)($stats['str'] ?? (isset($row['stat_fp']) ? $row['stat_fp'] : 0)),
                'agi' => (int)($stats['agi'] ?? (isset($row['stat_dp']) ? $row['stat_dp'] : 0)),
                'res' => (int)($stats['res'] ?? (isset($row['stat_rp']) ? $row['stat_rp'] : 0)),
                'vol' => (int)($stats['vol'] ?? (isset($row['stat_vp']) ? $row['stat_vp'] : 0)),
            ],
        ];
        
        // Sort Diario
        usort($char['cronologia']['diario'], function($a, $b) {
            $peso_a = ((int)($a['year'] ?? 0) * 400) + ((int)($a['season'] ?? 0) * 100) + (int)($a['day'] ?? 0);
            $peso_b = ((int)($b['year'] ?? 0) * 400) + ((int)($b['season'] ?? 0) * 100) + (int)($b['day'] ?? 0);
            return $peso_a <=> $peso_b;
        });
    }
}

// 1. Calculate Global Rol Date (shared function in bootstrap.php)
$global_date_string = game_global_rol_date();

// 2. Load all characters for the Select (remove approved filter so any char can be linked)
$all_chars = [];
$chars_q = $db->query("SELECT id, name FROM {$prefix}game_personajes ORDER BY name ASC");
while ($c = $db->fetch_array($chars_q)) {
    $all_chars[] = $c;
}

$bb = $mybb->settings['bburl'];
$b_url = $bb . '/images/game/personaje_banner.png';

ob_start();
?>
<style>
/* Pestañas para la ficha */
.pj-preview-tabs { display: flex; border-bottom: 2px solid var(--border-color); margin-bottom: 24px; }
.pj-preview-tab {
    padding: 10px 20px; font-family: var(--font-heading); font-weight: 700; font-size: 14px;
    color: var(--text-muted); cursor: pointer; border-bottom: 3px solid transparent;
    margin-bottom: -2px; transition: all 0.2s ease;
}
.pj-preview-tab:hover { color: var(--text-primary); }
.pj-preview-tab.active { color: var(--accent-indigo); border-bottom-color: var(--accent-indigo); }
.pj-preview-tab-content { display: none; }
.pj-preview-tab-content.active { display: block; }

/* Barras de stats (copiadas del creador) */
.rpg-preview-stat-bar { background: var(--bg-card); border-radius: 10px; height: 8px; width: 100%; overflow: hidden; margin-top: 4px; }
.rpg-preview-stat-fill { height: 100%; background: linear-gradient(90deg, var(--accent-indigo), var(--accent-purple)); border-radius: 10px; transition: width 0.5s ease; }
.rpg-preview-stat-row { margin-bottom: 12px; text-align: left; }

/* Gene cards */
.gene-card { display: flex; align-items: center; gap: 15px; padding: 12px 15px; background: var(--bg-main); border: 1px solid var(--border-color); border-radius: var(--radius-md); margin-bottom: 10px; transition: all 0.2s ease; }
.gene-card:hover { border-color: rgba(99,102,241,0.4); }
.gene-card-icon { width: 42px; height: 42px; border-radius: 50%; flex-shrink: 0; background: linear-gradient(135deg, rgba(99,102,241,0.12), rgba(168,85,247,0.08)); border: 2px solid var(--accent-indigo); display: flex; align-items: center; justify-content: center; color: var(--accent-indigo); font-size: 16px; }
.gene-card-info { flex: 1; }
.gene-card-name { font-weight: 700; font-size: 14px; color: var(--text-primary); margin-bottom: 2px; }
.gene-card-desc { font-size: 12px; color: var(--text-muted); line-height: 1.3; }

/* Custom Scrollbars for boxes */
.pj-scroll-box {
    background: var(--bg-surface); border: 1px solid var(--border-color);
    border-radius: var(--radius-md); padding: 20px; height: 280px;
    overflow-y: auto; margin-bottom: 30px; font-size: 14px; line-height: 1.7; color: var(--text-secondary);
}
.pj-scroll-box::-webkit-scrollbar { width: 6px; }
.pj-scroll-box::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); border-radius: 4px; }
.pj-scroll-box::-webkit-scrollbar-thumb { background: var(--accent-indigo); border-radius: 4px; }

/* Timeline (Diario estilo libreta) */
.pj-timeline { position: relative; margin-top: 20px; }
.pj-timeline-item { background: #fdfbf7; color: #333; padding: 20px 25px; border-radius: 4px; margin-bottom: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.15); position: relative; border-left: 5px solid #d4c5b0; font-family: 'Georgia', serif; }
.pj-timeline-item::before { content: ''; position: absolute; left: 8px; right: 8px; top: 8px; bottom: 8px; border: 1px dashed rgba(0,0,0,0.08); pointer-events: none; }
.pj-timeline-date { font-family: var(--font-heading); font-size: 13px; font-weight: 700; color: #8c7b66; border-bottom: 2px solid rgba(212,197,176,0.3); padding-bottom: 5px; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px; }
.pj-timeline-desc { font-size: 15px; color: #4a4a4a; line-height: 1.8; margin-bottom: 15px; font-style: italic; }
.pj-timeline-link { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: bold; text-transform: uppercase; color: #8c7b66; background: #f0e9df; padding: 6px 14px; border-radius: 20px; text-decoration: none; transition: all 0.2s; border: 1px solid #d4c5b0; font-family: var(--font-main); font-style: normal; }
.pj-timeline-link:hover { background: #d4c5b0; color: #fff; }

/* Relations Grid */
.pj-relations-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; margin-top: 20px; }
.pj-relation-card { background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 15px; text-align: center; transition: transform 0.2s, border-color 0.2s; }
.pj-relation-card:hover { transform: translateY(-5px); border-color: var(--accent-purple); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
.pj-relation-img { width: 75px; height: 75px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent-indigo); margin: 0 auto 12px auto; display: block; padding: 3px; background: rgba(255,255,255,0.05); }
.pj-relation-name { font-family: var(--font-heading); font-size: 15px; font-weight: 700; color: var(--text-primary); margin-bottom: 5px; }
.pj-relation-tag-wrap { display: flex; flex-wrap: wrap; gap: 4px; justify-content: center; margin-top: 4px; }
.pj-relation-tag { font-size: 10px; font-weight: 700; text-transform: uppercase; display: inline-block; padding: 2px 8px; border-radius: 10px; letter-spacing: 0.3px; }

/* Tag selector */
.pj-tag-selector { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 5px; }
.pj-tag-option { font-size: 11px; font-weight: 600; padding: 4px 12px; border-radius: 14px; cursor: pointer; border: 2px solid transparent; transition: all 0.15s; opacity: 0.5; user-select: none; }
.pj-tag-option.selected { opacity: 1; border-color: currentColor; box-shadow: 0 0 8px rgba(0,0,0,0.15); }
.pj-tag-option:hover { opacity: 0.8; }


/* In-situ Modals (Beautified) */
.pj-modal-overlay { position: fixed; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.7); display:none; justify-content:center; align-items:center; z-index: 9999; backdrop-filter: blur(8px); }
.pj-modal { background: var(--bg-surface); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; width: 560px; max-width: 94vw; padding: 40px; box-shadow: 0 25px 50px rgba(0,0,0,0.8); position: relative; overflow: visible; }
.pj-modal::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 5px; background: linear-gradient(90deg, var(--accent-indigo), var(--accent-purple)); }
.pj-modal-title { font-family: var(--font-heading); font-size: 22px; color: #fff; margin-bottom: 25px; text-align: center; font-weight: 800; }
.pj-modal .form-group { margin-bottom: 18px; }
.pj-modal .textbox { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.05); color: #fff; border-radius: 8px; padding: 14px 15px; transition: all 0.3s; width: 100%; box-sizing: border-box; }
.pj-modal .textbox:focus { background: rgba(0,0,0,0.4); border-color: var(--accent-indigo); box-shadow: 0 0 0 3px rgba(99,102,241,0.2); outline: none; }
.pj-modal label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); font-weight: 700; margin-bottom: 8px; display: block; }
.pj-btn-add { background: linear-gradient(135deg, var(--accent-indigo), var(--accent-purple)); color: white; border: none; padding: 10px 22px; border-radius: 8px; font-size: 13px; font-weight: 800; text-transform: uppercase; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 15px rgba(99,102,241,0.3); }
.pj-btn-add:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(99,102,241,0.5); }
.pj-btn-cancel { background: rgba(255,255,255,0.05); color: var(--text-muted); border: 1px solid rgba(255,255,255,0.1); box-shadow: none; }
.pj-btn-cancel:hover { background: rgba(255,255,255,0.1); color: #fff; transform: none; box-shadow: none; }
.pj-modal-actions { text-align: right; margin-top: 25px; display: flex; justify-content: flex-end; gap: 12px; }
.pj-edit-list { max-height: 450px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; }
.pj-edit-item { background: rgba(0,0,0,0.15); border: 1px solid var(--border-color); border-radius: 10px; padding: 12px 14px; display: flex; align-items: center; gap: 10px; }
.pj-edit-item-body { flex: 1; min-width: 0; }
.pj-edit-item-actions { display: flex; gap: 5px; flex-shrink: 0; }
.pj-edit-btn { width: 32px; height: 32px; border-radius: 8px; border: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; font-size: 13px; transition: all 0.15s; }
.pj-edit-btn:hover { transform: scale(1.1); }
.pj-edit-btn-edit { background: rgba(59,130,246,0.15); color: #3b82f6; }
.pj-edit-btn-edit:hover { background: rgba(59,130,246,0.3); }
.pj-edit-btn-del { background: rgba(239,68,68,0.15); color: #ef4444; }
.pj-edit-btn-del:hover { background: rgba(239,68,68,0.3); }
.pj-cat-counter { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
.pj-cat-chip { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px 4px 6px; border-radius: 6px; font-size: 11px; font-weight: 700; line-height: 1; }
.pj-cat-chip .num { font-size: 14px; font-weight: 800; }
.pj-cat-picker { cursor:pointer; border-radius:8px; padding:6px 16px; font-weight:700; font-size:12px; transition:all 0.15s; opacity:0.6; user-select:none; }
.pj-cat-picker:hover { opacity:0.9; }
.pj-cat-picker.active { opacity:1; box-shadow: 0 0 10px rgba(0,0,0,0.3); }
</style>

<div class="rpg-char-page" style="max-width: 1200px; margin: 0 auto;">
  <?php if (!$char): ?>
    <?php if ($req_pj_id): ?>
    <div class="rpg-char-empty">
      <i class="fas fa-user-slash"></i>
      <h2>Personaje no encontrado</h2>
      <p>El personaje solicitado no existe o no est&aacute; disponible.</p>
    </div>
    <?php elseif (!$user_id): ?>
    <div class="rpg-char-empty">
      <i class="fas fa-user-lock"></i>
      <h2>Debes iniciar sesi&oacute;n</h2>
      <p>Inicia sesi&oacute;n en el foro para ver tu ficha de personaje.</p>
    </div>
    <?php else: ?>
    <div class="rpg-char-empty">
      <i class="fas fa-user-plus"></i>
      <h2>No tienes personaje</h2>
      <p>A&uacute;n no se ha vinculado ning&uacute;n personaje a tu cuenta. ¡Ve a la gesti&oacute;n de personajes para crear uno!</p>
    </div>
    <?php endif; ?>
  <?php else: ?>
  
  <?php
    $genes_activos = (!empty($char['linaje']['geneNames'])) ? implode(', ', $char['linaje']['geneNames']) : 'Ninguno';
    
    // Evaluate permissions based on ACTIVE CHARACTER
    $active_char_is_staff = false;
    if ($active_id && $active_id !== (int)($char['id'])) {
        $active_q = $db->query("SELECT is_staff FROM {$prefix}game_personajes WHERE id = {$active_id} LIMIT 1");
        if ($a_row = $db->fetch_array($active_q)) {
            $active_char_is_staff = (bool)$a_row['is_staff'];
        }
    } elseif ($active_id && $char && $active_id === (int)$char['id']) {
        $active_char_is_staff = (bool)$char['is_staff'];
    }
    
    $is_active_pj = ($char && $active_id === (int)$char['id']);
    $can_edit = $is_active_pj;
    $can_view_private = ($is_active_pj || $active_char_is_staff);
  ?>

  <div style="display: flex; background: var(--bg-main); border: 1px solid var(--border-color); border-radius: var(--radius-lg); overflow: hidden; min-height: 700px; margin-top: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
      
      <!-- LEFT COLUMN (Avatar & Stats) -->
      <div style="width: 320px; background: var(--bg-surface); border-right: 1px solid var(--border-color); display:flex; flex-direction:column; flex-shrink: 0;">
          <div style="width:100%; height:450px; min-height:450px; background-size:cover; background-position:center; background-image:url('<?= htmlspecialchars($char['avatar'] ?: 'https://placehold.co/320x450') ?>'); border-bottom: 2px solid var(--accent-indigo);"></div>
          
          <div style="padding: 20px;">
              <h2 style="font-family:var(--font-heading); font-size:24px; color:var(--text-primary); margin-bottom:10px; text-align:center;"><?= htmlspecialchars($char['name']) ?></h2>
              
              <div style="display:flex; justify-content:center; gap:8px; flex-wrap:wrap; margin-bottom: 15px;">
                  <?php if ($char['approved']): ?>
                      <span style="background:rgba(16, 185, 129, 0.1); color:#10b981; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700;"><i class="fas fa-check-circle"></i> Aprobada</span>
                  <?php else: ?>
                      <span style="background:rgba(239, 68, 68, 0.1); color:#ef4444; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700;"><i class="fas fa-clock"></i> Pendiente</span>
                  <?php endif; ?>
                  <span style="background:rgba(99,102,241,0.1); color:var(--accent-indigo); padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700;"><i class="fas fa-flag"></i> Facci&oacute;n</span>
                  <span style="background:rgba(168,85,247,0.1); color:var(--accent-purple); padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700;"><i class="fas fa-medal"></i> <?= htmlspecialchars($char['rango'] ?: 'Sin Rango') ?></span>
                  <?php if ($char['is_staff']): ?>
                    <span style="background:var(--accent-indigo); color:#fff; padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700;"><i class="fas fa-star"></i> Staff</span>
                  <?php endif; ?>
              </div>
              
              <div style="background: var(--bg-card); border-radius: var(--radius-md); padding: 15px; border: 1px solid var(--border-color); margin-bottom: 20px;">
                  <div style="display:flex; align-items:center; gap:10px; margin-bottom: 10px; border-bottom:1px solid var(--border-color); padding-bottom:5px;">
                      <i class="fas fa-shield-alt" style="color:var(--text-secondary); font-size:20px;"></i>
                      <div>
                          <div style="font-size:10px; color:var(--text-muted); text-transform:uppercase; font-weight:bold;">Arquetipo B&eacute;lico</div>
                          <div style="font-weight:700; color:var(--text-primary); font-size:14px;"><?= htmlspecialchars($char['arquetipo']) ?></div>
                      </div>
                  </div>
                  <div style="display:flex; align-items:center; gap:10px; margin-bottom: 10px; border-bottom:1px solid var(--border-color); padding-bottom:5px;">
                      <i class="fas fa-anchor" style="color:var(--text-secondary); font-size:20px;"></i>
                      <div>
                          <div style="font-size:10px; color:var(--text-muted); text-transform:uppercase; font-weight:bold;">Oficio</div>
                          <div style="font-weight:700; color:var(--text-primary); font-size:14px;"><?= htmlspecialchars($char['job_name'] ?: 'Ninguno') ?></div>
                      </div>
                  </div>
                  <div style="display:flex; align-items:center; gap:10px;">
                      <i class="fas fa-dna" style="color:var(--accent-purple); font-size:20px;"></i>
                      <div>
                          <div style="font-size:10px; color:var(--text-muted); text-transform:uppercase; font-weight:bold;">Genes Activos</div>
                          <div style="font-weight:700; color:var(--accent-purple); font-size:13px; line-height:1.2;"><?= $genes_activos ?></div>
                      </div>
                  </div>
              </div>
              
              <h3 style="font-size:12px; font-family:var(--font-heading); color:var(--text-muted); text-transform:uppercase; margin-bottom:10px;">Atributos Base</h3>
              <div class="rpg-preview-stat-row">
                  <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>FUERZA</span><span><?= $char['stats']['str'] ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill" style="width:<?= min(100, $char['stats']['str'] * 10) ?>%;"></div></div>
              </div>
              <div class="rpg-preview-stat-row">
                  <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>AGILIDAD</span><span><?= $char['stats']['agi'] ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill" style="width:<?= min(100, $char['stats']['agi'] * 10) ?>%; background:linear-gradient(90deg,#10b981,#059669);"></div></div>
              </div>
              <div class="rpg-preview-stat-row">
                  <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>RESISTENCIA</span><span><?= $char['stats']['res'] ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill" style="width:<?= min(100, $char['stats']['res'] * 10) ?>%; background:linear-gradient(90deg,#f59e0b,#d97706);"></div></div>
              </div>
              <div class="rpg-preview-stat-row">
                  <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>VOLUNTAD</span><span><?= $char['stats']['vol'] ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill" style="width:<?= min(100, $char['stats']['vol'] * 10) ?>%; background:linear-gradient(90deg,#ef4444,#dc2626);"></div></div>
              </div>
          </div>
      </div>
      
      <!-- RIGHT COLUMN (Tabs & Content) -->
      <div style="flex:1; padding: 40px; overflow-y:auto;">
          <div class="pj-preview-tabs">
              <div class="pj-preview-tab active" onclick="switchPjTab('bio', this)"><i class="fas fa-file-alt"></i> Biograf&iacute;a</div>
              <div class="pj-preview-tab" onclick="switchPjTab('linaje', this)"><i class="fas fa-dna"></i> Mapa Gen&eacute;tico</div>
              <div class="pj-preview-tab" onclick="switchPjTab('cronologia', this)"><i class="fas fa-calendar-alt"></i> Bit&aacute;cora</div>
              <?php if ($can_view_private): ?>
              <div class="pj-preview-tab" onclick="switchPjTab('tecnicas', this)"><i class="fas fa-fist-raised"></i> T&eacute;cnicas</div>
              <div class="pj-preview-tab" onclick="switchPjTab('gestion', this)"><i class="fas fa-cogs"></i> Gesti&oacute;n</div>
              <?php endif; ?>
          </div>

          <!-- TAB: BIO -->
          <div id="pjTab_bio" class="pj-preview-tab-content active">
              <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:30px; background:var(--bg-surface); padding:20px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                  <div style="font-size:14px;"><strong>Edad:</strong> <?= htmlspecialchars($char['age']) ?></div>
                  <div style="font-size:14px;"><strong>Origen:</strong> <?= htmlspecialchars($char['origin']) ?></div>
                  <div style="font-size:14px;"><strong>Raza:</strong> <?= htmlspecialchars($char['race_name']) ?></div>
                  <div style="font-size:14px;"><strong>PB:</strong> <?= htmlspecialchars($char['pb']) ?></div>
              </div>
              
              <h3 style="font-family:var(--font-heading); font-size:18px; color:var(--text-primary); margin-bottom:15px; border-bottom:1px solid var(--border-color); padding-bottom:5px;">Apariencia F&iacute;sica</h3>
              <div class="pj-scroll-box" style="height: 200px;">
                  <?= nl2br(htmlspecialchars($char['physique'] ?: 'Sin registrar.')) ?>
              </div>
              
              <h3 style="font-family:var(--font-heading); font-size:18px; color:var(--text-primary); margin-bottom:15px; border-bottom:1px solid var(--border-color); padding-bottom:5px;">Perfil Psicol&oacute;gico</h3>
              <div class="pj-scroll-box" style="height: 200px;">
                  <?= nl2br(htmlspecialchars($char['psychology'] ?: ($char['desc'] ?: 'Sin historia registrada.'))) ?>
              </div>
              
              <h3 style="font-family:var(--font-heading); font-size:18px; color:var(--text-primary); margin-bottom:15px; border-bottom:1px solid var(--border-color); padding-bottom:5px;">Extras y Notas</h3>
              <div class="pj-scroll-box" style="height: 200px;">
                  <?= nl2br(htmlspecialchars($char['extras'] ?: ($char['details'] ?: 'Sin notas extras.'))) ?>
              </div>
          </div>

          <!-- TAB: LINAJE -->
          <div id="pjTab_linaje" class="pj-preview-tab-content">
              <p style="color:var(--text-muted); font-size:14px; margin-bottom:20px;">Genes desbloqueados en el Mapa Gen&eacute;tico de tu personaje.</p>
              
              <?php if (empty($char['linaje']['geneNames'])): ?>
              <div style="padding: 30px; text-align:center; background: var(--bg-surface); border-radius: var(--radius-md); border: 1px dashed var(--border-color);">
                  <i class="fas fa-dna" style="font-size: 40px; color: var(--accent-purple); opacity: 0.5; margin-bottom:15px;"></i>
                  <h4 style="color:var(--text-primary); margin-bottom:5px;">Sin Genes Extra</h4>
                  <p style="color:var(--text-muted); font-size:13px;">Este personaje no ha desarrollado genes más allá de los básicos de su raza.</p>
              </div>
              <?php else: ?>
                  <div class="gene-cards-container">
                  <?php foreach ($char['linaje']['geneNames'] as $geneName): ?>
                      <div class="gene-card">
                          <div class="gene-card-icon"><i class="fas fa-dna"></i></div>
                          <div class="gene-card-info">
                              <div class="gene-card-name"><?= htmlspecialchars($geneName) ?></div>
                              <div class="gene-card-desc">Gen activo del mapa genético.</div>
                          </div>
                      </div>
                  <?php endforeach; ?>
                  </div>
              <?php endif; ?>
          </div>

          <!-- TAB: CRONOLOGIA -->
          <div id="pjTab_cronologia" class="pj-preview-tab-content">
              <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-color); padding-bottom:10px; margin-bottom:20px;">
                  <h3 style="font-family:var(--font-heading); font-size:18px; color:var(--text-primary); margin:0;">Diario de Aventuras</h3>
                  <?php if ($can_edit): ?>
                      <div style="display:flex; gap:8px;">
                          <button class="pj-btn-add" onclick="editingEntryId=null;document.getElementById('diario_day').value='';document.getElementById('diario_season').value='0';document.getElementById('diario_year').value='';document.querySelectorAll('.pj-cat-picker').forEach(function(c){c.classList.toggle('active',c.dataset.cat==='Presente')});document.getElementById('diario_cat').value='Presente';document.getElementById('diario_desc').value='';document.getElementById('diario_link').value='';document.getElementById('modal_diario').style.display='flex'"><i class="fas fa-plus"></i> Añadir</button>
                          <button class="pj-btn-add pj-btn-cancel" onclick="openEditDiario()"><i class="fas fa-list"></i> Editar</button>
                      </div>
                  <?php endif; ?>
              </div>
              
              <?php
              $cat_list = ['Pasado'=>'#8b5cf6','Presente'=>'#10b981','Mision'=>'#f59e0b','Evento'=>'#3b82f6','Trama'=>'#ef4444','Fic'=>'#ec4899'];
              $cat_counts = [];
              foreach ($cat_list as $cn => $cc) $cat_counts[$cn] = 0;
              foreach ($char['cronologia']['diario'] as $entry) {
                  $ec = $entry['category'] ?? 'Presente';
                  if (isset($cat_counts[$ec])) $cat_counts[$ec]++;
              }
              ?>
              <div class="pj-cat-counter">
                  <?php foreach ($cat_list as $cn => $cc): ?>
                  <span class="pj-cat-chip" style="color:<?= $cc ?>;background:<?= $cc ?>22;">
                      <span class="num"><?= $cat_counts[$cn] ?></span> <?= $cn ?>
                  </span>
                  <?php endforeach; ?>
              </div>

              <?php if (empty($char['cronologia']['diario'])): ?>
                  <p style="color:var(--text-muted); font-size:14px; text-align:center; margin-bottom:40px;">No hay registros en el diario.</p>
              <?php else: ?>
                  <div class="pj-scroll-box" style="height: 350px;">
                      <div class="pj-timeline">
                      <?php 
                      $s_names = ['Primavera', 'Verano', 'Otoño', 'Invierno'];
                      foreach ($char['cronologia']['diario'] as $entry): 
                          $d = $entry['day'] ?? '?';
                          $s_id = $entry['season'] ?? 0;
                          $y = $entry['year'] ?? '?';
                          $s_name = $s_names[$s_id] ?? 'Desconocida';
                          $fecha_str = "Día {$d} de {$s_name}, Año {$y}";
                          $entry_cat = $entry['category'] ?? 'Presente';
                          $cat_color = $cat_list[$entry_cat] ?? '#6366f1';
                      ?>
                          <div class="pj-timeline-item">
                              <div class="pj-timeline-date" style="display:flex;align-items:center;gap:10px;">
                                  <?= htmlspecialchars($fecha_str) ?>
                                  <span style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;padding:2px 8px;border-radius:6px;color:<?= $cat_color ?>;background:<?= $cat_color ?>22;"><?= htmlspecialchars($entry_cat) ?></span>
                              </div>
                              <div class="pj-timeline-desc"><?= nl2br(htmlspecialchars($entry['desc'] ?? '')) ?></div>
                              <?php if (!empty($entry['link'])): ?>
                                  <a href="<?= htmlspecialchars((string)($entry['link'] ?? '')) ?>" class="pj-timeline-link" target="_blank"><i class="fas fa-book-open"></i> Leer Tema</a>
                              <?php endif; ?>
                          </div>
                      <?php endforeach; ?>
                      </div>
                  </div>
              <?php endif; ?>

              <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-color); padding-bottom:10px; margin-top:40px; margin-bottom:20px;">
                  <h3 style="font-family:var(--font-heading); font-size:18px; color:var(--text-primary); margin:0;">Red de Contactos</h3>
                  <?php if ($can_edit): ?>
                      <div style="display:flex; gap:8px;">
                          <button class="pj-btn-add" onclick="document.getElementById('modal_relacion').style.display='flex'"><i class="fas fa-plus"></i> Añadir Contacto</button>
                          <button class="pj-btn-add" onclick="openEditRelacion()"><i class="fas fa-cog"></i> Editar / Borrar</button>
                          <button class="pj-btn-add" style="background:var(--bg-surface); color:var(--text-primary);" onclick="document.getElementById('group_modal_title').textContent='Crear Grupo'; document.getElementById('grp_name').value=''; editingEntryId=null; document.querySelectorAll('input[name=\'grp_members[]\']').forEach(function(cb){cb.checked=false;}); document.getElementById('modal_group').style.display='flex'"><i class="fas fa-users"></i> Crear Grupo</button>
                          <button class="pj-btn-add" style="background:var(--bg-surface); color:var(--text-primary);" onclick="document.getElementById('conn_modal_title').textContent='Crear Conexión'; document.getElementById('conn_label').value=''; document.getElementById('conn_source').value=''; document.getElementById('conn_target').value=''; editingEntryId=null; document.getElementById('modal_connection').style.display='flex'"><i class="fas fa-project-diagram"></i> Conectar Contactos</button>
                      </div>
                  <?php endif; ?>
              </div>

              <?php
              $tag_colors = [
                  'Amigo' => '#10b981', 'Compañero' => '#3b82f6', 'Aliado' => '#3b82f6',
                  'Rival' => '#f59e0b', 'Enemigo' => '#ef4444', 'Némesis' => '#ef4444',
                  'Familiar' => '#ec4899', 'Hermano' => '#ec4899', 'Hermana' => '#ec4899',
                  'Padre' => '#8b5cf6', 'Madre' => '#8b5cf6',
                  'Maestro' => '#f97316', 'Mentor' => '#f97316',
                  'Aprendiz' => '#06b6d4', 'Protegido' => '#06b6d4',
                  'Interés Romántico' => '#ec4899', 'Cónyuge' => '#ec4899', 'Amante' => '#ec4899',
                  'Conocido' => '#6b7280', 'Socio' => '#8b5cf6', 'Cómplice' => '#8b5cf6',
                  'Subordinado' => '#64748b', 'Superior' => '#64748b',
                  'Adversario' => '#f59e0b', 'Seguidor' => '#06b6d4', 'Líder' => '#f97316',
                  'Miembro' => '#6b7280',
              ];
              ?>
              <?php if (empty($char['cronologia']['relaciones'])): ?>
                  <p style="color:var(--text-muted); font-size:14px; text-align:center;">No hay relaciones registradas.</p>
              <?php else: ?>
                  <div style="position:relative;">
                      <!-- Controles integrados flotantes en la esquina superior derecha -->
                      <div style="position:absolute; top:15px; right:15px; z-index:10; display:flex; gap:15px;">
                          <button id="btn-view-graph" style="background:none; border:none; color:var(--text-primary); font-size:22px; cursor:pointer; opacity:1; transition:opacity 0.2s;" onclick="document.getElementById('pj-view-graph').style.display='block'; document.getElementById('pj-view-list').style.display='none'; this.style.opacity=1; document.getElementById('btn-view-list').style.opacity=0.4;" title="Mapa de Relaciones"><i class="fas fa-project-diagram"></i></button>
                          <button id="btn-view-list" style="background:none; border:none; color:var(--text-primary); font-size:22px; cursor:pointer; opacity:0.4; transition:opacity 0.2s;" onclick="document.getElementById('pj-view-graph').style.display='none'; document.getElementById('pj-view-list').style.display='block'; this.style.opacity=1; document.getElementById('btn-view-graph').style.opacity=0.4;" title="Vista Lista"><i class="fas fa-th-large"></i></button>
                      </div>
                      
                      <div id="pj-view-graph">
                          <div id="pj-network-container" style="width: 100%; height: 500px; background: radial-gradient(circle, var(--bg-surface) 0%, var(--bg-main) 100%); border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden; position: relative;"></div>
                          <script>
                          window.__PJ_NETWORK_DATA = {
                              relations: <?= json_encode($char['cronologia']['relaciones'] ?? [], JSON_UNESCAPED_UNICODE) ?>,
                              groups: <?= json_encode($char['cronologia']['groups'] ?? [], JSON_UNESCAPED_UNICODE) ?>,
                              connections: <?= json_encode($char['cronologia']['connections'] ?? [], JSON_UNESCAPED_UNICODE) ?>
                          };
                          </script>
                          <script src="../../jscripts/game/game_network.js?v=<?= time() ?>"></script>
                      </div>
                      
                      <div id="pj-view-list" style="display:none; background:var(--bg-surface); border:1px solid var(--border-color); border-radius:var(--radius-md); padding-top:40px;">
                          <div class="pj-scroll-box" style="height: 460px; border:none; background:transparent;">
                              <div class="pj-relations-grid">
                              <?php foreach ($char['cronologia']['relaciones'] as $rel):
                                  $tags = $rel['tags'] ?? [];
                                  if (empty($tags) && !empty($rel['relation'])) $tags = [$rel['relation']];
                                  if (!is_array($tags)) $tags = [$tags];
                              ?>
                                  <?php if (!empty($rel['pj_id'])): ?>
                                      <a href="personaje.php?pj=<?= htmlspecialchars((string)$rel['pj_id']) ?>" target="_blank" style="text-decoration:none; color:inherit;">
                                  <?php endif; ?>
                                  <div class="pj-relation-card" style="position:relative;">
                                      <?php if (!empty($rel['is_npc'])): ?>
                                          <div style="position:absolute; top:-5px; right:-5px; background:#f59e0b; color:#000; font-size:9px; font-weight:800; padding:2px 6px; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.5); z-index:2;">NPC</div>
                                      <?php endif; ?>
                                      <img src="<?= htmlspecialchars($rel['image'] ?: 'https://placehold.co/70x70') ?>" class="pj-relation-img">
                                      <div class="pj-relation-name"><?= htmlspecialchars($rel['name']) ?></div>
                                      <div class="pj-relation-tag-wrap">
                                          <?php foreach ($tags as $t): $t = trim($t); if (!$t) continue; $c = $tag_colors[$t] ?? '#6366f1'; ?>
                                          <span class="pj-relation-tag" style="color:<?= $c ?>; background:<?= $c ?>22;"><?= htmlspecialchars($t) ?></span>
                                          <?php endforeach; ?>
                                      </div>
                                      <?php if (!empty($rel['desc'])): ?>
                                          <div style="font-size:11px; color:var(--text-muted); margin-top:8px; line-height:1.4;"><?= htmlspecialchars($rel['desc']) ?></div>
                                      <?php endif; ?>
                                  </div>
                                  <?php if (!empty($rel['pj_id'])): ?>
                                      </a>
                                  <?php endif; ?>
                              <?php endforeach; ?>
                              </div>
                          </div>
                      </div>
                  </div>
              <?php endif; ?>
          </div>

          <?php if ($can_view_private): ?>
          <!-- TAB: TECNICAS -->
          <div id="pjTab_tecnicas" class="pj-preview-tab-content">
              <div style="padding: 50px 30px; text-align:center; background: var(--bg-surface); border-radius: var(--radius-md); border: 1px dashed var(--border-color);">
                  <i class="fas fa-tools" style="font-size: 50px; color: var(--text-muted); opacity: 0.5; margin-bottom:20px;"></i>
                  <h4 style="color:var(--text-primary); margin-bottom:10px; font-size:20px;">Sección en Mantenimiento</h4>
                  <p style="color:var(--text-muted); font-size:14px;">El gestor de técnicas de combate está siendo desarrollado por el staff y estará disponible próximamente.</p>
              </div>
          </div>

          <!-- TAB: GESTION -->
          <div id="pjTab_gestion" class="pj-preview-tab-content">
              <div style="padding: 50px 30px; text-align:center; background: var(--bg-surface); border-radius: var(--radius-md); border: 1px dashed var(--border-color);">
                  <i class="fas fa-cogs" style="font-size: 50px; color: var(--text-muted); opacity: 0.5; margin-bottom:20px;"></i>
                  <h4 style="color:var(--text-primary); margin-bottom:10px; font-size:20px;">Panel de Gestión en Mantenimiento</h4>
                  <p style="color:var(--text-muted); font-size:14px;">El panel de administración del personaje, inventario y consumibles se encuentra bajo construcción.</p>
              </div>
          </div>
          <?php endif; ?>
      </div>
      
  </div>
  </div>
  
  <?php if ($can_edit): ?>
  <!-- MODAL DIARIO -->
  <div id="modal_diario" class="pj-modal-overlay" onclick="if(event.target===this)this.style.display='none'">
      <div class="pj-modal">
          <div class="pj-modal-title">Añadir Entrada al Diario</div>
          <div class="form-group">
              <label>Fecha en el Rol</label>
              <div style="display:flex; gap:12px;">
                  <div style="flex:1;">
                      <label style="font-size:10px; margin-bottom:4px;">Día (1-100)</label>
                      <input type="number" id="diario_day" class="textbox" min="1" max="100" placeholder="Ej: 1">
                  </div>
                  <div style="flex:1;">
                      <label style="font-size:10px; margin-bottom:4px;">Estación</label>
                      <select id="diario_season" class="textbox">
                          <option value="0">Primavera</option>
                          <option value="1">Verano</option>
                          <option value="2">Otoño</option>
                          <option value="3">Invierno</option>
                      </select>
                  </div>
                  <div style="flex:1;">
                      <label style="font-size:10px; margin-bottom:4px;">Año</label>
                      <input type="number" id="diario_year" class="textbox" min="1" placeholder="Ej: 1">
                  </div>
              </div>
          </div>
          <div class="form-group">
              <label>Categoría</label>
              <div style="display:flex; gap:8px; flex-wrap:wrap;">
                  <?php $cat_list_display = ['Pasado'=>'#8b5cf6','Presente'=>'#10b981','Mision'=>'#f59e0b','Evento'=>'#3b82f6','Trama'=>'#ef4444','Fic'=>'#ec4899']; foreach ($cat_list_display as $cn => $cc): ?>
                  <span class="pj-cat-picker <?= $cn === 'Presente' ? 'active' : '' ?>" style="color:<?= $cc ?>;background:<?= $cc ?>22;border:2px solid <?= $cc ?>44;" data-cat="<?= $cn ?>" onclick="selectDiaryCat(this)"><?= $cn ?></span>
                  <?php endforeach; ?>
              </div>
              <input type="hidden" id="diario_cat" value="Presente">
          </div>
          <div class="form-group">
              <label>Descripción Corta</label>
              <textarea id="diario_desc" class="textbox" rows="4" placeholder="Resumen de los hechos..."></textarea>
          </div>
          <div class="form-group">
              <label>Link al Tema (Opcional)</label>
              <input type="url" id="diario_link" class="textbox" placeholder="https://...">
          </div>
          <div class="pj-modal-actions">
              <button class="pj-btn-add pj-btn-cancel" onclick="document.getElementById('modal_diario').style.display='none'">Cancelar</button>
              <button class="pj-btn-add" onclick="saveCronologia('diario')"><i class="fas fa-save"></i> Guardar</button>
          </div>
      </div>
  </div>

  <!-- MODAL RELACION -->
  <div id="modal_relacion" class="pj-modal-overlay" onclick="if(event.target===this)this.style.display='none'">
      <div class="pj-modal">
          <div class="pj-modal-title">Añadir Relación</div>
          <div class="form-group">
              <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                  <input type="checkbox" id="rel_is_npc" onchange="document.getElementById('rel_npc_box').style.display=this.checked?'block':'none'; document.getElementById('rel_pj_box').style.display=this.checked?'none':'block';">
                  Es un NPC (Personaje No Jugador)
              </label>
          </div>
          <div class="form-group" id="rel_pj_box">
              <label>Personaje del Foro <span style="color:var(--text-muted);font-weight:400;text-transform:none;">— empieza a escribir para buscar</span></label>
              <input type="text" id="rel_pj_search" class="textbox" placeholder="Buscar personaje..." autocomplete="off" oninput="searchPersonaje(this.value)">
              <select id="rel_pj_id" style="display:none;">
                  <option value="">Selecciona un personaje</option>
                  <?php foreach($all_chars as $c): ?>
                  <option value="<?= $c['id'] ?>" data-name="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                  <?php endforeach; ?>
              </select>
              <div id="rel_pj_results" style="margin-top:6px;display:flex;flex-wrap:wrap;gap:6px;"></div>
          </div>
          <div class="form-group" id="rel_npc_box" style="display:none;">
              <label>Nombre del NPC</label>
              <input type="text" id="rel_npc_name" class="textbox" placeholder="Ej: Alcalde de la ciudad">
          </div>
          <div class="form-group">
              <label>Relación (Etiquetas) — haz clic para añadir varias</label>
              <div class="pj-tag-selector" id="rel_tag_container">
                  <?php
                  $tag_list = ['Amigo','Compañero','Aliado','Rival','Enemigo','Némesis','Familiar','Hermano','Hermana','Padre','Madre','Maestro','Mentor','Aprendiz','Protegido','Interés Romántico','Cónyuge','Amante','Conocido','Socio','Cómplice','Subordinado','Superior','Adversario','Seguidor','Líder','Miembro'];
                  $tcolors = ['Amigo'=>'#10b981','Compañero'=>'#3b82f6','Aliado'=>'#3b82f6','Rival'=>'#f59e0b','Enemigo'=>'#ef4444','Némesis'=>'#ef4444','Familiar'=>'#ec4899','Hermano'=>'#ec4899','Hermana'=>'#ec4899','Padre'=>'#8b5cf6','Madre'=>'#8b5cf6','Maestro'=>'#f97316','Mentor'=>'#f97316','Aprendiz'=>'#06b6d4','Protegido'=>'#06b6d4','Interés Romántico'=>'#ec4899','Cónyuge'=>'#ec4899','Amante'=>'#ec4899','Conocido'=>'#6b7280','Socio'=>'#8b5cf6','Cómplice'=>'#8b5cf6','Subordinado'=>'#64748b','Superior'=>'#64748b','Adversario'=>'#f59e0b','Seguidor'=>'#06b6d4','Líder'=>'#f97316','Miembro'=>'#6b7280'];
                  foreach ($tag_list as $t): $c = $tcolors[$t] ?? '#6366f1'; ?>
                  <span class="pj-tag-option" style="color:<?= $c ?>;background:<?= $c ?>22;border-color:<?= $c ?>44;" data-tag="<?= htmlspecialchars($t) ?>" onclick="toggleTag(this)"><?= htmlspecialchars($t) ?></span>
                  <?php endforeach; ?>
              </div>
              <input type="hidden" id="rel_tags" value="">
          </div>
          <div class="form-group">
              <label>Descripción Corta</label>
              <input type="text" id="rel_desc" class="textbox" placeholder="Breve nota sobre la relación...">
          </div>
          <div class="form-group">
              <label>Imagen (URL 70x70 aprox)</label>
              <input type="url" id="rel_img" class="textbox" placeholder="https://i.imgur.com/...">
          </div>
          <div class="pj-modal-actions">
              <button class="pj-btn-add pj-btn-cancel" onclick="document.getElementById('modal_relacion').style.display='none'">Cancelar</button>
              <button class="pj-btn-add" onclick="saveCronologia('relacion')"><i class="fas fa-save"></i> Guardar</button>
          </div>
      </div>
  </div>

  <!-- MODAL GESTIONAR DIARIO -->
  <div id="modal_gestionar_diario" class="pj-modal-overlay" onclick="if(event.target===this)this.style.display='none'">
      <div class="pj-modal">
          <div class="pj-modal-title">Gestionar Entradas del Diario</div>
          <div class="pj-edit-list">
              <?php $s_names = ['Primavera', 'Verano', 'Otoño', 'Invierno']; if (empty($char['cronologia']['diario'])): ?>
              <p style="color:var(--text-muted);text-align:center;padding:20px;font-size:14px;">No hay entradas en el diario.</p>
              <?php else: foreach ($char['cronologia']['diario'] as $entry):
                  $d = $entry['day'] ?? '?'; $s_id = $entry['season'] ?? 0; $y = $entry['year'] ?? '?';
                  $fecha_str = "Día {$d} de " . ($s_names[$s_id] ?? 'Desconocida') . ", Año {$y}";
                  $ec = $entry['category'] ?? 'Presente'; $cc = $cat_list[$ec] ?? '#6366f1';
              ?>
              <div class="pj-edit-item" data-eid="<?= htmlspecialchars($entry['id'] ?? '') ?>"
                   data-day="<?= (int)($entry['day'] ?? 1) ?>" data-season="<?= (int)($entry['season'] ?? 0) ?>"
                   data-year="<?= (int)($entry['year'] ?? 1) ?>" data-cat="<?= htmlspecialchars($ec) ?>"
                   data-desc="<?= htmlspecialchars($entry['desc'] ?? '') ?>" data-link="<?= htmlspecialchars($entry['link'] ?? '') ?>">
                  <div class="pj-edit-item-body">
                      <div style="font-size:13px;font-weight:700;color:var(--text-primary);"><?= htmlspecialchars($fecha_str) ?></div>
                      <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                          <span style="color:<?= $cc ?>;font-weight:700;"><?= htmlspecialchars($ec) ?></span>
                          &mdash; <?= htmlspecialchars(substr($entry['desc'] ?? '', 0, 80)) ?><?= strlen($entry['desc'] ?? '') > 80 ? '&#8230;' : '' ?>
                      </div>
                  </div>
                  <div class="pj-edit-item-actions">
                      <button class="pj-edit-btn pj-edit-btn-edit" title="Editar" onclick="editDiarioEntry(this)"><i class="fas fa-pen"></i></button>
                      <button class="pj-edit-btn pj-edit-btn-del" title="Eliminar" onclick="deleteEntry('diario','<?= htmlspecialchars($entry['id'] ?? '') ?>')"><i class="fas fa-trash"></i></button>
                  </div>
              </div>
              <?php endforeach; endif; ?>
          </div>
          <div class="pj-modal-actions">
              <button class="pj-btn-add pj-btn-cancel" onclick="document.getElementById('modal_gestionar_diario').style.display='none'">Cerrar</button>
          </div>
      </div>
  </div>

  <!-- MODAL EDITAR RELACIONES Y GRUPOS -->
  <div id="modal_gestionar_relaciones" class="pj-modal-overlay" onclick="if(event.target===this)this.style.display='none'">
      <div class="pj-modal" style="width: 700px; max-width: 95vw;">
          <div class="pj-modal-title">Editar Relaciones, Grupos y Conexiones</div>
          <div class="pj-scroll-box" style="height: 350px; padding:0; background:transparent; border:none;">
              <h4 style="color:#fff; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:5px; margin-bottom:15px;">Contactos</h4>
              <?php if (empty($char['cronologia']['relaciones'])): ?>
                  <p style="color:var(--text-muted); font-size:13px; text-align:center;">No hay relaciones registradas.</p>
              <?php else: ?>
                  <div class="pj-edit-list" style="margin-bottom:20px;">
                      <?php foreach ($char['cronologia']['relaciones'] as $rel): 
                          $rtags = $rel['tags'] ?? [];
                          if(empty($rtags) && !empty($rel['relation'])) $rtags = [$rel['relation']];
                          if(!is_array($rtags)) $rtags = [$rtags];
                      ?>
                      <div class="pj-edit-item" data-eid="<?= htmlspecialchars((string)$rel['id']) ?>"
                           data-is-npc="<?= !empty($rel['is_npc']) ? '1' : '0' ?>"
                           data-name="<?= htmlspecialchars($rel['name']) ?>"
                           data-pj-id="<?= (int)($rel['pj_id'] ?? 0) ?>"
                           data-desc="<?= htmlspecialchars($rel['desc'] ?? '') ?>"
                           data-img="<?= htmlspecialchars($rel['image'] ?? '') ?>"
                           data-tags="<?= htmlspecialchars(json_encode($rtags, JSON_HEX_APOS|JSON_HEX_QUOT)) ?>">
                          <div style="display:flex; align-items:center; gap:15px; flex:1;">
                              <img src="<?= htmlspecialchars($rel['image'] ?: 'https://placehold.co/40x40') ?>" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                              <div>
                                  <div style="font-size:15px; font-weight:700; color:var(--text-primary);">
                                      <?= htmlspecialchars($rel['name']) ?>
                                      <?php if(!empty($rel['is_npc'])): ?><span style="font-size:10px; background:#f59e0b; color:#000; padding:2px 6px; border-radius:6px; font-weight:800; margin-left:8px; vertical-align:middle; display:inline-block;">NPC</span><?php endif; ?>
                                  </div>
                                  <div style="font-size:12px; margin-top:6px;">
                                      <?php foreach ($rtags as $t): $t = trim($t); if (!$t) continue; $c = $tag_colors[$t] ?? '#6366f1'; ?>
                                          <span style="color:<?= $c ?>; margin-right:10px; font-weight:600;"><?= htmlspecialchars($t) ?></span>
                                      <?php endforeach; ?>
                                  </div>
                              </div>
                          </div>
                          <div class="pj-edit-item-actions">
                              <button class="pj-edit-btn pj-edit-btn-edit" title="Editar" onclick="editRelacionEntry(this)"><i class="fas fa-edit"></i></button>
                              <button class="pj-edit-btn pj-edit-btn-del" title="Eliminar" onclick="if(confirm('¿Eliminar contacto?')) deleteEntry('relacion', '<?= htmlspecialchars((string)$rel['id']) ?>')"><i class="fas fa-trash"></i></button>
                          </div>
                      </div>
                      <?php endforeach; ?>
                  </div>
              <?php endif; ?>

              <h4 style="color:#fff; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:5px; margin-bottom:15px; margin-top:30px;">Grupos</h4>
              <?php if (empty($char['cronologia']['groups'])): ?>
                  <p style="color:var(--text-muted); font-size:13px; text-align:center;">No hay grupos creados.</p>
              <?php else: ?>
                  <div class="pj-edit-list">
                      <?php foreach ($char['cronologia']['groups'] as $grp): ?>
                      <div class="pj-edit-item">
                          <div style="display:flex; align-items:center; gap:12px; flex:1;">
                              <span style="display:inline-block; width:16px; height:16px; border-radius:50%; background:<?= $grp['color'] ?>;"></span>
                              <div style="font-size:15px; font-weight:700; color:var(--text-primary);"><?= htmlspecialchars($grp['name']) ?></div>
                          </div>
                          <div style="font-size:13px; color:var(--text-muted); text-align:center; padding:0 20px; font-weight:600;">
                              <?= count($grp['members'] ?? []) ?> miembros
                          </div>
                          <div class="pj-edit-item-actions">
                              <button class="pj-edit-btn pj-edit-btn-edit" title="Editar" onclick="editGroupEntry('<?= htmlspecialchars((string)$grp['id']) ?>', '<?= htmlspecialchars(json_encode($grp, JSON_HEX_APOS|JSON_HEX_QUOT)) ?>')"><i class="fas fa-edit"></i></button>
                              <button class="pj-edit-btn pj-edit-btn-del" title="Eliminar" onclick="if(confirm('¿Eliminar grupo?')) deleteEntry('group', '<?= htmlspecialchars((string)$grp['id']) ?>')"><i class="fas fa-trash"></i></button>
                          </div>
                      </div>
                      <?php endforeach; ?>
                  </div>
              <?php endif; ?>

              <h4 style="color:#fff; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:5px; margin-bottom:15px; margin-top:30px;">Conexiones</h4>
              <?php if (empty($char['cronologia']['connections'])): ?>
                  <p style="color:var(--text-muted); font-size:13px; text-align:center;">No hay conexiones registradas.</p>
              <?php else: ?>
                  <div class="pj-edit-list">
                      <?php foreach ($char['cronologia']['connections'] as $conn): ?>
                      <div class="pj-edit-item">
                          <div style="display:flex; align-items:center; gap:12px; flex:1; font-size:13px;">
                              <span style="font-weight:700;"><?= htmlspecialchars($conn['label']) ?></span>
                              <span style="color:var(--text-muted);">(<?= htmlspecialchars($conn['source_name'] ?? 'ID:'.$conn['source']) ?> ↔ <?= htmlspecialchars($conn['target_name'] ?? 'ID:'.$conn['target']) ?>)</span>
                          </div>
                          <div class="pj-edit-item-actions">
                              <button class="pj-edit-btn pj-edit-btn-edit" title="Editar" onclick="editConnectionEntry('<?= htmlspecialchars((string)$conn['id']) ?>', '<?= htmlspecialchars(json_encode($conn, JSON_HEX_APOS|JSON_HEX_QUOT)) ?>')"><i class="fas fa-edit"></i></button>
                              <button class="pj-edit-btn pj-edit-btn-del" title="Eliminar" onclick="if(confirm('¿Eliminar conexión?')) deleteEntry('connection', '<?= htmlspecialchars((string)$conn['id']) ?>')"><i class="fas fa-trash"></i></button>
                          </div>
                      </div>
                      <?php endforeach; ?>
                  </div>
              <?php endif; ?>
          </div>
          <div style="text-align:right; margin-top:20px;">
              <button class="pj-btn-add pj-btn-cancel" onclick="document.getElementById('modal_gestionar_relaciones').style.display='none'">Cerrar</button>
          </div>
      </div>
  </div>

  <!-- MODAL GRUPO -->
  <div id="modal_group" class="pj-modal-overlay" onclick="if(event.target===this)this.style.display='none'">
      <div class="pj-modal" style="width: 500px;">
          <div class="pj-modal-title" id="group_modal_title">Crear Grupo</div>
          
          <div class="form-group">
              <label>Nombre del Grupo</label>
              <input type="text" id="grp_name" class="textbox" placeholder="Ej: La Tripulación, Familia Real, etc.">
          </div>
          
          <div class="form-group">
              <label>Color del Grupo</label>
              <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:8px;" id="grp_colors">
                  <?php 
                  $g_colors = ['#10b981','#3b82f6','#6366f1','#8b5cf6','#ec4899','#ef4444','#f97316','#f59e0b'];
                  foreach ($g_colors as $c): ?>
                      <div class="grp-color-swatch" data-color="<?= $c ?>" style="width:28px; height:28px; border-radius:50%; background:<?= $c ?>; cursor:pointer; border:2px solid transparent; transition:transform 0.15s;" onclick="selectGroupColor(this)"></div>
                  <?php endforeach; ?>
              </div>
              <input type="hidden" id="grp_color" value="#6366f1">
          </div>

          <div class="form-group">
              <label>Seleccionar Miembros (Mín. 2)</label>
              <div class="pj-scroll-box" style="height: 180px; padding:10px; background:rgba(0,0,0,0.15); border:1px solid rgba(255,255,255,0.05); margin-bottom:0;">
                  <?php if (empty($char['cronologia']['relaciones'])): ?>
                      <div style="font-size:12px; color:var(--text-muted); text-align:center; padding-top:20px;">No tienes contactos. Añade contactos primero.</div>
                  <?php else: ?>
                      <?php foreach ($char['cronologia']['relaciones'] as $rel): ?>
                      <label style="display:flex; align-items:center; gap:10px; cursor:pointer; padding:8px; border-radius:6px; transition:background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.background='transparent'">
                          <input type="checkbox" name="grp_members[]" value="<?= htmlspecialchars((string)$rel['id']) ?>" style="width:16px; height:16px;">
                          <img src="<?= htmlspecialchars($rel['image'] ?: 'https://placehold.co/24x24') ?>" style="width:24px; height:24px; border-radius:50%; object-fit:cover;">
                          <span style="font-size:13px; color:var(--text-primary); text-transform:none; letter-spacing:normal; font-weight:normal;"><?= htmlspecialchars($rel['name']) ?></span>
                      </label>
                      <?php endforeach; ?>
                  <?php endif; ?>
              </div>
          </div>

          <div style="text-align:right; margin-top:30px;">
              <button class="pj-btn-add pj-btn-cancel" style="margin-right:10px;" onclick="document.getElementById('modal_group').style.display='none'">Cancelar</button>
              <button class="pj-btn-add" onclick="saveCronologia('group')"><i class="fas fa-save"></i> Guardar</button>
          </div>
      </div>
  </div>

  <!-- MODAL CONEXION -->
  <div id="modal_connection" class="pj-modal-overlay" onclick="if(event.target===this)this.style.display='none'">
      <div class="pj-modal" style="width: 500px;">
          <div class="pj-modal-title" id="conn_modal_title">Crear Conexión</div>
          
          <div class="form-group">
              <label>Contacto A</label>
              <select id="conn_source" class="textbox">
                  <option value="">Selecciona Contacto...</option>
                  <?php if (!empty($char['cronologia']['relaciones'])): foreach ($char['cronologia']['relaciones'] as $rel): ?>
                      <option value="<?= htmlspecialchars((string)$rel['id']) ?>"><?= htmlspecialchars($rel['name']) ?></option>
                  <?php endforeach; endif; ?>
              </select>
          </div>

          <div class="form-group">
              <label>Contacto B</label>
              <select id="conn_target" class="textbox">
                  <option value="">Selecciona Contacto...</option>
                  <?php if (!empty($char['cronologia']['relaciones'])): foreach ($char['cronologia']['relaciones'] as $rel): ?>
                      <option value="<?= htmlspecialchars((string)$rel['id']) ?>"><?= htmlspecialchars($rel['name']) ?></option>
                  <?php endforeach; endif; ?>
              </select>
          </div>
          
          <div class="form-group">
              <label>Nombre de la Relación (Ej: Novios, Hermanos)</label>
              <input type="text" id="conn_label" class="textbox" placeholder="Aparecerá en la línea...">
          </div>
          
          <div class="form-group">
              <label>Color de la Línea</label>
              <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:8px;" id="conn_colors">
                  <?php $g_colors = ['#10b981','#3b82f6','#6366f1','#8b5cf6','#ec4899','#ef4444','#f97316','#f59e0b']; foreach ($g_colors as $c): ?>
                      <div class="conn-color-swatch" data-color="<?= $c ?>" style="width:28px; height:28px; border-radius:50%; background:<?= $c ?>; cursor:pointer; border:2px solid transparent; transition:transform 0.15s;" onclick="selectConnColor(this)"></div>
                  <?php endforeach; ?>
              </div>
              <input type="hidden" id="conn_color" value="#ec4899">
          </div>

          <div style="text-align:right; margin-top:30px;">
              <button class="pj-btn-add pj-btn-cancel" style="margin-right:10px;" onclick="document.getElementById('modal_connection').style.display='none'">Cancelar</button>
              <button class="pj-btn-add" onclick="saveCronologia('connection')"><i class="fas fa-save"></i> Guardar</button>
          </div>
      </div>
  </div>

  <?php endif; ?>
  <?php endif; ?>
</div>

<script>
var selectedTags = new Set();
var selectedPjId = 0;
var selectedPjName = '';
var editingEntryId = null;

function toggleTag(el) {
    var tag = el.dataset.tag;
    if (selectedTags.has(tag)) {
        selectedTags.delete(tag);
        el.classList.remove('selected');
    } else {
        selectedTags.add(tag);
        el.classList.add('selected');
    }
    updateTagsHidden();
}

function updateTagsHidden() {
    document.getElementById('rel_tags').value = JSON.stringify(Array.from(selectedTags));
}

function searchPersonaje(q) {
    var select = document.getElementById('rel_pj_id');
    var results = document.getElementById('rel_pj_results');
    results.innerHTML = '';
    if (!q || q.length < 1) return;
    var found = false;
    for (var i = 0; i < select.options.length; i++) {
        var opt = select.options[i];
        if (!opt.value) continue;
        var name = opt.getAttribute('data-name') || opt.text;
        if (name.toLowerCase().indexOf(q.toLowerCase()) !== -1) {
            var chip = document.createElement('span');
            chip.className = 'pj-tag-option selected';
            chip.style.cssText = 'color:#3b82f6;background:#3b82f622;border-color:#3b82f6;';
            chip.textContent = name;
            chip.onclick = function(n, id) { return function() { selectPersonaje(id, n); }; }(name, opt.value);
            results.appendChild(chip);
            found = true;
        }
    }
    if (!found) {
        results.innerHTML = '<span style="color:var(--text-muted);font-size:12px;">Sin resultados</span>';
    }
}

function selectPersonaje(id, name) {
    selectedPjId = parseInt(id);
    selectedPjName = name;
    document.getElementById('rel_pj_search').value = name;
    document.getElementById('rel_pj_results').innerHTML = '';
    /* highlight selected */
    var chips = document.querySelectorAll('#rel_pj_results .pj-tag-option');
    chips.forEach(function(c){ c.style.borderColor = '#10b981'; });
}

function switchPjTab(tabId, tabEl) {
    document.querySelectorAll('.pj-preview-tab').forEach(function(t){ t.classList.remove('active'); });
    document.querySelectorAll('.pj-preview-tab-content').forEach(function(c){ c.classList.remove('active'); });
    tabEl.classList.add('active');
    document.getElementById('pjTab_' + tabId).classList.add('active');
}

function resetTagSelector() {
    selectedTags.clear();
    document.querySelectorAll('#rel_tag_container .pj-tag-option').forEach(function(el) { el.classList.remove('selected'); });
    document.getElementById('rel_tags').value = '';
    selectedPjId = 0;
    selectedPjName = '';
    document.getElementById('rel_pj_search').value = '';
    document.getElementById('rel_pj_results').innerHTML = '';
}

/* Reset tags when modal opens via Añadir (not via edit) */
document.addEventListener('DOMContentLoaded', function() {
    var modalRel = document.getElementById('modal_relacion');
    if (modalRel) {
        var obs = new MutationObserver(function() {
            if (modalRel.style.display === 'flex' && !editingEntryId) resetTagSelector();
        });
        obs.observe(modalRel, { attributes: true, attributeFilter: ['style'] });
    }
});

function openEditDiario() {
    editingEntryId = null;
    document.getElementById('modal_gestionar_diario').style.display = 'flex';
}

function openEditRelacion() {
    editingEntryId = null;
    document.getElementById('modal_gestionar_relaciones').style.display = 'flex';
}

function editDiarioEntry(btn) {
    var item = btn.closest('.pj-edit-item');
    if (!item) return;
    document.getElementById('diario_day').value = item.dataset.day;
    document.getElementById('diario_season').value = item.dataset.season;
    document.getElementById('diario_year').value = item.dataset.year;
    document.getElementById('diario_desc').value = item.dataset.desc;
    document.getElementById('diario_link').value = item.dataset.link;
    var cat = item.dataset.cat || 'Presente';
    document.querySelectorAll('.pj-cat-picker').forEach(function(c) {
        c.classList.toggle('active', c.dataset.cat === cat);
    });
    document.getElementById('diario_cat').value = cat;
    editingEntryId = item.dataset.eid;
    document.getElementById('modal_gestionar_diario').style.display = 'none';
    document.getElementById('modal_diario').style.display = 'flex';
}

function editRelacionEntry(btn) {
    var item = btn.closest('.pj-edit-item');
    if (!item) return;
    var isNpc = item.dataset.isNpc === '1';
    document.getElementById('rel_is_npc').checked = isNpc;
    document.getElementById('rel_npc_box').style.display = isNpc ? 'block' : 'none';
    document.getElementById('rel_pj_box').style.display = isNpc ? 'none' : 'block';
    if (isNpc) {
        document.getElementById('rel_npc_name').value = item.dataset.name;
    } else {
        document.getElementById('rel_pj_search').value = item.dataset.name;
        selectedPjId = parseInt(item.dataset.pjId) || 0;
        selectedPjName = item.dataset.name || '';
    }
    document.getElementById('rel_desc').value = item.dataset.desc || '';
    document.getElementById('rel_img').value = item.dataset.img || '';
    try { var tags = JSON.parse(item.dataset.tags); } catch(e) { var tags = []; }
    selectedTags.clear();
    document.querySelectorAll('#rel_tag_container .pj-tag-option').forEach(function(el) { el.classList.remove('selected'); });
    tags.forEach(function(t) {
        if (!t) return;
        selectedTags.add(t);
        var chip = document.querySelector('#rel_tag_container .pj-tag-option[data-tag="' + t.replace(/"/g, '&quot;') + '"]');
        if (chip) chip.classList.add('selected');
    });
    updateTagsHidden();
    editingEntryId = item.dataset.eid;
    document.getElementById('modal_gestionar_relaciones').style.display = 'none';
    document.getElementById('modal_relacion').style.display = 'flex';
}

function selectGroupColor(el) {
    document.querySelectorAll('.grp-color-swatch').forEach(function(c) {
        c.style.transform = 'none';
        c.style.borderColor = 'transparent';
    });
    el.style.transform = 'scale(1.2)';
    el.style.borderColor = '#fff';
    document.getElementById('grp_color').value = el.dataset.color;
}

function selectConnColor(el) {
    document.querySelectorAll('.conn-color-swatch').forEach(function(c) {
        c.style.transform = 'none';
        c.style.borderColor = 'transparent';
    });
    el.style.transform = 'scale(1.2)';
    el.style.borderColor = '#fff';
    document.getElementById('conn_color').value = el.dataset.color;
}

function editGroupEntry(id, jsonStr) {
    try {
        var grp = JSON.parse(jsonStr);
        document.getElementById('group_modal_title').textContent = 'Editar Grupo';
        document.getElementById('grp_name').value = grp.name || '';
        
        var color = grp.color || '#6366f1';
        document.getElementById('grp_color').value = color;
        document.querySelectorAll('.grp-color-swatch').forEach(function(c) {
            if (c.dataset.color === color) {
                c.style.transform = 'scale(1.2)';
                c.style.borderColor = '#fff';
            } else {
                c.style.transform = 'none';
                c.style.borderColor = 'transparent';
            }
        });
        
        var members = grp.members || [];
        document.querySelectorAll('input[name="grp_members[]"]').forEach(function(cb) {
            cb.checked = members.indexOf(cb.value) !== -1;
        });
        
        editingEntryId = id;
        document.getElementById('modal_gestionar_relaciones').style.display = 'none';
        document.getElementById('modal_group').style.display = 'flex';
    } catch (e) {
        console.error("Error parsing group JSON", e);
    }
}

function editConnectionEntry(id, jsonStr) {
    try {
        var conn = JSON.parse(jsonStr);
        document.getElementById('conn_modal_title').textContent = 'Editar Conexión';
        document.getElementById('conn_label').value = conn.label || '';
        document.getElementById('conn_source').value = conn.source || '';
        document.getElementById('conn_target').value = conn.target || '';
        
        var color = conn.color || '#ec4899';
        document.getElementById('conn_color').value = color;
        document.querySelectorAll('.conn-color-swatch').forEach(function(c) {
            if (c.dataset.color === color) {
                c.style.transform = 'scale(1.2)';
                c.style.borderColor = '#fff';
            } else {
                c.style.transform = 'none';
                c.style.borderColor = 'transparent';
            }
        });
        
        editingEntryId = id;
        document.getElementById('modal_gestionar_relaciones').style.display = 'none';
        document.getElementById('modal_connection').style.display = 'flex';
    } catch (e) {
        console.error("Error parsing connection JSON", e);
    }
}

function deleteEntry(type, id) {
    if (!confirm('¿Estás seguro de eliminar esta entrada?')) return;
    fetch(AJAX_BASE + '/update_cronologia.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pj_id: <?= (int)($char['id'] ?? 0) ?>, type: type, action: 'delete', entry_id: id })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) { window.location.reload(); }
        else { alert('Error: ' + (data.error ? data.error.message : 'Desconocido')); }
    })
    .catch(function() { alert('Error de conexión.'); });
}

function selectDiaryCat(el) {
    document.querySelectorAll('.pj-cat-picker').forEach(function(c){ c.classList.remove('active'); });
    el.classList.add('active');
    document.getElementById('diario_cat').value = el.dataset.cat;
}

<?php if ($can_edit): ?>
var AJAX_BASE = '<?= rtrim($bb, '/') ?>/game/ajax';

function saveCronologia(type) {
    var payload = { pj_id: <?= (int)($char['id'] ?? 0) ?>, type: type };
    if (type === 'diario') {
        payload.day = parseInt(document.getElementById('diario_day').value) || 1;
        payload.season = parseInt(document.getElementById('diario_season').value) || 0;
        payload.year = parseInt(document.getElementById('diario_year').value) || 1;
        payload.category = document.getElementById('diario_cat').value;
        payload.desc = document.getElementById('diario_desc').value;
        payload.link = document.getElementById('diario_link').value;
        if(!payload.desc) { alert("La Descripción es obligatoria."); return; }
    } else if (type === 'relacion') {
        var is_npc = document.getElementById('rel_is_npc').checked;
        payload.is_npc = is_npc;
        if (is_npc) {
            payload.npc_name = document.getElementById('rel_npc_name').value;
            if (!payload.npc_name) { alert("El nombre del NPC es obligatorio."); return; }
        } else {
            payload.target_pj_id = selectedPjId;
            payload.target_pj_name = selectedPjName;
            if (!payload.target_pj_id) { alert("Busca y selecciona un personaje de los resultados."); return; }
        }
        payload.tags = Array.from(selectedTags);
        payload.desc = document.getElementById('rel_desc').value;
        payload.image = document.getElementById('rel_img').value;
        if (payload.tags.length === 0) { alert("Selecciona al menos una etiqueta de relación."); return; }
    } else if (type === 'group') {
        payload.name = document.getElementById('grp_name').value;
        payload.color = document.getElementById('grp_color').value;
        var members = [];
        document.querySelectorAll('input[name="grp_members[]"]:checked').forEach(function(cb) {
            members.push(cb.value);
        });
        payload.members = members;
        if (!payload.name) { alert("El nombre del grupo es obligatorio."); return; }
        if (members.length < 2) { alert("Selecciona al menos 2 miembros para el grupo."); return; }
    } else if (type === 'connection') {
        payload.source = document.getElementById('conn_source').value;
        payload.target = document.getElementById('conn_target').value;
        payload.label = document.getElementById('conn_label').value;
        payload.color = document.getElementById('conn_color').value;
        
        if (!payload.source || !payload.target) { alert("Selecciona Contacto A y Contacto B."); return; }
        if (payload.source === payload.target) { alert("El Contacto A y el Contacto B no pueden ser el mismo."); return; }
        if (!payload.label) { alert("El nombre de la conexión es obligatorio."); return; }
    }

    if (editingEntryId) { payload.entry_id = editingEntryId; }
    fetch(AJAX_BASE + '/update_cronologia.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) { window.location.reload(); }
        else { alert('Error al guardar: ' + (data.error ? data.error.message : 'Desconocido')); }
    })
    .catch(function() { alert('Error de conexión.'); });
}
<?php endif; ?>
</script>
<?php
$content = ob_get_clean();
game_render_page('Mi Personaje', $content);
