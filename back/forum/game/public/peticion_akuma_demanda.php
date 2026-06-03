<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb;

if (!isset($mybb) || !is_object($mybb) || (int)($mybb->user['uid'] ?? 0) === 0) {
    header('Location: ../member.php?action=login');
    exit;
}

$b_url = $mybb->settings['bburl'];

ob_start();
?>
<div class="rpg-peticiones">
  <div class="rpg-peticiones-header rpg-peticiones-header--gradient">
    <div class="rpg-peticiones-header-content">
      <a href="<?= htmlspecialchars($b_url) ?>/game/public/peticion_akuma.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Modo de solicitud</a>
      <h1><i class="fas fa-hand-pointer"></i> Akuma bajo demanda</h1>
      <p>Solicita una fruta concreta del cat&aacute;logo. El staff revisar&aacute; tu solicitud administrativa.</p>
    </div>
  </div>

  <div class="rpg-peticiones-form-container">
    <form class="rpg-peticion-form" id="akuma-demand-form">
      <div class="rpg-form-group">
        <label for="akuma_fruit_id"><i class="fas fa-apple-alt"></i> Fruta solicitada</label>
        <select name="akuma_fruit_id" id="akuma_fruit_id" class="rpg-form-select" required>
          <option value="" disabled selected>Cargando cat&aacute;logo...</option>
        </select>
        <span class="rpg-form-hint" id="akuma-fruit-preview"></span>
      </div>

      <div class="rpg-form-group">
        <label for="motivo"><i class="fas fa-bullseye"></i> Motivo de la solicitud</label>
        <input type="text" name="motivo" id="motivo" class="rpg-form-input" placeholder="Ej: Encaja con la trama de mi bando pirata..." required maxlength="200">
      </div>

      <div class="rpg-form-group">
        <label for="justificacion"><i class="fas fa-align-left"></i> Justificaci&oacute;n narrativa</label>
        <textarea name="justificacion" id="justificacion" class="rpg-form-textarea" rows="6" placeholder="Describe por qu&eacute; tu personaje deber&iacute;a obtener esta fruta, contexto IC, planes de rol..." required></textarea>
      </div>

      <div class="rpg-form-group">
        <label for="link_demanda"><i class="fas fa-link"></i> Enlace de apoyo (opcional)</label>
        <input type="url" name="link" id="link_demanda" class="rpg-form-input" placeholder="https://...">
      </div>

      <div id="akuma-demand-msg" class="rpg-modal-msg rpg-is-hidden"></div>

      <div class="rpg-form-actions">
        <button type="submit" class="rpg-btn-primary" id="akuma-demand-submit">
          <i class="fas fa-paper-plane"></i> Enviar solicitud administrativa
        </button>
      </div>
    </form>
  </div>
</div>

<script>
window.PETICION_AKUMA_DEMANDA_CONFIG = { bburl: '<?= $b_url ?>' };
</script>
<script src="<?= rtrim($b_url, '/') ?>/jscripts/game/peticion_akuma_demanda.js?v=1"></script>
<?php
$content = ob_get_clean();
game_render_page('Akuma bajo demanda', $content);
