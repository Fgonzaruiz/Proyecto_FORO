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

// Solo staff nivel 3 (Administrador) puede gestionar personajes
if ($staff_level < 3) {
    header('Location: ../index.php');
    exit;
}

$b_url = $mybb->settings['bburl'];

// ═══════════════════════════════════════════════
// ACCIONES DE GESTIÓN
// ═══════════════════════════════════════════════

// 1. Guardar asignaciones de NPCs a Narrador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_assignments') {
    $narrator_id = (int)$_POST['narrator_id'];
    if ($narrator_id > 0) {
        $db->write_query("DELETE FROM {$prefix}game_npc_assignments WHERE narrator_id = {$narrator_id}");
        if (isset($_POST['assigned_npcs']) && is_array($_POST['assigned_npcs'])) {
            foreach ($_POST['assigned_npcs'] as $npc_id) {
                $npc_id = (int)$npc_id;
                $db->write_query("INSERT INTO {$prefix}game_npc_assignments (character_id, narrator_id) VALUES ({$npc_id}, {$narrator_id})");
            }
        }
        header("Location: zona_staff_personajes.php?msg=assigned");
        exit;
    }
}

// 2. Cambiar staff_level / rol
if (isset($_GET['action']) && $_GET['action'] === 'set_role') {
    $char_id = (int)($_GET['id'] ?? 0);
    $level = (int)($_GET['level'] ?? 0);
    if ($char_id > 0 && in_array($level, [0, 1, 2, 3])) {
        $is_staff = $level > 0 ? 1 : 0;
        $db->write_query("UPDATE {$prefix}game_personajes SET staff_level = {$level}, is_staff = {$is_staff} WHERE id = {$char_id} AND is_npc = 0");
        header("Location: zona_staff_personajes.php?msg=role_updated");
        exit;
    }
}

// 3. Cambiar is_narrator
if (isset($_GET['action']) && $_GET['action'] === 'toggle_narrator') {
    $char_id = (int)($_GET['id'] ?? 0);
    $val = (int)($_GET['val'] ?? 0);
    if ($char_id > 0) {
        $db->write_query("UPDATE {$prefix}game_personajes SET is_narrator = {$val} WHERE id = {$char_id} AND is_npc = 0");
        header("Location: zona_staff_personajes.php?msg=narrator_updated");
        exit;
    }
}

// 4. Cambiar status (Matar / Revivir)
if (isset($_GET['action']) && $_GET['action'] === 'set_status') {
    $char_id = (int)($_GET['id'] ?? 0);
    $status = $_GET['status'] === 'muerto' ? 'muerto' : 'aprobada';
    if ($char_id > 0) {
        $db->write_query("UPDATE {$prefix}game_personajes SET status = '{$status}' WHERE id = {$char_id} AND is_npc = 0");
        header("Location: zona_staff_personajes.php?msg=status_updated");
        exit;
    }
}

// 5. Eliminar personaje
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $char_id = (int)($_GET['id'] ?? 0);
    if ($char_id > 0) {
        $db->write_query("DELETE FROM {$prefix}game_personajes WHERE id = {$char_id} AND is_npc = 0");
        $db->write_query("DELETE FROM {$prefix}game_npc_assignments WHERE narrator_id = {$char_id}");
        header("Location: zona_staff_personajes.php?msg=deleted");
        exit;
    }
}

function pj_img_url(string $path, string $bb): string {
    if ($path === '') return '';
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) return $path;
    return rtrim($bb, '/') . '/' . ltrim($path, '/');
}

$assign_narrator_id = (int)($_GET['assign_narrator_id'] ?? 0);
$assign_char = null;

if ($assign_narrator_id > 0) {
    $narr_q = $db->query("SELECT * FROM {$prefix}game_personajes WHERE id = {$assign_narrator_id} AND is_npc = 0 LIMIT 1");
    $assign_char = $db->fetch_array($narr_q);
}

ob_start();
?>
<div class="rpg-staff-zone">
  
  <?php if ($assign_char): ?>
    <!-- ═══════════════════════════════════════════════ -->
    <!-- MODO ASIGNACIÓN DE NPCS A NARRADOR             -->
    <!-- ═══════════════════════════════════════════════ -->
    <div class="rpg-staff-header rpg-staff-header--narrador">
      <div class="rpg-staff-header-content">
        <a href="zona_staff_personajes.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Volver a Gestión de Personajes</a>
        <h1><i class="fas fa-user-shield"></i> Asignar NPCs a Narrador</h1>
        <p>Asigna qué NPCs Mayores puede utilizar el personaje de narrador <strong><?= htmlspecialchars($assign_char['name']) ?></strong>.</p>
      </div>
    </div>

    <?php
    // Obtener todos los NPCs mayores disponibles
    $npcs_q = $db->query("SELECT id, name, faction, avatar FROM {$prefix}game_personajes WHERE is_npc = 1 ORDER BY name ASC");
    $npcs = [];
    while ($row = $db->fetch_array($npcs_q)) {
        $npcs[] = $row;
    }

    // Obtener asignaciones actuales
    $curr_q = $db->query("SELECT character_id FROM {$prefix}game_npc_assignments WHERE narrator_id = {$assign_char['id']}");
    $current_assignments = [];
    while ($row = $db->fetch_array($curr_q)) {
        $current_assignments[] = (int)$row['character_id'];
    }
    ?>

    <form method="POST" class="rpg-npc-creator-form" style="max-width: 700px;">
      <input type="hidden" name="action" value="save_assignments" />
      <input type="hidden" name="narrator_id" value="<?= $assign_char['id'] ?>" />

      <h3 class="rpg-wizard-preview-stats-title" style="margin-bottom: 20px; font-weight: 800; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">NPCs Mayores Disponibles</h3>

      <?php if (empty($npcs)): ?>
        <p style="color: var(--text-muted); font-style: italic;">No se han creado NPCs mayores todavía en el foro.</p>
      <?php else: ?>
        <div style="display: grid; grid-template-columns: 1fr; gap: 12px; margin-bottom: 24px;">
          <?php foreach ($npcs as $npc): 
              $avatar = $npc['avatar'] ? pj_img_url($npc['avatar'], $b_url) : $b_url . '/images/game/personaje_banner.png';
              $is_checked = in_array((int)$npc['id'], $current_assignments) ? 'checked' : '';
          ?>
            <label class="rpg-npc-card-badge" style="display: flex; align-items: center; gap: 15px; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-card); cursor: pointer; text-transform: none; font-size: 13px; font-weight: normal; color: var(--text-primary);">
              <input type="checkbox" name="assigned_npcs[]" value="<?= $npc['id'] ?>" <?= $is_checked ?> style="transform: scale(1.2);" />
              <img src="<?= $avatar ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border-color);" />
              <div style="flex: 1;">
                <strong><?= htmlspecialchars($npc['name']) ?></strong>
                <div style="font-size: 10px; color: var(--text-muted);"><?= htmlspecialchars($npc['faction'] ?: 'Civil') ?></div>
              </div>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div style="text-align: right; border-top: 1px solid var(--border-color); padding-top: 15px;">
        <a href="zona_staff_personajes.php" class="rpg-btn-reject-lg" style="text-decoration: none; padding: 10px 24px; margin-right: 10px; display: inline-block;">Cancelar</a>
        <button type="submit" class="rpg-btn-approve-lg" style="padding: 10px 32px; cursor: pointer; border: none; font-size: 13px;">
          <i class="fas fa-save"></i> Guardar Asignaciones
        </button>
      </div>
    </form>

  <?php else: ?>
    <!-- ═══════════════════════════════════════════════ -->
    <!-- MODO LISTADO DE PERSONAJES GENERAL             -->
    <!-- ═══════════════════════════════════════════════ -->
    <div class="rpg-staff-header rpg-staff-header--personajes">
      <div class="rpg-staff-header-content">
        <a href="zona_staff.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Volver a Zona Staff</a>
        <h1><i class="fas fa-users"></i> Gestión de Personajes del Foro</h1>
        <p>Asigna roles (Colaborador, Moderador, Administrador), modifica su estado (aprobada/muerto), activa permisos de narrador o elimina personajes.</p>
      </div>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'role_updated'): ?>
      <div class="rpg-post-mods-container" style="border-color: rgba(16, 185, 129, 0.3); background: rgba(16, 185, 129, 0.05); color: #10b981; margin-bottom: 20px;">
        <span class="rpg-post-mods-title" style="color: #10b981;"><i class="fas fa-check-circle"></i> Rol de staff actualizado correctamente.</span>
      </div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'narrator_updated'): ?>
      <div class="rpg-post-mods-container" style="border-color: rgba(59, 130, 246, 0.3); background: rgba(59, 130, 246, 0.05); color: #3b82f6; margin-bottom: 20px;">
        <span class="rpg-post-mods-title" style="color: #3b82f6;"><i class="fas fa-info-circle"></i> Permisos de narrador actualizados correctamente.</span>
      </div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'status_updated'): ?>
      <div class="rpg-post-mods-container" style="border-color: rgba(245, 158, 11, 0.3); background: rgba(245, 158, 11, 0.05); color: #f59e0b; margin-bottom: 20px;">
        <span class="rpg-post-mods-title" style="color: #f59e0b;"><i class="fas fa-heartbeat"></i> Estado de vida/muerte del personaje actualizado.</span>
      </div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
      <div class="rpg-post-mods-container" style="border-color: rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.05); color: #ef4444; margin-bottom: 20px;">
        <span class="rpg-post-mods-title" style="color: #ef4444;"><i class="fas fa-trash-alt"></i> Personaje eliminado del foro correctamente.</span>
      </div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'assigned'): ?>
      <div class="rpg-post-mods-container" style="border-color: rgba(16, 185, 129, 0.3); background: rgba(16, 185, 129, 0.05); color: #10b981; margin-bottom: 20px;">
        <span class="rpg-post-mods-title" style="color: #10b981;"><i class="fas fa-save"></i> NPCs asignados correctamente al narrador.</span>
      </div>
    <?php endif; ?>

    <?php
    // Obtener parámetros de búsqueda y filtros
    $search = trim($_GET['search'] ?? '');
    $filter_role = trim($_GET['role'] ?? '');

    $where_clauses = ["p.is_npc = 0"];
    if ($search !== '') {
        $searchEsc = $db->escape_string($search);
        $where_clauses[] = "p.name LIKE '%{$searchEsc}%'";
    }

    if ($filter_role !== '') {
        if ($filter_role === 'narrator') {
            $where_clauses[] = "p.is_narrator = 1";
        } elseif ($filter_role === 'regular') {
            $where_clauses[] = "p.staff_level = 0 AND p.is_narrator = 0";
        } else {
            $level = (int)$filter_role;
            $where_clauses[] = "p.staff_level = {$level}";
        }
    }

    $where_sql = implode(' AND ', $where_clauses);
    $chars_q = $db->query("SELECT p.*, u.username FROM {$prefix}game_personajes p 
        LEFT JOIN {$prefix}users u ON p.user_id = u.uid 
        WHERE {$where_sql} 
        ORDER BY p.name ASC");
    $chars = [];
    while ($row = $db->fetch_array($chars_q)) {
        $chars[] = $row;
    }
    ?>

    <!-- Barra de Filtros -->
    <form method="GET" class="rpg-npc-creator-form" style="max-width: 100%; margin-bottom: 24px; padding: 20px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
      <div style="flex: 2; min-width: 250px;">
        <input type="text" name="search" class="textbox" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar personaje por nombre..." />
      </div>
      <div style="flex: 1; min-width: 180px;">
        <select name="role" class="textbox">
          <option value="">-- Todos los roles --</option>
          <option value="3" <?= $filter_role === '3' ? 'selected' : '' ?>>Administradores (Rango 3)</option>
          <option value="2" <?= $filter_role === '2' ? 'selected' : '' ?>>Moderadores (Rango 2)</option>
          <option value="1" <?= $filter_role === '1' ? 'selected' : '' ?>>Colaboradores (Rango 1)</option>
          <option value="narrator" <?= $filter_role === 'narrator' ? 'selected' : '' ?>>Narradores (is_narrator)</option>
          <option value="regular" <?= $filter_role === 'regular' ? 'selected' : '' ?>>Sin Rango (Regular)</option>
        </select>
      </div>
      <button type="submit" class="rpg-btn-approve-lg" style="padding: 10px 24px; font-size: 13px; cursor: pointer; border: none;">
        <i class="fas fa-search"></i> Filtrar
      </button>
      <?php if ($search !== '' || $filter_role !== ''): ?>
        <a href="zona_staff_personajes.php" class="rpg-btn-reject-lg" style="text-decoration: none; padding: 10px 20px; display: inline-block;">Limpiar</a>
      <?php endif; ?>
    </form>

    <?php if (empty($chars)): ?>
      <div class="rpg-akuma-empty">
        <div class="rpg-akuma-empty-icon">
          <i class="fas fa-users-slash"></i>
        </div>
        <p>No se encontraron personajes que coincidan con la búsqueda.</p>
      </div>
    <?php else: ?>
      <table class="rpg-staff-table">
        <thead>
          <tr>
            <th style="width: 50px;">Avatar</th>
            <th>Personaje</th>
            <th>Propietario</th>
            <th>Facción</th>
            <th>Estado</th>
            <th>Rango Staff</th>
            <th>Narrador</th>
            <th style="width: 250px; text-align: right;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($chars as $c): 
              $avatar = $c['avatar'] ? pj_img_url($c['avatar'], $b_url) : $b_url . '/images/game/personaje_banner.png';
              $status = $c['status'];
          ?>
            <tr>
              <td>
                <img src="<?= $avatar ?>" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border-color);" />
              </td>
              <td>
                <strong><?= htmlspecialchars($c['name']) ?></strong>
                <div style="font-size: 10px; color: var(--text-muted);"><?= htmlspecialchars($c['race_name']) ?> • <?= htmlspecialchars($c['occupation_name'] ?: 'Sin Profesión') ?></div>
              </td>
              <td>
                <?php if ($c['username']): ?>
                  <span style="font-size: 12px; color: var(--text-primary);"><i class="fas fa-user"></i> <?= htmlspecialchars($c['username']) ?></span>
                  <div style="font-size: 9px; color: var(--text-muted);">UID: <?= $c['user_id'] ?></div>
                <?php else: ?>
                  <span style="color: var(--text-muted); font-style: italic;">Sin Cuenta</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="rpg-npc-card-badge" style="font-size: 10px;"><?= htmlspecialchars($c['faction'] ?: 'Civil') ?></span>
              </td>
              <td>
                <?php if ($status === 'muerto'): ?>
                  <span class="rpg-pj-card-status rpg-pj-card-status--rechazada" style="padding: 2px 8px; border-radius: 10px; font-size: 9px; display: inline-block; text-transform: uppercase;">Muerto</span>
                <?php else: ?>
                  <span class="rpg-pj-card-status rpg-pj-card-status--<?= htmlspecialchars($status) ?>" style="padding: 2px 8px; border-radius: 10px; font-size: 9px; display: inline-block; text-transform: uppercase;"><?= htmlspecialchars($status) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <!-- Cambiar Rango -->
                <select onchange="window.location.href = 'zona_staff_personajes.php?action=set_role&id=<?= $c['id'] ?>&level=' + this.value" class="textbox" style="padding: 4px; font-size: 11px;">
                  <option value="0" <?= (int)$c['staff_level'] === 0 ? 'selected' : '' ?>>Ninguno</option>
                  <option value="1" <?= (int)$c['staff_level'] === 1 ? 'selected' : '' ?>>1 - Colaborador</option>
                  <option value="2" <?= (int)$c['staff_level'] === 2 ? 'selected' : '' ?>>2 - Moderador</option>
                  <option value="3" <?= (int)$c['staff_level'] === 3 ? 'selected' : '' ?>>3 - Administrador</option>
                </select>
              </td>
              <td>
                <!-- Toggle Narrador -->
                <?php if ((int)$c['is_narrator'] === 1): ?>
                  <a href="zona_staff_personajes.php?action=toggle_narrator&id=<?= $c['id'] ?>&val=0" class="rpg-btn-approve-lg" style="padding: 4px 8px; font-size: 10px; background: rgba(16, 185, 129, 0.15); border-color: #10b981; color: #10b981; text-decoration: none;">
                    <i class="fas fa-check"></i> Sí
                  </a>
                <?php else: ?>
                  <a href="zona_staff_personajes.php?action=toggle_narrator&id=<?= $c['id'] ?>&val=1" class="rpg-btn-reject-lg" style="padding: 4px 8px; font-size: 10px; text-decoration: none; color: var(--text-muted); border-color: var(--border-color); background: transparent;">
                    No
                  </a>
                <?php endif; ?>
              </td>
              <td style="text-align: right;">
                <div style="display: inline-flex; gap: 6px;">
                  <!-- Asignar NPCs (sólo si es narrador) -->
                  <?php if ((int)$c['is_narrator'] === 1): ?>
                    <a href="zona_staff_personajes.php?assign_narrator_id=<?= $c['id'] ?>" class="rpg-btn-approve-lg" style="padding: 5px 10px; font-size: 11px; text-decoration: none; background: rgba(184, 151, 66, 0.12); border-color: rgba(184, 151, 66, 0.35); color: #7a5c12;">
                      <i class="fas fa-users-cog"></i> Asignar NPCs
                    </a>
                  <?php endif; ?>

                  <!-- Matar / Revivir -->
                  <?php if ($status === 'muerto'): ?>
                    <a href="zona_staff_personajes.php?action=set_status&id=<?= $c['id'] ?>&status=aprobada" class="rpg-btn-approve-lg" style="padding: 5px 10px; font-size: 11px; text-decoration: none;">
                      <i class="fas fa-heart"></i> Revivir
                    </a>
                  <?php else: ?>
                    <a href="zona_staff_personajes.php?action=set_status&id=<?= $c['id'] ?>&status=muerto" onclick="return confirm('¿Seguro que deseas matar a este personaje?')" class="rpg-btn-reject-lg" style="padding: 5px 10px; font-size: 11px; text-decoration: none;">
                      <i class="fas fa-skull"></i> Matar
                    </a>
                  <?php endif; ?>

                  <!-- Eliminar -->
                  <a href="zona_staff_personajes.php?action=delete&id=<?= $c['id'] ?>" onclick="return confirm('¿Seguro que deseas eliminar definitivamente a este personaje? Esta acción no se puede deshacer y borrará al personaje del foro.')" class="rpg-btn-reject-lg" style="padding: 5px 8px; font-size: 11px; text-decoration: none;">
                    <i class="fas fa-trash-alt"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  <?php endif; ?>

</div>
<?php
$content = ob_get_clean();
game_render_page("Gestión de Personajes del Foro", $content);
