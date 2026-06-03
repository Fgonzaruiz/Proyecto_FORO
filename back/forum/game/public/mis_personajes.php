<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

$uid = (int)($mybb->user['uid'] ?? 0);
$prefix = TABLE_PREFIX;

if (!$uid) {
    ob_start();
    ?><div class="rpg-char-empty"><i class="fas fa-user-lock"></i><h2>Debes iniciar sesi&oacute;n</h2><p>Inicia sesi&oacute;n para ver tus personajes.</p></div><?php
    $content = ob_get_clean();
    game_render_page('Mis Personajes', $content);
    exit;
}

// Ensure user_config exists
$db->write_query("INSERT INTO {$prefix}game_user_config (user_id, max_slots, slots_used) VALUES ({$uid}, 1, 0) ON DUPLICATE KEY UPDATE user_id=user_id");

// Temporary hack to give user 1 a free slot for testing:
if ($uid === 1) {
    $db->write_query("UPDATE {$prefix}game_user_config SET max_slots = 3 WHERE user_id = 1");
}

$cfg_q = $db->query("SELECT * FROM {$prefix}game_user_config WHERE user_id = {$uid}");
$cfg = $db->fetch_array($cfg_q);

$max_slots = (int)$cfg['max_slots'];
$active_id = (int)$cfg['active_pj_id'];

// Recalculate slots_used from actual non-rejected characters to prevent desync
$actual_q = $db->query("SELECT COUNT(*) AS cnt FROM {$prefix}game_personajes WHERE user_id = {$uid} AND status != 'rechazada'");
$slots_used = (int)$db->fetch_field($actual_q, 'cnt');
if ((int)($cfg['slots_used'] ?? 0) !== $slots_used) {
    $db->write_query("UPDATE {$prefix}game_user_config SET slots_used = {$slots_used} WHERE user_id = {$uid}");
}

$chars_q = $db->query("SELECT * FROM {$prefix}game_personajes WHERE user_id = {$uid} AND status != 'rechazada' ORDER BY id ASC");
$chars = [];
while ($row = $db->fetch_array($chars_q)) {
    $chars[] = $row;
}

$bb = $mybb->settings['bburl'];

function resolve_img(string $path, string $bb): string {
    if ($path === '') return '';
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) return $path;
    return rtrim($bb, '/') . '/' . ltrim($path, '/');
}

$b_url = $bb . '/images/game/personaje_banner.png';

ob_start();
?>
<div class="rpg-char-page rpg-char-page--wide">
    <div class="rpg-char-page-header">
        <h1 class="rpg-char-page-title">Mis Personajes</h1>
        <span class="rpg-lib-modal-badge rpg-lib-modal-badge--accent">
            <i class="fas fa-layer-group"></i> Slots: <?= $slots_used ?> / <?= $max_slots ?>
        </span>
    </div>

    <?php if (empty($chars) && $slots_used >= $max_slots): ?>
        <div class="rpg-char-empty"><p>No tienes personajes y no te quedan slots.</p></div>
    <?php else: ?>
        <div class="rpg-pj-grid">
            <?php foreach ($chars as $c):
                $is_active = (int)$c['id'] === $active_id;
                $img = $c['avatar'] ?: $c['banner'] ?? '';
                $avatar = $img ? resolve_img($img, $bb) : $b_url;
                $status = $c['status'] ?? 'pendiente';
            ?>
                <div class="rpg-pj-card <?= $is_active ? 'rpg-pj-card--active' : '' ?>" data-pj-id="<?= $c['id'] ?>">
                    <div class="rpg-pj-card-avatar rpg-pj-card-avatar--has-img" style="--avatar-bg:url('<?= htmlspecialchars($avatar) ?>')">
                        <?php if ($is_active): ?>
                            <div class="rpg-pj-active-badge"><i class="fas fa-check-circle"></i></div>
                        <?php endif; ?>
                        <div class="rpg-pj-card-status rpg-pj-card-status--<?= htmlspecialchars($status) ?>">
                            <?= htmlspecialchars($status) ?>
                        </div>
                    </div>
                    <div class="rpg-pj-card-body">
                        <h3 class="rpg-pj-card-name"><?= htmlspecialchars($c['name']) ?></h3>
                        <div class="rpg-pj-card-meta">
                            <span><i class="fas fa-dragon"></i> <?= htmlspecialchars($c['race_name']) ?></span>
                            <span><i class="fas fa-briefcase"></i> <?= htmlspecialchars($c['occupation_name'] ?? 'Ninguno') ?></span>
                        </div>
                        <?php if ($c['rango'] || ($c['tripulacion'] ?? '')): ?>
                            <div class="rpg-pj-card-tags">
                                <?php if ($c['rango']): ?><span class="rpg-pj-tag"><?= htmlspecialchars($c['rango']) ?></span><?php endif; ?>
                                <?php if ($c['tripulacion'] ?? ''): ?><span class="rpg-pj-tag"><?= htmlspecialchars($c['tripulacion']) ?></span><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="rpg-pj-card-actions rpg-pj-card-actions--stack">
                        <?php if (!$is_active): ?>
                            <button class="rpg-pj-btn rpg-pj-btn-primary rpg-pj-btn--block" onclick="switchPJ(<?= $c['id'] ?>, this)">Seleccionar</button>
                        <?php else: ?>
                            <span class="rpg-pj-btn rpg-pj-btn-active rpg-pj-btn--block"><i class="fas fa-check"></i> Activo</span>
                        <?php endif; ?>
                        
                        <div class="rpg-pj-btn-row">
                            <?php if ($status === 'revision' || $status === 'pendiente'): ?>
                                <a href="<?= $bb ?>/game/public/crear_personaje.php?pj_id=<?= $c['id'] ?>" class="rpg-pj-btn rpg-pj-btn-edit"><i class="fas fa-edit"></i> Editar</a>
                            <?php endif; ?>
                            <a href="<?= $bb ?>/game/public/personaje.php?pj=<?= $c['id'] ?>" class="rpg-pj-btn rpg-pj-btn-secondary"><i class="fas fa-external-link-alt"></i> Ver</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if ($slots_used < $max_slots): ?>
                <a href="<?= $bb ?>/game/public/crear_personaje.php" class="rpg-pj-card-empty-link">
                    <div class="rpg-pj-card rpg-pj-card-empty rpg-pj-card-empty--create">
                        <div class="rpg-pj-card-avatar">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div class="rpg-pj-card-body">
                            <h3 class="rpg-pj-card-name">¡Crear nuevo personaje!</h3>
                        </div>
                    </div>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function switchPJ(pjId, btn) {
    btn.disabled = true;
    btn.textContent = '...';

    var url = '<?= $bb ?>/game/ajax/set_active_pj.php';
    var req = window.gamePostJson
        ? window.gamePostJson(url, { pj_id: pjId })
        : fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Mybb-Post-Key': window.GAME_CSRF || '' },
            credentials: 'same-origin',
            body: JSON.stringify({ pj_id: pjId, my_post_key: window.GAME_CSRF || '' })
        }).then(function (r) { return r.json(); });
    req.then(function(d){
        if (!d.ok) { alert(d.error.message); btn.disabled = false; btn.textContent = 'Seleccionar'; return; }

        // Remove all badges and active classes
        document.querySelectorAll('.rpg-pj-card').forEach(function(card){
            card.classList.remove('rpg-pj-card--active');
            var b = card.querySelector('.rpg-pj-active-badge');
            if (b) b.remove();
        });

        // Replace any "Activo" span with a clickable "Seleccionar" button
        document.querySelectorAll('.rpg-pj-btn-active').forEach(function(span){
            var card = span.closest('.rpg-pj-card');
            var pid = card ? card.getAttribute('data-pj-id') : null;
            var outer = span.parentNode;
            var newBtn = document.createElement('button');
            newBtn.className = 'rpg-pj-btn rpg-pj-btn-primary';
            newBtn.textContent = 'Seleccionar';
            if (pid) newBtn.setAttribute('onclick', 'switchPJ(' + pid + ', this)');
            outer.replaceChild(newBtn, span);
        });

        // Activate selected card
        var card = document.querySelector('.rpg-pj-card[data-pj-id="'+pjId+'"]');
        if (card) {
            card.classList.add('rpg-pj-card--active');
            // Add badge
            var avatar = card.querySelector('.rpg-pj-card-avatar');
            var badge = document.createElement('div');
            badge.className = 'rpg-pj-active-badge';
            badge.innerHTML = '<i class="fas fa-check-circle"></i>';
            avatar.appendChild(badge);
            // Update top-right name
            var wb = document.querySelector('.nav-welcome-text');
            if (wb) {
                var text = wb.childNodes[0];
                if (text) text.textContent = ' ' + card.querySelector('.rpg-pj-card-name').textContent + ' ';
            }
            // This button becomes "Activo"
            btn.outerHTML = '<span class="rpg-pj-btn rpg-pj-btn-active"><i class="fas fa-check"></i> Activo</span>';
        }
    })
    .catch(function(e){ alert('Error de conexión'); btn.disabled = false; btn.textContent = 'Seleccionar'; });
}
</script>
<?php
$content = ob_get_clean();
game_render_page('Mis Personajes', $content);
