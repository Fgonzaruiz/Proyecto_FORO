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
  <div class="rpg-staff-header" style="background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(139,92,246,0.1));">
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
        <a class="rpg-staff-card" href="#">
          <div class="rpg-staff-card-icon" style="background: linear-gradient(135deg, var(--accent-indigo), var(--accent-purple));">
            <i class="fas fa-users-cog"></i>
          </div>
          <div class="rpg-staff-card-body">
            <h3>Gestionar NPCs</h3>
            <p>Crear, editar y administrar personajes no jugadores.</p>
          </div>
        </a>
        <a class="rpg-staff-card" href="#">
          <div class="rpg-staff-card-icon" style="background: linear-gradient(135deg, var(--accent-teal), var(--accent-emerald));">
            <i class="fas fa-scroll"></i>
          </div>
          <div class="rpg-staff-card-body">
            <h3>Bit&aacute;cora de Partidas</h3>
            <p>Registrar y dar seguimiento a partidas y eventos de rol.</p>
          </div>
        </a>
        <a class="rpg-staff-card" href="#">
          <div class="rpg-staff-card-icon" style="background: linear-gradient(135deg, var(--accent-amber), var(--accent-orange));">
            <i class="fas fa-file-alt"></i>
          </div>
          <div class="rpg-staff-card-body">
            <h3>Reportes</h3>
            <p>Revisar reportes de jugadores y actividades sospechosas.</p>
          </div>
        </a>
      </div>
    </div>

    <!-- HERRAMIENTAS DE MODERACIÓN (Moderador+) -->
    <?php if ($staff_level >= 2): ?>
    <div class="rpg-staff-section">
      <h2><i class="fas fa-gavel"></i> Herramientas de Moderaci&oacute;n</h2>
      <div class="rpg-staff-cards">
        <a class="rpg-staff-card" href="#">
          <div class="rpg-staff-card-icon" style="background: linear-gradient(135deg, var(--accent-rose), var(--accent-pink));">
            <i class="fas fa-flag"></i>
          </div>
          <div class="rpg-staff-card-body">
            <h3>Sanciones</h3>
            <p>Gestionar advertencias, suspensiones y expulsiones.</p>
          </div>
        </a>
        <a class="rpg-staff-card" href="<?= $b_url ?>/game/public/zona_staff_peticiones.php">
          <div class="rpg-staff-card-icon" style="background: linear-gradient(135deg, var(--accent-purple), var(--accent-pink));">
            <i class="fas fa-clipboard-check"></i>
          </div>
          <div class="rpg-staff-card-body">
            <h3>Peticiones Pendientes</h3>
            <p>Revisar y responder peticiones de los jugadores.</p>
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
        <a class="rpg-staff-card" href="#">
          <div class="rpg-staff-card-icon" style="background: linear-gradient(135deg, var(--accent-amber), var(--accent-rose));">
            <i class="fas fa-database"></i>
          </div>
          <div class="rpg-staff-card-body">
            <h3>Gesti&oacute;n del Juego</h3>
            <p>Configurar par&aacute;metros globales del RPG, econom&iacute;a y balances.</p>
          </div>
        </a>
        <a class="rpg-staff-card" href="<?= $b_url ?>/game/public/cartas_staff.php">
          <div class="rpg-staff-card-icon" style="background: linear-gradient(135deg, var(--accent-rose), var(--accent-purple));">
            <i class="fas fa-layer-group"></i>
          </div>
          <div class="rpg-staff-card-body">
            <h3>Sistema de Cartas</h3>
            <p>Crear, gestionar y asignar cartas a personajes.</p>
          </div>
        </a>
        <a class="rpg-staff-card" href="#">
          <div class="rpg-staff-card-icon" style="background: linear-gradient(135deg, var(--accent-indigo), var(--accent-blue));">
            <i class="fas fa-user-shield"></i>
          </div>
          <div class="rpg-staff-card-body">
            <h3>Staff</h3>
            <p>Administrar el equipo de moderaci&oacute;n y permisos.</p>
          </div>
        </a>
        <a class="rpg-staff-card" href="#">
          <div class="rpg-staff-card-icon" style="background: linear-gradient(135deg, var(--accent-orange), var(--accent-amber));">
            <i class="fas fa-cogs"></i>
          </div>
          <div class="rpg-staff-card-body">
            <h3>Configuraci&oacute;n Global</h3>
            <p>Ajustes del foro y del sistema de juego.</p>
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
