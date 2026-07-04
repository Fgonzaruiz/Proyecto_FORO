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
      <a href="<?= htmlspecialchars($b_url) ?>/game/public/peticiones_general.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Solicitudes</a>
      <h1><i class="fas fa-clipboard-list"></i> Solicitud Administrativa</h1>
      <p>Env&iacute;a una solicitud al staff para revisi&oacute;n y aprobaci&oacute;n.</p>
    </div>
  </div>

  <div class="rpg-peticiones-form-container">
    <form class="rpg-peticion-form" id="peticion-admin-form" method="post" action="#">
      <div class="rpg-form-group">
        <label for="tipo_peticion"><i class="fas fa-tag"></i> Tipo de Solicitud</label>
        <select name="tipo_peticion" id="tipo_peticion" class="rpg-form-select" required>
          <option value="" disabled selected>Selecciona un tipo...</option>
          <option value="creacion_personaje">Creaci&oacute;n de Personaje</option>
          <option value="modificacion_personaje">Modificaci&oacute;n de Personaje</option>
          <option value="eliminacion_personaje">Eliminaci&oacute;n de Personaje</option>
          <option value="nen_despertar">Solicitud de Despertar Nen</option>
          <option value="nen_habilidad">Solicitud de Habilidad Nen (Hatsu)</option>
          <option value="objeto">Solicitud de Objeto / Equipamiento</option>
          <option value="mision">Solicitud de Misi&oacute;n</option>
          <option value="evento">Propuesta de Evento</option>
          <option value="sancion">Apelaci&oacute;n / Sanci&oacute;n</option>
          <option value="otro">Otro</option>
        </select>
      </div>

      <div class="rpg-form-group">
        <label for="titulo_admin"><i class="fas fa-heading"></i> T&iacute;tulo breve</label>
        <input type="text" name="titulo" id="titulo_admin" class="rpg-form-input" placeholder="Resumen de tu solicitud" required maxlength="200">
      </div>

      <div class="rpg-form-group">
        <label for="descripcion"><i class="fas fa-align-left"></i> Descripci&oacute;n</label>
        <textarea name="descripcion" id="descripcion" class="rpg-form-textarea" rows="8" placeholder="Describe detalladamente tu solicitud..." required></textarea>
      </div>

      <div class="rpg-form-group">
        <label for="link"><i class="fas fa-link"></i> Link</label>
        <input type="url" name="link" id="link" class="rpg-form-input" placeholder="https://foro.com/hilo/ejemplo (opcional)">
        <span class="rpg-form-hint">Enlace a un hilo, post o imagen relevante para tu solicitud.</span>
      </div>

      <div id="peticion-admin-msg" class="rpg-modal-msg rpg-is-hidden"></div>

      <div class="rpg-form-actions">
        <button type="submit" class="rpg-action-btn rpg-btn-primary" id="peticion-admin-submit">
          <i class="fas fa-paper-plane"></i> Enviar Solicitud
        </button>
      </div>
    </form>
  </div>
</div>

<script>
window.PETICIONES_ADMIN_CONFIG = { bburl: '<?= $b_url ?>' };
</script>
<script src="<?= rtrim($b_url, '/') ?>/jscripts/game/peticiones_admin.js?v=1"></script>
<?php
$content = ob_get_clean();
game_render_page('Solicitud Administrativa', $content);
