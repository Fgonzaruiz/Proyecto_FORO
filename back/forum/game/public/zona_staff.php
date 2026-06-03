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

// Obtener personaje activo y su staff_level
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

if ($staff_level === 0) {
    header('Location: ../index.php');
    exit;
}

// Pending character count for badge
$pendientes_q = $db->query("SELECT COUNT(*) as cnt FROM {$prefix}game_personajes WHERE status != 'aprobada'");
$pendientes_row = $db->fetch_array($pendientes_q);
$pendientes_count = (int)$pendientes_row['cnt'];

// Pending peticiones count for badge (cartas + búsquedas)
$peticiones_q = $db->query("SELECT (SELECT COUNT(*) FROM {$prefix}game_card_requests WHERE status = 'pendiente') + (SELECT COUNT(*) FROM {$prefix}game_busquedas WHERE status = 'pendiente') as cnt");
$peticiones_row = $db->fetch_array($peticiones_q);
$peticiones_count = (int)$peticiones_row['cnt'];

$staff_labels = [
    1 => 'Colaborador',
    2 => 'Moderador',
    3 => 'Administrador',
];
$staff_label = $staff_labels[$staff_level] ?? 'Staff';
$b_url = $mybb->settings['bburl'];

ob_start();
?>
<div class="rpg-staff-zone">
  <div class="rpg-staff-header" style="background: linear-gradient(135deg, rgba(198,40,40,0.15), rgba(139,92,246,0.1));">
    <div class="rpg-staff-header-content">
      <h1><i class="fas fa-shield-alt"></i> Zona <?= $staff_label ?></h1>
      <p>Bienvenido, <strong><?= htmlspecialchars($pj_name) ?></strong>. Panel de gesti&oacute;n y herramientas de staff.</p>
      <span class="rpg-staff-badge level-<?= $staff_level ?>"><?= $staff_label ?></span>
    </div>
  </div>

  <div class="rpg-staff-grid">

    <!-- HERRAMIENTAS GENERALES (todos los niveles) -->
    <div class="rpg-staff-section">
      <h2><i class="fas fa-tools"></i> Herramientas Generales</h2>
      <div class="rpg-staff-cards">
        <a class="rpg-staff-card" href="<?= $b_url ?>/game/public/zona_staff_aprobar.php">
          <div class="rpg-staff-card-icon" style="background: linear-gradient(135deg, var(--accent-emerald), var(--accent-teal));">
            <i class="fas fa-user-check"></i>
            <?php if ($pendientes_count > 0): ?>
              <span class="rpg-staff-badge-count"><?= $pendientes_count ?></span>
            <?php endif; ?>
          </div>
          <div class="rpg-staff-card-body">
            <h3>Aprobar Personajes</h3>
            <p>Revisar y aprobar fichas de personaje pendientes.</p>
          </div>
        </a>
      </div>
    </div>

    <!-- HERRAMIENTAS DE MODERACIÓN (Moderador+) -->
    <?php if ($staff_level >= 2): ?>
    <div class="rpg-staff-section">
      <h2><i class="fas fa-gavel"></i> Herramientas de Moderaci&oacute;n</h2>
      <div class="rpg-staff-cards">
        <a class="rpg-staff-card" href="<?= $b_url ?>/game/public/zona_staff_peticiones.php">
          <div class="rpg-staff-card-icon" style="background: linear-gradient(135deg, var(--accent-purple), var(--accent-pink));">
            <i class="fas fa-clipboard-check"></i>
            <?php if ($peticiones_count > 0): ?>
              <span class="rpg-staff-badge-count"><?= $peticiones_count ?></span>
            <?php endif; ?>
          </div>
          <div class="rpg-staff-card-body">
            <h3>Peticiones</h3>
            <p>Cartas, búsquedas de rol y demás solicitudes de los jugadores.</p>
          </div>
        </a>
      </div>
    </div>
    <?php endif; ?>

    <!-- HERRAMIENTAS DE ADMINISTRACIÓN (Admin solamente) -->
    <?php if ($staff_level >= 3): ?>
    <div class="rpg-staff-section">
      <h2><i class="fas fa-crown"></i> Herramientas de Administraci&oacute;n</h2>
      <div class="rpg-staff-cards">
        <a class="rpg-staff-card" href="<?= $b_url ?>/game/public/cartas_staff.php">
          <div class="rpg-staff-card-icon" style="background: linear-gradient(135deg, var(--accent-rose), var(--accent-purple));">
            <i class="fas fa-layer-group"></i>
          </div>
          <div class="rpg-staff-card-body">
            <h3>Sistema de Cartas</h3>
            <p>Crear, gestionar y asignar cartas a personajes.</p>
          </div>
        </a>
        <a class="rpg-staff-card" href="<?= $b_url ?>/game/public/anuncios_staff.php">
          <div class="rpg-staff-card-icon" style="background: linear-gradient(135deg, var(--accent-indigo), var(--accent-purple));">
            <i class="fas fa-bullhorn"></i>
          </div>
          <div class="rpg-staff-card-body">
            <h3>Gestión de Tablón</h3>
            <p>Crear y editar anuncios para el índice del foro.</p>
          </div>
        </a>
      </div>
    </div>
    <?php endif; ?>

    <!-- INFORMACIÓN DEL STAFF (todos los niveles) -->
    <div class="rpg-staff-section">
      <h2><i class="fas fa-info-circle"></i> Informaci&oacute;n del Staff</h2>
      <div class="rpg-staff-info">
        <p><strong>Personaje activo:</strong> <?= htmlspecialchars($pj_name) ?></p>
        <p><strong>Rol:</strong> <?= $staff_label ?></p>
        <p><strong>Nivel de acceso:</strong> <?= $staff_level ?></p>
      </div>
    </div>

  </div>
</div>
<?php
$content = ob_get_clean();
game_render_page("Zona {$staff_label}", $content);
