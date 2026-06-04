<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

game_require_staff_level(3);

global $mybb;
$b_url = $mybb->settings['bburl'];

ob_start();
?>
<div class="rpg-staff-zone" id="staffCuentasApp">
  <div class="rpg-staff-header rpg-staff-header--cuentas">
    <div class="rpg-staff-header-content">
      <a href="<?= htmlspecialchars($b_url) ?>/game/public/zona_staff.php" class="rpg-akuma-back">
        <i class="fas fa-arrow-left"></i> Volver a Zona Staff
      </a>
      <h1><i class="fas fa-user-cog"></i> Gestionar Cuenta</h1>
      <p>Moderación a nivel de usuario: baneos, narrador, slots de personaje, NPCs asignados y restricciones de publicación. Requiere <strong>Administrador (nivel 3)</strong>.</p>
    </div>
  </div>

  <div class="rpg-npc-creator-form rpg-staff-account-search">
    <div class="rpg-staff-filter-grow">
      <input type="text" id="staffAccountQuery" class="textbox" placeholder="Nombre de usuario o UID del foro..." autocomplete="off" />
    </div>
    <button type="button" id="staffAccountSearchBtn" class="rpg-btn-approve-lg rpg-staff-filter-submit">
      <i class="fas fa-search"></i> Buscar cuenta
    </button>
  </div>

  <div id="staffAccountFlash" class="rpg-is-hidden"></div>
  <div id="staffAccountPanel" class="rpg-staff-account-panel rpg-is-hidden"></div>

  <div id="staffAccountEmpty" class="rpg-akuma-empty">
    <div class="rpg-akuma-empty-icon"><i class="fas fa-user-search"></i></div>
    <p>Busca un usuario del foro para ver y editar su cuenta.</p>
  </div>
</div>

<script>
window.ZONA_STAFF_CUENTAS_CONFIG = <?= json_encode([
    'bburl' => rtrim($b_url, '/'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= htmlspecialchars(rtrim($b_url, '/')) ?>/jscripts/game/zona_staff_cuentas.js?v=1"></script>
<?php
$content = ob_get_clean();
game_render_page('Gestionar Cuenta — Staff', $content);
