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
      <h1><i class="fas fa-clipboard-list"></i> Petici&oacute;n Administrativa</h1>
      <p>Env&iacute;a una solicitud al staff para revisi&oacute;n y aprobaci&oacute;n.</p>
    </div>
  </div>

  <div class="rpg-peticiones-form-container">
    <form class="rpg-peticion-form" id="peticion-admin-form" method="post" action="#">
      <div class="rpg-form-group">
        <label for="tipo_peticion"><i class="fas fa-tag"></i> Tipo de Petici&oacute;n</label>
        <select name="tipo_peticion" id="tipo_peticion" class="rpg-form-select" required>
          <option value="" disabled selected>Selecciona un tipo...</option>
          <option value="creacion_personaje">Creaci&oacute;n de Personaje</option>
          <option value="modificacion_personaje">Modificaci&oacute;n de Personaje</option>
          <option value="eliminacion_personaje">Eliminaci&oacute;n de Personaje</option>
          <option value="fruta_diablo">Solicitud de Fruta del Diablo</option>
          <option value="haki">Solicitud de Haki</option>
          <option value="objeto">Solicitud de Objeto / Equipamiento</option>
          <option value="mision">Solicitud de Misi&oacute;n</option>
          <option value="evento">Propuesta de Evento</option>
          <option value="sancion">Apelaci&oacute;n / Sanci&oacute;n</option>
          <option value="otro">Otro</option>
        </select>
      </div>

      <div class="rpg-form-group">
        <label for="descripcion"><i class="fas fa-align-left"></i> Descripci&oacute;n</label>
        <textarea name="descripcion" id="descripcion" class="rpg-form-textarea" rows="8" placeholder="Describe detalladamente tu petici&oacute;n..." required></textarea>
      </div>

      <div class="rpg-form-group">
        <label for="link"><i class="fas fa-link"></i> Link</label>
        <input type="url" name="link" id="link" class="rpg-form-input" placeholder="https://foro.com/hilo/ejemplo (opcional)">
        <span class="rpg-form-hint">Enlace a un hilo, post o imagen relevante para tu petici&oacute;n.</span>
      </div>

      <div class="rpg-form-actions">
        <button type="submit" class="rpg-btn-primary" disabled>
          <i class="fas fa-paper-plane"></i> Enviar Petici&oacute;n
        </button>
        <span class="rpg-form-disabled-note"><i class="fas fa-info-circle"></i> Pr&oacute;ximamente disponible</span>
      </div>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();
game_render_page('Petición Administrativa', $content);
