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
];
$typeLabels = [
    'role_reply' => 'Respuesta de Rol',
    'admin_request' => 'Petición Admin',
    'message' => 'Mensaje',
    'system' => 'Sistema',
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
        <div class="notif-header-row" style="display:grid; grid-template-columns:40px 1fr auto auto; gap:0; background:var(--bg-main); border-bottom:1px solid var(--border-color); padding:0 16px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted);">
            <span style="padding:10px 0;"></span>
            <span style="padding:10px 0;">Notificación</span>
            <span style="padding:10px 0; text-align:right; min-width:70px;">Fecha</span>
            <span style="padding:10px 0; text-align:center; width:70px;"></span>
        </div>
        <?php foreach ($data['items'] as $n):
            $icon = $typeIcons[$n['type']] ?? 'fa-bell';
            $label = $typeLabels[$n['type']] ?? ucfirst($n['type']);
            $isUnread = !$n['is_read'];
            $rowBg = $isUnread ? 'var(--bg-card-hover)' : 'transparent';
        ?>
        <div class="notif-row <?= $isUnread ? 'notif-unread' : '' ?>" data-id="<?= $n['id'] ?>" style="display:grid; grid-template-columns:40px 1fr auto auto; gap:0; padding:0 16px; border-bottom:1px solid var(--border-color); background:<?= $rowBg ?>; transition:background 0.15s;">
            <div style="display:flex; align-items:center; justify-content:center;">
                <i class="fas <?= $icon ?>" style="color:<?= $isUnread ? 'var(--accent-indigo)' : 'var(--text-muted)' ?>; font-size:14px;"></i>
            </div>
            <div style="padding:14px 8px;">
                <?php if ($n['link']): 
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
                    &mdash; <?= htmlspecialchars(substr($n['body'], 0, 120)) ?><?= strlen($n['body']) > 120 ? '…' : '' ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div style="display:flex; align-items:center; justify-content:flex-end; padding:14px 8px; white-space:nowrap; font-size:12px; color:var(--text-muted);"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($n['created_at']))) ?></div>
            <div style="display:flex; align-items:center; justify-content:center; width:70px; gap:6px;">
                <button class="notif-dismiss-btn" title="<?= $n['is_dismissed'] ? 'Reactivar notificación' : 'Silenciar (quitar globo)' ?>" onclick="toggleDismiss(<?= $n['id'] ?>, <?= $n['is_dismissed'] ? 'false' : 'true' ?>, this)" style="background:none; border:none; cursor:pointer; color:var(--text-muted); font-size:14px; padding:4px; border-radius:4px; transition:all 0.15s;">
                    <i class="fas <?= $n['is_dismissed'] ? 'fa-bell-slash' : 'fa-bell' ?>"></i>
                </button>
                <button class="notif-delete-btn" title="Borrar permanentemente" onclick="deleteNotif(<?= $n['id'] ?>, this)" style="background:none; border:none; cursor:pointer; color:#ef4444; font-size:14px; padding:4px; border-radius:4px; transition:all 0.15s; opacity:0.8;">
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

function marcarLeida(id, el) {
    fetch(AJAX_BASE + '/notifications_mark_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    }).then(function(r){ return r.json() }).then(function(d){
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
    fetch(AJAX_BASE + '/notifications_mark_all_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: '{}'
    }).then(function(r){ return r.json() }).then(function(d){
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
    fetch(AJAX_BASE + '/notifications_dismiss.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, dismissed: dismissed })
    }).then(function(r){ return r.json() }).then(function(d){
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
    fetch(AJAX_BASE + '/notifications_delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    }).then(function(r){ return r.json() }).then(function(d){
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
</script>
<?php
$content = ob_get_clean();
game_render_page('Notificaciones', $content);
