<?php
declare(strict_types=1);

define('THIS_SCRIPT', 'anuncios_staff.php');
require_once __DIR__ . '/../bootstrap.php';

game_require_staff_character();

global $mybb, $db;
$prefix = TABLE_PREFIX;

$uid = (int)$mybb->user['uid'];
$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$pj_q = $db->query("SELECT staff_level FROM {$prefix}game_personajes WHERE id = " . (int)$cfg['active_pj_id'] . " LIMIT 1");
$pj = $db->fetch_array($pj_q);

if (!$pj || (int)$pj['staff_level'] < 3) {
    error_no_permission();
}

$bburl = $mybb->settings['bburl'];

ob_start();
?>
<div class="rpg-anuncios-page">
    <h1 class="rpg-anuncios-title">
        <i class="fas fa-bullhorn"></i> Gestión de Tablón de Anuncios
    </h1>

    <div class="rpg-anuncios-panel">
        <h2>Publicar Nuevo Anuncio</h2>
        
        <div class="rpg-form-group">
            <label class="rpg-form-label"><i class="fas fa-heading"></i> Título del anuncio</label>
            <input type="text" id="ann_title" class="rpg-form-input" placeholder="Ej: Mantenimiento del servidor...">
        </div>
        
        <div class="rpg-form-group">
            <label class="rpg-form-label"><i class="fas fa-align-left"></i> Contenido (acepta HTML básico)</label>
            <textarea id="ann_content" class="rpg-editor-textarea" rows="4" placeholder="Escribe el anuncio aquí..."></textarea>
        </div>
        
        <div class="rpg-anuncios-actions">
            <button type="button" onclick="saveAnnouncement()" class="rpg-action-btn rpg-btn-primary">
                <i class="fas fa-paper-plane"></i> Publicar
            </button>
        </div>
    </div>

    <div class="rpg-anuncios-panel">
        <h2>Anuncios Actuales</h2>
        
        <div id="announcements-list" class="rpg-anuncios-list">
            <div class="rpg-anuncios-loading">Cargando anuncios...</div>
        </div>
    </div>
</div>

<script>
window.ANUNCIOS_STAFF_CONFIG = { bburl: '<?= $bburl ?>' };
</script>
<script src="<?= rtrim($bburl, '/') ?>/jscripts/game/anuncios_staff.js?v=1"></script>

<?php
$content = ob_get_clean();
game_render_page('Gestión de Anuncios', $content);
