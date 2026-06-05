<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/Application/Services/StaffAccountService.php';

game_require_staff_level(3);

global $mybb, $db;
$prefix = TABLE_PREFIX;
$b_url = $mybb->settings['bburl'];
$uid = (int)($mybb->user['uid'] ?? 0);

// Get service instance
$service = new Game\Application\Services\StaffAccountService($db, $prefix, $uid);

// Handle actions
$error = '';
$msg = $_GET['msg'] ?? '';

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $target_uid = (int)($_GET['uid'] ?? 0);

    try {
        if ($action === 'ban') {
            $reason = trim($_GET['reason'] ?? '');
            $service->banUser($target_uid, $reason);
            header('Location: zona_staff_cuentas.php?msg=banned');
            exit;
        } elseif ($action === 'unban') {
            $service->unbanUser($target_uid);
            header('Location: zona_staff_cuentas.php?msg=unbanned');
            exit;
        } elseif ($action === 'set_narrator') {
            $enabled = (int)($_GET['enabled'] ?? 0) === 1;
            $service->setNarrator($target_uid, $enabled);
            header('Location: zona_staff_cuentas.php?msg=narrator_updated');
            exit;
        } elseif ($action === 'set_max_slots') {
            $slots = (int)($_GET['slots'] ?? 1);
            $service->setMaxSlots($target_uid, $slots);
            header('Location: zona_staff_cuentas.php?msg=slots_updated');
            exit;
        } elseif ($action === 'set_posting') {
            $field = $_GET['field'] ?? '';
            $enabled = (int)($_GET['enabled'] ?? 0) === 1;
            if ($field === 'suspendposting') {
                $service->setSuspendPosting($target_uid, $enabled);
            } elseif ($field === 'moderateposts') {
                $service->setModeratePosts($target_uid, $enabled);
            }
            header('Location: zona_staff_cuentas.php?msg=posting_updated');
            exit;
        } elseif ($action === 'clear_active_pj') {
            $service->clearActiveCharacter($target_uid);
            header('Location: zona_staff_cuentas.php?msg=active_pj_cleared');
            exit;
        } elseif ($action === 'sync_slots') {
            $service->syncSlotsUsed($target_uid);
            header('Location: zona_staff_cuentas.php?msg=slots_synced');
            exit;
        }
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle POST actions (NPC assignments)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_npcs') {
    $target_uid = (int)($_POST['target_uid'] ?? 0);
    $npc_ids = $_POST['staff_npc_ids'] ?? [];
    try {
        $service->saveNpcAssignments($target_uid, array_map('intval', $npc_ids));
        header('Location: zona_staff_cuentas.php?msg=npcs_updated');
        exit;
    } catch (\Exception $e) {
        $error = $e->getMessage();
        $_GET['manage_npcs'] = $target_uid; // Fallback to keep showing form
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
<div class="rpg-staff-zone" id="staffCuentasApp">
    <div class="rpg-staff-header rpg-staff-header--cuentas">
        <div class="rpg-staff-header-content">
            <a href="zona_staff.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Volver a Zona Staff</a>
            <h1><i class="fas fa-user-cog"></i> Gestión de Cuentas del Foro</h1>
            <p>Moderación a nivel de usuario: slots de personajes, narrador, NPCs, suspensión de publicación y baneos.</p>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="rpg-post-mods-container rpg-flash rpg-flash--error">
            <span class="rpg-post-mods-title"><i class="fas fa-exclamation-circle"></i> Error: <?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($msg === 'banned'): ?>
        <div class="rpg-post-mods-container rpg-flash rpg-flash--error">
            <span class="rpg-post-mods-title"><i class="fas fa-ban"></i> Cuenta baneada correctamente del foro.</span>
        </div>
    <?php elseif ($msg === 'unbanned'): ?>
        <div class="rpg-post-mods-container rpg-flash rpg-flash--success">
            <span class="rpg-post-mods-title"><i class="fas fa-unlock"></i> Baneo de la cuenta removido correctamente.</span>
        </div>
    <?php elseif ($msg === 'narrator_updated'): ?>
        <div class="rpg-post-mods-container rpg-flash rpg-flash--success">
            <span class="rpg-post-mods-title"><i class="fas fa-check-circle"></i> Estado de narrador actualizado.</span>
        </div>
    <?php elseif ($msg === 'slots_updated'): ?>
        <div class="rpg-post-mods-container rpg-flash rpg-flash--success">
            <span class="rpg-post-mods-title"><i class="fas fa-check-circle"></i> Ranuras máximas de personajes actualizadas.</span>
        </div>
    <?php elseif ($msg === 'posting_updated'): ?>
        <div class="rpg-post-mods-container rpg-flash rpg-flash--success">
            <span class="rpg-post-mods-title"><i class="fas fa-comment-slash"></i> Moderación de publicación de cuenta actualizada.</span>
        </div>
    <?php elseif ($msg === 'active_pj_cleared'): ?>
        <div class="rpg-post-mods-container rpg-flash rpg-flash--warn">
            <span class="rpg-post-mods-title"><i class="fas fa-eraser"></i> Personaje activo de la cuenta limpiado.</span>
        </div>
    <?php elseif ($msg === 'slots_synced'): ?>
        <div class="rpg-post-mods-container rpg-flash rpg-flash--success">
            <span class="rpg-post-mods-title"><i class="fas fa-sync"></i> Ranuras en uso recalculadas y sincronizadas.</span>
        </div>
    <?php elseif ($msg === 'npcs_updated'): ?>
        <div class="rpg-post-mods-container rpg-flash rpg-flash--success">
            <span class="rpg-post-mods-title"><i class="fas fa-save"></i> NPCs asignados al narrador guardados correctamente.</span>
        </div>
    <?php endif; ?>

    <?php
    $manage_npcs_uid = (int)($_GET['manage_npcs'] ?? 0);
    if ($manage_npcs_uid > 0):
        // ---------------- NPC MANAGEMENT PANEL ----------------
        $u_q = $db->query("SELECT uid, username FROM {$prefix}users WHERE uid = {$manage_npcs_uid} LIMIT 1");
        $target_user = $db->fetch_array($u_q);
        if (!$target_user):
            echo "<p>Usuario no encontrado.</p>";
        else:
            // Fetch all NPCs
            $npcs = [];
            $n_q = $db->query("SELECT id, name, faction FROM {$prefix}game_personajes WHERE is_npc = 1 ORDER BY name ASC");
            while ($row = $db->fetch_array($n_q)) {
                $npcs[] = $row;
            }

            // Fetch current assignments
            $assigned = [];
            $a_q = $db->query("SELECT character_id FROM {$prefix}game_npc_assignments WHERE narrator_id = {$manage_npcs_uid}");
            while ($row = $db->fetch_array($a_q)) {
                $assigned[] = (int)$row['character_id'];
            }
            ?>
            <div class="rpg-staff-section">
                <h3><i class="fas fa-users-cog"></i> NPCs Asignados a Narrador: <?= htmlspecialchars($target_user['username']) ?> (UID <?= $manage_npcs_uid ?>)</h3>
                <form method="POST" action="zona_staff_cuentas.php" class="rpg-staff-editor-form">
                    <input type="hidden" name="action" value="save_npcs" />
                    <input type="hidden" name="target_uid" value="<?= $manage_npcs_uid ?>" />

                    <?php if (empty($npcs)): ?>
                        <p class="rpg-staff-empty-note">No hay NPCs registrados en el sistema. Créalos primero en la gestión de NPCs.</p>
                    <?php else: ?>
                        <div class="rpg-staff-npc-pick-grid">
                            <?php foreach ($npcs as $npc):
                                $checked = in_array((int)$npc['id'], $assigned, true) ? 'checked' : '';
                            ?>
                                <label class="rpg-staff-npc-pick">
                                    <input type="checkbox" name="staff_npc_ids[]" value="<?= $npc['id'] ?>" <?= $checked ?> />
                                    <div class="rpg-staff-pick-body">
                                        <strong><?= htmlspecialchars($npc['name']) ?></strong>
                                        <div class="rpg-staff-pick-meta"><?= htmlspecialchars($npc['faction'] ?: 'Civil') ?></div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="rpg-staff-editor-actions rpg-mt-20">
                        <a href="zona_staff_cuentas.php" class="rpg-action-btn rpg-btn-secondary">Volver a Cuentas</a>
                        <button type="submit" class="rpg-action-btn rpg-btn-primary">Guardar Asignaciones</button>
                    </div>
                </form>
            </div>
            <?php
        endif;
    else:
        // ---------------- MAIN USERS TABLE LIST ----------------
        $search = trim($_GET['search'] ?? '');
        $filter_status = trim($_GET['status'] ?? '');

        $where_clauses = ['1=1'];
        if ($search !== '') {
            $searchEsc = $db->escape_string($search);
            $where_clauses[] = "(u.username LIKE '%{$searchEsc}%' OR u.uid = '{$searchEsc}')";
        }

        if ($filter_status !== '') {
            if ($filter_status === 'banned') {
                $where_clauses[] = "u.uid IN (SELECT uid FROM {$prefix}banned)";
            } elseif ($filter_status === 'narrator') {
                $where_clauses[] = "uc.is_narrator = 1";
            } elseif ($filter_status === 'suspended') {
                $where_clauses[] = "u.suspendposting = 1";
            } elseif ($filter_status === 'moderate') {
                $where_clauses[] = "u.moderateposts = 1";
            }
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Pagination setup
        $limit = 25;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        $total_q = $db->query("SELECT COUNT(*) as total FROM {$prefix}users u LEFT JOIN {$prefix}game_user_config uc ON u.uid = uc.user_id WHERE {$where_sql}");
        $total = (int)$db->fetch_field($total_q, 'total');
        $total_pages = (int)ceil($total / $limit);

        $users_q = $db->query("SELECT u.uid, u.username, u.email, u.avatar, u.suspendposting, u.moderateposts,
            uc.max_slots, uc.slots_used, uc.active_pj_id, uc.is_narrator,
            (SELECT COUNT(*) FROM {$prefix}banned b WHERE b.uid = u.uid) as is_banned,
            (SELECT name FROM {$prefix}game_personajes p WHERE p.id = uc.active_pj_id LIMIT 1) as active_pj_name,
            (SELECT COUNT(*) FROM {$prefix}game_personajes p WHERE p.user_id = u.uid AND p.is_npc = 0) as actual_slots_used
            FROM {$prefix}users u
            LEFT JOIN {$prefix}game_user_config uc ON u.uid = uc.user_id
            WHERE {$where_sql}
            ORDER BY u.username ASC
            LIMIT {$offset}, {$limit}");

        $users = [];
        while ($row = $db->fetch_array($users_q)) {
            $users[] = $row;
        }
        ?>

        <form method="GET" class="rpg-npc-creator-form rpg-staff-filter-form">
            <div class="rpg-staff-filter-grow">
                <input type="text" name="search" class="textbox" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar cuenta por nombre de usuario o UID..." />
            </div>
            <div class="rpg-staff-filter-shrink">
                <select name="status" class="textbox">
                    <option value="">-- Todos los estados --</option>
                    <option value="banned" <?= $filter_status === 'banned' ? 'selected' : '' ?>>Baneados</option>
                    <option value="narrator" <?= $filter_status === 'narrator' ? 'selected' : '' ?>>Narradores</option>
                    <option value="suspended" <?= $filter_status === 'suspended' ? 'selected' : '' ?>>Posts Suspendidos</option>
                    <option value="moderate" <?= $filter_status === 'moderate' ? 'selected' : '' ?>>Posts Moderados</option>
                </select>
            </div>
            <button type="submit" class="rpg-btn-approve-lg rpg-staff-filter-submit">
                <i class="fas fa-search"></i> Filtrar
            </button>
            <?php if ($search !== '' || $filter_status !== ''): ?>
                <a href="zona_staff_cuentas.php" class="rpg-btn-reject-lg rpg-staff-filter-clear">Limpiar</a>
            <?php endif; ?>
        </form>

        <?php if (empty($users)): ?>
            <div class="rpg-akuma-empty">
                <div class="rpg-akuma-empty-icon"><i class="fas fa-user-slash"></i></div>
                <p>No se encontraron cuentas que coincidan con la búsqueda.</p>
            </div>
        <?php else: ?>
            <table class="rpg-staff-table">
                <thead>
                    <tr>
                        <th class="rpg-staff-col-avatar">Avatar</th>
                        <th>Usuario (UID)</th>
                        <th>Estado</th>
                        <th class="rpg-staff-col-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u):
                        $avatar = $u['avatar'] ? pj_img_url($u['avatar'], $b_url) : $b_url . '/images/default_avatar.png';
                        $is_banned = (int)$u['is_banned'] > 0;
                        $is_narrator = (int)($u['is_narrator'] ?? 0) === 1;
                        $max_slots = (int)($u['max_slots'] ?? 1);
                        $actual_slots = (int)$u['actual_slots_used'];
                    ?>
                        <tr>
                            <td>
                                <img src="<?= htmlspecialchars($avatar, ENT_QUOTES) ?>" alt="" class="rpg-avatar-md" />
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($u['username']) ?></strong>
                                <div class="rpg-staff-cell-sub">UID: <?= $u['uid'] ?> &bull; <?= htmlspecialchars($u['email']) ?></div>
                            </td>
                            <td>
                                <?php if ($is_banned): ?>
                                    <span class="rpg-pj-status-pill rpg-pj-card-status--rechazada">Baneado</span>
                                <?php else: ?>
                                    <span class="rpg-pj-status-pill rpg-pj-card-status--aprobada">Activa</span>
                                <?php endif; ?>
                            </td>
                            <td class="rpg-staff-col-actions">
                                <button type="button" class="rpg-btn-approve-lg rpg-btn-staff-sm edit-account-btn"
                                  data-uid="<?= $u['uid'] ?>"
                                  data-username="<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>"
                                  data-email="<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>"
                                  data-avatar="<?= htmlspecialchars($avatar, ENT_QUOTES) ?>"
                                  data-is-banned="<?= $is_banned ? '1' : '0' ?>"
                                  data-is-narrator="<?= $is_narrator ? '1' : '0' ?>"
                                  data-max-slots="<?= $max_slots ?>"
                                  data-actual-slots="<?= $actual_slots ?>"
                                  data-active-pj-id="<?= (int)$u['active_pj_id'] ?>"
                                  data-active-pj-name="<?= htmlspecialchars($u['active_pj_name'] ?? '', ENT_QUOTES) ?>"
                                  data-suspend-posting="<?= (int)$u['suspendposting'] === 1 ? '1' : '0' ?>"
                                  data-moderate-posts="<?= (int)$u['moderateposts'] === 1 ? '1' : '0' ?>">
                                  <i class="fas fa-edit"></i> Editar
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="rpg-pagination">
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <a href="zona_staff_cuentas.php?page=<?= $p ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>" class="rpg-pagination-link <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Drawer de edición de cuenta -->
<div class="rpg-staff-drawer rpg-is-hidden" id="account-editor-drawer">
    <div class="rpg-staff-drawer__backdrop" id="account-editor-backdrop"></div>
    <div class="rpg-staff-drawer__panel rpg-staff-drawer__panel--narrow">
        <div class="rpg-staff-drawer__header">
            <h2 id="account-editor-title"><i class="fas fa-user-cog"></i> Gestionar Cuenta</h2>
            <button type="button" class="rpg-staff-drawer__close" id="account-editor-close">&times;</button>
        </div>
        <div class="rpg-staff-drawer__body">
            <!-- Resumen del usuario -->
            <div class="rpg-staff-pj-summary">
                <img id="account-summary-avatar" src="" alt="" class="rpg-avatar-lg">
                <div class="rpg-staff-pj-summary-info">
                    <h3 id="account-summary-name"></h3>
                    <p id="account-summary-email"></p>
                    <p id="account-summary-uid"></p>
                </div>
            </div>

            <hr class="rpg-staff-divider">

            <!-- Slots de Personaje -->
            <div class="rpg-staff-form-section">
                <h4><i class="fas fa-layer-group"></i> Ranuras de Personajes</h4>
                <div class="rpg-staff-slots-row">
                    <div id="account-slots-used" class="rpg-mb-8">Ranuras en uso: 0</div>
                    <div class="rpg-staff-input-group">
                        <select id="account-slots-max-select" class="textbox">
                            <?php for ($s = 1; $s <= 20; $s++): ?>
                                <option value="<?= $s ?>"><?= $s ?> Slots Máximos</option>
                            <?php endfor; ?>
                        </select>
                        <button type="button" id="btn-save-slots" class="rpg-btn-approve-lg">Guardar</button>
                    </div>
                </div>
                <div class="rpg-mt-8">
                    <a href="" id="btn-sync-slots" class="rpg-btn-approve-lg rpg-btn-full"><i class="fas fa-sync"></i> Recalcular y Sincronizar Uso</a>
                </div>
            </div>

            <!-- Estado de Narrador -->
            <div class="rpg-staff-form-section">
                <h4><i class="fas fa-user-ninja"></i> Estado de Narrador</h4>
                <div class="rpg-staff-actions-grid">
                    <a href="" id="btn-toggle-narrator" class="rpg-btn-approve-lg rpg-btn-full">
                        <!-- Hacer Narrador / Quitar Narrador -->
                    </a>
                    <a href="" id="btn-manage-npcs" class="rpg-btn-approve-lg rpg-btn-full rpg-is-hidden">
                        <i class="fas fa-users-cog"></i> Asignar NPCs Mayores
                    </a>
                </div>
            </div>

            <!-- Moderación de Publicación -->
            <div class="rpg-staff-form-section">
                <h4><i class="fas fa-comment-slash"></i> Moderación de Publicación</h4>
                <div class="rpg-staff-actions-grid">
                    <a href="" id="btn-toggle-suspend" class="rpg-btn-approve-lg rpg-btn-full">
                        <!-- Suspender posts / Habilitar posts -->
                    </a>
                    <a href="" id="btn-toggle-moderate" class="rpg-btn-approve-lg rpg-btn-full">
                        <!-- Moderar posts / Quitar moderación -->
                    </a>
                </div>
            </div>

            <!-- Personaje Activo -->
            <div class="rpg-staff-form-section">
                <h4><i class="fas fa-user-check"></i> Personaje Activo</h4>
                <div id="account-active-pj-info" class="rpg-mb-8">Ninguno</div>
                <a href="" id="btn-clear-active-pj" class="rpg-btn-reject-lg rpg-btn-full rpg-is-hidden">
                    <i class="fas fa-eraser"></i> Limpiar Personaje Activo
                </a>
            </div>

            <!-- Danger Zone: Banear / Desbanear -->
            <div class="rpg-staff-form-section">
                <h4><i class="fas fa-exclamation-triangle"></i> Zona de Peligro</h4>
                <a href="" id="btn-toggle-ban" class="rpg-btn-reject-lg rpg-btn-full">
                    <!-- Banear Cuenta / Desbanear Cuenta -->
                </a>
            </div>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars(rtrim($b_url, '/')) ?>/jscripts/game/zona_staff_cuentas.js?v=3"></script>
<?php
$content = ob_get_clean();
game_render_page('Gestionar Cuentas — Staff', $content);
