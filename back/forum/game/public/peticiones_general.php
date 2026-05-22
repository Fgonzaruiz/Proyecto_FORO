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
      <h1><i class="fas fa-envelope"></i> Peticiones Generales</h1>
      <p>Selecciona el tipo de petici&oacute;n que deseas realizar.</p>
    </div>
  </div>

  <div class="rpg-peticiones-grid">
    <a class="rpg-peticion-card" href="#">
      <div class="rpg-peticion-card-icon" style="background: linear-gradient(135deg, var(--accent-purple), var(--accent-pink));">
        <i class="fas fa-apple-alt"></i>
      </div>
      <div class="rpg-peticion-card-body">
        <h3>Akuma no Mi</h3>
        <p>Solicita una fruta del diablo, consulta poderes disponibles o reporta una fruta en juego.</p>
      </div>
      <div class="rpg-peticion-card-arrow">
        <i class="fas fa-arrow-right"></i>
      </div>
    </a>

    <a class="rpg-peticion-card" href="#">
      <div class="rpg-peticion-card-icon" style="background: linear-gradient(135deg, var(--accent-amber), var(--accent-orange));">
        <i class="fas fa-hand-fist"></i>
      </div>
      <div class="rpg-peticion-card-body">
        <h3>Haki</h3>
        <p>Gestiona el despertar o entrenamiento de tu Haki: Armadura, Observaci&oacute;n y Rey.</p>
      </div>
      <div class="rpg-peticion-card-arrow">
        <i class="fas fa-arrow-right"></i>
      </div>
    </a>

    <a class="rpg-peticion-card" href="#">
      <div class="rpg-peticion-card-icon" style="background: linear-gradient(135deg, var(--accent-teal), var(--accent-emerald));">
        <i class="fas fa-store"></i>
      </div>
      <div class="rpg-peticion-card-body">
        <h3>Tienda</h3>
        <p>Compra y venta de objetos, equipamiento y recursos del juego.</p>
      </div>
      <div class="rpg-peticion-card-arrow">
        <i class="fas fa-arrow-right"></i>
      </div>
    </a>
  </div>
</div>
<?php
$content = ob_get_clean();
game_render_page('Peticiones Generales', $content);
