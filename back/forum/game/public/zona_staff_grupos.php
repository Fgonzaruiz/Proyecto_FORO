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

$pj_q = $db->query("SELECT staff_level FROM {$prefix}game_personajes WHERE user_id = {$uid} AND id = (SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1) LIMIT 1");
$pj = $db->fetch_array($pj_q);

if (!$pj || (int)$pj['staff_level'] < 2) { // Allow Game Master or Admin
    echo "Acceso denegado.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $crew_id = (int)($_POST['crew_id'] ?? 0);
    
    if ($_POST['action'] === 'approve_crew') {
        $db->query("UPDATE {$prefix}game_tripulaciones SET status = 'aprobada' WHERE id = {$crew_id}");
        // Update the leader's tripulacion_id now that it is approved
        $crew_data = $db->fetch_array($db->query("SELECT leader_pj_id FROM {$prefix}game_tripulaciones WHERE id = {$crew_id}"));
        if ($crew_data) {
            $db->query("UPDATE {$prefix}game_personajes SET tripulacion_id = {$crew_id} WHERE id = {$crew_data['leader_pj_id']}");
        }
    } elseif ($_POST['action'] === 'reject_crew') {
        $db->query("DELETE FROM {$prefix}game_tripulacion_miembros WHERE tripulacion_id = {$crew_id}");
        $db->query("DELETE FROM {$prefix}game_tripulaciones WHERE id = {$crew_id}");
    } elseif ($_POST['action'] === 'delete_crew') {
        $db->query("UPDATE {$prefix}game_personajes SET tripulacion_id = NULL WHERE tripulacion_id = {$crew_id}");
        $db->query("DELETE FROM {$prefix}game_tripulacion_miembros WHERE tripulacion_id = {$crew_id}");
        $db->query("DELETE FROM {$prefix}game_tripulaciones WHERE id = {$crew_id}");
    }
    
    header("Location: zona_staff_grupos.php");
    exit;
}

// Fetch all crews
$crews_pending = [];
$crews_active = [];

$q = $db->query("SELECT t.*, p.name as leader_name FROM {$prefix}game_tripulaciones t LEFT JOIN {$prefix}game_personajes p ON t.leader_pj_id = p.id ORDER BY t.name ASC");
while ($r = $db->fetch_array($q)) {
    if ($r['status'] === 'pendiente') {
        $crews_pending[] = $r;
    } else {
        $crews_active[] = $r;
    }
}

ob_start();
?>
<div class="rpg-staff-zone">
    <div class="rpg-staff-header">
        <div class="rpg-staff-header-content">
            <a href="zona_staff.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Volver</a>
            <h1><i class="fas fa-users"></i> Gestión de Grupos (Staff)</h1>
        </div>
    </div>
    
    <div class="rpg-staff-section">
        <h2>Grupos Pendientes de Aprobación</h2>
        <div class="rpg-table-responsive">
            <table class="rpg-staff-table">
                <thead>
                    <tr>
                        <th class="rpg-text-center">Bandera</th>
                        <th>Nombre</th>
                        <th>Lema</th>
                        <th>Líder</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($crews_pending as $c): ?>
                    <tr>
                        <td class="rpg-text-center">
                            <?php if ($c['image_url']): ?>
                                <img src="<?= htmlspecialchars($c['image_url']) ?>" class="crew-manage-avatar-xs" alt="">
                            <?php else: ?>
                                <i class="fas fa-skull-crossbones"></i>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="tripulacion.php?id=<?= $c['id'] ?>" class="rpg-text-primary-color crew-leader-link" target="_blank">
                                <?= htmlspecialchars($c['name']) ?> <i class="fas fa-external-link-alt"></i>
                            </a>
                        </td>
                        <td><em><?= htmlspecialchars($c['motto'] ?? 'Sin lema') ?></em></td>
                        <td><?= htmlspecialchars($c['leader_name'] ?? 'Sin asignar') ?></td>
                        <td>
                            <form method="post" class="rpg-inline-form">
                                <input type="hidden" name="action" value="approve_crew">
                                <input type="hidden" name="crew_id" value="<?= $c['id'] ?>">
                                <button type="submit" class="rpg-btn-sm rpg-btn--primary">Aprobar</button>
                            </form>
                            <form method="post" class="rpg-inline-form" onsubmit="return confirm('¿Rechazar solicitud y eliminar tripulación?');">
                                <input type="hidden" name="action" value="reject_crew">
                                <input type="hidden" name="crew_id" value="<?= $c['id'] ?>">
                                <button type="submit" class="rpg-btn-sm rpg-btn--danger">Rechazar</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($crews_pending)): ?>
                        <tr><td colspan="5" class="rpg-center">No hay grupos pendientes.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="rpg-staff-section rpg-mt-20">
        <h2>Grupos Activos</h2>
        <div class="rpg-table-responsive">
            <table class="rpg-staff-table">
                <thead>
                    <tr>
                        <th class="rpg-text-center">Bandera</th>
                        <th>Nombre</th>
                        <th>Lema</th>
                        <th>Líder</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($crews_active as $c): ?>
                    <tr>
                        <td class="rpg-text-center">
                            <?php if ($c['image_url']): ?>
                                <img src="<?= htmlspecialchars($c['image_url']) ?>" class="crew-manage-avatar-xs" alt="">
                            <?php else: ?>
                                <i class="fas fa-skull-crossbones"></i>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="tripulacion.php?id=<?= $c['id'] ?>" class="rpg-text-primary-color crew-leader-link" target="_blank">
                                <?= htmlspecialchars($c['name']) ?> <i class="fas fa-external-link-alt"></i>
                            </a>
                        </td>
                        <td><em><?= htmlspecialchars($c['motto'] ?? 'Sin lema') ?></em></td>
                        <td><?= htmlspecialchars($c['leader_name'] ?? 'Sin asignar') ?></td>
                        <td>
                            <form method="post" class="rpg-inline-form" onsubmit="return confirm('¿Eliminar tripulación por completo? (Disolver)');">
                                <input type="hidden" name="action" value="delete_crew">
                                <input type="hidden" name="crew_id" value="<?= $c['id'] ?>">
                                <button type="submit" class="rpg-btn-sm rpg-btn--danger">Disolver</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($crews_active)): ?>
                        <tr><td colspan="5" class="rpg-center">No hay grupos activos.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
game_render_page('Gestión de Grupos', $content);
