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
    <div class="rpg-post-mods-container" style="border-color: rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.05); color: #ef4444; margin-bottom: 20px;">
      <span class="rpg-post-mods-title" style="color: #ef4444;"><i class="fas fa-trash-alt"></i> NPC eliminado correctamente.</span>
    </div>
  <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'created'): ?>
    <div class="rpg-post-mods-container" style="border-color: rgba(16, 185, 129, 0.3); background: rgba(16, 185, 129, 0.05); color: #10b981; margin-bottom: 20px;">
      <span class="rpg-post-mods-title" style="color: #10b981;"><i class="fas fa-check-circle"></i> NPC creado correctamente.</span>
    </div>
  <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
    <div class="rpg-post-mods-container" style="border-color: rgba(59, 130, 246, 0.3); background: rgba(59, 130, 246, 0.05); color: #3b82f6; margin-bottom: 20px;">
      <span class="rpg-post-mods-title" style="color: #3b82f6;"><i class="fas fa-sync-alt"></i> NPC actualizado correctamente.</span>
    </div>
  <?php endif; ?>

  <div class="rpg-staff-npc-controls">
    <div>
      <span class="rpg-staff-badge level-3">Administración</span>
    </div>
    <a href="zona_staff_crear_npc.php" class="rpg-btn-approve-lg" style="text-decoration: none; padding: 10px 20px; font-size: 13px;">
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
    <div class="rpg-npc-manager-grid">
      <?php foreach ($npcs as $npc): 
          $avatar = $npc['avatar'] ? pj_img_url($npc['avatar'], $b_url) : $b_url . '/images/game/personaje_banner.png';
          $stats = !empty($npc['stats_json']) ? json_decode($npc['stats_json'], true) : ['fue'=>5,'agi'=>5,'des'=>5,'int'=>5,'esp'=>5,'inst'=>5];
          $is_active = ((int)$npc['id'] === $active_pj_id);
      ?>
        <div class="rpg-npc-manager-card">
          <div class="rpg-npc-card-banner" style="background-image: url('<?= $avatar ?>'); filter: brightness(0.65);">
            <div class="rpg-npc-card-avatar" style="background-image: url('<?= $avatar ?>');"></div>
          </div>
          
          <div class="rpg-npc-card-content">
            <h3 class="rpg-npc-card-name"><?= htmlspecialchars($npc['name']) ?></h3>
            
            <div class="rpg-npc-card-meta">
              <span class="rpg-npc-card-badge rpg-npc-card-badge--faction"><?= htmlspecialchars($npc['faction'] ?: 'Civil') ?></span>
              <span class="rpg-npc-card-badge"><?= htmlspecialchars($npc['race_name']) ?></span>
              <span class="rpg-npc-card-badge"><?= htmlspecialchars($npc['occupation_name'] ?: 'Sin Profesión') ?></span>
              <?php if ($npc['rango']): ?>
                <span class="rpg-npc-card-badge">Rango <?= htmlspecialchars($npc['rango']) ?></span>
              <?php endif; ?>
            </div>

            <div class="rpg-npc-card-stats">
              <div class="rpg-npc-card-stat">
                <span>FUE</span>
                <strong><?= (int)($stats['fue'] ?? 5) ?></strong>
              </div>
              <div class="rpg-npc-card-stat">
                <span>AGI</span>
                <strong><?= (int)($stats['agi'] ?? 5) ?></strong>
              </div>
              <div class="rpg-npc-card-stat">
                <span>DES</span>
                <strong><?= (int)($stats['des'] ?? 5) ?></strong>
              </div>
              <div class="rpg-npc-card-stat">
                <span>INT</span>
                <strong><?= (int)($stats['int'] ?? 5) ?></strong>
              </div>
              <div class="rpg-npc-card-stat">
                <span>ESP</span>
                <strong><?= (int)($stats['esp'] ?? 5) ?></strong>
              </div>
              <div class="rpg-npc-card-stat">
                <span>INST</span>
                <strong><?= (int)($stats['inst'] ?? 5) ?></strong>
              </div>
            </div>

            <div class="rpg-npc-card-actions">
              <a href="zona_staff_crear_npc.php?id=<?= (int)$npc['id'] ?>" class="rpg-btn-approve-lg" style="flex: 1; text-align: center; text-decoration: none; padding: 6px; font-size: 11px; background: rgba(59, 130, 246, 0.1); border-color: rgba(59, 130, 246, 0.3); color: #3b82f6;">
                <i class="fas fa-edit"></i> Editar
              </a>
              
              <?php if ($is_active): ?>
                <button type="button" class="rpg-btn-approve-lg" style="flex: 1.2; padding: 6px; font-size: 11px; background: rgba(16, 185, 129, 0.15); border-color: #10b981; color: #10b981;" disabled>
                  <i class="fas fa-user-check"></i> Activo
                </button>
              <?php else: ?>
                <button type="button" onclick="switchPJNav(<?= (int)$npc['id'] ?>)" class="rpg-btn-approve-lg" style="flex: 1.2; padding: 6px; font-size: 11px;">
                  <i class="fas fa-exchange-alt"></i> Activar
                </button>
              <?php endif; ?>

              <a href="zona_staff_npc.php?action=delete&id=<?= (int)$npc['id'] ?>" onclick="return confirm('¿Seguro que deseas eliminar este NPC?')" class="rpg-btn-reject-lg" style="text-align: center; text-decoration: none; padding: 6px 10px; font-size: 11px;">
                <i class="fas fa-trash-alt"></i>
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
// El switcher usará el script de foro_interact.js ya cargado en el headerinclude del foro.
</script>
<?php
$content = ob_get_clean();
game_render_page("Gestión de NPCs Mayores", $content);
