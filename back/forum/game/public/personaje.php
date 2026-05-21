<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db, $header, $footer, $theme;

$prefix = TABLE_PREFIX;
$user_id = (int)($mybb->user['uid'] ?? 0);

// If ?pj= is specified, load that character (if it belongs to the user)
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
    $query = $db->query("SELECT * FROM {$prefix}game_personajes WHERE id = {$load_id}" . ($user_id ? " AND (user_id = {$user_id} OR user_id IS NULL)" : "") . " LIMIT 1");
    $row = $db->fetch_array($query);
    if ($row) {
        $char = [
            'id'          => (int)$row['id'],
            'name'        => $row['name'],
            'race'        => $row['race'],
            'race_name'   => $row['race_name'],
            'is_staff'    => (bool)$row['is_staff'],
            'job'         => $row['occupation'],
            'job_name'    => $row['occupation_name'],
            'desc'        => $row['desc'],
            'details'     => $row['details'],
            'rango'       => $row['rango'],
            'tripulacion' => $row['tripulacion'],
            'recompensa'  => $row['recompensa'],
            'banner'      => $row['banner'],
            'avatar'      => $row['avatar'],
            'stats'       => [
                'FP' => (int)$row['stat_fp'],
                'DP' => (int)$row['stat_dp'],
                'RP' => (int)$row['stat_rp'],
                'IP' => (int)$row['stat_ip'],
                'VP' => (int)$row['stat_vp'],
                'HP' => (int)$row['stat_hp'],
            ],
        ];
    }
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
    // MOCK DATA FOR NEW FIELDS UNTIL DB MIGRATION
    $avatar_url = $char['avatar'] ?: 'https://placehold.co/320x450';
    $arquetipo = 'Desconocido';
    $edad = 'Desconocida';
    $origen = 'Desconocido';
    $pb = 'Desconocido';
    $genes_activos = 'Ninguno';
    
    // MAP OLD STATS TO NEW ONES (Temp)
    $stat_fuerza = $char['stats']['FP'] ?? 0;
    $stat_agilidad = $char['stats']['DP'] ?? 0;
    $stat_resistencia = $char['stats']['RP'] ?? 0;
    $stat_voluntad = $char['stats']['VP'] ?? 0;
  ?>

  <div style="display: flex; background: var(--bg-main); border: 1px solid var(--border-color); border-radius: var(--radius-lg); overflow: hidden; min-height: 700px; margin-top: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
      
      <!-- LEFT COLUMN (Avatar & Stats) -->
      <div style="width: 320px; background: var(--bg-surface); border-right: 1px solid var(--border-color); display:flex; flex-direction:column; flex-shrink: 0;">
          <div style="width:100%; height:450px; min-height:450px; background-size:cover; background-position:center; background-image:url('<?= htmlspecialchars($avatar_url) ?>'); border-bottom: 2px solid var(--accent-indigo);"></div>
          
          <div style="padding: 20px;">
              <h2 style="font-family:var(--font-heading); font-size:24px; color:var(--text-primary); margin-bottom:10px; text-align:center;"><?= htmlspecialchars($char['name']) ?></h2>
              
              <div style="display:flex; justify-content:center; gap:8px; flex-wrap:wrap; margin-bottom: 15px;">
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
                          <div style="font-weight:700; color:var(--text-primary); font-size:14px;"><?= $arquetipo ?></div>
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
                  <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>FUERZA</span><span><?= $stat_fuerza ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill" style="width:<?= min(100, $stat_fuerza * 10) ?>%;"></div></div>
              </div>
              <div class="rpg-preview-stat-row">
                  <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>AGILIDAD</span><span><?= $stat_agilidad ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill" style="width:<?= min(100, $stat_agilidad * 10) ?>%; background:linear-gradient(90deg,#10b981,#059669);"></div></div>
              </div>
              <div class="rpg-preview-stat-row">
                  <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>RESISTENCIA</span><span><?= $stat_resistencia ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill" style="width:<?= min(100, $stat_resistencia * 10) ?>%; background:linear-gradient(90deg,#f59e0b,#d97706);"></div></div>
              </div>
              <div class="rpg-preview-stat-row">
                  <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:bold;"><span>VOLUNTAD</span><span><?= $stat_voluntad ?></span></div>
                  <div class="rpg-preview-stat-bar"><div class="rpg-preview-stat-fill" style="width:<?= min(100, $stat_voluntad * 10) ?>%; background:linear-gradient(90deg,#ef4444,#dc2626);"></div></div>
              </div>
          </div>
      </div>
      
      <!-- RIGHT COLUMN (Tabs & Content) -->
      <div style="flex:1; padding: 40px; overflow-y:auto;">
          <div class="pj-preview-tabs">
              <div class="pj-preview-tab active" onclick="switchPjTab('bio', this)"><i class="fas fa-file-alt"></i> Biograf&iacute;a</div>
              <div class="pj-preview-tab" onclick="switchPjTab('linaje', this)"><i class="fas fa-dna"></i> Mapa Gen&eacute;tico</div>
          </div>

          <!-- TAB: BIO -->
          <div id="pjTab_bio" class="pj-preview-tab-content active">
              <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom:30px; background:var(--bg-surface); padding:20px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                  <div style="font-size:14px;"><strong>Edad:</strong> <?= $edad ?></div>
                  <div style="font-size:14px;"><strong>Origen:</strong> <?= $origen ?></div>
                  <div style="font-size:14px;"><strong>Raza:</strong> <?= htmlspecialchars($char['race_name'] ?: 'Desconocida') ?></div>
                  <div style="font-size:14px;"><strong>PB:</strong> <?= $pb ?></div>
              </div>
              
              <h3 style="font-family:var(--font-heading); font-size:18px; color:var(--text-primary); margin-bottom:15px; border-bottom:1px solid var(--border-color); padding-bottom:5px;">Apariencia F&iacute;sica</h3>
              <div style="color:var(--text-secondary); font-size:15px; line-height:1.7; white-space:pre-wrap; margin-bottom:30px;">Sin registrar en la base de datos actual.</div>
              
              <h3 style="font-family:var(--font-heading); font-size:18px; color:var(--text-primary); margin-bottom:15px; border-bottom:1px solid var(--border-color); padding-bottom:5px;">Perfil Psicol&oacute;gico</h3>
              <div style="color:var(--text-secondary); font-size:15px; line-height:1.7; white-space:pre-wrap; margin-bottom:30px;"><?= nl2br(htmlspecialchars($char['desc'] ?: 'Sin historia registrada.')) ?></div>
              
              <h3 style="font-family:var(--font-heading); font-size:18px; color:var(--text-primary); margin-bottom:15px; border-bottom:1px solid var(--border-color); padding-bottom:5px;">Extras y Notas</h3>
              <div style="color:var(--text-secondary); font-size:15px; line-height:1.7; white-space:pre-wrap;"><?= nl2br(htmlspecialchars($char['details'] ?: 'Sin notas extras.')) ?></div>
          </div>

          <!-- TAB: LINAJE -->
          <div id="pjTab_linaje" class="pj-preview-tab-content">
              <p style="color:var(--text-muted); font-size:14px; margin-bottom:20px;">Genes desbloqueados en el Mapa Gen&eacute;tico de tu personaje.</p>
              
              <div style="padding: 30px; text-align:center; background: var(--bg-surface); border-radius: var(--radius-md); border: 1px dashed var(--border-color);">
                  <i class="fas fa-dna" style="font-size: 40px; color: var(--accent-purple); opacity: 0.5; margin-bottom:15px;"></i>
                  <h4 style="color:var(--text-primary); margin-bottom:5px;">Sistema en Desarrollo</h4>
                  <p style="color:var(--text-muted); font-size:13px;">Tu mapa gen&eacute;tico se mostrar&aacute; aqu&iacute; una vez que realicemos la migraci&oacute;n de la base de datos para almacenar esta informaci&oacute;n.</p>
              </div>
          </div>
      </div>
      
  </div>
  <?php endif; ?>
</div>

<script>
function switchPjTab(tabId, tabEl) {
    document.querySelectorAll('.pj-preview-tab').forEach(function(t){ t.classList.remove('active'); });
    document.querySelectorAll('.pj-preview-tab-content').forEach(function(c){ c.classList.remove('active'); });
    tabEl.classList.add('active');
    document.getElementById('pjTab_' + tabId).classList.add('active');
}
</script>
<?php
$content = ob_get_clean();
game_render_page('Mi Personaje', $content);
