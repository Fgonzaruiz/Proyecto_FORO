<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

if (!isset($mybb) || !is_object($mybb) || (int)($mybb->user['uid'] ?? 0) === 0) {
    header('Location: ../member.php?action=login');
    exit;
}

$b_url = $mybb->settings['bburl'];
$uid = (int)$mybb->user['uid'];
$prefix = TABLE_PREFIX;

// Fetch active character
$char_id = 0;
if (function_exists('game_get_active_pj_id')) {
    $char_id = game_get_active_pj_id($uid);
} else {
    $cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
    if ($cfg = $db->fetch_array($cfg_q)) {
        $char_id = (int)$cfg['active_pj_id'];
    }
}

// Fetch character's Akuma no Mi card
$akuma_card = null;
$pre_awakening_card = null;

if ($char_id > 0) {
    $q = $db->query("
        SELECT c.id, c.name, c.tier, cc.current_rank,
               (SELECT COUNT(*) FROM {$prefix}game_post_cards pc WHERE pc.character_id = {$char_id} AND pc.card_id = c.id) as usos_totales
        FROM {$prefix}game_character_cards cc
        JOIN {$prefix}game_cards c ON cc.card_id = c.id
        WHERE cc.character_id = {$char_id} AND c.card_type = 'akuma_no_mi'
        LIMIT 1
    ");
    $akuma_card = $db->fetch_array($q);
    
    // Check if they have a Pre-Awakening card
    $q2 = $db->query("
        SELECT c.id 
        FROM {$prefix}game_character_cards cc
        JOIN {$prefix}game_cards c ON cc.card_id = c.id
        WHERE cc.character_id = {$char_id} AND (c.name LIKE '%Pre-Awakening%' OR c.name LIKE '%Despertar Incompleto%')
        LIMIT 1
    ");
    if ($db->num_rows($q2) > 0) {
        $pre_awakening_card = true;
    }
}

$is_awakening_hub = false;
$usos_base = 30;
$usos_totales = 0;
$usos_pre = 0;
$usos_final = 0;
$has_pre = false;

if ($akuma_card) {
    $is_awakening_hub = true;
    $tier = (int)($akuma_card['tier'] ?? 1);
    
    if ($tier == 3) $usos_base = 50;
    if ($tier == 4) $usos_base = 75;
    if ($tier >= 5) $usos_base = 100;
    
    $usos_pre = (int)ceil($usos_base / 2);
    $has_pre = $pre_awakening_card ? true : false;
    
    if ($has_pre) {
        $usos_final = (int)ceil($usos_base * 1.33); // 33% penalty
    } else {
        $usos_final = $usos_base;
    }
    
    $usos_totales = (int)$akuma_card['usos_totales'];
}

ob_start();
?>
<div class="rpg-peticiones rpg-akuma-hub">
  <div class="rpg-peticiones-header rpg-peticiones-header--gradient rpg-akuma-hub-header">
    <div class="rpg-peticiones-header-content">
      <a href="<?= htmlspecialchars($b_url) ?>/game/public/peticiones_general.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Solicitudes</a>
      <h1><i class="fas fa-apple-alt"></i> Solicitud Akuma no Mi</h1>
      <p>Elige c&oacute;mo deseas solicitar tu fruta del diablo o gestiona tu Awakening.</p>
    </div>
  </div>

  <?php if ($is_awakening_hub): ?>
  <!-- AWAKENING HUB -->
  <div class="rpg-awakening-hub rpg-awakening-hub-panel">
    <h2><i class="fas fa-fire-alt rpg-awakening-hub-title-icon"></i> Awakening: <?= htmlspecialchars($akuma_card['name']) ?></h2>
    <p class="rpg-awakening-hub-desc">Gestiona el progreso hacia el Despertar de tu Fruta del Diablo.</p>
    
    <div class="rpg-awakening-hub-flex">
      <div class="rpg-awakening-hub-box">
        <h4 class="rpg-awakening-hub-box-title">Progreso de Usos</h4>
        <div class="rpg-awakening-hub-progress">
          <?= $usos_totales ?> <span class="rpg-awakening-hub-progress-sub">/ <?= $usos_final ?> usos necesarios</span>
        </div>
        <progress class="rpg-awakening-hub-progress-bar" value="<?= $usos_totales ?>" max="<?= max(1, $usos_final) ?>"></progress>
      </div>
      
      <div class="rpg-awakening-hub-box">
        <h4 class="rpg-awakening-hub-box-title">Estado del Despertar</h4>
        <?php if ($has_pre): ?>
          <p class="rpg-awakening-hub-status-ok"><i class="fas fa-check-circle"></i> Despertar Incompleto (Adquirido)</p>
          <p class="rpg-awakening-hub-status-sub">Penalización activa: Se requiere llegar a <?= $usos_final ?> usos (base <?= $usos_base ?>) para completar el Awakening.</p>
        <?php else: ?>
          <p class="rpg-awakening-hub-status-sub">Ningún Awakening activo.</p>
          <p class="rpg-awakening-hub-status-sub">Puedes solicitar el Despertar Incompleto al llegar a <?= $usos_pre ?> usos.</p>
        <?php endif; ?>
      </div>
    </div>
    
    <div class="rpg-awakening-hub-actions">
      <?php if (!$has_pre): ?>
        <?php if ($usos_totales >= $usos_pre): ?>
          <a href="<?= htmlspecialchars($b_url) ?>/game/public/peticion_awakening.php?type=pre" class="rpg-btn rpg-btn--primary"><i class="fas fa-bolt"></i> Solicitar Despertar Incompleto</a>
        <?php else: ?>
          <button class="rpg-btn rpg-btn--disabled" disabled title="Necesitas <?= $usos_pre ?> usos"><i class="fas fa-lock"></i> Solicitar Despertar Incompleto</button>
        <?php endif; ?>
      <?php endif; ?>
      
      <?php if ($usos_totales >= $usos_final): ?>
        <a href="<?= htmlspecialchars($b_url) ?>/game/public/peticion_awakening.php?type=full" class="rpg-btn rpg-btn--primary rpg-btn--awakening-full"><i class="fas fa-sun"></i> Solicitar Awakening Completo</a>
      <?php else: ?>
        <button class="rpg-btn rpg-btn--disabled" disabled title="Necesitas <?= $usos_final ?> usos"><i class="fas fa-lock"></i> Solicitar Awakening Completo</button>
      <?php endif; ?>
    </div>
  </div>
  
  <h3 class="rpg-awakening-hub-separator">Opciones Regulares (Ya posees una fruta)</h3>
  <?php endif; ?>

  <div class="rpg-akuma-mode-grid <?= $is_awakening_hub ? 'rpg-opacity-70' : '' ?>">
    <a class="rpg-akuma-mode-card rpg-akuma-mode-card--random" href="<?= htmlspecialchars($b_url) ?>/game/public/peticion_akuma_aleatoria.php">
      <div class="rpg-akuma-mode-glow"></div>
      <div class="rpg-akuma-mode-icon"><i class="fas fa-dice"></i></div>
      <h2>Aleatoria</h2>
      <p>Cat&aacute;logo visual por tipo y rango. Solo frutas libres entran en la ruleta. El resultado genera una solicitud administrativa autom&aacute;tica.</p>
      <span class="rpg-akuma-mode-cta">Tirar el dado <i class="fas fa-arrow-right"></i></span>
    </a>

    <a class="rpg-akuma-mode-card rpg-akuma-mode-card--demand" href="<?= htmlspecialchars($b_url) ?>/game/public/peticion_akuma_demanda.php">
      <div class="rpg-akuma-mode-glow"></div>
      <div class="rpg-akuma-mode-icon"><i class="fas fa-hand-pointer"></i></div>
      <h2>Bajo demanda</h2>
      <p>Selecciona la fruta que deseas, explica el motivo y env&iacute;a la solicitud al staff para revisi&oacute;n.</p>
      <span class="rpg-akuma-mode-cta">Abrir formulario <i class="fas fa-arrow-right"></i></span>
    </a>
  </div>
</div>
<?php
$content = ob_get_clean();
game_render_page('Solicitud Akuma no Mi', $content);
