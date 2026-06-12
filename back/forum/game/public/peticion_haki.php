<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

if (!isset($mybb) || !is_object($mybb) || (int)($mybb->user['uid'] ?? 0) === 0) {
    header('Location: ../../member.php?action=login');
    exit;
}

$uid = (int)$mybb->user['uid'];
$prefix = TABLE_PREFIX;

// Load user config to get active PJ ID
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;

if ($active_pj_id <= 0) {
    ob_start();
    ?>
    <div class="rpg-peticiones">
      <div class="rpg-peticiones-header rpg-peticiones-header--gradient">
        <div class="rpg-peticiones-header-content">
          <a href="peticiones_general.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Trámites</a>
          <h1><i class="fas fa-bahai"></i> Gestión de Haki</h1>
          <p>No tienes ningún personaje activo seleccionado.</p>
        </div>
      </div>
      <div class="rpg-peticiones-form-container">
        <div class="red_alert">
          Debes tener un personaje activo para poder despertar o entrenar tu Haki. Selecciona tu personaje activo desde <a href="mis_personajes.php">Mis Personajes</a>.
        </div>
      </div>
    </div>
    <?php
    $content = ob_get_clean();
    game_render_page('Gestión de Haki', $content);
    exit;
}

// Load character details
$char_q = $db->query("SELECT * FROM {$prefix}game_personajes WHERE id = {$active_pj_id} LIMIT 1");
$char = $db->fetch_array($char_q);
$row = $char;

if (!$char || (int)$char['approved'] !== 1) {
    ob_start();
    ?>
    <div class="rpg-peticiones">
      <div class="rpg-peticiones-header rpg-peticiones-header--gradient">
        <div class="rpg-peticiones-header-content">
          <a href="peticiones_general.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Trámites</a>
          <h1><i class="fas fa-bahai"></i> Gestión de Haki</h1>
          <p>Personaje no aprobado.</p>
        </div>
      </div>
      <div class="rpg-peticiones-form-container">
        <div class="red_alert">
          Tu personaje activo debe estar aprobado por el staff para gestionar tu Haki.
        </div>
      </div>
    </div>
    <?php
    $content = ob_get_clean();
    game_render_page('Gestión de Haki', $content);
    exit;
}

$b_url = $mybb->settings['bburl'];

// Check if active character is staff (staff_level >= 2)
$active_char_is_staff = false;
$viewer_pj_id = (int)($mybb->user['active_pj_id'] ?? 0);
if ($viewer_pj_id > 0) {
    $viewer_pj_q = $db->query("SELECT staff_level FROM {$prefix}game_personajes WHERE id = {$viewer_pj_id} LIMIT 1");
    $viewer_pj = $db->fetch_array($viewer_pj_q);
    $active_char_is_staff = $viewer_pj && (int)$viewer_pj['staff_level'] >= 2;
}

ob_start();
?>
<div class="rpg-peticiones">
  <div class="rpg-peticiones-header rpg-peticiones-header--gradient">
    <div class="rpg-peticiones-header-content">
      <a href="peticiones_general.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Trámites</a>
      <h1><i class="fas fa-bahai"></i> Despertar y Entrenamiento de Haki</h1>
      <p>Gestiona los niveles de Haki de tu personaje activo: <strong><?= htmlspecialchars($char['name']) ?></strong>.</p>
    </div>
  </div>

  <div class="rpg-peticiones-form-container">
    <?php
    $is_active_pj = true;
    include __DIR__ . '/../views/personaje/_tab_haki.php';
    ?>
  </div>
</div>

<script>
window.PERSONAJE_PAGE_CONFIG = { bburl: '<?= $b_url ?>' };
</script>
<script src="<?= rtrim($b_url, '/') ?>/jscripts/game/peticion_haki.js?v=1"></script>
<?php
$content = ob_get_clean();
game_render_page('Gestión de Haki', $content);
