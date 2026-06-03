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
$actual_q = $db->query("SELECT COUNT(*) AS cnt FROM {$prefix}game_personajes WHERE user_id = {$uid} AND status != 'rechazada' AND is_npc = 0");
$slots_used = (int)$db->fetch_field($actual_q, 'cnt');
if ((int)($cfg['slots_used'] ?? 0) !== $slots_used) {
    $db->write_query("UPDATE {$prefix}game_user_config SET slots_used = {$slots_used} WHERE user_id = {$uid}");
}

$chars_q = $db->query("SELECT * FROM {$prefix}game_personajes WHERE user_id = {$uid} AND status != 'rechazada' AND is_npc = 0 ORDER BY id ASC");
$chars = [];
while ($row = $db->fetch_array($chars_q)) {
    $chars[] = $row;
}

// Encontrar si el usuario tiene algún personaje narrador
$user_narrator_pjs = [];
$npjs_assigned = [];

$narrator_pjs_q = $db->query("SELECT id FROM {$prefix}game_personajes WHERE user_id = {$uid} AND is_narrator = 1");
while ($narr_row = $db->fetch_array($narrator_pjs_q)) {
    $user_narrator_pjs[] = (int)$narr_row['id'];
}

$is_admin = false;
$check_admin_q = $db->query("SELECT COUNT(*) as cnt FROM {$prefix}game_personajes WHERE user_id = {$uid} AND staff_level = 3");
if ($db->fetch_field($check_admin_q, 'cnt') > 0) {
    $is_admin = true;
}

if ($is_admin) {
    $assigned_q = $db->query("SELECT * FROM {$prefix}game_personajes WHERE is_npc = 1 ORDER BY name ASC");
    while ($npc_row = $db->fetch_array($assigned_q)) {
        $npjs_assigned[] = $npc_row;
    }
} elseif (!empty($user_narrator_pjs)) {
    $narr_ids_str = implode(',', $user_narrator_pjs);
    $assigned_q = $db->query("SELECT p.* FROM {$prefix}game_personajes p 
        INNER JOIN {$prefix}game_npc_assignments a ON p.id = a.character_id 
        WHERE a.narrator_id IN ({$narr_ids_str}) AND p.is_npc = 1
        ORDER BY p.name ASC");
    while ($npc_row = $db->fetch_array($assigned_q)) {
        $npjs_assigned[] = $npc_row;
    }
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
                    <div class="rpg-pj-card-avatar rpg-pj-card-avatar--has-img" data-bg="<?= htmlspecialchars($avatar, ENT_QUOTES) ?>">
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

    <?php if (!empty($npjs_assigned)): ?>
        <div class="rpg-char-page-header" style="margin-top: 40px; border-top: 1px dashed var(--border-color); padding-top: 30px;">
            <h2 class="rpg-char-page-title" style="font-size: 18px;"><i class="fas fa-user-secret"></i> NPCs Mayores Asignados</h2>
        </div>
        <div class="rpg-pj-grid">
            <?php foreach ($npjs_assigned as $c):
                $is_active = (int)$c['id'] === $active_id;
                $img = $c['avatar'] ?: $c['banner'] ?? '';
                $avatar = $img ? resolve_img($img, $bb) : $b_url;
            ?>
                <div class="rpg-pj-card <?= $is_active ? 'rpg-pj-card--active' : '' ?>" data-pj-id="<?= $c['id'] ?>">
                    <div class="rpg-pj-card-avatar rpg-pj-card-avatar--has-img" data-bg="<?= htmlspecialchars($avatar, ENT_QUOTES) ?>">
                        <?php if ($is_active): ?>
                            <div class="rpg-pj-active-badge"><i class="fas fa-check-circle"></i></div>
                        <?php endif; ?>
                    </div>
                    <div class="rpg-pj-card-body">
                        <h3 class="rpg-pj-card-name"><?= htmlspecialchars($c['name']) ?></h3>
                        <div class="rpg-pj-card-meta">
                            <span><i class="fas fa-dragon"></i> <?= htmlspecialchars($c['race_name']) ?></span>
                            <span><i class="fas fa-briefcase"></i> <?= htmlspecialchars($c['occupation_name'] ?? 'Ninguno') ?></span>
                        </div>
                        <div class="rpg-pj-card-tags">
                            <span class="rpg-pj-tag rpg-npc-card-badge--faction" style="background: rgba(184, 151, 66, 0.12); color: #7a5c12; border-color: rgba(184, 151, 66, 0.3); font-size: 9px;"><?= htmlspecialchars($c['faction'] ?: 'Civil') ?></span>
                        </div>
                    </div>
                    <div class="rpg-pj-card-actions rpg-pj-card-actions--stack">
                        <?php if (!$is_active): ?>
                            <button class="rpg-pj-btn rpg-pj-btn-primary rpg-pj-btn--block" onclick="switchPJ(<?= $c['id'] ?>, this)">Seleccionar</button>
                        <?php else: ?>
                            <span class="rpg-pj-btn rpg-pj-btn-active rpg-pj-btn--block"><i class="fas fa-check"></i> Activo</span>
                        <?php endif; ?>
                        
                        <div class="rpg-pj-btn-row">
                            <a href="<?= $bb ?>/game/public/personaje.php?pj=<?= $c['id'] ?>" class="rpg-pj-btn rpg-pj-btn-secondary" style="flex: 1;"><i class="fas fa-external-link-alt"></i> Ver Ficha</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
window.MIS_PERSONAJES_CONFIG = { bburl: '<?= $bb ?>' };
</script>
<script src="<?= rtrim($bb, '/') ?>/jscripts/game/mis_personajes.js?v=1"></script>
<?php
$content = ob_get_clean();
game_render_page('Mis Personajes', $content);
