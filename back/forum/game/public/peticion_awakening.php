<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb, $db;

if (!isset($mybb) || !is_object($mybb) || (int)($mybb->user['uid'] ?? 0) === 0) {
    header('Location: ../member.php?action=login');
    exit;
}

$b_url = $mybb->settings['bburl'];
$type = $_GET['type'] ?? 'full';
$is_pre = ($type === 'pre');

$title_label = $is_pre ? 'Despertar Incompleto (Pre-Awakening)' : 'Awakening Completo';
$icon = $is_pre ? 'fa-bolt' : 'fa-sun';

ob_start();
?>
<div class="rpg-peticiones">
  <div class="rpg-peticiones-header rpg-peticiones-header--gradient <?= !$is_pre ? 'rpg-bg-awakening-gradient' : '' ?>">
    <div class="rpg-peticiones-header-content">
      <a href="<?= htmlspecialchars($b_url) ?>/game/public/peticion_akuma.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Volver al Hub</a>
      <h1><i class="fas <?= $icon ?>"></i> Solicitud: <?= $title_label ?></h1>
      <p>Rellena los datos para que el staff revise tu petici&oacute;n de Despertar.</p>
    </div>
  </div>

  <div class="rpg-peticiones-form-container">
    <form class="rpg-peticion-form" id="awakening-form">
      <input type="hidden" id="awakening_type" value="<?= $is_pre ? 'pre_awakening' : 'full_awakening' ?>">
      
      <div class="rpg-form-group">
        <label for="link_condicion"><i class="fas fa-link"></i> Link a la Condici&oacute;n Narrativa</label>
        <input type="url" name="link" id="link_condicion" class="rpg-form-input" placeholder="https://..." required>
        <span class="rpg-form-hint">Enlace directo al hilo/post donde se cumple la condici&oacute;n estipulada en tu carta.</span>
      </div>

      <div class="rpg-form-group">
        <label for="propuesta_poderes"><i class="fas fa-fist-raised"></i> Propuesta de Poderes / Efectos</label>
        <textarea name="propuesta_poderes" id="propuesta_poderes" class="rpg-form-textarea" rows="6" placeholder="Describe c&oacute;mo se manifiesta el despertar y qu&eacute; mec&aacute;nicas nuevas sugieres (1-2 habilidades)..." required></textarea>
        <?php if ($is_pre): ?>
          <span class="rpg-form-hint rpg-text-warning"><i class="fas fa-exclamation-triangle"></i> Al ser un Despertar Incompleto, el staff a&ntilde;adir&aacute; 'drawbacks' (consecuencias negativas) a la carta.</span>
        <?php endif; ?>
      </div>

      <div id="awakening-msg" class="rpg-modal-msg rpg-is-hidden"></div>

      <div class="rpg-form-actions">
        <button type="submit" class="rpg-btn-primary <?= !$is_pre ? 'rpg-bg-awakening-gradient' : '' ?>" id="awakening-submit">
          <i class="fas fa-paper-plane"></i> Enviar solicitud al Staff
        </button>
      </div>
    </form>
  </div>
</div>

<script>
window.PETICION_AWAKENING_CONFIG = { 
    bburl: '<?= $b_url ?>',
    typeLabel: '<?= $title_label ?>'
};
</script>
<script src="<?= rtrim($b_url, '/') ?>/jscripts/game/peticion_awakening.js?v=1"></script>
<?php
$content = ob_get_clean();
game_render_page('Solicitud de Awakening', $content);
