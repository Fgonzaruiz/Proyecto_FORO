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
<div class="rpg-char-page">
  <div class="rpg-char-banner" style="background-image: url('<?= $b_url ?>');">
    <div class="rpg-char-banner-overlay">
      <h1 class="rpg-char-name"><?= $char ? htmlspecialchars($char['name']) : 'Sin Personaje' ?></h1>
      <?php if ($char): ?>
      <div class="rpg-char-badges">
        <span class="rpg-lib-modal-badge"><?= htmlspecialchars($char['race_name']) ?></span>
        <span class="rpg-lib-modal-badge"><?= htmlspecialchars($char['job_name']) ?></span>
        <span class="rpg-lib-modal-badge"><?= htmlspecialchars($char['rango']) ?></span>
        <?php if ($char['is_staff']): ?><span class="rpg-lib-modal-badge" style="background:var(--accent-indigo);color:#fff;">Staff</span><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

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
      <p>A&uacute;n no se ha vinculado ning&uacute;n personaje a tu cuenta. Contacta con el staff para crear tu ficha.</p>
    </div>
  <?php else: ?>
  <div class="rpg-char-body">
    <div class="rpg-char-grid">
      <div class="rpg-char-col-left">
        <div class="rpg-char-card glass">
          <div class="rpg-char-card-title"><i class="fas fa-address-card"></i> Informaci&oacute;n B&aacute;sica</div>
          <div class="rpg-char-info-list">
            <div class="rpg-char-info-item"><span class="rpg-char-info-lbl">Raza</span><span class="rpg-char-info-val"><?= htmlspecialchars($char['race_name']) ?></span></div>
            <div class="rpg-char-info-item"><span class="rpg-char-info-lbl">Ocupaci&oacute;n</span><span class="rpg-char-info-val"><?= htmlspecialchars($char['job_name']) ?></span></div>
            <div class="rpg-char-info-item"><span class="rpg-char-info-lbl">Rango</span><span class="rpg-char-info-val"><?= htmlspecialchars($char['rango']) ?></span></div>
            <div class="rpg-char-info-item"><span class="rpg-char-info-lbl">Tripulaci&oacute;n</span><span class="rpg-char-info-val"><?= htmlspecialchars($char['tripulacion']) ?></span></div>
            <div class="rpg-char-info-item"><span class="rpg-char-info-lbl">Recompensa</span><span class="rpg-char-info-val"><?= htmlspecialchars($char['recompensa']) ?></span></div>
          </div>
        </div>
      </div>
      <div class="rpg-char-col-right">
        <div class="rpg-char-card glass">
          <div class="rpg-char-card-title"><i class="fas fa-chart-pie"></i> Estad&iacute;sticas</div>
          <div class="rpg-char-stats-wrap">
            <div class="rpg-radar-container" id="char-radar"></div>
            <div class="rpg-char-stat-values">
              <?php foreach (['FP' => 'Fuerza', 'DP' => 'Destreza', 'RP' => 'Resistencia', 'IP' => 'Inteligencia', 'VP' => 'Voluntad', 'HP' => 'Haki'] as $k => $l): ?>
              <div class="rpg-char-stat-bar">
                <span class="rpg-char-stat-lbl"><?= $l ?></span>
                <span class="rpg-char-stat-num"><?= (int)($char['stats'][$k] ?? 0) ?></span>
                <div class="rpg-char-stat-track"><div class="rpg-char-stat-fill" style="width: <?= min(100, (int)($char['stats'][$k] ?? 0) / 150 * 100) ?>%;"></div></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="rpg-char-card glass" style="margin-top:16px;">
      <div class="rpg-char-card-title"><i class="fas fa-scroll"></i> Biograf&iacute;a</div>
      <div class="rpg-char-bio">
        <p><?= nl2br(htmlspecialchars($char['desc'])) ?></p>
        <?php if ($char['details']): ?><p style="margin-top:12px;"><?= nl2br(htmlspecialchars($char['details'])) ?></p><?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
document.addEventListener("DOMContentLoaded",function(){
<?php if ($char): ?>
var s=<?= json_encode($char['stats']) ?>;
var k=['FP','DP','RP','IP','VP','HP'],l=['Fuerza','Destreza','Resist.','Intel.','Voluntad','Haki'];
var mv=150,cx=170,cy=170,ra=100,g='',a='',lm=[];
for(var i=1;i<=5;i++){var r=ra*(i/5),p=[];for(var j=0;j<6;j++){var A=(j*60-90)*Math.PI/180;p.push((cx+r*Math.cos(A)).toFixed(1)+','+(cy+r*Math.sin(A)).toFixed(1))};g+='<polygon points="'+p.join(' ')+'" class="rpg-radar-polygon-bg"/>'}
for(var j=0;j<6;j++){var A=(j*60-90)*Math.PI/180;a+='<line x1="'+cx+'" y1="'+cy+'" x2="'+(cx+ra*Math.cos(A)).toFixed(1)+'" y2="'+(cy+ra*Math.sin(A)).toFixed(1)+'" class="rpg-radar-line"/>'}
var vp=[];for(var j=0;j<6;j++){var v=s[k[j]]||10,r=ra*Math.min(v,mv)/mv,A=(j*60-90)*Math.PI/180;vp.push((cx+r*Math.cos(A)).toFixed(1)+','+(cy+r*Math.sin(A)).toFixed(1))};vg='<polygon points="'+vp.join(' ')+'" class="rpg-radar-polygon-value"/>';
for(var j=0;j<6;j++){var lb=l[j],v=s[k[j]]||0,A=(j*60-90)*Math.PI/180,x=cx+(ra+22)*Math.cos(A),y=cy+(ra+22)*Math.sin(A),an='middle';if(Math.cos(A)>0.1)an='start';else if(Math.cos(A)<-0.1)an='end';lm.push('<text x="'+x.toFixed(1)+'" y="'+(y+4).toFixed(1)+'" text-anchor="'+an+'" class="rpg-radar-label">'+lb+' ('+v+')</text>')}
document.getElementById('char-radar').innerHTML='<svg viewBox="0 0 340 340" class="rpg-radar-svg">'+g+a+vg+lm.join('')+'</svg>';
<?php endif; ?>
});
</script>
<?php
$content = ob_get_clean();
game_render_page('Mi Personaje', $content);
