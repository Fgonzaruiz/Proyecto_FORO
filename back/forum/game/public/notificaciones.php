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
    'system' => 'fa-bell',
    'busqueda_contact' => 'fa-search',
];
$typeLabels = [
    'role_reply' => 'Respuesta de Rol',
    'admin_request' => 'Petición Admin',
    'message' => 'Mensaje',
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
const AJAX_BASE = '<?= $bb ?>/game/ajax';

function notifPost(path, payload) {
    var url = AJAX_BASE + path;
    if (window.gamePostJson) {
        return window.gamePostJson(url, payload || {});
    }
    var body = payload || {};
    if (window.GAME_CSRF) {
        body.my_post_key = window.GAME_CSRF;
    }
    return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Mybb-Post-Key': window.GAME_CSRF || '' },
        credentials: 'same-origin',
        body: JSON.stringify(body)
    }).then(function (r) { return r.json(); });
}

function markRowRead(row) {
    if (!row) return;
    row.classList.remove('notif-unread');
    var title = row.querySelector('.notif-title');
    if (title) title.classList.remove('notif-title--bold');
}

function marcarLeida(id, el) {
    notifPost('/notifications_mark_read.php', { id: id }).then(function(d){
        if (d.ok) {
            markRowRead(document.querySelector('.notif-row[data-id="' + id + '"]'));
            actualizarBadge();
        }
    }).catch(function(){});
    return true;
}

function marcarTodasLeidas() {
    notifPost('/notifications_mark_all_read.php', {}).then(function(d){
        if (d.ok) {
            document.querySelectorAll('.notif-row').forEach(markRowRead);
            actualizarBadge();
        }
    }).catch(function(){});
}

function toggleDismiss(id, dismissed, btn) {
    notifPost('/notifications_dismiss.php', { id: id, dismissed: dismissed }).then(function(d){
        if (d.ok) {
            var icon = btn.querySelector('i');
            if (dismissed) {
                icon.className = 'fas fa-bell-slash';
                btn.title = 'Reactivar notificación';
                btn.setAttribute('onclick', 'toggleDismiss(' + id + ', false, this)');
            } else {
                icon.className = 'fas fa-bell';
                btn.title = 'Silenciar (quitar globo)';
                btn.setAttribute('onclick', 'toggleDismiss(' + id + ', true, this)');
            }
            actualizarBadge();
        }
    }).catch(function(){});
}

function deleteNotif(id, btn) {
    if (!confirm('¿Seguro que deseas borrar esta notificación?')) return;
    notifPost('/notifications_delete.php', { id: id }).then(function(d){
        if (d.ok) {
            var row = document.querySelector('.notif-row[data-id="' + id + '"]');
            if (row) row.remove();
            actualizarBadge();
        } else {
            alert('Error al borrar: ' + (d.error ? d.error.message : 'Desconocido'));
        }
    }).catch(function(){});
}

function actualizarBadge() {
    var badge = document.getElementById('notification-badge');
    if (!badge) return;
    fetch(AJAX_BASE + '/notifications_count.php?_t=' + Date.now())
        .then(function(r){ return r.json() })
        .then(function(d){
            if (d.ok && d.data) {
                var cnt = d.data.unread || 0;
                var bell = document.getElementById('notification-bell');
                if (cnt > 0) {
                    badge.textContent = cnt;
                    badge.classList.remove('is-hidden');
                    if (bell) bell.classList.add('has-unread');
                } else {
                    badge.classList.add('is-hidden');
                    if (bell) bell.classList.remove('has-unread');
                }
            }
        })
        .catch(function(){});
}

function resolverPropuestaTrama(notifId, action, btn) {
    if (btn.disabled) return;
    btn.disabled = true;
    var origText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ...';

    var fd = new FormData();
    fd.append('notification_id', notifId);
    fd.append('action', action);

    (window.gamePostForm
        ? window.gamePostForm(AJAX_BASE + '/busquedas_resolve_contact.php', fd)
        : fetch(AJAX_BASE + '/busquedas_resolve_contact.php', {
            method: 'POST',
            headers: { 'X-Mybb-Post-Key': window.GAME_CSRF || '' },
            credentials: 'same-origin',
            body: (function () {
                if (window.GAME_CSRF) { fd.append('my_post_key', window.GAME_CSRF); }
                return fd;
            })()
        }).then(function (r) { return r.json(); })
    ).then(function (res) {
            btn.disabled = false;
            btn.innerHTML = origText;
            if (res.ok) {
                var row = document.querySelector('.notif-row[data-id="' + notifId + '"]');
                if (row) {
                    row.classList.add('is-processed');
                    markRowRead(row);
                    var descDiv = row.querySelector('.notif-row-body');
                    if (descDiv) {
                        var statusMsg = action === 'aceptar'
                            ? '<div class="notif-status-msg notif-status-msg--ok"><i class="fas fa-check-circle"></i> Aceptaste la trama. Búsqueda eliminada.</div>'
                            : '<div class="notif-status-msg notif-status-msg--no"><i class="fas fa-info-circle"></i> Declinaste la propuesta. Sigues buscando.</div>';
                        descDiv.insertAdjacentHTML('beforeend', statusMsg);
                    }
                    var btnWrap = row.querySelector('.propuesta-btn-wrap');
                    if (btnWrap) btnWrap.remove();
                }
                actualizarBadge();
            } else {
                alert('Error: ' + res.error);
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.innerHTML = origText;
            alert('Error de conexión.');
        });
}
</script>
<?php
$content = ob_get_clean();
game_render_page('Notificaciones', $content);
