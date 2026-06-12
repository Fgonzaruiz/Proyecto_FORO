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
  <div class="rpg-peticiones-header">
    <div class="rpg-peticiones-header-content">
      <h1><i class="fas fa-scroll"></i> Trámites y Gestiones RPG</h1>
      <p>Selecciona el tipo de gestión que deseas realizar.</p>
    </div>
  </div>

  <div class="rpg-staff-cards">

    <a class="rpg-staff-card" href="<?= htmlspecialchars($b_url) ?>/game/public/tablon_misiones.php">
      <div class="rpg-staff-card-icon rpg-staff-card-icon--blue-indigo">
        <i class="fas fa-compass"></i>
      </div>
      <div class="rpg-staff-card-body">
        <h3>Tablón de Misiones</h3>
        <p>Acepta encargos narrativos oficiales, explora islas y gana Puntos Destino (PD) y Berries.</p>
      </div>
    </a>

    <a class="rpg-staff-card" href="<?= htmlspecialchars($b_url) ?>/game/public/tienda_destino.php">
      <div class="rpg-staff-card-icon rpg-staff-card-icon--amber">
        <i class="fas fa-star"></i>
      </div>
      <div class="rpg-staff-card-body">
        <h3>Tienda de Destino</h3>
        <p>Canjea tus Puntos Destino (PD) por estilos de combate secundarios, técnicas prohibidas y poderes narrativos.</p>
      </div>
    </a>

    <a class="rpg-staff-card" href="<?= htmlspecialchars($b_url) ?>/game/public/peticion_akuma.php">
      <div class="rpg-staff-card-icon rpg-staff-card-icon--purple">
        <i class="fas fa-apple-alt"></i>
      </div>
      <div class="rpg-staff-card-body">
        <h3>Akuma no Mi</h3>
        <p>Tirada aleatoria o petición bajo demanda. Genera una solicitud administrativa para el staff.</p>
      </div>
    </a>

    <a class="rpg-staff-card" href="<?= htmlspecialchars($b_url) ?>/game/public/peticion_haki.php">
      <div class="rpg-staff-card-icon rpg-staff-card-icon--amber">
        <i class="fas fa-hand-fist"></i>
      </div>
      <div class="rpg-staff-card-body">
        <h3>Haki</h3>
        <p>Gestiona el despertar o entrenamiento de tu Haki: Armadura, Observación y Rey.</p>
      </div>
    </a>

    <a class="rpg-staff-card" href="<?= htmlspecialchars($b_url) ?>/game/public/tienda.php">
      <div class="rpg-staff-card-icon rpg-staff-card-icon--emerald">
        <i class="fas fa-store"></i>
      </div>
      <div class="rpg-staff-card-body">
        <h3>Tienda</h3>
        <p>Compra y venta de objetos, equipamiento y recursos del juego.</p>
      </div>
    </a>

    <a class="rpg-staff-card" href="<?= htmlspecialchars($b_url) ?>/game/public/peticiones_admin.php">
      <div class="rpg-staff-card-icon rpg-staff-card-icon--rose-purple">
        <i class="fas fa-clipboard-list"></i>
      </div>
      <div class="rpg-staff-card-body">
        <h3>Solicitud administrativa</h3>
        <p>Creación de personaje, modificaciones, objetos, misiones y otras solicitudes al staff.</p>
      </div>
    </a>

    <a class="rpg-staff-card" href="<?= htmlspecialchars($b_url) ?>/game/public/peticion_sucesos.php">
      <div class="rpg-staff-card-icon rpg-staff-card-icon--indigo">
        <i class="fas fa-envelope-open-text"></i>
      </div>
      <div class="rpg-staff-card-body">
        <h3>Notificar Suceso</h3>
        <p>¿Algo grande ha ocurrido en el mar? Envía un aviso de tu tema para que se registre en la Historia del Mundo.</p>
      </div>
    </a>


    <!-- BÚSQUEDA DE ROL -->
    <a class="rpg-staff-card" href="#" onclick="openBusquedaModal(event)">
      <div class="rpg-staff-card-icon rpg-staff-card-icon--blue">
        <i class="fas fa-search"></i>
      </div>
      <div class="rpg-staff-card-body">
        <h3>Búsqueda de Rol</h3>
        <p>Publica una búsqueda de trama o compañero de rol que aparecerá en el tablón del foro.</p>
      </div>
    </a>
  </div>
</div>

<!-- MODAL: B&Uacute;SQUEDA DE ROL -->
<div id="busqueda-modal" class="rpg-modal-overlay" data-modal>
  <div class="rpg-modal-panel rpg-modal-panel--md">
    <div class="rpg-modal-header">
      <h3 class="rpg-modal-title"><i class="fas fa-search"></i> Nueva B&uacute;squeda de Rol</h3>
      <button type="button" onclick="closeBusquedaModal()" class="rpg-modal-close"><i class="fas fa-times"></i></button>
    </div>
    <div class="rpg-modal-body">
      <p class="rpg-modal-intro">Tu b&uacute;squeda pasar&aacute; por revisi&oacute;n del staff antes de publicarse en el tabl&oacute;n.</p>

      <label class="rpg-modal-label">T&iacute;tulo <span class="rpg-modal-title-icon">*</span></label>
      <input type="text" id="busqueda-titulo" class="rpg-form-input" placeholder="Ej: Busco compa&ntilde;ero para trama pirata..." maxlength="120">

      <label class="rpg-modal-label">Imagen (URL) &mdash; <span class="rpg-form-label-hint">opcional, pero recomendada</span></label>
      <input type="url" id="busqueda-imagen" class="rpg-form-input" placeholder="https://i.imgur.com/...jpg">

      <label class="rpg-modal-label">Descripci&oacute;n <span class="rpg-modal-title-icon">*</span></label>
      <textarea id="busqueda-desc" rows="5" class="rpg-form-input" placeholder="Describe qu&eacute; buscas, qu&eacute; tipo de historia, personajes ideales, disponibilidad..."></textarea>

      <div id="busqueda-msg" class="rpg-modal-msg rpg-is-hidden"></div>

      <button type="button" onclick="submitBusqueda()" id="busqueda-btn" class="rpg-btn-busqueda-submit">
        <i class="fas fa-paper-plane"></i> Enviar al Staff
      </button>
    </div>
  </div>
</div>

<div id="peticiones-wrapper" data-bburl="<?= $b_url ?>">
<script src="<?= rtrim($b_url, '/') ?>/jscripts/game/peticiones_general.js?v=1"></script>
</div>
<?php
$content = ob_get_clean();
game_render_page('Trámites y Gestiones RPG', $content);
