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
$is_narrator = (int)($cfg['is_narrator'] ?? 0) === 1;

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

$npjs_assigned = [];

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
} elseif ($is_narrator) {
    $assigned_q = $db->query("SELECT p.* FROM {$prefix}game_personajes p 
        INNER JOIN {$prefix}game_npc_assignments a ON p.id = a.character_id 
        WHERE a.narrator_id = {$uid} AND p.is_npc = 1
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

$auto_open_edit = null;
$edit_pj_param = isset($_GET['edit_pj']) ? (int)$_GET['edit_pj'] : 0;
if ($edit_pj_param > 0) {
    $found_c = null;
    foreach ($chars as $c) {
        if ((int)$c['id'] === $edit_pj_param) {
            $found_c = $c;
            break;
        }
    }
    if (!$found_c && ($is_admin || $is_narrator)) {
        foreach ($npjs_assigned as $c) {
            if ((int)$c['id'] === $edit_pj_param) {
                $found_c = $c;
                break;
            }
        }
    }
    if ($found_c) {
        $auto_open_edit = [
            'id' => (int)$found_c['id'],
            'name' => $found_c['name'],
            'avatar' => $found_c['avatar'] ?? '',
            'banner' => $found_c['banner'] ?? '',
            'signature' => $found_c['firma'] ?? '',
            'isNpc' => (int)($found_c['is_npc'] ?? 0) === 1,
        ];
    }
}

ob_start();
?>
<div class="rpg-char-page rpg-char-page--wide">
    <div class="rpg-char-page-header">
        <h1 class="rpg-char-page-title">Mis Personajes</h1>
        <span class="rpg-lib-modal-badge rpg-lib-modal-badge--accent">
            <i class="fas fa-layer-group"></i> Slots: <?= $slots_used ?> / <?= $max_slots ?>
        </span>
    </div>

    <?php if ($is_admin || $is_narrator): ?>
        <div class="rpg-narrator-switch-row">
            <span class="rpg-narrator-switch-label active" id="label-own" onclick="setNarratorSwitch(false)">
                <i class="fas fa-user-friends"></i> Mis Personajes
            </span>
            <label class="rpg-narrator-switch">
                <input type="checkbox" id="char-type-toggle" onchange="toggleCharTab(this.checked)">
                <span class="rpg-narrator-switch-slider"></span>
            </label>
            <span class="rpg-narrator-switch-label" id="label-npc" onclick="setNarratorSwitch(true)">
                <i class="fas fa-user-secret"></i> NPCs Mayores (<?= count($npjs_assigned) ?>)
            </span>
        </div>
    <?php endif; ?>

    <div id="char-grid-own" class="rpg-char-tab-content active">
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
                                <button class="rpg-pj-card-btn rpg-pj-card-btn--primary rpg-pj-card-btn--block" onclick="switchPJ(<?= $c['id'] ?>, this)">Seleccionar</button>
                                <div class="rpg-pj-card-btn-row">
                                    <a href="<?= $bb ?>/game/public/personaje.php?pj=<?= $c['id'] ?>" class="rpg-pj-card-btn rpg-pj-card-btn--secondary rpg-pj-card-btn--flex"><i class="fas fa-external-link-alt"></i> Ver</a>
                                </div>
                            <?php else: ?>
                                <div class="rpg-pj-card-btn-row">
                                    <span class="rpg-pj-card-btn rpg-pj-card-btn--active rpg-pj-card-btn--flex rpg-pj-card-active-state"><i class="fas fa-check"></i> Activo</span>
                                    <a href="<?= $bb ?>/game/public/personaje.php?pj=<?= $c['id'] ?>" class="rpg-pj-card-btn rpg-pj-card-btn--secondary rpg-pj-card-btn--flex"><i class="fas fa-external-link-alt"></i> Ver</a>
                                </div>
                            <?php endif; ?>
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

    <?php if ($is_admin || $is_narrator): ?>
        <div id="char-grid-npc" class="rpg-char-tab-content">
            <?php if (empty($npjs_assigned)): ?>
                <div class="rpg-char-empty"><p><i class="fas fa-user-secret"></i> No tienes NPCs mayores asignados.</p></div>
            <?php else: ?>
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
                                    <span class="rpg-pj-tag rpg-npc-card-badge--faction rpg-pj-tag--faction-sm"><?= htmlspecialchars($c['faction'] ?: 'Civil') ?></span>
                                </div>
                            </div>
                            <div class="rpg-pj-card-actions rpg-pj-card-actions--stack">
                                <?php if (!$is_active): ?>
                                    <button class="rpg-pj-card-btn rpg-pj-card-btn--primary rpg-pj-card-btn--block" onclick="switchPJ(<?= $c['id'] ?>, this)">Seleccionar</button>
                                    <div class="rpg-pj-card-btn-row">
                                        <a href="<?= $bb ?>/game/public/personaje.php?pj=<?= $c['id'] ?>" class="rpg-pj-card-btn rpg-pj-card-btn--secondary rpg-pj-card-btn--flex"><i class="fas fa-external-link-alt"></i> Ver</a>
                                    </div>
                                <?php else: ?>
                                    <div class="rpg-pj-card-btn-row">
                                        <span class="rpg-pj-card-btn rpg-pj-card-btn--active rpg-pj-card-btn--flex rpg-pj-card-active-state"><i class="fas fa-check"></i> Activo</span>
                                        <a href="<?= $bb ?>/game/public/personaje.php?pj=<?= $c['id'] ?>" class="rpg-pj-card-btn rpg-pj-card-btn--secondary rpg-pj-card-btn--flex"><i class="fas fa-external-link-alt"></i> Ver</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de Edición Rápida (Avatar y Firma) -->
<div id="fast-edit-modal" class="rpg-fast-edit-modal">
    <div class="rpg-fast-edit-modal__backdrop" onclick="closeFastEdit()"></div>
    <div class="rpg-fast-edit-modal__container">
        <div class="rpg-fast-edit-modal__header">
            <h3><i class="fas fa-user-edit"></i> Editar Perfil de Personaje</h3>
            <button type="button" class="rpg-system-tab-btn rpg-system-tab-btn--compact" onclick="closeFastEdit()" aria-label="Cerrar"><i class="fas fa-times"></i></button>
        </div>
        <form id="fast-edit-form" onsubmit="saveFastEdit(event)">
            <input type="hidden" id="fast-edit-pj-id" value="" />
            <div class="rpg-fast-edit-modal__body">
                <div class="rpg-form-group">
                    <label class="rpg-form-label" for="fast-edit-avatar"><i class="fas fa-image"></i> Retrato principal (columna derecha, ~250×450)</label>
                    <input type="url" id="fast-edit-avatar" class="rpg-form-input textbox rpg-form-input--block" placeholder="https://example.com/retrato.png" required />
                </div>
                <div class="rpg-form-group rpg-form-group--mt">
                    <label class="rpg-form-label" for="fast-edit-banner"><i class="fas fa-circle"></i> Retrato Nexus (centro del radar, ~200×200, cuadrado)</label>
                    <input type="url" id="fast-edit-banner" class="rpg-form-input textbox rpg-form-input--block" placeholder="https://example.com/nexus.png" />
                    <p class="rpg-form-help">Imagen distinta al retrato principal. Aparece dentro del triángulo Nen en la Portada.</p>
                </div>
                <div class="rpg-form-group rpg-form-group--mt">
                    <label class="rpg-form-label" for="fast-edit-firma"><i class="fas fa-signature"></i> Firma (Soporta MyCode / BBCode)</label>
                    <textarea id="fast-edit-firma" class="rpg-form-input textbox rpg-form-textarea--firma" placeholder="Escribe tu firma aquí..."></textarea>
                </div>
            </div>
            <div class="rpg-fast-edit-modal__footer">
                <button type="button" class="rpg-system-tab-btn" onclick="closeFastEdit()">Cancelar</button>
                <button type="submit" id="fast-edit-save-btn" class="rpg-action-btn rpg-btn-primary">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
window.MIS_PERSONAJES_CONFIG = <?= json_encode([
    'bburl' => $bb,
    'autoOpenEdit' => $auto_open_edit,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= rtrim($bb, '/') ?>/jscripts/game/mis_personajes.js?v=7"></script>
<?php
$content = ob_get_clean();
game_render_page('Mis Personajes', $content);
