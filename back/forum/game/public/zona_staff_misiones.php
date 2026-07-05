<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

$uid = (int)($mybb->user['uid'] ?? 0);
if ($uid === 0) {
    header('Location: ../../member.php?action=login');
    exit;
}

$prefix = TABLE_PREFIX;
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;
if ($active_pj_id > 0) {
    $pj_q = $db->query("SELECT staff_level FROM {$prefix}game_personajes WHERE id = {$active_pj_id} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    $staff_level = $pj ? (int)$pj['staff_level'] : 0;
}
if ($staff_level < 3) {
    header('Location: ../index.php');
    exit;
}

$b_url = rtrim($mybb->settings['bburl'], '/');

// Fetch all active missions
$mQ = $db->query("SELECT * FROM {$prefix}game_missions WHERE is_active = 1 ORDER BY FIELD(`rank`, 'D', 'C', 'B', 'A', 'S') ASC, title ASC");
$missions = [];
while ($row = $db->fetch_array($mQ)) {
    $missions[] = $row;
}

ob_start();
?>
<div class="rpg-staff-zone">
  <div class="rpg-staff-header rpg-staff-header--misiones">
    <div class="rpg-staff-header-content">
      <a href="zona_staff.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Volver a Zona Staff</a>
      <h1><i class="fas fa-compass"></i> Gestionar Catálogo de Misiones</h1>
      <p>Crea, edita y desactiva las misiones que aparecen disponibles en el tablón público.</p>
    </div>
  </div>

  <div class="rpg-peticiones-form-container rpg-shop-catalog-panel rpg-mt-20">
    <div class="rpg-shop-catalog-toolbar">
      <div class="rpg-shop-catalog-toolbar__text">
        <h2 class="rpg-shop-catalog-title"><i class="fas fa-list"></i> Catálogo de Misiones</h2>
        <p class="rpg-shop-catalog-subtitle">Lista de misiones oficiales activas en el juego.</p>
      </div>
      <button type="button" class="rpg-action-btn rpg-btn-primary" onclick="openMissionModal('create')">
        <i class="fas fa-plus"></i> Nueva Misión
      </button>
    </div>

    <div class="rpg-table-responsive rpg-mt-20">
      <table class="rpg-table-pd-history">
        <thead>
          <tr>
            <th>Título</th>
            <th>Rango</th>
            <th>Isla</th>
            <th>Facción</th>
            <th>Categoría</th>
            <th>Recompensas</th>
            <th>Límite Posts</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($missions)): ?>
            <tr>
              <td colspan="7" class="rpg-text-center rpg-muted-soft">No hay misiones configuradas en el catálogo.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($missions as $m): ?>
              <tr>
                <td><strong><?= htmlspecialchars($m['title']) ?></strong></td>
                <td><span class="rpg-stat-rank rpg-stat-rank--<?= strtolower($m['rank']) ?>"><?= htmlspecialchars($m['rank']) ?></span></td>
                <td><?= htmlspecialchars($m['isla']) ?></td>
                <td><?= htmlspecialchars($m['faction'] ?? 'Global') ?></td>
                <td><?= htmlspecialchars(ucfirst($m['categoria'])) ?></td>
                <td>
                  <span class="rpg-peticion-card-reward-pd"><i class="fas fa-star"></i> <?= $m['points_reward'] ?> PD</span> |
                  <span class="rpg-peticion-card-reward-berries"><i class="fas fa-coins"></i> <?= number_format((int)$m['jenny_reward']) ?></span>
                </td>
                <td><?= $m['max_posts'] ?> posts</td>
                <td>
                  <div class="rpg-flex-gap-10">
                    <button type="button" class="rpg-action-btn rpg-btn-primary" onclick='openMissionModal("edit", <?= json_encode($m, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                      <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="rpg-system-tab-btn rpg-staff-btn-danger" onclick="deleteMission(<?= $m['id'] ?>, '<?= htmlspecialchars($m['title'], ENT_QUOTES) ?>')">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- MODAL: CREADOR / EDITOR DE MISIONES -->
<div id="mission-editor-modal" class="rpg-modal-overlay" data-modal>
  <div class="rpg-modal-panel rpg-modal-panel--md">
    <div class="rpg-modal-header">
      <h3 class="rpg-modal-title" id="modal-mission-title"><i class="fas fa-plus-circle"></i> Crear Misión</h3>
      <button type="button" onclick="closeMissionModal()" class="rpg-modal-close"><i class="fas fa-times"></i></button>
    </div>
    <div class="rpg-modal-body">
      <input type="hidden" id="edit_mission_id" value="">
      
      <div class="rpg-form-stack">
        <div class="form-group">
          <label class="rpg-form-label">Título de la Misión</label>
          <input type="text" id="mission_title" class="textbox rpg-form-input" placeholder="Ej: Escolta de Suministros">
        </div>

        <div class="form-group">
          <label class="rpg-form-label">Descripción Narrativa</label>
          <textarea id="mission_description" rows="4" class="textbox rpg-form-input rpg-form-input--resize" placeholder="Detalles de la misión, objetivos y oráculos..."></textarea>
        </div>

        <div class="rpg-form-grid-2 rpg-mt-20">
          <div class="form-group">
            <label class="rpg-form-label">Rango</label>
            <select id="mission_rank" class="textbox rpg-form-input">
              <option value="D">Rango D</option>
              <option value="C">Rango C</option>
              <option value="B">Rango B</option>
              <option value="A">Rango A</option>
              <option value="S">Rango S</option>
            </select>
          </div>

          <div class="form-group">
            <label class="rpg-form-label">Isla de Destino</label>
            <input type="text" id="mission_isla" class="textbox rpg-form-input" placeholder="Ej: Alabasta">
          </div>

          <div class="form-group">
            <label class="rpg-form-label">Facción</label>
            <select id="mission_faction" class="textbox rpg-form-input">
              <option value="Global">Global (Todas)</option>
              <option value="Marine">Marina</option>
              <option value="Pirata">Piratas</option>
              <option value="Revolucionario">Revolucionario</option>
              <option value="Gobierno">Gobierno Mundial</option>
              <option value="Cazador">Cazadores</option>
              <option value="Civil">Civiles</option>
            </select>
          </div>

          <div class="form-group">
            <label class="rpg-form-label">Nivel Mínimo</label>
            <input type="number" id="mission_min_level" min="1" value="1" class="textbox rpg-form-input">
          </div>

          <div class="form-group">
            <label class="rpg-form-label">Nivel Máximo</label>
            <input type="number" id="mission_max_level" min="1" value="99" class="textbox rpg-form-input">
          </div>

          <div class="form-group">
            <label class="rpg-form-label">Recompensa PD</label>
            <input type="number" id="mission_points_reward" min="0" value="1" class="textbox rpg-form-input">
          </div>

          <div class="form-group">
            <label class="rpg-form-label">Recompensa Jenny</label>
            <input type="number" id="mission_jenny_reward" min="0" value="500" class="textbox rpg-form-input">
          </div>

          <div class="form-group">
            <label class="rpg-form-label">Categoría</label>
            <select id="mission_categoria" class="textbox rpg-form-input">
              <option value="combate">Combate</option>
              <option value="exploracion">Exploración</option>
              <option value="sigilo">Sigilo</option>
              <option value="escolta">Escolta</option>
              <option value="supervivencia">Supervivencia</option>
            </select>
          </div>

          <div class="form-group">
            <label class="rpg-form-label">Límite de Posts</label>
            <input type="number" id="mission_max_posts" min="5" value="15" class="textbox rpg-form-input">
          </div>
        </div>
      </div>

      <div class="rpg-misiones-modal-footer rpg-mt-20">
        <button type="button" onclick="closeMissionModal()" class="rpg-system-tab-btn">Cancelar</button>
        <button type="button" onclick="submitMissionForm()" id="btn_submit_mission" class="rpg-action-btn rpg-btn-primary">
          <i class="fas fa-save"></i> Guardar Misión
        </button>
      </div>
    </div>
  </div>
</div>

<script>
window.ZONA_STAFF_MISIONES_CONFIG = {
  bburl: '<?= $b_url ?>'
};
</script>
<script src="<?= rtrim($b_url, '/') ?>/jscripts/game/zona_staff_misiones.js?v=2"></script>
<?php
$content = ob_get_clean();
game_render_page('Gestionar Misiones — Staff', $content);
