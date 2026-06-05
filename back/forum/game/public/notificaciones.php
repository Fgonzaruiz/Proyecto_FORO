<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Game\Application\Services\NotificationService;

global $mybb, $db, $header, $footer;

$uid = (int)($mybb->user['uid'] ?? 0);
if ($uid === 0) {
    header('Location: ' . $mybb->settings['bburl'] . '/member.php?action=login');
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$prefix = TABLE_PREFIX;
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : null;

$data = NotificationService::list($uid, $active_pj_id, $page, $perPage);

$typeIcons = [
    'role_reply' => 'fa-reply',
    'admin_request' => 'fa-clipboard-list',
    'message' => 'fa-envelope',
    'dm' => 'fa-inbox',
    'system' => 'fa-bell',
    'busqueda_contact' => 'fa-search',
];
$typeLabels = [
    'role_reply' => 'Respuesta de Rol',
    'admin_request' => 'Petición Admin',
    'message' => 'Mensaje',
    'dm' => 'Buzón',
    'system' => 'Sistema',
    'busqueda_contact' => 'Propuesta de Trama',
];
$bb = $mybb->settings['bburl'];

ob_start();
?>
<div class="notif-page">
    <div class="notif-page-header">
        <h1 class="notif-page-title"><i class="fas fa-bell"></i> Notificaciones</h1>
        <?php if (!empty($data['items'])): ?>
        <button class="notif-btn-markall" onclick="marcarTodasLeidas()"><i class="fas fa-check-double"></i> Leer todas</button>
        <?php endif; ?>
    </div>

    <?php if (empty($data['items'])): ?>
    <div class="notif-empty">
        <i class="fas fa-bell-slash"></i>
        <p>No tienes notificaciones.</p>
    </div>
    <?php else: ?>
    <div class="notif-table-wrap">
        <div class="notif-header-row">
            <span></span>
            <span>Notificación</span>
            <span>Fecha</span>
            <span>Acciones</span>
        </div>
        <?php foreach ($data['items'] as $n):
            $icon = $typeIcons[$n['type']] ?? 'fa-bell';
            $label = $typeLabels[$n['type']] ?? ucfirst($n['type']);
            $isUnread = !$n['is_read'];
        ?>
        <div class="notif-row <?= $isUnread ? 'notif-unread' : '' ?>" data-id="<?= $n['id'] ?>">
            <div class="notif-row-icon">
                <i class="fas <?= $icon ?>"></i>
            </div>
            <div class="notif-row-body">
                <?php if ($n['link'] && $n['type'] !== 'busqueda_contact'):
                    $link = (strpos($n['link'], 'http://') === 0 || strpos($n['link'], 'https://') === 0) ? $n['link'] : rtrim($bb, '/') . '/' . ltrim($n['link'], '/');
                ?>
                <a href="<?= htmlspecialchars($link) ?>" class="notif-link" onclick="return marcarLeida(<?= $n['id'] ?>, this)">
                    <div class="notif-title"><?= htmlspecialchars($n['title']) ?></div>
                    <div class="notif-sub">
                        <span class="notif-type-badge"><?= htmlspecialchars($label) ?></span>
                        <?php if ($n['body']): ?>
                        &mdash; <?= htmlspecialchars(substr($n['body'], 0, 120)) ?><?= strlen($n['body']) > 120 ? '…' : '' ?>
                        <?php endif; ?>
                    </div>
                </a>
                <?php else: ?>
                <div class="notif-title"><?= htmlspecialchars($n['title']) ?></div>
                <div class="notif-sub">
                    <span class="notif-type-badge"><?= htmlspecialchars($label) ?></span>
                    <?php if ($n['body']): ?>
                    &mdash; <?= htmlspecialchars($n['body']) ?>
                    <?php endif; ?>
                </div>

                <?php if ($n['type'] === 'busqueda_contact' && !$n['is_dismissed']): ?>
                <div class="propuesta-btn-wrap">
                    <button class="notif-btn-accept" onclick="resolverPropuestaTrama(<?= $n['id'] ?>, 'aceptar', this)"><i class="fas fa-check"></i> Aceptar Trama</button>
                    <button class="notif-btn-reject" onclick="resolverPropuestaTrama(<?= $n['id'] ?>, 'rechazar', this)"><i class="fas fa-times"></i> Seguir buscando</button>
                </div>
                <?php endif; ?>

                <?php endif; ?>
            </div>
            <div class="notif-row-date"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($n['created_at']))) ?></div>
            <div class="notif-row-actions">
                <button class="notif-action-btn notif-dismiss-btn" title="<?= $n['is_dismissed'] ? 'Reactivar notificación' : 'Silenciar (quitar globo)' ?>" onclick="toggleDismiss(<?= $n['id'] ?>, <?= $n['is_dismissed'] ? 'false' : 'true' ?>, this)">
                    <i class="fas <?= $n['is_dismissed'] ? 'fa-bell-slash' : 'fa-bell' ?>"></i>
                </button>
                <button class="notif-action-btn notif-delete-btn" title="Borrar permanentemente" onclick="deleteNotif(<?= $n['id'] ?>, this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($data['total_pages'] > 1): ?>
    <div class="notif-pagination">
        <?php for ($p = 1; $p <= $data['total_pages']; $p++): ?>
        <a href="?page=<?= $p ?>" class="notif-page-link<?= $p === $page ? ' is-active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
window.NOTIFICACIONES_CONFIG = { bburl: '<?= $bb ?>', ajaxBase: '<?= $bb ?>/game/ajax' };
</script>
<script src="<?= rtrim($bb, '/') ?>/jscripts/game/notificaciones.js?v=1"></script>
<?php
$content = ob_get_clean();
game_render_page('Notificaciones', $content);
