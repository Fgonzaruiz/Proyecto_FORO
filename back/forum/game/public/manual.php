<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb;
$b_url = $mybb->settings['bburl'] . '/images/game/manual_banner.png';

$sections = [
    'intro' => ['titulo' => 'Introducci&oacute;n', 'icono' => 'fa-compass'],
    'creacion' => ['titulo' => 'Creaci&oacute;n de Personaje', 'icono' => 'fa-user-plus'],
    'combate' => ['titulo' => 'Sistema de Combate', 'icono' => 'fa-crosshairs'],
    'frutas' => ['titulo' => 'Frutas del Diablo', 'icono' => 'fa-apple-alt'],
    'haki' => ['titulo' => 'Haki', 'icono' => 'fa-hand-fist'],
    'tripulaciones' => ['titulo' => 'Tripulaciones y Alianzas', 'icono' => 'fa-ship'],
    'economia' => ['titulo' => 'Econom&iacute;a y Recompensas', 'icono' => 'fa-coins'],
    'misiones' => ['titulo' => 'Misiones y Eventos', 'icono' => 'fa-scroll'],
    'reglas' => ['titulo' => 'Reglas de Rol', 'icono' => 'fa-gavel'],
    'faq' => ['titulo' => 'Preguntas Frecuentes', 'icono' => 'fa-question-circle'],
];

$toc_items = '';
foreach ($sections as $id => $s) {
    $toc_items .= '<li><a href="#sec-' . $id . '"><i class="fas ' . $s['icono'] . '"></i> ' . $s['titulo'] . '</a></li>';
}

$section_search = [
    'intro' => 'introduccion bienvenida reglas basicas',
    'creacion' => 'creacion personaje stats atributos raza clase',
    'combate' => 'combate batalla daño ataque defensa turno',
    'frutas' => 'fruta diablo akuma mi paramecia zoan logia',
    'haki' => 'haki armadura observacion rey conquista',
    'tripulaciones' => 'tripulacion alianza barco capitán tripulante roles',
    'economia' => 'economia dinero berries recompensa tienda objeto',
    'misiones' => 'misiones eventos diarios semanales historia',
    'reglas' => 'reglas rol normas prohibido sancion',
    'faq' => 'faq preguntas frecuentes ayuda dudas',
];

ob_start();
?>
<div class="rpg-manual">
  <div class="rpg-lib-header">
    <div class="rpg-lib-header-content">
      <h1><i class="fas fa-scroll"></i> Manual del RPG</h1>
      <p>Gu&iacute;a completa de reglas, mec&aacute;nicas, historia y sistemas del juego de rol.</p>
    </div>
  </div>

  <div class="rpg-manual-layout">
    <aside class="rpg-manual-sidebar" id="manual-sidebar">
      <div class="rpg-manual-search-wrapper">
        <i class="fas fa-search"></i>
        <input type="text" id="manual-search" class="textbox" placeholder="Buscar secci&oacute;n..." oninput="filterManualToc()">
      </div>
      <h3><i class="fas fa-list"></i> &Iacute;ndice</h3>
      <ul class="rpg-manual-toc" id="manual-toc"><?= $toc_items ?></ul>
    </aside>

    <main class="rpg-manual-content" id="manual-content"><?php foreach ($sections as $id => $s): ?>
      <section class="rpg-manual-section<?= $id === 'intro' ? ' active' : '' ?>" id="sec-<?= $id ?>" data-section="sec-<?= $id ?>" data-search="<?= htmlspecialchars($section_search[$id] ?? '') ?>">
        <h2><i class="fas <?= $s['icono'] ?>"></i> <?= $s['titulo'] ?></h2>
        <div class="rpg-manual-section-body"><?php include __DIR__ . '/manual_secciones/' . $id . '.php'; ?></div>
      </section><?php endforeach; ?>

      <div class="rpg-manual-footer">
        <p>Manual del RPG v2.0 &mdash; &Uacute;ltima actualizaci&oacute;n: Mayo 2026</p>
        <p>Foro One Piece &mdash; Todos los derechos reservados a sus respectivos autores.</p>
      </div>

    </main>
  </div>
</div>

<script>
window.MANUAL_CONFIG = {};
</script>
<script src="<?= rtrim($mybb->settings['bburl'], '/') ?>/jscripts/game/manual.js?v=1"></script>
<?php
$content = ob_get_clean();
game_render_page('Manual del RPG', $content);
