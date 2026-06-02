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
<div class="notif-page" style="max-width: 900px; margin: 0 auto; padding: 20px 0;">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom: 24px; flex-wrap:wrap; gap:12px;">
        <h1 style="font-family:var(--font-heading); font-size:24px; color:var(--text-primary); margin:0;"><i class="fas fa-bell" style="color:var(--accent-indigo); margin-right:10px;"></i> Notificaciones</h1>
        <?php if (!empty($data['items'])): ?>
        <button class="notif-btn-markall" onclick="marcarTodasLeidas()" style="background:var(--accent-indigo); color:#fff; border:none; padding:8px 16px; border-radius:var(--radius-md); font-size:13px; font-weight:600; cursor:pointer;"><i class="fas fa-check-double"></i> Leer todas</button>
        <?php endif; ?>
    </div>

    <?php if (empty($data['items'])): ?>
    <div style="text-align:center; padding:60px 20px; color:var(--text-muted);">
        <i class="fas fa-bell-slash" style="font-size:48px; margin-bottom:16px; opacity:0.4;"></i>
        <p style="font-size:16px;">No tienes notificaciones.</p>
    </div>
    <?php else: ?>
    <div class="notif-table-wrap" style="background:var(--bg-surface); border:1px solid var(--border-color); border-radius:var(--radius-lg); overflow:hidden;">
        <div class="notif-header-row" style="display:grid; grid-template-columns:40px minmax(0, 1fr) auto 80px; gap:12px; background:var(--bg-main); border-bottom:1px solid var(--border-color); padding:0 16px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); align-items:center;">
            <span style="padding:10px 0;"></span>
            <span style="padding:10px 0;">Notificación</span>
            <span style="padding:10px 0; text-align:right; min-width:70px;">Fecha</span>
            <span style="padding:10px 0; text-align:center;">Acciones</span>
        </div>
        <?php foreach ($data['items'] as $n):
            $icon = $typeIcons[$n['type']] ?? 'fa-bell';
            $label = $typeLabels[$n['type']] ?? ucfirst($n['type']);
            $isUnread = !$n['is_read'];
            $rowBg = $isUnread ? 'var(--bg-card-hover)' : 'transparent';
        ?>
        <div class="notif-row <?= $isUnread ? 'notif-unread' : '' ?>" data-id="<?= $n['id'] ?>" style="display:grid; grid-template-columns:40px minmax(0, 1fr) auto 80px; gap:12px; padding:0 16px; border-bottom:1px solid var(--border-color); background:<?= $rowBg ?>; transition:background 0.15s; align-items:center;">
            <div style="display:flex; align-items:center; justify-content:center;">
                <i class="fas <?= $icon ?>" style="color:<?= $isUnread ? 'var(--accent-indigo)' : 'var(--text-muted)' ?>; font-size:14px;"></i>
            </div>
            <div style="padding:14px 8px;">
                <?php if ($n['link'] && $n['type'] !== 'busqueda_contact'): 
                    $link = (strpos($n['link'], 'http://') === 0 || strpos($n['link'], 'https://') === 0) ? $n['link'] : rtrim($bb, '/') . '/' . ltrim($n['link'], '/');
                ?>
                <a href="<?= htmlspecialchars($link) ?>" class="notif-link" onclick="return marcarLeida(<?= $n['id'] ?>, this)" style="text-decoration:none; color:inherit; display:block;">
                    <div style="font-size:14px; font-weight:<?= $isUnread ? '700' : '400' ?>; color:var(--text-primary); margin-bottom:2px;"><?= htmlspecialchars($n['title']) ?></div>
                    <div style="font-size:12px; color:var(--text-muted);">
                        <span style="background:var(--bg-main); padding:1px 6px; border-radius:4px; font-size:10px; font-weight:600; text-transform:uppercase;"><?= htmlspecialchars($label) ?></span>
                        <?php if ($n['body']): ?>
                        &mdash; <?= htmlspecialchars(substr($n['body'], 0, 120)) ?><?= strlen($n['body']) > 120 ? '…' : '' ?>
                        <?php endif; ?>
                    </div>
                </a>
                <?php else: ?>
                <div style="font-size:14px; font-weight:<?= $isUnread ? '700' : '400' ?>; color:var(--text-primary); margin-bottom:2px;"><?= htmlspecialchars($n['title']) ?></div>
                <div style="font-size:12px; color:var(--text-muted);">
                    <span style="background:var(--bg-main); padding:1px 6px; border-radius:4px; font-size:10px; font-weight:600; text-transform:uppercase;"><?= htmlspecialchars($label) ?></span>
                    <?php if ($n['body']): ?>
                    &mdash; <?= htmlspecialchars($n['body']) ?>
                    <?php endif; ?>
                </div>
                
                <?php if ($n['type'] === 'busqueda_contact' && !$n['is_dismissed']): ?>
                <div class="propuesta-btn-wrap" style="margin-top: 10px; display: flex; gap: 8px;">
                    <button onclick="resolverPropuestaTrama(<?= $n['id'] ?>, 'aceptar', this)" style="background: linear-gradient(135deg, var(--accent-emerald), #059669); color: #fff; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; transition: opacity 0.2s; display:flex; align-items:center; gap:4px;"><i class="fas fa-check"></i> Aceptar Trama</button>
                    <button onclick="resolverPropuestaTrama(<?= $n['id'] ?>, 'rechazar', this)" style="background: linear-gradient(135deg, var(--accent-rose), #dc2626); color: #fff; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; transition: opacity 0.2s; display:flex; align-items:center; gap:4px;"><i class="fas fa-times"></i> Seguir buscando</button>
                </div>
                <?php endif; ?>
                
                <?php endif; ?>
            </div>
            <div style="display:flex; align-items:center; justify-content:flex-end; padding:14px 8px; white-space:nowrap; font-size:12px; color:var(--text-muted);"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($n['created_at']))) ?></div>
            <div style="display:flex; align-items:center; justify-content:center; gap:6px; padding:14px 0;">
                <button class="notif-dismiss-btn" title="<?= $n['is_dismissed'] ? 'Reactivar notificación' : 'Silenciar (quitar globo)' ?>" onclick="toggleDismiss(<?= $n['id'] ?>, <?= $n['is_dismissed'] ? 'false' : 'true' ?>, this)" style="background:var(--accent-indigo); border:none; cursor:pointer; color:#fff; font-size:14px; width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; transition:all 0.15s; box-shadow:0 2px 8px rgba(99,102,241,0.2);">
                    <i class="fas <?= $n['is_dismissed'] ? 'fa-bell-slash' : 'fa-bell' ?>"></i>
                </button>
                <button class="notif-delete-btn" title="Borrar permanentemente" onclick="deleteNotif(<?= $n['id'] ?>, this)" style="background:var(--accent-indigo); border:none; cursor:pointer; color:#fff; font-size:14px; width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; transition:all 0.15s; box-shadow:0 2px 8px rgba(99,102,241,0.2);">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($data['total_pages'] > 1): ?>
    <div style="display:flex; justify-content:center; gap:6px; margin-top:20px; flex-wrap:wrap;">
        <?php for ($p = 1; $p <= $data['total_pages']; $p++): ?>
        <a href="?page=<?= $p ?>" style="padding:6px 14px; border-radius:var(--radius-md); background:<?= $p === $page ? 'var(--accent-indigo)' : 'var(--bg-surface)' ?>; color:<?= $p === $page ? '#fff' : 'var(--text-primary)' ?>; text-decoration:none; font-size:13px; font-weight:600; border:1px solid var(--border-color);"><?= $p ?></a>
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

function marcarLeida(id, el) {
    notifPost('/notifications_mark_read.php', { id: id }).then(function(d){
        if (d.ok) {
            var row = document.querySelector('.notif-row[data-id="' + id + '"]');
            if (row) {
                row.classList.remove('notif-unread');
                row.style.background = 'transparent';
                var link = row.querySelector('.notif-link');
                if (link) {
                    var title = link.querySelector('div:first-child');
                    if (title) title.style.fontWeight = '400';
                }
            }
            actualizarBadge();
        }
    }).catch(function(){});
    return true;
}

function marcarTodasLeidas() {
    notifPost('/notifications_mark_all_read.php', {}).then(function(d){
        if (d.ok) {
            document.querySelectorAll('.notif-row').forEach(function(row){
                row.classList.remove('notif-unread');
                row.style.background = 'transparent';
                var link = row.querySelector('.notif-link');
                if (link) {
                    var title = link.querySelector('div:first-child');
                    if (title) title.style.fontWeight = '400';
                }
            });
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
                if (cnt > 0) {
                    badge.textContent = cnt;
                    badge.style.display = 'flex';
                    var bell = document.getElementById('notification-bell');
                    if (bell) bell.classList.add('has-unread');
                } else {
                    badge.style.display = 'none';
                    var bell = document.getElementById('notification-bell');
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
                    row.style.opacity = '0.7';
                    // Marcar visualmente la fila como leída/procesada
                    row.classList.remove('notif-unread');
                    row.style.background = 'transparent';
                    
                    var descDiv = row.querySelector('div:nth-child(2)');
                    if (descDiv) {
                        var statusMsg = action === 'aceptar' 
                            ? '<div style="color:var(--accent-emerald); font-weight:700; margin-top:6px; font-size:12px;"><i class="fas fa-check-circle"></i> Aceptaste la trama. Búsqueda eliminada.</div>'
                            : '<div style="color:var(--accent-rose); font-weight:700; margin-top:6px; font-size:12px;"><i class="fas fa-info-circle"></i> Declinaste la propuesta. Sigues buscando.</div>';
                        descDiv.innerHTML += statusMsg;
                    }
                    var btnWrap = row.querySelector('.propuesta-btn-wrap');
                    if (btnWrap) btnWrap.remove();
                }
                actualizarBadge();
            } else {
                alert('Error: ' + res.error);
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = origText;
            alert('Error de conexión.');
        });
}
</script>
<?php
$content = ob_get_clean();
game_render_page('Notificaciones', $content);
