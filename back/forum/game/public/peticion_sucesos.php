<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

if (!isset($mybb) || !is_object($mybb) || (int)($mybb->user['uid'] ?? 0) === 0) {
    header('Location: ../../member.php?action=login');
    exit;
}

$uid = (int)$mybb->user['uid'];
$prefix = TABLE_PREFIX;

$cfg_q = $db->query("SELECT active_pj_id FROM {$prefix}game_user_config WHERE user_id = {$uid} LIMIT 1");
$cfg = $db->fetch_array($cfg_q);
$active_pj_id = $cfg ? (int)$cfg['active_pj_id'] : 0;

$b_url = $mybb->settings['bburl'];

game_set_hxh_page(
    'TRÁMITES · NOTIFICAR SUCESO',
    'Notificar Suceso',
    'Envía una gaviota mensajera a la redacción de News Coo o a los cronistas del mundo.'
);

ob_start();
?>
<div class="rpg-sucesos-page hxh-tramites-body">
    <div class="rpg-peticiones-toolbar">
        <a href="peticiones_general.php" class="rpg-sucesos-back"><i class="fas fa-arrow-left"></i> Volver a Trámites</a>
    </div>

    <div class="rpg-sucesos-container">
        <?php if ($active_pj_id <= 0): ?>
            <div class="red_alert">Debes tener un personaje activo seleccionado para poder enviar noticias.</div>
        <?php else: ?>
            <div class="rpg-sucesos-form-box hxh-panel" id="sucesos-form-wrapper">
                <form id="sucesos-form" onsubmit="enviarSuceso(event)">
                    <div class="rpg-sucesos-field">
                        <label>URL del Tema / Rol <i class="fas fa-link"></i></label>
                        <input type="url" id="s_url" placeholder="https://..." required>
                    </div>
                    <div class="rpg-sucesos-field">
                        <label>Titular <i class="fas fa-heading"></i></label>
                        <input type="text" id="s_title" placeholder="Ej: Piratas arrasan el puerto de Loguetown" required maxlength="150">
                    </div>
                    <div class="rpg-sucesos-field">
                        <label>Resumen del evento <i class="fas fa-scroll"></i></label>
                        <textarea id="s_desc" rows="5" placeholder="Resume qué ha pasado exactamente y quiénes están implicados..." required></textarea>
                    </div>
                    
                    <button type="submit" class="rpg-sucesos-submit" id="sucesos-btn">
                        <span>Enviar Gaviota</span> <i class="fas fa-paper-plane" id="sucesos-icon"></i>
                    </button>
                    <div id="sucesos-msg" class="rpg-sucesos-msg rpg-is-hidden"></div>
                </form>

                <!-- Animación del sobre volando -->
                <div class="rpg-sucesos-envelope" id="flying-envelope">
                    <i class="fas fa-envelope"></i>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
window.SUCESOS_CONFIG = { bburl: '<?= $b_url ?>', uid: <?= $uid ?>, pj_id: <?= $active_pj_id ?> };
</script>
<script src="<?= rtrim($b_url, '/') ?>/jscripts/game/peticion_sucesos.js?v=<?= time() ?>"></script>
<?php
$content = ob_get_clean();
game_render_page('Notificar Suceso', $content);
