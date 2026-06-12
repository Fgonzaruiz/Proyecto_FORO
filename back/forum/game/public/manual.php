<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

global $mybb;

$sections = [
    'intro'      => ['titulo' => 'Introducci&oacute;n', 'icono' => 'fa-compass'],
    'creacion'   => ['titulo' => 'Creaci&oacute;n de Personaje', 'icono' => 'fa-user-plus'],
    'linaje'     => ['titulo' => 'Linaje y Razas', 'icono' => 'fa-dna'],
    'combate'    => ['titulo' => 'Sistema de Combate', 'icono' => 'fa-crosshairs'],
    'estilos'    => ['titulo' => 'Estilos Can&oacute;nicos', 'icono' => 'fa-fist-raised'],
    'cartas'     => ['titulo' => 'Cartas e Inventario', 'icono' => 'fa-layer-group'],
    'economia'   => ['titulo' => 'Econom&iacute;a y Tienda', 'icono' => 'fa-coins'],
    'reglas'     => ['titulo' => 'Reglas de Rol', 'icono' => 'fa-gavel'],
    'faq'        => ['titulo' => 'Preguntas Frecuentes', 'icono' => 'fa-question-circle']
];

$toc_items = '';
foreach ($sections as $id => $s) {
    $toc_items .= '<li><a href="#sec-' . $id . '" class="js-manual-link"><i class="fas ' . $s['icono'] . '"></i> ' . $s['titulo'] . '</a></li>';
}

ob_start();
?>
<div class="rpg-manual-app">
  <div class="rpg-lib-header">
    <div class="rpg-lib-header-content">
      <h1><i class="fas fa-scroll" aria-hidden="true"></i> Manual de Reglas</h1>
      <p>Sistemas, mecánicas y normativas para jugar en este foro. Todo lo que necesitas saber desde tu primer post.</p>
    </div>
  </div>

  <div class="rpg-manual-container">
    <aside class="rpg-manual-nav-sidebar">
      <div class="rpg-manual-search">
        <i class="fas fa-search"></i>
        <input type="text" id="manual-search" placeholder="Filtrar secciones...">
      </div>
      <nav class="rpg-manual-toc" id="manual-toc">
        <ul><?= $toc_items ?></ul>
      </nav>
    </aside>

    <main class="rpg-manual-content" id="manual-content">
      <?php $first = true; foreach ($sections as $id => $s): ?>
      <section class="rpg-manual-chapter js-manual-section <?= $first ? 'active' : 'rpg-is-hidden' ?>" id="sec-<?= $id ?>">
        <header class="rpg-manual-chapter-header">
          <h2><i class="fas <?= $s['icono'] ?>"></i> <?= $s['titulo'] ?></h2>
        </header>
        <div class="rpg-manual-chapter-body">
            <div class="rpg-info-box">
                <p><i class="fas fa-tools"></i> Sección en construcción. Próximamente se añadirá el contenido.</p>
            </div>
        </div>
      </section>
      <?php $first = false; endforeach; ?>

      <footer class="rpg-manual-footer">
        <p>Manual RPG Premium &mdash; v3.0 &mdash; Foro One Piece</p>
      </footer>
    </main>
  </div>
</div>

<script src="<?= rtrim($mybb->settings['bburl'], '/') ?>/jscripts/game/manual.js?v=3"></script>
<?php
$content = ob_get_clean();
game_render_page('Manual del RPG', $content);
