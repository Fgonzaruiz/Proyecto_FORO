<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $db, $mybb;
$prefix = TABLE_PREFIX;
$uid = (int)($mybb->user['uid'] ?? 0);

if ($uid <= 0) {
    error_no_permission();
}

$active_pj_id = (int)($db->fetch_field(
    $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1"),
    "active_pj_id"
) ?? 0);

if ($active_pj_id <= 0) {
    error_no_permission();
}

$pj = $db->fetch_array($db->query("SELECT id, name, tripulacion_id FROM {$prefix}game_personajes WHERE id = {$active_pj_id}"));
if (!$pj) {
    error_no_permission();
}

if (!empty($pj['tripulacion_id'])) {
    $bburl = $mybb->settings['bburl'];
    ob_start();
    require __DIR__ . '/../views/personaje/_styles.php';
    echo '<div class="rpg-char-page"><div class="pj-page-shell rpg-crew-form-shell"><div class="pj-data-group rpg-error-card"><i class="fas fa-exclamation-triangle rpg-error-icon"></i><h2>Ya perteneces a un grupo</h2><p class="rpg-error-desc">No puedes fundar un nuevo grupo mientras formes parte de otro.</p><a href="'.$bburl.'/game/public/tripulacion.php" class="rpg-action-btn rpg-btn-primary rpg-error-btn">Ver Mi Grupo</a></div></div></div>';
    $content = ob_get_clean();
    game_render_page('Error - Grupo', $content);
    exit;
}

$bburl = $mybb->settings['bburl'];

ob_start();
require __DIR__ . '/../views/personaje/_styles.php';
?>
<div class="rpg-char-page">
    <div class="pj-page-shell rpg-crew-form-shell">
        <h1 class="pj-stat-heading rpg-crew-form-heading"><i class="fas fa-users"></i> Fundar Nuevo Grupo</h1>
        
        <div class="pj-data-group rpg-crew-form-group">
            <p class="rpg-crew-form-desc">
                Funda tu propio grupo. Como líder, serás responsable de aceptar miembros y gestionar su desarrollo.
            </p>
            
            <form id="create_crew_form" onsubmit="event.preventDefault(); submitCreateCrew();">
                <div class="rpg-form-group">
                    <label class="pj-label-inline--bold">Nombre del Grupo</label>
                    <input type="text" id="crew_name" class="textbox" required class="textbox rpg-crew-form-input">
                </div>
                
                <div class="rpg-form-group rpg-crew-form-margin">
                    <label class="pj-label-inline--bold">Lema</label>
                    <input type="text" id="crew_motto" class="textbox" placeholder="Opcional" class="textbox rpg-crew-form-input">
                </div>

                <div class="rpg-form-group rpg-crew-form-margin">
                    <label class="pj-label-inline--bold">URL Emblema</label>
                    <input type="url" id="crew_image" class="textbox" placeholder="https://..." class="textbox rpg-crew-form-input">
                </div>
                
                <div class="rpg-crew-form-submit-wrap">
                    <button type="submit" class="rpg-action-btn rpg-btn-primary rpg-action-btn rpg-btn-primary rpg-crew-form-submit-btn">
                        <i class="fas fa-skull-crossbones"></i> Fundar Grupo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>window.CREW_CONFIG = { bburl: "<?= $bburl ?>" };</script>
<script src="<?= $bburl ?>/jscripts/game/tripulacion_crear.js?v=1"></script>
<?php
$content = ob_get_clean();
game_render_page('Fundar Grupo', $content);
