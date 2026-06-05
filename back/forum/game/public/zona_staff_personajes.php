<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

game_require_staff_level(3);

global $mybb, $db;
$prefix = TABLE_PREFIX;
$b_url = $mybb->settings['bburl'];

// Cambiar staff_level / rol del personaje
if (isset($_GET['action']) && $_GET['action'] === 'set_role') {
    $char_id = (int)($_GET['id'] ?? 0);
    $level = (int)($_GET['level'] ?? 0);
    if ($char_id > 0 && in_array($level, [0, 1, 2, 3], true)) {
        $is_staff = $level > 0 ? 1 : 0;
        $db->write_query("UPDATE {$prefix}game_personajes SET staff_level = {$level}, is_staff = {$is_staff} WHERE id = {$char_id} AND is_npc = 0");
        header('Location: zona_staff_personajes.php?msg=role_updated');
        exit;
    }
}

// Cambiar berries del personaje
if (isset($_GET['action']) && $_GET['action'] === 'set_berries') {
    $char_id = (int)($_GET['id'] ?? 0);
    $berries = (int)($_GET['berries'] ?? 0);
    if ($char_id > 0 && $berries >= 0) {
        $db->write_query("UPDATE {$prefix}game_personajes SET berries = {$berries} WHERE id = {$char_id} AND is_npc = 0");
        header('Location: zona_staff_personajes.php?msg=berries_updated');
        exit;
    }
}

// Cambiar status (Matar / Revivir)
if (isset($_GET['action']) && $_GET['action'] === 'set_status') {
    $char_id = (int)($_GET['id'] ?? 0);
    $status = $_GET['status'] === 'muerto' ? 'muerto' : 'aprobada';
    if ($char_id > 0) {
        $db->write_query("UPDATE {$prefix}game_personajes SET status = '{$status}' WHERE id = {$char_id} AND is_npc = 0");
        header('Location: zona_staff_personajes.php?msg=status_updated');
        exit;
    }
}

// Eliminar personaje
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $char_id = (int)($_GET['id'] ?? 0);
    if ($char_id > 0) {
        $db->write_query("DELETE FROM {$prefix}game_personajes WHERE id = {$char_id} AND is_npc = 0");
        $db->write_query("DELETE FROM {$prefix}game_npc_assignments WHERE character_id = {$char_id}");
        header('Location: zona_staff_personajes.php?msg=deleted');
        exit;
    }
}

function pj_img_url(string $path, string $bb): string {
    if ($path === '') {
        return '';
    }
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
        return $path;
    }
    return rtrim($bb, '/') . '/' . ltrim($path, '/');
}

ob_start();
?>
<div class="rpg-staff-zone">
    <div class="rpg-staff-header rpg-staff-header--personajes">
      <div class="rpg-staff-header-content">
        <a href="zona_staff.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Volver a Zona Staff</a>
        <h1><i class="fas fa-users"></i> Gestión de Personajes del Foro</h1>
        <p>Asigna rango staff al personaje (Colaborador, Moderador, Administrador), cambia su estado de vida o elimina fichas. La moderación de cuenta (narrador, slots, baneos) está en <a href="zona_staff_cuentas.php">Gestionar Cuenta</a>.</p>
      </div>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'role_updated'): ?>
      <div class="rpg-post-mods-container rpg-flash rpg-flash--success">
        <span class="rpg-post-mods-title"><i class="fas fa-check-circle"></i> Rol de staff actualizado correctamente.</span>
      </div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'berries_updated'): ?>
      <div class="rpg-post-mods-container rpg-flash rpg-flash--success">
        <span class="rpg-post-mods-title"><i class="fas fa-coins"></i> Saldo de Berries del personaje actualizado.</span>
      </div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'status_updated'): ?>
      <div class="rpg-post-mods-container rpg-flash rpg-flash--warn">
        <span class="rpg-post-mods-title"><i class="fas fa-heartbeat"></i> Estado de vida/muerte del personaje actualizado.</span>
      </div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
      <div class="rpg-post-mods-container rpg-flash rpg-flash--error">
        <span class="rpg-post-mods-title"><i class="fas fa-trash-alt"></i> Personaje eliminado del foro correctamente.</span>
      </div>
    <?php endif; ?>

    <?php
    $search = trim($_GET['search'] ?? '');
    $filter_role = trim($_GET['role'] ?? '');

    $where_clauses = ['p.is_npc = 0'];
    if ($search !== '') {
        $searchEsc = $db->escape_string($search);
        $where_clauses[] = "p.name LIKE '%{$searchEsc}%'";
    }

    if ($filter_role !== '') {
        if ($filter_role === 'regular') {
            $where_clauses[] = 'p.staff_level = 0';
        } else {
            $level = (int)$filter_role;
            $where_clauses[] = "p.staff_level = {$level}";
        }
    }

    $where_sql = implode(' AND ', $where_clauses);
    $chars_q = $db->query("SELECT p.*, u.username
        FROM {$prefix}game_personajes p
        LEFT JOIN {$prefix}users u ON p.user_id = u.uid
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
            <th>Berries</th>
            <th>Estado</th>
            <th>Rango Staff</th>
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
                <div class="rpg-staff-cell-sub"><?= htmlspecialchars($c['race_name']) ?> &bull; <?= htmlspecialchars($c['occupation_name'] ?: 'Sin Profesión') ?></div>
              </td>
              <td>
                <?php if ($c['username']): ?>
                  <span class="rpg-staff-cell-user"><i class="fas fa-user"></i> <?= htmlspecialchars($c['username']) ?></span>
                  <div class="rpg-staff-cell-uid">
                    UID: <?= (int)$c['user_id'] ?>
                    &middot; <a href="zona_staff_cuentas.php?q=<?= (int)$c['user_id'] ?>">Gestionar cuenta</a>
                  </div>
                <?php else: ?>
                  <span class="rpg-staff-cell-muted">Sin Cuenta</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="rpg-npc-card-badge rpg-npc-card-badge--sm"><?= htmlspecialchars($c['faction'] ?: 'Civil') ?></span>
              </td>
              <td>
                <form method="GET" class="rpg-staff-berries-form">
                  <input type="hidden" name="action" value="set_berries">
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                  <input type="number" name="berries" value="<?= (int)($c['berries'] ?? 0) ?>" class="textbox rpg-staff-berries-input" min="0">
                  <button type="submit" class="rpg-staff-berries-btn" title="Guardar Berries">
                    <i class="fas fa-save"></i>
                  </button>
                </form>
              </td>
              <td>
                <?php if ($status === 'muerto'): ?>
                  <span class="rpg-pj-status-pill rpg-pj-card-status--rechazada">Muerto</span>
                <?php else: ?>
                  <span class="rpg-pj-status-pill rpg-pj-card-status--<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <select onchange="window.location.href = 'zona_staff_personajes.php?action=set_role&id=<?= (int)$c['id'] ?>&level=' + this.value" class="textbox rpg-staff-select-sm">
                  <option value="0" <?= (int)$c['staff_level'] === 0 ? 'selected' : '' ?>>Ninguno</option>
                  <option value="1" <?= (int)$c['staff_level'] === 1 ? 'selected' : '' ?>>1 - Colaborador</option>
                  <option value="2" <?= (int)$c['staff_level'] === 2 ? 'selected' : '' ?>>2 - Moderador</option>
                  <option value="3" <?= (int)$c['staff_level'] === 3 ? 'selected' : '' ?>>3 - Administrador</option>
                </select>
              </td>
              <td class="rpg-staff-col-actions">
                <div class="rpg-staff-actions-inline">
                  <a href="<?= htmlspecialchars($b_url) ?>/game/public/personaje.php?pj=<?= (int)$c['id'] ?>" class="rpg-btn-approve-lg rpg-btn-staff-sm" target="_blank" rel="noopener">
                    <i class="fas fa-external-link-alt"></i> Ficha
                  </a>
                  <?php if ($status === 'muerto'): ?>
                    <a href="zona_staff_personajes.php?action=set_status&id=<?= (int)$c['id'] ?>&status=aprobada" class="rpg-btn-approve-lg rpg-btn-staff-sm">
                      <i class="fas fa-heart"></i> Revivir
                    </a>
                  <?php else: ?>
                    <a href="zona_staff_personajes.php?action=set_status&id=<?= (int)$c['id'] ?>&status=muerto" onclick="return confirm('¿Seguro que deseas matar a este personaje?')" class="rpg-btn-reject-lg rpg-btn-staff-sm">
                      <i class="fas fa-skull"></i> Matar
                    </a>
                  <?php endif; ?>
                  <a href="zona_staff_personajes.php?action=delete&id=<?= (int)$c['id'] ?>" onclick="return confirm('¿Eliminar definitivamente este personaje?')" class="rpg-btn-reject-lg rpg-btn-staff-delete">
                    <i class="fas fa-trash-alt"></i>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
game_render_page('Gestión de Personajes del Foro', $content);
