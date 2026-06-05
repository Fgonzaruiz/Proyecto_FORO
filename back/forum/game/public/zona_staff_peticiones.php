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

if ($active_pj_id > 0) {
    $pj_q = $db->query("SELECT name, staff_level FROM {$prefix}game_personajes WHERE id = {$active_pj_id} AND user_id = {$uid} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    if ($pj) {
        $staff_level = (int)$pj['staff_level'];
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
  <div class="rpg-staff-header rpg-staff-header--peticiones">
    <div class="rpg-staff-header-content">
      <h1><i class="fas fa-clipboard-check"></i> Solicitudes</h1>
      <p>Todas las peticiones pendientes en un solo listado. Selecciona una para revisarla a la derecha.</p>
    </div>
  </div>

  <div class="aprobar-layout rpg-peticiones-unified">
    <div class="aprobar-list" id="requests-list">
      <div class="aprobar-list-header">
        <span>Peticiones Pendientes</span>
        <span class="aprobar-count" id="requests-count">0</span>
      </div>
      <div id="requests-list-items" class="aprobar-list-items">
        <div class="aprobar-empty"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>
      </div>
    </div>

    <div class="aprobar-preview" id="request-preview">
      <i class="fas fa-clipboard-list rpg-preview-empty-icon"></i>
      Selecciona una solicitud para procesarla
    </div>
  </div>
</div>

<script>
window.ZONA_STAFF_PETICIONES_CONFIG = {
  bburl: '<?= $b_url ?>',
  staffLevel: <?= $staff_level ?>
};
</script>
<script src="<?= rtrim($b_url, '/') ?>/jscripts/game/zona_staff_peticiones.js?v=3"></script>
<?php
$content = ob_get_clean();
game_render_page("Solicitudes", $content);
