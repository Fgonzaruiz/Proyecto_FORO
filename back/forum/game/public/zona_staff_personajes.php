<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

// Solo staff
game_require_staff_character();

global $mybb, $db;
$prefix = TABLE_PREFIX;
$b_url = $mybb->settings['bburl'];

// Verificar que sea admin (staff_level = 3) para realizar estas acciones
$current_uid = (int)($mybb->user['uid'] ?? 0);
$check_admin_q = $db->query("SELECT COUNT(*) as cnt FROM {$prefix}game_personajes WHERE user_id = {$current_uid} AND staff_level = 3");
if ($db->fetch_field($check_admin_q, 'cnt') <= 0) {
    error_no_permission();
}

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
        $pj_q = $db->query("SELECT user_id FROM {$prefix}game_personajes WHERE id = {$char_id} LIMIT 1");
        $pj = $db->fetch_array($pj_q);
        if ($pj && $pj['user_id'] > 0) {
            $u_id = (int)$pj['user_id'];
            $db->write_query("INSERT INTO {$prefix}game_user_config (user_id, max_slots, slots_used, is_narrator) 
                VALUES ({$u_id}, 1, 0, {$val}) 
                ON DUPLICATE KEY UPDATE is_narrator = {$val}");
        }
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
        $db->write_query("DELETE FROM {$prefix}game_npc_assignments WHERE character_id = {$char_id}");
        header("Location: zona_staff_personajes.php?msg=deleted");
        exit;
    }
}

function pj_img_url(string $path, string $bb): string {
    if ($path === '') return '';
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) return $path;
    return rtrim($bb, '/') . '/' . ltrim($path, '/');
}

$assign_narrator_uid = (int)($_GET['assign_narrator_uid'] ?? 0);
$assign_user = null;

if ($assign_narrator_uid > 0) {
    $user_q = $db->query("SELECT uid, username FROM {$prefix}users WHERE uid = {$assign_narrator_uid} LIMIT 1");
    $assign_user = $db->fetch_array($user_q);
}

ob_start();
?>
<div class="rpg-staff-zone">
  
  <?php if ($assign_user): ?>
    <div class="rpg-staff-header rpg-staff-header--narrador">
      <div class="rpg-staff-header-content">
        <a href="zona_staff_personajes.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Volver a Gestión de Personajes</a>
        <h1><i class="fas fa-user-shield"></i> Asignar NPCs a Narrador</h1>
        <p>Asigna qué NPCs Mayores puede utilizar el usuario narrador <strong><?= htmlspecialchars($assign_user['username']) ?></strong>.</p>
      </div>
    </div>

    <?php
    $npcs_q = $db->query("SELECT id, name, faction, avatar FROM {$prefix}game_personajes WHERE is_npc = 1 ORDER BY name ASC");
    $npcs = [];
    while ($row = $db->fetch_array($npcs_q)) {
        $npcs[] = $row;
    }

    $curr_q = $db->query("SELECT character_id FROM {$prefix}game_npc_assignments WHERE narrator_id = {$assign_user['uid']}");
    $current_assignments = [];
    while ($row = $db->fetch_array($curr_q)) {
        $current_assignments[] = (int)$row['character_id'];
    }
    ?>

    <form method="POST" class="rpg-npc-creator-form rpg-staff-form--narrow">
      <input type="hidden" name="action" value="save_assignments" />
      <input type="hidden" name="narrator_id" value="<?= $assign_user['uid'] ?>" />

      <h3 class="rpg-wizard-preview-stats-title rpg-staff-section-title">NPCs Mayores Disponibles</h3>

      <?php if (empty($npcs)): ?>
        <p class="rpg-staff-empty-note">No se han creado NPCs mayores todavía en el foro.</p>
      <?php else: ?>
        <div class="rpg-staff-npc-pick-grid">
          <?php foreach ($npcs as $npc):
              $avatar = $npc['avatar'] ? pj_img_url($npc['avatar'], $b_url) : $b_url . '/images/game/personaje_banner.png';
              $is_checked = in_array((int)$npc['id'], $current_assignments, true) ? 'checked' : '';
          ?>
            <label class="rpg-staff-npc-pick">
              <input type="checkbox" name="assigned_npcs[]" value="<?= $npc['id'] ?>" <?= $is_checked ?> />
              <img src="<?= htmlspecialchars($avatar, ENT_QUOTES) ?>" alt="" class="rpg-avatar-sm" />
              <div class="rpg-staff-pick-body">
                <strong><?= htmlspecialchars($npc['name']) ?></strong>
                <div class="rpg-staff-pick-meta"><?= htmlspecialchars($npc['faction'] ?: 'Civil') ?></div>
              </div>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="rpg-staff-form-footer">
        <a href="zona_staff_personajes.php" class="rpg-btn-reject-lg rpg-btn-link-cancel">Cancelar</a>
        <button type="submit" class="rpg-btn-approve-lg rpg-btn-submit-lg">
          <i class="fas fa-save"></i> Guardar Asignaciones
        </button>
      </div>
    </form>

  <?php else: ?>
    <div class="rpg-staff-header rpg-staff-header--personajes">
      <div class="rpg-staff-header-content">
        <a href="zona_staff.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Volver a Zona Staff</a>
        <h1><i class="fas fa-users"></i> Gestión de Personajes del Foro</h1>
        <p>Asigna roles (Colaborador, Moderador, Administrador), modifica su estado (aprobada/muerto), activa permisos de narrador o elimina personajes.</p>
      </div>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'role_updated'): ?>
      <div class="rpg-post-mods-container rpg-flash rpg-flash--success">
        <span class="rpg-post-mods-title"><i class="fas fa-check-circle"></i> Rol de staff actualizado correctamente.</span>
      </div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'narrator_updated'): ?>
      <div class="rpg-post-mods-container rpg-flash rpg-flash--info">
        <span class="rpg-post-mods-title"><i class="fas fa-info-circle"></i> Permisos de narrador actualizados correctamente.</span>
      </div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'status_updated'): ?>
      <div class="rpg-post-mods-container rpg-flash rpg-flash--warn">
        <span class="rpg-post-mods-title"><i class="fas fa-heartbeat"></i> Estado de vida/muerte del personaje actualizado.</span>
      </div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
      <div class="rpg-post-mods-container rpg-flash rpg-flash--error">
        <span class="rpg-post-mods-title"><i class="fas fa-trash-alt"></i> Personaje eliminado del foro correctamente.</span>
      </div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'assigned'): ?>
      <div class="rpg-post-mods-container rpg-flash rpg-flash--success">
        <span class="rpg-post-mods-title"><i class="fas fa-save"></i> NPCs asignados correctamente al narrador.</span>
      </div>
    <?php endif; ?>

    <?php
    $search = trim($_GET['search'] ?? '');
    $filter_role = trim($_GET['role'] ?? '');

    $where_clauses = ["p.is_npc = 0"];
    if ($search !== '') {
        $searchEsc = $db->escape_string($search);
        $where_clauses[] = "p.name LIKE '%{$searchEsc}%'";
    }

    if ($filter_role !== '') {
        if ($filter_role === 'narrator') {
            $where_clauses[] = "uc.is_narrator = 1";
        } elseif ($filter_role === 'regular') {
            $where_clauses[] = "p.staff_level = 0 AND IFNULL(uc.is_narrator, 0) = 0";
        } else {
            $level = (int)$filter_role;
            $where_clauses[] = "p.staff_level = {$level}";
        }
    }

    $where_sql = implode(' AND ', $where_clauses);
    $chars_q = $db->query("SELECT p.*, u.username, IFNULL(uc.is_narrator, 0) as is_narrator FROM {$prefix}game_personajes p 
        LEFT JOIN {$prefix}users u ON p.user_id = u.uid 
        LEFT JOIN {$prefix}game_user_config uc ON p.user_id = uc.user_id
        WHERE {$where_sql} 
        ORDER BY p.name ASC");
    $chars = [];
    while ($row = $db->fetch_array($chars_q)) {
        $chars[] = $row;
    }
    ?>

    <form method="GET" class="rpg-npc-creator-form rpg-staff-filter-form">
      <div class="rpg-staff-filter-grow">
        <input type="text" name="search" class="textbox" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar personaje por nombre..." />
      </div>
      <div class="rpg-staff-filter-shrink">
        <select name="role" class="textbox">
          <option value="">-- Todos los roles --</option>
          <option value="3" <?= $filter_role === '3' ? 'selected' : '' ?>>Administradores (Rango 3)</option>
          <option value="2" <?= $filter_role === '2' ? 'selected' : '' ?>>Moderadores (Rango 2)</option>
          <option value="1" <?= $filter_role === '1' ? 'selected' : '' ?>>Colaboradores (Rango 1)</option>
          <option value="narrator" <?= $filter_role === 'narrator' ? 'selected' : '' ?>>Narradores (is_narrator)</option>
          <option value="regular" <?= $filter_role === 'regular' ? 'selected' : '' ?>>Sin Rango (Regular)</option>
        </select>
      </div>
      <button type="submit" class="rpg-btn-approve-lg rpg-staff-filter-submit">
        <i class="fas fa-search"></i> Filtrar
      </button>
      <?php if ($search !== '' || $filter_role !== ''): ?>
        <a href="zona_staff_personajes.php" class="rpg-btn-reject-lg rpg-staff-filter-clear">Limpiar</a>
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
            <th class="rpg-staff-col-avatar">Avatar</th>
            <th>Personaje</th>
            <th>Propietario</th>
            <th>Facción</th>
            <th>Estado</th>
            <th>Rango Staff</th>
            <th>Narrador</th>
            <th class="rpg-staff-col-actions">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($chars as $c):
              $avatar = $c['avatar'] ? pj_img_url($c['avatar'], $b_url) : $b_url . '/images/game/personaje_banner.png';
              $status = $c['status'];
          ?>
            <tr>
              <td>
                <img src="<?= htmlspecialchars($avatar, ENT_QUOTES) ?>" alt="" class="rpg-avatar-md" />
              </td>
              <td>
                <strong><?= htmlspecialchars($c['name']) ?></strong>
                <div class="rpg-staff-cell-sub"><?= htmlspecialchars($c['race_name']) ?> • <?= htmlspecialchars($c['occupation_name'] ?: 'Sin Profesión') ?></div>
              </td>
              <td>
                <?php if ($c['username']): ?>
                  <span class="rpg-staff-cell-user"><i class="fas fa-user"></i> <?= htmlspecialchars($c['username']) ?></span>
                  <div class="rpg-staff-cell-uid">UID: <?= $c['user_id'] ?></div>
                <?php else: ?>
                  <span class="rpg-staff-cell-muted">Sin Cuenta</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="rpg-npc-card-badge rpg-npc-card-badge--sm"><?= htmlspecialchars($c['faction'] ?: 'Civil') ?></span>
              </td>
              <td>
                <?php if ($status === 'muerto'): ?>
                  <span class="rpg-pj-card-status rpg-pj-card-status--rechazada rpg-pj-status-pill">Muerto</span>
                <?php else: ?>
                  <span class="rpg-pj-card-status rpg-pj-card-status--<?= htmlspecialchars($status) ?> rpg-pj-status-pill"><?= htmlspecialchars($status) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <select onchange="window.location.href = 'zona_staff_personajes.php?action=set_role&id=<?= $c['id'] ?>&level=' + this.value" class="textbox rpg-staff-select-sm">
                  <option value="0" <?= (int)$c['staff_level'] === 0 ? 'selected' : '' ?>>Ninguno</option>
                  <option value="1" <?= (int)$c['staff_level'] === 1 ? 'selected' : '' ?>>1 - Colaborador</option>
                  <option value="2" <?= (int)$c['staff_level'] === 2 ? 'selected' : '' ?>>2 - Moderador</option>
                  <option value="3" <?= (int)$c['staff_level'] === 3 ? 'selected' : '' ?>>3 - Administrador</option>
                </select>
              </td>
              <td>
                <?php if ((int)$c['is_narrator'] === 1): ?>
                  <a href="zona_staff_personajes.php?action=toggle_narrator&id=<?= $c['id'] ?>&val=0" class="rpg-btn-approve-lg rpg-btn-narrator-yes">
                    <i class="fas fa-check"></i> Sí
                  </a>
                <?php else: ?>
                  <a href="zona_staff_personajes.php?action=toggle_narrator&id=<?= $c['id'] ?>&val=1" class="rpg-btn-reject-lg rpg-btn-narrator-no">
                    No
                  </a>
                <?php endif; ?>
              </td>
              <td class="rpg-staff-col-actions">
                <div class="rpg-staff-actions-inline">
                  <?php if ((int)$c['is_narrator'] === 1): ?>
                    <a href="zona_staff_personajes.php?assign_narrator_uid=<?= $c['user_id'] ?>" class="rpg-btn-approve-lg rpg-btn-assign-npcs">
                      <i class="fas fa-users-cog"></i> Asignar NPCs
                    </a>
                  <?php endif; ?>

                  <?php if ($status === 'muerto'): ?>
                    <a href="zona_staff_personajes.php?action=set_status&id=<?= $c['id'] ?>&status=aprobada" class="rpg-btn-approve-lg rpg-btn-staff-sm">
                      <i class="fas fa-heart"></i> Revivir
                    </a>
                  <?php else: ?>
                    <a href="zona_staff_personajes.php?action=set_status&id=<?= $c['id'] ?>&status=muerto" onclick="return confirm('¿Seguro que deseas matar a este personaje?')" class="rpg-btn-reject-lg rpg-btn-staff-sm">
                      <i class="fas fa-skull"></i> Matar
                    </a>
                  <?php endif; ?>

                  <a href="zona_staff_personajes.php?action=delete&id=<?= $c['id'] ?>" onclick="return confirm('¿Seguro que deseas eliminar definitivamente a este personaje? Esta acción no se puede deshacer y borrará al personaje del foro.')" class="rpg-btn-reject-lg rpg-btn-staff-delete">
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
