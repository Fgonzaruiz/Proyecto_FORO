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
      <h1><i class="fas fa-clipboard-check"></i> Peticiones</h1>
      <p>Revisa y gestiona todas las peticiones enviadas por los jugadores.</p>
    </div>
  </div>

  <!-- PESTAÑAS -->
  <div class="rpg-peticiones-tabs">
    <button type="button" id="tab-btn-cartas" class="rpg-peticiones-tab is-active" onclick="switchTab('cartas')">
      <i class="fas fa-layer-group"></i> Peticiones de Cartas
      <span id="tab-count-cartas" class="rpg-peticiones-tab-count is-active">0</span>
    </button>
    <button type="button" id="tab-btn-busquedas" class="rpg-peticiones-tab" onclick="switchTab('busquedas')">
      <i class="fas fa-search"></i> Búsquedas de Rol
      <span id="tab-count-busquedas" class="rpg-peticiones-tab-count">0</span>
    </button>
    <button type="button" id="tab-btn-admin" class="rpg-peticiones-tab" onclick="switchTab('admin')">
      <i class="fas fa-clipboard-list"></i> Administrativas
      <span id="tab-count-admin" class="rpg-peticiones-tab-count">0</span>
    </button>
  </div>

  <!-- ═══════════════════════════════════════════════ -->
  <!-- PESTAÑA: CARTAS                                -->
  <!-- ═══════════════════════════════════════════════ -->
  <div id="tab-cartas" class="rpg-peticiones-tab-panel">
    <div class="aprobar-layout">
      <!-- LEFT: Requests List -->
      <div class="aprobar-list" id="requests-list">
        <div class="aprobar-list-header">
          <span>Solicitudes Pendientes</span>
          <span class="aprobar-count" id="requests-count">0</span>
        </div>
        <div id="requests-list-items" class="aprobar-list-items">
          <div class="aprobar-empty">Cargando...</div>
        </div>
      </div>

      <!-- RIGHT: Preview Panel -->
      <div class="aprobar-preview" id="request-preview">
        <i class="fas fa-clipboard-list rpg-preview-empty-icon"></i>
        Selecciona una solicitud para procesarla
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════ -->
  <!-- PESTAÑA: BÚSQUEDAS DE ROL                      -->
  <!-- ═══════════════════════════════════════════════ -->
  <div id="tab-busquedas" class="rpg-peticiones-tab-panel rpg-is-hidden">
    <div id="busquedas-pending-list">
      <div class="rpg-peticiones-loading">
        <i class="fas fa-spinner fa-spin fa-2x"></i><br><br>Cargando búsquedas...
      </div>
    </div>
  </div>

  <div id="tab-admin" class="rpg-peticiones-tab-panel rpg-is-hidden">
    <div id="admin-requests-list">
      <div class="rpg-peticiones-loading">
        <i class="fas fa-spinner fa-spin fa-2x"></i><br><br>Cargando peticiones administrativas...
      </div>
    </div>
  </div>

  <!-- Modal revisión petición administrativa -->
  <div id="admin-review-modal" class="rpg-modal-overlay">
    <div class="rpg-modal-panel">
      <div class="rpg-modal-header">
        <h3 id="arm-title" class="rpg-modal-title"><i class="fas fa-clipboard-list"></i> <span id="arm-title-text"></span></h3>
        <button type="button" onclick="closeAdminReview()" class="rpg-modal-close"><i class="fas fa-times"></i></button>
      </div>
      <div class="rpg-modal-body">
        <div class="rpg-modal-author">
          <img id="arm-avatar" src="" alt="" class="rpg-modal-avatar" />
          <div>
            <div id="arm-pj" class="rpg-modal-pj"></div>
            <div id="arm-source" class="rpg-modal-date"></div>
          </div>
        </div>
        <div id="arm-desc" class="rpg-modal-desc"></div>
        <input type="hidden" id="arm-id" value="" />
        <label class="rpg-modal-label">Respuesta para el jugador (mensaje directo si escribes aquí):</label>
        <textarea id="arm-nota" rows="3" class="rpg-staff-textarea" placeholder="Aprobación, denegación o indicaciones..."></textarea>
        <div class="rpg-modal-actions">
          <button type="button" onclick="accionAdminRequest('aprobar')" class="rpg-btn-approve-lg">
            <i class="fas fa-check"></i> Aprobar
          </button>
          <button type="button" onclick="accionAdminRequest('denegar')" class="rpg-btn-reject-lg">
            <i class="fas fa-times"></i> Denegar
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal revisión búsqueda -->
  <div id="busqueda-review-modal" class="rpg-modal-overlay">
    <div class="rpg-modal-panel">
      <div class="rpg-modal-header">
        <h3 id="brm-titulo" class="rpg-modal-title"><i class="fas fa-search" class="rpg-modal-title-icon"></i> <span id="brm-titulo-text"></span></h3>
        <button onclick="closeBusquedaReview()" class="rpg-modal-close"><i class="fas fa-times"></i></button>
      </div>
      <div class="rpg-modal-body">
        <img id="brm-img" src="" class="rpg-modal-img rpg-is-hidden" />
        <div class="rpg-modal-author">
          <img id="brm-avatar" src="" class="rpg-modal-avatar" />
          <div>
            <div id="brm-pj" class="rpg-modal-pj"></div>
            <div id="brm-date" class="rpg-modal-date"></div>
          </div>
        </div>
        <div id="brm-desc" class="rpg-modal-desc"></div>
        <input type="hidden" id="brm-id" value="" />
        <label class="rpg-modal-label">Nota para el jugador (opcional):</label>
        <textarea id="brm-nota" rows="3" class="rpg-staff-textarea" placeholder="Añade una nota que recibirá el jugador..."></textarea>
        <div class="rpg-modal-actions">
          <button onclick="accionBusqueda('aprobar')" class="rpg-btn-approve-lg">
            <i class="fas fa-check"></i> Aprobar y publicar
          </button>
          <button onclick="accionBusqueda('denegar')" class="rpg-btn-reject-lg">
            <i class="fas fa-times"></i> Denegar
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
window.ZONA_STAFF_PETICIONES_CONFIG = {
  bburl: '<?= $b_url ?>',
  staffLevel: <?= $staff_level ?>
};
</script>
<script src="<?= rtrim($b_url, '/') ?>/jscripts/game/zona_staff_peticiones.js?v=2"></script>
<script src="<?= rtrim($b_url, '/') ?>/jscripts/game/zona_staff_admin_requests.js?v=1"></script>
<?php
$content = ob_get_clean();
game_render_page("Peticiones", $content);
