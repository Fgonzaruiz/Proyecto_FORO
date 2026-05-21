<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db, $header, $footer, $theme;

$prefix = TABLE_PREFIX;
file_put_contents(__DIR__ . '/debug.txt', "1. Init complete\n");
$user_id = (int)($mybb->user['uid'] ?? 0);

// If ?pj= is specified, load that character (if it belongs to the user)
$req_pj_id = isset($_GET['pj']) ? (int)$_GET['pj'] : 0;
file_put_contents(__DIR__ . '/debug.txt', "2. User: $user_id, Req Pj: $req_pj_id\n", FILE_APPEND);

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
    file_put_contents(__DIR__ . '/debug.txt', "3. Loading char ID: $load_id\n", FILE_APPEND);
    $query = $db->query("SELECT * FROM {$prefix}game_personajes WHERE id = {$load_id}" . ($user_id ? " AND (user_id = {$user_id} OR user_id IS NULL)" : "") . " LIMIT 1");
    file_put_contents(__DIR__ . '/debug.txt', "4. Query executed\n", FILE_APPEND);
    $row = $db->fetch_array($query);
    file_put_contents(__DIR__ . '/debug.txt', "5. Row fetched\n", FILE_APPEND);
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
        file_put_contents(__DIR__ . '/debug.txt', "6. Char array built\n", FILE_APPEND);
    } else {
        file_put_contents(__DIR__ . '/debug.txt', "6. Row is false\n", FILE_APPEND);
    }
} else {
    file_put_contents(__DIR__ . '/debug.txt', "3. No load_id\n", FILE_APPEND);
}

$bb = $mybb->settings['bburl'];
$b_url = $bb . '/images/game/personaje_banner.png';

file_put_contents(__DIR__ . '/debug.txt', "7. Starting output buffering\n", FILE_APPEND);
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
.pj-relation-tag { font-size: 11px; font-weight: bold; color: var(--accent-indigo); text-transform: uppercase; background: rgba(99,102,241,0.1); display: inline-block; padding: 3px 10px; border-radius: 12px; }

/* In-situ Modals (Beautified) */
.pj-modal-overlay { position: fixed; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.7); display:none; justify-content:center; align-items:center; z-index: 9999; backdrop-filter: blur(8px); }
.pj-modal { background: var(--bg-surface); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; width: 450px; max-width: 90vw; padding: 35px; box-shadow: 0 25px 50px rgba(0,0,0,0.8); position: relative; overflow: hidden; }
.pj-modal::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 5px; background: linear-gradient(90deg, var(--accent-indigo), var(--accent-purple)); }
.pj-modal-title { font-family: var(--font-heading); font-size: 22px; color: #fff; margin-bottom: 25px; text-align: center; font-weight: 800; }
.pj-modal .textbox { background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.05); color: #fff; border-radius: 8px; padding: 14px 15px; transition: all 0.3s; width: 100%; box-sizing: border-box; }
.pj-modal .textbox:focus { background: rgba(0,0,0,0.4); border-color: var(--accent-indigo); box-shadow: 0 0 0 3px rgba(99,102,241,0.2); outline: none; }
.pj-modal label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); font-weight: 700; margin-bottom: 8px; display: block; }
.pj-btn-add { background: linear-gradient(135deg, var(--accent-indigo), var(--accent-purple)); color: white; border: none; padding: 10px 22px; border-radius: 8px; font-size: 13px; font-weight: 800; text-transform: uppercase; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 15px rgba(99,102,241,0.3); }
.pj-btn-add:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(99,102,241,0.5); }
.pj-btn-cancel { background: rgba(255,255,255,0.05); color: var(--text-muted); border: 1px solid rgba(255,255,255,0.1); box-shadow: none; }
.pj-btn-cancel:hover { background: rgba(255,255,255,0.1); color: #fff; transform: none; box-shadow: none; }
</style>

<div class="rpg-char-page" style="max-width: 1200px; margin: 0 auto;">
  <?php if (!$user_id): ?>
    <div class="rpg-char-empty">
      <i class="fas fa-user-lock"></i>
      <h2>Debes iniciar sesi&oacute;n</h2>
      <p>Inicia sesi&oacute;n en el foro para ver tu ficha de personaje.</p>
    </div>
  <?php elseif (!$char): ?>
    <div class="rpg-char-empty">
      <i class="fas fa-user-plus"></i>
      <h2>No tienes personaje</h2>
      <p>A&uacute;n no se ha vinculado ning&uacute;n personaje a tu cuenta. ¡Ve a la gesti&oacute;n de personajes para crear uno!</p>
    </div>
  <?php else: ?>
  
  <?php
    $genes_activos = (!empty($char['linaje']['geneNames'])) ? implode(', ', $char['linaje']['geneNames']) : 'Ninguno';
    $is_owner = ($user_id && $char && $user_id === (int)$char['user_id']);
    $is_staff = (!empty($mybb->usergroup['cancp']) || !empty($mybb->usergroup['issupermod']));
    $can_view_private = ($is_owner || $is_staff);
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
              <div class="pj-preview-tab" onclick="switchPjTab('cronologia', this)"><i class="fas fa-calendar-alt"></i> Cronolog&iacute;a</div>
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
                  <?php if ($user_id === (int)$char['user_id']): ?>
                      <button class="pj-btn-add" onclick="document.getElementById('modal_diario').style.display='flex'"><i class="fas fa-plus"></i> Añadir Entrada</button>
                  <?php endif; ?>
              </div>
              
              <?php if (empty($char['cronologia']['diario'])): ?>
                  <p style="color:var(--text-muted); font-size:14px; text-align:center; margin-bottom:40px;">No hay registros en el diario.</p>
              <?php else: ?>
                  <div class="pj-scroll-box" style="height: 350px;">
                      <div class="pj-timeline">
                      <?php foreach ($char['cronologia']['diario'] as $entry): ?>
                          <div class="pj-timeline-item">
                              <div class="pj-timeline-date"><?= htmlspecialchars($entry['date']) ?></div>
                              <div class="pj-timeline-desc"><?= nl2br(htmlspecialchars($entry['desc'])) ?></div>
                              <?php if (!empty($entry['link'])): ?>
                                  <a href="<?= htmlspecialchars($entry['link']) ?>" class="pj-timeline-link" target="_blank"><i class="fas fa-book-open"></i> Leer Tema</a>
                              <?php endif; ?>
                          </div>
                      <?php endforeach; ?>
                      </div>
                  </div>
              <?php endif; ?>

              <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-color); padding-bottom:10px; margin-top:40px; margin-bottom:20px;">
                  <h3 style="font-family:var(--font-heading); font-size:18px; color:var(--text-primary); margin:0;">Red de Contactos</h3>
                  <?php if ($user_id === (int)$char['user_id']): ?>
                      <button class="pj-btn-add" onclick="document.getElementById('modal_relacion').style.display='flex'"><i class="fas fa-plus"></i> Añadir Relación</button>
                  <?php endif; ?>
              </div>

              <?php if (empty($char['cronologia']['relaciones'])): ?>
                  <p style="color:var(--text-muted); font-size:14px; text-align:center;">No hay relaciones registradas.</p>
              <?php else: ?>
                  <div class="pj-scroll-box" style="height: 350px;">
                      <div class="pj-relations-grid">
                      <?php foreach ($char['cronologia']['relaciones'] as $rel): ?>
                          <div class="pj-relation-card">
                              <img src="<?= htmlspecialchars($rel['image'] ?: 'https://placehold.co/70x70') ?>" class="pj-relation-img">
                              <div class="pj-relation-name"><?= htmlspecialchars($rel['name']) ?></div>
                              <div class="pj-relation-tag"><?= htmlspecialchars($rel['relation']) ?></div>
                              <?php if (!empty($rel['link'])): ?>
                                  <div style="margin-top:10px;">
                                      <a href="<?= htmlspecialchars($rel['link']) ?>" target="_blank" style="font-size:12px; color:var(--accent-indigo); text-decoration:none;"><i class="fas fa-external-link-alt"></i> Ver Ficha</a>
                                  </div>
                              <?php endif; ?>
                          </div>
                      <?php endforeach; ?>
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
  
  <?php if ($char && $user_id === (int)$char['user_id']): ?>
  <!-- MODAL DIARIO -->
  <div id="modal_diario" class="pj-modal-overlay" onclick="if(event.target===this)this.style.display='none'">
      <div class="pj-modal">
          <div class="pj-modal-title">Añadir Entrada al Diario</div>
          <div class="form-group">
              <label>Fecha / Época</label>
              <input type="text" id="diario_fecha" class="textbox" placeholder="Ej: 14 de Mayo, Año 1522">
          </div>
          <div class="form-group">
              <label>Descripción Corta</label>
              <textarea id="diario_desc" class="textbox" rows="4" placeholder="Resumen de los hechos..."></textarea>
          </div>
          <div class="form-group">
              <label>Link al Tema (Opcional)</label>
              <input type="url" id="diario_link" class="textbox" placeholder="https://...">
          </div>
          <div style="text-align:right; margin-top:30px;">
              <button class="pj-btn-add pj-btn-cancel" style="margin-right:10px;" onclick="document.getElementById('modal_diario').style.display='none'">Cancelar</button>
              <button class="pj-btn-add" onclick="saveCronologia('diario')"><i class="fas fa-save"></i> Guardar</button>
          </div>
      </div>
  </div>

  <!-- MODAL RELACION -->
  <div id="modal_relacion" class="pj-modal-overlay" onclick="if(event.target===this)this.style.display='none'">
      <div class="pj-modal">
          <div class="pj-modal-title">Añadir Relación</div>
          <div class="form-group">
              <label>Nombre del Personaje</label>
              <input type="text" id="rel_name" class="textbox" placeholder="Ej: Monkey D. Luffy">
          </div>
          <div class="form-group">
              <label>Relación (Tag)</label>
              <input type="text" id="rel_tag" class="textbox" placeholder="Ej: Rival, Enemigo, Capitán...">
          </div>
          <div class="form-group">
              <label>Imagen (URL 70x70 aprox)</label>
              <input type="url" id="rel_img" class="textbox" placeholder="https://i.imgur.com/...">
          </div>
          <div class="form-group">
              <label>Link a su Ficha (Opcional)</label>
              <input type="url" id="rel_link" class="textbox" placeholder="https://...">
          </div>
          <div style="text-align:right; margin-top:30px;">
              <button class="pj-btn-add pj-btn-cancel" style="margin-right:10px;" onclick="document.getElementById('modal_relacion').style.display='none'">Cancelar</button>
              <button class="pj-btn-add" onclick="saveCronologia('relacion')"><i class="fas fa-save"></i> Guardar</button>
          </div>
      </div>
  </div>
  <?php endif; ?>

  <?php endif; ?>
</div>

<script>
function switchPjTab(tabId, tabEl) {
    document.querySelectorAll('.pj-preview-tab').forEach(function(t){ t.classList.remove('active'); });
    document.querySelectorAll('.pj-preview-tab-content').forEach(function(c){ c.classList.remove('active'); });
    tabEl.classList.add('active');
    document.getElementById('pjTab_' + tabId).classList.add('active');
}

<?php if ($char && $user_id === (int)$char['user_id']): ?>
function saveCronologia(type) {
    var payload = { pj_id: <?= $char['id'] ?>, type: type };
    if (type === 'diario') {
        payload.date = document.getElementById('diario_fecha').value;
        payload.desc = document.getElementById('diario_desc').value;
        payload.link = document.getElementById('diario_link').value;
        if(!payload.date || !payload.desc) { alert("Fecha y Descripción son obligatorios."); return; }
    } else {
        payload.name = document.getElementById('rel_name').value;
        payload.relation = document.getElementById('rel_tag').value;
        payload.image = document.getElementById('rel_img').value;
        payload.link = document.getElementById('rel_link').value;
        if(!payload.name || !payload.relation) { alert("Nombre y Relación son obligatorios."); return; }
    }

    fetch('ajax/update_cronologia.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.ok) {
            window.location.reload();
        } else {
            alert('Error al guardar: ' + (data.error ? data.error.message : 'Desconocido'));
        }
    })
    .catch(err => {
        alert('Error de conexión.');
    });
}
<?php endif; ?>
</script>
<?php
file_put_contents(__DIR__ . '/debug.txt', "8. End of HTML block\n", FILE_APPEND);
$content = ob_get_clean();
file_put_contents(__DIR__ . '/debug.txt', "9. Rendering page\n", FILE_APPEND);
game_render_page('Mi Personaje', $content);
file_put_contents(__DIR__ . '/debug.txt', "10. Done\n", FILE_APPEND);
