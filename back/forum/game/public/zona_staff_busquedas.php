<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

if (!isset($mybb) || (int)($mybb->user['uid'] ?? 0) === 0) {
    header('Location: ../member.php?action=login');
    exit;
}

$uid = (int)$mybb->user['uid'];
$prefix = TABLE_PREFIX;

$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$cid = $cfg ? (int)$cfg['active_pj_id'] : 0;
$staff_level = 0;
$pj_name = '';

if ($cid > 0) {
    $pj_q = $db->query("SELECT name, staff_level FROM {$prefix}game_personajes WHERE id = {$cid} AND user_id = {$uid} LIMIT 1");
    $pj = $db->fetch_array($pj_q);
    if ($pj) {
        $staff_level = (int)$pj['staff_level'];
        $pj_name = $pj['name'];
    }
}

if ($staff_level < 2) {
    header('Location: ../index.php');
    exit;
}

$b_url = $mybb->settings['bburl'];

ob_start();
?>
<div class="rpg-staff-zone rpg-staff-zone--wide">
    <div class="rpg-staff-header rpg-staff-header--row rpg-staff-header--busquedas">
        <div class="rpg-staff-header-content">
            <h1><i class="fas fa-search"></i> Búsquedas de Rol Pendientes</h1>
            <p>Revisa y responde las búsquedas enviadas por los jugadores.</p>
        </div>
        <a href="<?= $b_url ?>/game/public/zona_staff.php" class="rpg-staff-header__back">
            <i class="fas fa-arrow-left"></i> Volver al Panel
        </a>
    </div>

    <div id="busquedas-staff-list">
        <div class="rpg-peticiones-loading">
            <i class="fas fa-spinner fa-spin fa-2x"></i><br><br>Cargando búsquedas...
        </div>
    </div>

    <!-- Modal de revisión -->
    <div id="busqueda-review-modal" class="rpg-modal-overlay">
        <div class="rpg-modal-panel">
            <div class="rpg-modal-header">
                <h3 id="modal-review-titulo" class="rpg-modal-title"></h3>
                <button onclick="closeBusquedaReview()" class="rpg-modal-close"><i class="fas fa-times"></i></button>
            </div>
            <div class="rpg-modal-body">
                <img id="modal-review-img" src="" class="rpg-modal-img rpg-is-hidden" alt="" />
                <div class="rpg-modal-author">
                    <img id="modal-review-avatar" src="" class="rpg-modal-avatar" alt="" />
                    <div>
                        <div id="modal-review-pj" class="rpg-modal-pj"></div>
                        <div id="modal-review-date" class="rpg-modal-date"></div>
                    </div>
                </div>
                <div id="modal-review-desc" class="rpg-modal-desc"></div>

                <input type="hidden" id="modal-review-id" value="" />
                <label class="rpg-modal-label">Nota para el jugador (opcional):</label>
                <textarea id="modal-review-nota" rows="3" class="rpg-staff-textarea" placeholder="Añade una nota que recibirá el jugador..."></textarea>

                <div class="rpg-modal-actions">
                    <button type="button" onclick="accionBusqueda('aprobar')" class="rpg-btn-approve-lg">
                        <i class="fas fa-check"></i> Aprobar y publicar
                    </button>
                    <button type="button" onclick="accionBusqueda('denegar')" class="rpg-btn-reject-lg">
                        <i class="fas fa-times"></i> Denegar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.ZONA_STAFF_BUSQUEDAS_CONFIG = { bburl: '<?= $b_url ?>' };
</script>
<script src="<?= rtrim($b_url, '/') ?>/jscripts/game/zona_staff_busquedas.js?v=1"></script>
<?php
$content = ob_get_clean();
game_render_page('Búsquedas de Rol — Staff', $content);
