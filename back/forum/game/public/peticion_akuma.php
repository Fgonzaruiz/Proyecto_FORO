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
<div class="rpg-peticiones rpg-akuma-hub">
  <div class="rpg-peticiones-header rpg-peticiones-header--gradient rpg-akuma-hub-header">
    <div class="rpg-peticiones-header-content">
      <a href="<?= htmlspecialchars($b_url) ?>/game/public/peticiones_general.php" class="rpg-akuma-back"><i class="fas fa-arrow-left"></i> Peticiones</a>
      <h1><i class="fas fa-apple-alt"></i> Petici&oacute;n Akuma no Mi</h1>
      <p>Elige c&oacute;mo deseas solicitar tu fruta del diablo.</p>
    </div>
  </div>

  <div class="rpg-akuma-mode-grid">
    <a class="rpg-akuma-mode-card rpg-akuma-mode-card--random" href="<?= htmlspecialchars($b_url) ?>/game/public/peticion_akuma_aleatoria.php">
      <div class="rpg-akuma-mode-glow"></div>
      <div class="rpg-akuma-mode-icon"><i class="fas fa-dice"></i></div>
      <h2>Aleatoria</h2>
      <p>Cat&aacute;logo visual por tipo y rango. Solo frutas libres entran en la ruleta. El resultado genera una petici&oacute;n administrativa autom&aacute;tica.</p>
      <span class="rpg-akuma-mode-cta">Tirar el dado <i class="fas fa-arrow-right"></i></span>
    </a>

    <a class="rpg-akuma-mode-card rpg-akuma-mode-card--demand" href="<?= htmlspecialchars($b_url) ?>/game/public/peticion_akuma_demanda.php">
      <div class="rpg-akuma-mode-glow"></div>
      <div class="rpg-akuma-mode-icon"><i class="fas fa-hand-pointer"></i></div>
      <h2>Bajo demanda</h2>
      <p>Selecciona la fruta que deseas, explica el motivo y env&iacute;a la solicitud al staff para revisi&oacute;n.</p>
      <span class="rpg-akuma-mode-cta">Abrir formulario <i class="fas fa-arrow-right"></i></span>
    </a>
  </div>
</div>
<?php
$content = ob_get_clean();
game_render_page('Petición Akuma no Mi', $content);
