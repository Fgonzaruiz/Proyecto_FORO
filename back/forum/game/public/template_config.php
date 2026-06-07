<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

$uid = (int)($mybb->user['uid'] ?? 0);
$prefix = TABLE_PREFIX;
$bburl = htmlspecialchars((string)($mybb->settings['bburl'] ?? ''));

if (!$uid) {
    ob_start();
    ?><div class="rpg-char-empty"><i class="fas fa-user-lock"></i><h2>Debes iniciar sesi&oacute;n</h2><p>Inicia sesi&oacute;n para acceder a esta página.</p></div><?php
    $content = ob_get_clean();
    game_render_page('Templates de Personaje', $content);
    exit;
}

$active_pj_id = 0;
$active_pj_name = '';
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_candidate = $cfg ? (int)$cfg['active_pj_id'] : 0;
if ($active_candidate > 0) {
    $pj_q = $db->query("SELECT id, name FROM {$prefix}game_personajes WHERE id = {$active_candidate} AND user_id = {$uid} AND is_npc = 0 LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    if ($pj) {
        $active_pj_id = (int)$pj['id'];
        $active_pj_name = (string)$pj['name'];
    }
}

ob_start();
?>
<div class="rpg-templates-page" data-active-char-id="<?= $active_pj_id ?>">
    <div class="rpg-templates-header">
        <h2><i class="fas fa-scroll"></i> Templates de Personaje</h2>
        <p>Configura <strong>templates resumen</strong> para tu personaje activo. Al escribir un post, podr&aacute;s elegir cu&aacute;l insertar con el bot&oacute;n <strong>Template</strong>.</p>
    </div>

    <?php if ($active_pj_id <= 0): ?>
        <div class="rpg-char-empty">
            <i class="fas fa-user-slash"></i>
            <h3>Sin personaje activo</h3>
            <p>Selecciona un personaje activo en <a href="<?= $bburl ?>/game/public/mis_personajes.php">Mis Personajes</a> para configurar sus templates.</p>
        </div>
    <?php else: ?>
        <div class="rpg-templates-editor" id="template-editor-panel">
            <div class="rpg-templates-list-header">
                <span class="rpg-templates-list-title"><i class="fas fa-list"></i> Tus Templates</span>
                <button type="button" class="rpg-btn--primary rpg-btn--sm" id="template-add-btn">
                    <i class="fas fa-plus"></i> Nuevo Template
                </button>
            </div>
            <div id="template-list" class="rpg-template-list"></div>

            <div id="template-editor-area" class="is-hidden">
                <div class="rpg-form-group">
                    <label class="rpg-form-label" for="template-name-input">
                        <i class="fas fa-tag"></i> Nombre del Template
                    </label>
                    <input type="text" id="template-name-input" class="rpg-form-input" placeholder="Ej: Resumen, Ficha rápida, ..." maxlength="100">
                </div>
                <div class="rpg-form-group">
                    <label class="rpg-form-label" for="template-editor-textarea">
                        <i class="fas fa-pen"></i> Contenido (BBCode)
                    </label>
                    <p class="rpg-form-hint">Puedes usar BBCode: spoilers, listas, imágenes, etc.</p>
                    <textarea id="template-editor-textarea" class="rpg-editor-textarea" rows="16" placeholder="Escribe aquí tu template..."></textarea>
                </div>
                <div class="rpg-templates-actions">
                    <button type="button" class="rpg-btn--primary" id="template-save-btn" disabled>
                        <i class="fas fa-save"></i> Guardar
                    </button>
                    <button type="button" class="rpg-btn--secondary" id="template-cancel-btn">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <span class="rpg-templates-status" id="template-save-status"></span>
                </div>
            </div>
        </div>

        <div class="rpg-templates-preview is-hidden" id="template-preview-panel">
            <h3><i class="fas fa-eye"></i> Vista previa</h3>
            <div class="rpg-templates-preview-content" id="template-preview-content"></div>
        </div>
    <?php endif; ?>
</div>

<script src="<?= $bburl ?>/jscripts/game/template_config.js?v=4"></script>
<?php
$content = ob_get_clean();
game_render_page('Templates de Personaje', $content);
