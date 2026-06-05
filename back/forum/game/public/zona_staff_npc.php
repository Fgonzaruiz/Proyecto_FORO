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

// Obtener personaje activo y verificar staff_level
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

// Solo staff nivel 3 (Administrador) puede crear/gestionar NPCs mayores
if ($staff_level < 3) {
    header('Location: ../index.php');
    exit;
}

$b_url = $mybb->settings['bburl'];

// Manejar eliminación si se solicita
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $delete_id = (int)($_GET['id'] ?? 0);
    if ($delete_id > 0) {
        $db->write_query("DELETE FROM {$prefix}game_personajes WHERE id = {$delete_id} AND is_npc = 1");
        header('Location: zona_staff_npc.php?msg=deleted');
        exit;
    }
}

// Obtener lista de NPCs mayores
$npcs_q = $db->query("SELECT * FROM {$prefix}game_personajes WHERE is_npc = 1 ORDER BY name ASC");
$npcs = [];
while ($row = $db->fetch_array($npcs_q)) {
    $npcs[] = $row;
}

function pj_img_url(string $path, string $bb): string {
    if ($path === '') return '';
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) return $path;
    return rtrim($bb, '/') . '/' . ltrim($path, '/');
}

ob_start();
?>
<div class="rpg-staff-zone">
  <div class="rpg-staff-header rpg-staff-header--npc">
    <div class="rpg-staff-header-content">
      <a href="zona_staff.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Volver a Zona Staff</a>
      <h1><i class="fas fa-users-cog"></i> Gestión de NPCs Mayores</h1>
      <p>Crea y edita personajes no jugables (NPCs Mayores) que el staff de nivel 3 puede activar para postear e interactuar.</p>
    </div>
  </div>

  <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
    <div class="rpg-post-mods-container rpg-flash rpg-flash--error">
      <span class="rpg-post-mods-title"><i class="fas fa-trash-alt"></i> NPC eliminado correctamente.</span>
    </div>
  <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'created'): ?>
    <div class="rpg-post-mods-container rpg-flash rpg-flash--success">
      <span class="rpg-post-mods-title"><i class="fas fa-check-circle"></i> NPC creado correctamente.</span>
    </div>
  <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
    <div class="rpg-post-mods-container rpg-flash rpg-flash--info">
      <span class="rpg-post-mods-title"><i class="fas fa-sync-alt"></i> NPC actualizado correctamente.</span>
    </div>
  <?php endif; ?>

  <div class="rpg-staff-npc-controls">
    <div>
      <span class="rpg-staff-badge level-3">Administración</span>
    </div>
    <a href="crear_personaje.php?is_npc=1" class="rpg-btn-approve-lg rpg-staff-npc-create-link">
      <i class="fas fa-user-plus"></i> Crear NPC Mayor
    </a>
  </div>

  <?php if (empty($npcs)): ?>
    <div class="rpg-akuma-empty">
      <div class="rpg-akuma-empty-icon">
        <i class="fas fa-users-slash"></i>
      </div>
      <p>No se han creado NPCs mayores aún.</p>
    </div>
  <?php else: ?>
    <table class="rpg-staff-table">
      <thead>
        <tr>
          <th class="rpg-staff-col-avatar">Avatar</th>
          <th>NPC</th>
          <th>Facción</th>
          <th>Rango</th>
          <th class="rpg-staff-col-actions">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($npcs as $npc):
            $avatar = $npc['avatar'] ? pj_img_url($npc['avatar'], $b_url) : $b_url . '/images/game/personaje_banner.png';
            $stats = !empty($npc['stats_json']) ? json_decode($npc['stats_json'], true) : ['fue'=>5,'agi'=>5,'des'=>5,'int'=>5,'esp'=>5,'inst'=>5];
            $is_active = ((int)$npc['id'] === $active_pj_id);
        ?>
          <tr>
            <td>
              <img src="<?= htmlspecialchars($avatar, ENT_QUOTES) ?>" alt="" class="rpg-avatar-md" />
            </td>
            <td>
              <strong><?= htmlspecialchars($npc['name']) ?></strong>
              <div class="rpg-staff-cell-sub"><?= htmlspecialchars($npc['race_name']) ?> &bull; <?= htmlspecialchars($npc['occupation_name'] ?: 'Sin Profesión') ?></div>
            </td>
            <td>
              <span class="rpg-npc-card-badge rpg-npc-card-badge--faction"><?= htmlspecialchars($npc['faction'] ?: 'Civil') ?></span>
            </td>
            <td>
              <?= $npc['rango'] ? 'Rango ' . htmlspecialchars($npc['rango']) : 'Ninguno' ?>
            </td>
            <td class="rpg-staff-col-actions">
              <button type="button" class="rpg-btn-approve-lg rpg-btn-staff-sm edit-npc-btn"
                data-id="<?= (int)$npc['id'] ?>"
                data-name="<?= htmlspecialchars($npc['name'], ENT_QUOTES) ?>"
                data-avatar="<?= htmlspecialchars($avatar, ENT_QUOTES) ?>"
                data-race="<?= htmlspecialchars($npc['race_name'], ENT_QUOTES) ?>"
                data-occupation="<?= htmlspecialchars($npc['occupation_name'] ?: 'Sin Profesión', ENT_QUOTES) ?>"
                data-faction="<?= htmlspecialchars($npc['faction'] ?: 'Civil', ENT_QUOTES) ?>"
                data-rango="<?= htmlspecialchars($npc['rango'] ?? '', ENT_QUOTES) ?>"
                data-active="<?= $is_active ? '1' : '0' ?>"
                data-fue="<?= (int)($stats['fue'] ?? 5) ?>"
                data-agi="<?= (int)($stats['agi'] ?? 5) ?>"
                data-des="<?= (int)($stats['des'] ?? 5) ?>"
                data-int="<?= (int)($stats['int'] ?? 5) ?>"
                data-esp="<?= (int)($stats['esp'] ?? 5) ?>"
                data-inst="<?= (int)($stats['inst'] ?? 5) ?>">
                <i class="fas fa-edit"></i> Editar
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- Drawer de edición de NPC -->
<div class="rpg-staff-drawer rpg-is-hidden" id="npc-editor-drawer">
  <div class="rpg-staff-drawer__backdrop" id="npc-editor-backdrop"></div>
  <div class="rpg-staff-drawer__panel rpg-staff-drawer__panel--narrow">
    <div class="rpg-staff-drawer__header">
      <h2 id="npc-editor-title"><i class="fas fa-user-edit"></i> Gestionar NPC</h2>
      <button type="button" class="rpg-staff-drawer__close" id="npc-editor-close">&times;</button>
    </div>
    <div class="rpg-staff-drawer__body">
      <!-- Resumen del NPC -->
      <div class="rpg-staff-pj-summary">
        <img id="npc-summary-avatar" src="" alt="" class="rpg-avatar-lg">
        <div class="rpg-staff-pj-summary-info">
          <h3 id="npc-summary-name"></h3>
          <p id="npc-summary-meta"></p>
          <p id="npc-summary-faction"></p>
        </div>
      </div>

      <hr class="rpg-staff-divider">

      <!-- Estadísticas del NPC -->
      <div class="rpg-staff-form-section">
        <h4><i class="fas fa-chart-bar"></i> Atributos del NPC</h4>
        <div class="rpg-npc-card-stats" style="margin-top: 10px;">
          <div class="rpg-npc-card-stat">
            <span>FUE</span>
            <strong id="npc-stat-fue">5</strong>
          </div>
          <div class="rpg-npc-card-stat">
            <span>AGI</span>
            <strong id="npc-stat-agi">5</strong>
          </div>
          <div class="rpg-npc-card-stat">
            <span>DES</span>
            <strong id="npc-stat-des">5</strong>
          </div>
          <div class="rpg-npc-card-stat">
            <span>INT</span>
            <strong id="npc-stat-int">5</strong>
          </div>
          <div class="rpg-npc-card-stat">
            <span>ESP</span>
            <strong id="npc-stat-esp">5</strong>
          </div>
          <div class="rpg-npc-card-stat">
            <span>INST</span>
            <strong id="npc-stat-inst">5</strong>
          </div>
        </div>
      </div>

      <hr class="rpg-staff-divider">

      <!-- Acciones de Gestión -->
      <div class="rpg-staff-form-section">
        <h4><i class="fas fa-tools"></i> Acciones</h4>
        <div class="rpg-staff-actions-grid">
          <a href="" id="btn-edit-npc-link" class="rpg-btn-approve-lg rpg-btn-full">
            <i class="fas fa-edit"></i> Editar Ficha y Atributos
          </a>
          
          <a href="" id="btn-switch-npc" class="rpg-btn-approve-lg rpg-btn-full">
            <!-- Activar personaje -->
          </a>

          <a href="" id="btn-delete-npc" class="rpg-btn-reject-lg rpg-btn-full" onclick="return confirm('¿Seguro que deseas eliminar este NPC?')">
            <i class="fas fa-trash-alt"></i> Eliminar NPC
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="<?= htmlspecialchars(rtrim($b_url, '/')) ?>/jscripts/game/zona_staff_npc.js?v=1"></script>
<?php
$content = ob_get_clean();
game_render_page("Gestión de NPCs Mayores", $content);
